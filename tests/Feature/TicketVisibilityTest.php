<?php

namespace Tests\Feature;

use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsHelpdesk;
use Tests\TestCase;

class TicketVisibilityTest extends TestCase
{
    use RefreshDatabase, SeedsHelpdesk;

    private Ticket $itTicket;

    private Ticket $hrTicket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHelpdesk();
        $this->itTicket = Ticket::factory()->create(['department_id' => $this->department('IT Support')->id, 'subject' => 'IT printer problem']);
        $this->hrTicket = Ticket::factory()->create(['department_id' => $this->department('Human Resources')->id, 'subject' => 'HR payroll question']);
    }

    public function test_admin_sees_everything(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('tickets.index', ['view' => 'all']))
            ->assertSee('IT printer problem')->assertSee('HR payroll question');
        $this->actingAs($admin)->get(route('tickets.show', $this->hrTicket))->assertOk();
    }

    public function test_agent_sees_own_department_only(): void
    {
        $agent = $this->agent($this->department('IT Support'));

        $this->actingAs($agent)->get(route('tickets.index', ['view' => 'all']))
            ->assertSee('IT printer problem')->assertDontSee('HR payroll question');
        $this->actingAs($agent)->get(route('tickets.show', $this->itTicket))->assertOk();
        $this->actingAs($agent)->get(route('tickets.show', $this->hrTicket))->assertForbidden();
    }

    public function test_agent_sees_ticket_assigned_from_another_department(): void
    {
        $agent = $this->agent($this->department('IT Support'));
        $this->hrTicket->update(['assignee_id' => $agent->id]);

        $this->actingAs($agent)->get(route('tickets.show', $this->hrTicket))->assertOk();
    }

    public function test_requester_sees_only_own_and_watched_tickets(): void
    {
        $requester = $this->requester();
        $own = Ticket::factory()->create(['requester_id' => $requester->id, 'subject' => 'My own laptop issue']);
        $this->hrTicket->watchers()->attach($requester);

        $this->actingAs($requester)->get(route('tickets.index', ['view' => 'all']))
            ->assertSee('My own laptop issue')->assertSee('HR payroll question')->assertDontSee('IT printer problem');

        $this->actingAs($requester)->get(route('tickets.show', $own))->assertOk();
        $this->actingAs($requester)->get(route('tickets.show', $this->itTicket))->assertForbidden();
    }

    public function test_requester_cannot_edit_properties_or_delete(): void
    {
        $requester = $this->requester();
        $own = Ticket::factory()->create(['requester_id' => $requester->id]);

        $this->actingAs($requester)->patch(route('tickets.update', $own), ['status_id' => $this->statusNamed('Closed')->id])->assertForbidden();
        $this->actingAs($requester)->delete(route('tickets.destroy', $own))->assertForbidden();
        $this->actingAs($requester)->get(route('tickets.export'))->assertForbidden();
    }

    public function test_only_admin_can_delete(): void
    {
        $agent = $this->agent($this->department('IT Support'));
        $this->actingAs($agent)->delete(route('tickets.destroy', $this->itTicket))->assertForbidden();

        $this->actingAs($this->admin())->delete(route('tickets.destroy', $this->itTicket))->assertRedirect(route('tickets.index'));
        $this->assertSoftDeleted($this->itTicket);
    }

    public function test_full_text_search(): void
    {
        $admin = $this->admin();
        Ticket::factory()->create(['subject' => 'Outlook crashes', 'description' => 'The mail client freezes when opening attachments']);

        $this->actingAs($admin)->get(route('tickets.index', ['view' => 'all', 'q' => 'freezing attachment']))
            ->assertSee('Outlook crashes')->assertDontSee('IT printer problem');

        $this->actingAs($admin)->get(route('tickets.index', ['view' => 'all', 'q' => $this->hrTicket->reference]))
            ->assertSee('HR payroll question');
    }

    public function test_deactivated_user_is_logged_out(): void
    {
        $user = $this->requester(['is_active' => false]);

        $this->actingAs($user)->get(route('home'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
