<?php

namespace Tests\Feature;

use App\Http\Controllers\TicketController;
use App\Models\Category;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Ticket;
use App\Notifications\TicketAssignedNotification;
use App\Support\Lookups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SeedsHelpdesk;
use Tests\TestCase;

/** Renders every page for each role to catch template and query errors. */
class PagesSmokeTest extends TestCase
{
    use RefreshDatabase, SeedsHelpdesk;

    public function test_guest_is_redirected_and_login_page_renders(): void
    {
        $this->get('/')->assertRedirect('/home');
        $this->get('/home')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Helpdesk', false);
        $this->get('/register')->assertNotFound();
    }

    public function test_all_pages_render_for_each_role(): void
    {
        $this->seedHelpdesk();
        Notification::fake();

        $admin = $this->admin();
        $agent = $this->agent();
        $requester = $this->requester();

        $ticket = Ticket::factory()->create(['requester_id' => $requester->id, 'assignee_id' => $agent->id, 'department_id' => $agent->department_id]);
        $this->actingAs($agent)->post(route('tickets.replies.store', $ticket), ['body' => 'Working on it']);
        $article = KbArticle::factory()->create(['kb_category_id' => KbCategory::first()->id]);
        $requester->notify(new TicketAssignedNotification($ticket));

        $common = [
            route('home'), route('tickets.index'), route('tickets.create'), route('tickets.show', $ticket),
            route('kb.index'), route('kb.index', ['q' => 'test']), route('kb.category', KbCategory::first()), route('kb.articles.show', $article),
            route('notifications.index'), route('notifications.poll'), route('profile.edit'),
            ...collect(array_keys(TicketController::VIEWS))->map(fn ($v) => route('tickets.index', ['view' => $v])),
        ];
        $staff = [
            route('tickets.edit', $ticket), route('reports.index'), route('canned-responses.index'), route('canned-responses.create'),
            route('kb.articles.create'), route('kb.articles.edit', $article),
            route('tickets.index', ['view' => 'all', 'sort' => 'priority', 'dir' => 'asc', 'q' => 'x', 'breached' => 1, 'assignee' => 'none']),
        ];
        $adminOnly = [
            route('admin.users.index'), route('admin.users.create'), route('admin.users.edit', $requester),
            ...collect(array_keys(Lookups::all()))->flatMap(fn ($t) => [route('admin.lookups.index', $t), route('admin.lookups.create', $t)]),
            route('admin.lookups.edit', ['categories', Category::first()->id]),
        ];

        foreach ([[$admin, [...$common, ...$staff, ...$adminOnly]], [$agent, [...$common, ...$staff]], [$requester, $common]] as [$user, $urls]) {
            foreach ($urls as $url) {
                $this->actingAs($user)->get($url)->assertOk();
            }
        }
    }
}
