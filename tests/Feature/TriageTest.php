<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Notifications\SlaBreachedNotification;
use App\Notifications\TicketAssignedNotification;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketNeedsTriageNotification;
use App\Support\PriorityMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SeedsHelpdesk;
use Tests\TestCase;

class TriageTest extends TestCase
{
    use RefreshDatabase, SeedsHelpdesk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHelpdesk();
        Notification::fake();
    }

    private function triager()
    {
        return tap($this->agent(), fn ($u) => $u->givePermissionTo('tickets.triage'));
    }

    public function test_priority_matrix_suggestions(): void
    {
        $this->assertSame('Low', PriorityMatrix::suggest(1, 1)->name);
        $this->assertSame('Medium', PriorityMatrix::suggest(1, 2)->name);
        $this->assertSame('High', PriorityMatrix::suggest(2, 2)->name);
        $this->assertSame('High', PriorityMatrix::suggest(3, 2)->name);
        $this->assertSame('Urgent', PriorityMatrix::suggest(3, 3)->name);
        $this->assertNull(PriorityMatrix::suggest(null, 2));
    }

    public function test_category_routes_to_its_department_and_sets_minimum_priority(): void
    {
        $category = Category::where('name', 'Payroll')->firstOrFail();
        $category->update(['default_priority_id' => $this->priorityNamed('High')->id]);

        $this->actingAs($this->requester())->post(route('tickets.store'), [
            'subject' => 'Payslip wrong', 'description' => 'x', 'category_id' => $category->id, 'impact' => 1, 'urgency' => 1,
        ]);

        $ticket = Ticket::firstOrFail();
        $this->assertSame($category->department_id, $ticket->department_id, 'department derived from category on the server');
        $this->assertSame('High', $ticket->priority->name, 'category minimum beats the Low suggestion');
    }

    public function test_staff_created_routed_tickets_are_triaged_requester_tickets_are_not(): void
    {
        $agent = $this->agent();

        $this->actingAs($agent)->post(route('tickets.store'), ['subject' => 'Staff', 'description' => 'x', 'department_id' => $agent->department_id]);
        $this->actingAs($this->requester())->post(route('tickets.store'), ['subject' => 'Requester', 'description' => 'x', 'department_id' => $agent->department_id]);

        $this->assertNotNull(Ticket::where('subject', 'Staff')->value('triaged_at'));
        $this->assertNull(Ticket::where('subject', 'Requester')->value('triaged_at'));
    }

    public function test_unrouted_ticket_notifies_triagers_only(): void
    {
        $triager = $this->triager();
        $plainAgent = $this->agent();
        $admin = $this->admin();

        $this->actingAs($this->requester())->post(route('tickets.store'), ['subject' => 'Where does this go?', 'description' => 'x']);

        Notification::assertSentTo([$triager, $admin], TicketCreatedNotification::class);
        Notification::assertNotSentTo($plainAgent, TicketCreatedNotification::class);
    }

    public function test_triage_queue_visibility(): void
    {
        $hr = $this->department('Human Resources');
        $unrouted = Ticket::factory()->create(['subject' => 'Unrouted problem']);
        $hrTicket = Ticket::factory()->create(['subject' => 'HR question', 'department_id' => $hr->id]);
        Ticket::factory()->create(['subject' => 'Already triaged', 'triaged_at' => now()]);

        $triager = $this->triager(); // IT agent with triage permission
        $this->actingAs($triager)->get(route('tickets.index', ['view' => 'triage']))
            ->assertOk()->assertSee('Unrouted problem')->assertSee('HR question')->assertDontSee('Already triaged');
        $this->actingAs($triager)->get(route('tickets.show', $hrTicket))->assertOk();

        // A plain IT agent sees neither the queue nor other departments' untriaged tickets.
        $agent = $this->agent();
        $this->actingAs($agent)->get(route('tickets.index', ['view' => 'triage']))->assertDontSee('Needs triage</a>', false);
        $this->actingAs($agent)->get(route('tickets.show', $unrouted))->assertForbidden();
        $this->actingAs($agent)->get(route('tickets.show', $hrTicket))->assertForbidden();
    }

    public function test_triage_routes_prioritises_and_auto_assigns(): void
    {
        $this->freezeSecond();
        $triager = $this->triager();
        $facilities = $this->department('Facilities');
        $facilitiesAgent = $this->agent($facilities);
        $ticket = Ticket::factory()->create(['priority_id' => $this->priorityNamed('Low')->id]);
        $urgent = $this->priorityNamed('Urgent');

        $this->actingAs($triager)->post(route('tickets.triage', $ticket), [
            'department_id' => $facilities->id,
            'priority_id' => $urgent->id,
        ])->assertRedirect(route('tickets.show', $ticket))->assertSessionHas('success');

        $ticket->refresh();
        $this->assertSame($facilities->id, $ticket->department_id);
        $this->assertSame($urgent->id, $ticket->priority_id);
        $this->assertSame($facilitiesAgent->id, $ticket->assignee_id);
        $this->assertSame($triager->id, $ticket->triaged_by);
        $this->assertTrue($ticket->triaged_at->equalTo(now()));
        $this->assertTrue($ticket->due_resolution_at->equalTo($ticket->created_at->copy()->addMinutes($urgent->resolution_minutes)));
        Notification::assertSentTo($facilitiesAgent, TicketAssignedNotification::class);

        // Once triaged it leaves the queue and the triage action is no longer allowed.
        $this->actingAs($triager)->post(route('tickets.triage', $ticket), ['department_id' => $facilities->id, 'priority_id' => $urgent->id])->assertForbidden();
        $this->actingAs($triager)->get(route('tickets.show', $ticket))->assertSee('triaged the ticket');
    }

    public function test_requesters_and_unrelated_agents_cannot_triage(): void
    {
        $requester = $this->requester();
        $ticket = Ticket::factory()->create(['requester_id' => $requester->id]);
        $payload = ['department_id' => $this->department()->id, 'priority_id' => $this->priorityNamed('Low')->id];

        $this->actingAs($requester)->post(route('tickets.triage', $ticket), $payload)->assertForbidden();
        $this->actingAs($this->agent())->post(route('tickets.triage', $ticket), $payload)->assertForbidden();
    }

    public function test_department_agent_can_triage_their_own_ticket(): void
    {
        $agent = $this->agent();
        $ticket = Ticket::factory()->create(['department_id' => $agent->department_id]);

        $this->actingAs($agent)->post(route('tickets.triage', $ticket), [
            'department_id' => $agent->department_id, 'priority_id' => $this->priorityNamed('High')->id, 'assignee_id' => $agent->id,
        ])->assertRedirect();

        $this->assertNotNull($ticket->fresh()->triaged_at);
    }

    public function test_send_back_to_triage(): void
    {
        $triager = $this->triager();
        $agent = $this->agent();
        $ticket = Ticket::factory()->create(['department_id' => $agent->department_id, 'assignee_id' => $agent->id, 'triaged_at' => now(), 'triaged_by' => $triager->id]);

        $this->actingAs($agent)->post(route('tickets.send-back', $ticket), ['reason' => 'This is a Facilities issue'])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertNull($ticket->triaged_at);
        $this->assertNull($ticket->assignee_id);
        $this->assertDatabaseHas('ticket_replies', ['ticket_id' => $ticket->id, 'is_internal' => true, 'body' => 'Sent back to triage: This is a Facilities issue']);
        Notification::assertSentTo($triager, TicketNeedsTriageNotification::class);

        $this->actingAs($agent)->post(route('tickets.send-back', $ticket), ['reason' => 'again'])->assertForbidden();
        $this->actingAs($agent)->post(route('tickets.send-back', Ticket::factory()->create(['triaged_at' => now()])), ['reason' => 'x'])->assertForbidden();
    }

    public function test_send_back_requires_a_reason(): void
    {
        $agent = $this->agent();
        $ticket = Ticket::factory()->create(['department_id' => $agent->department_id, 'triaged_at' => now()]);

        $this->actingAs($agent)->post(route('tickets.send-back', $ticket), ['reason' => ''])->assertSessionHasErrors('reason');
    }

    public function test_overdue_triage_escalates_once_to_triagers(): void
    {
        $triager = $this->triager();
        $admin = $this->admin();
        $old = Ticket::factory()->create(['created_at' => now()->subMinutes(config('helpdesk.triage_minutes') + 5)]);
        Ticket::factory()->create(['created_at' => now()->subMinutes(5)]);

        $this->artisan('helpdesk:check-sla')->expectsOutputToContain('triage overdue: 1');
        $this->artisan('helpdesk:check-sla')->expectsOutputToContain('triage overdue: 0');

        $this->assertDatabaseHas('sla_escalations', ['ticket_id' => $old->id, 'type' => 'triage']);
        Notification::assertSentTo([$triager, $admin], SlaBreachedNotification::class, fn ($n) => $n->type === 'triage');
    }

    public function test_admin_grants_triage_access(): void
    {
        $agent = $this->agent();

        $this->actingAs($this->admin())->put(route('admin.users.update', $agent), [
            'name' => $agent->name, 'email' => $agent->email, 'role' => 'agent', 'is_active' => 1, 'can_triage' => 1,
        ])->assertRedirect();
        $this->assertTrue($agent->fresh()->canTriage());

        $this->actingAs($this->admin())->put(route('admin.users.update', $agent), [
            'name' => $agent->name, 'email' => $agent->email, 'role' => 'agent', 'is_active' => 1,
        ]);
        $this->assertFalse($agent->fresh()->canTriage());
    }

    public function test_reports_show_triage_metrics(): void
    {
        Ticket::factory()->create(['created_at' => now()->subHour(), 'triaged_at' => now()->subMinutes(30)]);
        Ticket::factory()->create();

        $this->actingAs($this->admin())->get(route('reports.index'))
            ->assertSee('Avg time to triage')->assertSee('30m')->assertSee('Awaiting triage now');
    }
}
