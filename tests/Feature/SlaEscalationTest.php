<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Notifications\SlaBreachedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SeedsHelpdesk;
use Tests\TestCase;

class SlaEscalationTest extends TestCase
{
    use RefreshDatabase, SeedsHelpdesk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHelpdesk();
        Notification::fake();
    }

    public function test_breached_ticket_is_flagged_and_escalated_to_assignee_and_lead(): void
    {
        $dept = $this->department();
        $lead = $this->agent($dept);
        $dept->update(['lead_id' => $lead->id]);
        $agent = $this->agent($dept);
        $admin = $this->admin();

        $ticket = Ticket::factory()->create([
            'department_id' => $dept->id,
            'assignee_id' => $agent->id,
            'due_response_at' => now()->subHour(),
            'due_resolution_at' => now()->addDay(),
        ]);

        $this->artisan('helpdesk:check-sla')->assertSuccessful();

        $ticket->refresh();
        $this->assertTrue($ticket->response_breached);
        $this->assertFalse($ticket->resolution_breached);
        $this->assertSame(1, $ticket->escalation_level);
        $this->assertDatabaseHas('sla_escalations', ['ticket_id' => $ticket->id, 'level' => 1, 'type' => 'response']);

        Notification::assertSentTo([$agent, $lead], SlaBreachedNotification::class);
        Notification::assertNotSentTo($admin, SlaBreachedNotification::class);
    }

    public function test_second_level_escalation_goes_to_admins_after_delay(): void
    {
        $agent = $this->agent();
        $admin = $this->admin();
        $ticket = Ticket::factory()->create([
            'department_id' => $agent->department_id,
            'assignee_id' => $agent->id,
            'first_responded_at' => now()->subDays(2),
            'due_resolution_at' => now()->subHour(),
        ]);

        $this->artisan('helpdesk:check-sla');
        $this->assertSame(1, $ticket->fresh()->escalation_level);

        // Running again immediately does not re-escalate.
        $this->artisan('helpdesk:check-sla');
        $this->assertSame(1, $ticket->fresh()->escalation_level);

        $this->travel(config('helpdesk.escalation_level2_after_minutes') + 1)->minutes();
        $this->artisan('helpdesk:check-sla');

        $this->assertSame(2, $ticket->fresh()->escalation_level);
        Notification::assertSentTo($admin, SlaBreachedNotification::class, fn ($n) => $n->level === 2);
    }

    public function test_unassigned_breach_escalates_to_admins(): void
    {
        $admin = $this->admin();
        Ticket::factory()->create(['due_response_at' => now()->subMinute()]);

        $this->artisan('helpdesk:check-sla');

        Notification::assertSentTo($admin, SlaBreachedNotification::class);
    }

    public function test_paused_and_closed_tickets_are_ignored(): void
    {
        Ticket::factory()->create(['due_response_at' => now()->subHour(), 'sla_paused_at' => now()->subHours(2)]);
        Ticket::factory()->create(['due_response_at' => now()->subHour(), 'status_id' => $this->statusNamed('Closed')->id]);

        $this->artisan('helpdesk:check-sla');

        $this->assertSame(0, Ticket::where('response_breached', true)->count());
        Notification::assertNothingSent();
    }
}
