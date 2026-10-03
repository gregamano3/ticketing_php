<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Ticket;
use App\Notifications\TicketAssignedNotification;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketReceivedNotification;
use App\Notifications\TicketRepliedNotification;
use App\Notifications\TicketStatusChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SeedsHelpdesk;
use Tests\TestCase;

class TicketLifecycleTest extends TestCase
{
    use RefreshDatabase, SeedsHelpdesk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHelpdesk();
        Notification::fake();
    }

    public function test_requester_creates_ticket_with_reference_sla_and_auto_assignment(): void
    {
        $this->freezeSecond();
        $busy = $this->agent();
        $free = $this->agent();
        Ticket::factory()->count(2)->create(['assignee_id' => $busy->id, 'department_id' => $this->department()->id]);
        $requester = $this->requester();
        $high = $this->priorityNamed('High');

        $this->actingAs($requester)->post(route('tickets.store'), [
            'subject' => 'VPN is down',
            'description' => 'Cannot connect since this morning.',
            'department_id' => $this->department()->id,
            'priority_id' => $high->id,
            'assignee_id' => $busy->id, // requesters may not choose an assignee
        ])->assertRedirect();

        $ticket = Ticket::where('subject', 'VPN is down')->firstOrFail();

        $this->assertMatchesRegularExpression('/^TKT-\d{6}$/', $ticket->reference);
        $this->assertSame($requester->id, $ticket->requester_id);
        $this->assertSame($free->id, $ticket->assignee_id, 'least-busy agent should be auto-assigned');
        $this->assertSame('Open', $ticket->status->name);
        $this->assertTrue($ticket->due_response_at->equalTo(now()->addMinutes($high->response_minutes)));
        $this->assertTrue($ticket->due_resolution_at->equalTo(now()->addMinutes($high->resolution_minutes)));

        Notification::assertSentTo($requester, TicketReceivedNotification::class);
        Notification::assertSentTo($free, TicketCreatedNotification::class);
        Notification::assertNotSentTo($busy, TicketCreatedNotification::class);
    }

    public function test_ticket_creation_validates_input(): void
    {
        $this->actingAs($this->requester())
            ->post(route('tickets.store'), ['subject' => '', 'description' => ''])
            ->assertSessionHasErrors(['subject', 'description']);
    }

    public function test_staff_reply_sets_first_response_and_internal_notes_are_hidden_from_requester(): void
    {
        $agent = $this->agent();
        $requester = $this->requester();
        $ticket = Ticket::factory()->create(['requester_id' => $requester->id, 'assignee_id' => $agent->id, 'department_id' => $agent->department_id]);

        $this->actingAs($agent)->post(route('tickets.replies.store', $ticket), ['body' => 'Secret triage note', 'is_internal' => 1]);
        $this->assertNull($ticket->fresh()->first_responded_at, 'internal notes do not count as a response');
        Notification::assertNotSentTo($requester, TicketRepliedNotification::class);

        $this->actingAs($agent)->post(route('tickets.replies.store', $ticket), ['body' => 'We are on it']);
        $this->assertNotNull($ticket->fresh()->first_responded_at);
        Notification::assertSentTo($requester, TicketRepliedNotification::class);

        $this->actingAs($requester)->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('We are on it')
            ->assertDontSee('Secret triage note');

        $this->actingAs($agent)->get(route('tickets.show', $ticket))->assertSee('Secret triage note');
    }

    public function test_requester_cannot_post_internal_notes(): void
    {
        $requester = $this->requester();
        $ticket = Ticket::factory()->create(['requester_id' => $requester->id]);

        $this->actingAs($requester)
            ->post(route('tickets.replies.store', $ticket), ['body' => 'hi', 'is_internal' => 1])
            ->assertForbidden();
    }

    public function test_requester_reply_reopens_resolved_ticket(): void
    {
        $requester = $this->requester();
        $ticket = Ticket::factory()->create(['requester_id' => $requester->id, 'status_id' => $this->statusNamed('Resolved')->id, 'resolved_at' => now()]);

        $this->actingAs($requester)->post(route('tickets.replies.store', $ticket), ['body' => 'Still broken']);

        $ticket->refresh();
        $this->assertSame('Open', $ticket->status->name);
        $this->assertNull($ticket->resolved_at);
    }

    public function test_assignment_and_status_change_notify_and_set_timestamps(): void
    {
        $agent = $this->agent();
        $other = $this->agent();
        $requester = $this->requester();
        $ticket = Ticket::factory()->create(['requester_id' => $requester->id, 'assignee_id' => $agent->id, 'department_id' => $agent->department_id]);

        $this->actingAs($agent)->patch(route('tickets.update', $ticket), ['assignee_id' => $other->id])->assertRedirect();
        Notification::assertSentTo($other, TicketAssignedNotification::class);

        $this->actingAs($agent)->patch(route('tickets.update', $ticket), ['status_id' => $this->statusNamed('Resolved')->id]);
        $ticket->refresh();
        $this->assertNotNull($ticket->resolved_at);
        Notification::assertSentTo($requester, TicketStatusChangedNotification::class);
        Notification::assertNotSentTo($agent, TicketStatusChangedNotification::class);
    }

    public function test_pending_status_pauses_and_resume_extends_sla(): void
    {
        $this->freezeSecond();
        $agent = $this->agent();
        $ticket = Ticket::factory()->create(['assignee_id' => $agent->id, 'department_id' => $agent->department_id, 'due_resolution_at' => now()->addHours(2)]);

        $this->actingAs($agent)->patch(route('tickets.update', $ticket), ['status_id' => $this->statusNamed('Pending')->id]);
        $this->assertNotNull($ticket->fresh()->sla_paused_at);

        $this->travel(3)->hours();
        $this->actingAs($agent)->patch(route('tickets.update', $ticket), ['status_id' => $this->statusNamed('In Progress')->id]);

        $ticket->refresh();
        $this->assertNull($ticket->sla_paused_at);
        $this->assertTrue($ticket->due_resolution_at->equalTo(now()->addHours(2)), 'due date shifted by the 3h pause');
    }

    public function test_changing_priority_recalculates_sla(): void
    {
        $agent = $this->agent();
        $ticket = Ticket::factory()->create(['assignee_id' => $agent->id, 'department_id' => $agent->department_id, 'priority_id' => $this->priorityNamed('Low')->id]);
        $urgent = $this->priorityNamed('Urgent');

        $this->actingAs($agent)->patch(route('tickets.update', $ticket), ['priority_id' => $urgent->id]);

        $ticket->refresh();
        $this->assertTrue($ticket->due_resolution_at->equalTo($ticket->created_at->copy()->addMinutes($urgent->resolution_minutes)));
    }

    public function test_tags_and_watchers_can_be_cleared(): void
    {
        $agent = $this->agent();
        $watcher = $this->requester();
        $ticket = Ticket::factory()->create(['assignee_id' => $agent->id, 'department_id' => $agent->department_id]);
        $ticket->watchers()->attach($watcher);
        $ticket->tags()->attach(Tag::first());

        $this->actingAs($agent)->patch(route('tickets.update', $ticket), ['tags' => '', 'watchers' => '']);

        $this->assertCount(0, $ticket->tags()->get());
        $this->assertCount(0, $ticket->watchers()->get());
    }

    public function test_audit_log_records_changes(): void
    {
        $agent = $this->agent();
        $ticket = Ticket::factory()->create(['assignee_id' => $agent->id, 'department_id' => $agent->department_id]);

        $this->actingAs($agent)->patch(route('tickets.update', $ticket), ['status_id' => $this->statusNamed('In Progress')->id]);

        $this->actingAs($agent)->get(route('tickets.show', $ticket))
            ->assertSee('Status: Open → In Progress');
    }
}
