<?php

namespace Tests\Feature;

use App\Models\CannedResponse;
use App\Models\Priority;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsHelpdesk;
use Tests\TestCase;

class ReportsAndAdminTest extends TestCase
{
    use RefreshDatabase, SeedsHelpdesk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHelpdesk();
    }

    public function test_reports_are_for_staff_and_csv_export_works(): void
    {
        $agent = $this->agent();
        Ticket::factory()->count(3)->create(['assignee_id' => $agent->id, 'department_id' => $agent->department_id, 'resolved_at' => now(), 'first_responded_at' => now()]);

        $this->actingAs($this->requester())->get(route('reports.index'))->assertForbidden();
        $this->actingAs($agent)->get(route('reports.index'))->assertOk()->assertSee('Agent performance')->assertSee($agent->name);

        $csv = $this->actingAs($agent)->get(route('reports.export', 'agents'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Agent,Assigned,Resolved', $csv);
        $this->assertStringContainsString('"'.$agent->name.'",3,3,', $csv);

        $csv = $this->actingAs($agent)->get(route('reports.export', 'department'))->streamedContent();
        $this->assertStringContainsString('"IT Support",3', $csv);
    }

    public function test_ticket_csv_export(): void
    {
        $agent = $this->agent();
        $ticket = Ticket::factory()->create(['department_id' => $agent->department_id, 'subject' => 'Exported ticket']);

        $csv = $this->actingAs($agent)->get(route('tickets.export', ['view' => 'all']))->assertOk()->streamedContent();

        $this->assertStringContainsString($ticket->reference, $csv);
        $this->assertStringContainsString('Exported ticket', $csv);
    }

    public function test_admin_area_is_admin_only(): void
    {
        $this->actingAs($this->agent())->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($this->agent())->get(route('admin.lookups.index', 'priorities'))->assertForbidden();
    }

    public function test_admin_creates_user_with_role(): void
    {
        $this->actingAs($this->admin())->post(route('admin.users.store'), [
            'name' => 'New Agent', 'email' => 'new.agent@example.com', 'role' => 'agent',
            'password' => 'Secret-pass-123', 'password_confirmation' => 'Secret-pass-123',
            'department_id' => $this->department()->id, 'is_active' => 1,
        ])->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'new.agent@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('agent'));
        $this->assertTrue($user->is_active);
    }

    public function test_admin_deactivates_user_but_not_self(): void
    {
        $admin = $this->admin();
        $user = $this->requester();

        $this->actingAs($admin)->delete(route('admin.users.destroy', $user))->assertRedirect();
        $this->assertFalse($user->fresh()->is_active);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))->assertStatus(422);
    }

    public function test_lookup_crud_and_single_default_flag(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.lookups.store', 'priorities'), [
            'name' => 'Critical', 'level' => 5, 'color' => 'danger', 'response_minutes' => 5, 'resolution_minutes' => 60, 'is_default' => 1,
        ])->assertRedirect(route('admin.lookups.index', 'priorities'));

        $this->assertSame(['Critical'], Priority::where('is_default', true)->pluck('name')->all());

        $tag = Tag::first();
        $this->actingAs($admin)->put(route('admin.lookups.update', ['tags', $tag->id]), ['name' => 'renamed', 'color' => 'info'])->assertRedirect();
        $this->assertSame('renamed', $tag->fresh()->name);

        $this->actingAs($admin)->post(route('admin.lookups.store', 'tags'), ['name' => 'x', 'color' => 'not-a-color'])->assertSessionHasErrors('color');
        $this->actingAs($admin)->get(route('admin.lookups.index', 'nope'))->assertNotFound();
    }

    public function test_priority_in_use_cannot_be_deleted(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs($this->admin())->delete(route('admin.lookups.destroy', ['priorities', $ticket->priority_id]))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('priorities', ['id' => $ticket->priority_id]);
    }

    public function test_canned_responses_personal_vs_shared(): void
    {
        $agent = $this->agent();
        $other = $this->agent();
        $shared = CannedResponse::create(['title' => 'Shared one', 'body' => 'x']);
        $personal = CannedResponse::create(['title' => 'Mine', 'body' => 'y', 'user_id' => $agent->id]);

        $this->actingAs($other)->get(route('canned-responses.index'))->assertSee('Shared one')->assertDontSee('Mine');
        $this->actingAs($other)->put(route('canned-responses.update', $personal), ['title' => 'hack', 'body' => 'z'])->assertForbidden();
        $this->actingAs($agent)->put(route('canned-responses.update', $shared), ['title' => 'hack', 'body' => 'z'])->assertForbidden();

        // Agents cannot create shared responses: the flag is ignored.
        $this->actingAs($agent)->post(route('canned-responses.store'), ['title' => 'Try share', 'body' => 'b', 'shared' => 1]);
        $this->assertSame($agent->id, CannedResponse::where('title', 'Try share')->value('user_id'));

        $this->actingAs($this->requester())->get(route('canned-responses.index'))->assertForbidden();
    }
}
