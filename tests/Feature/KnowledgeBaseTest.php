<?php

namespace Tests\Feature;

use App\Models\KbArticle;
use App\Models\KbCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsHelpdesk;
use Tests\TestCase;

class KnowledgeBaseTest extends TestCase
{
    use RefreshDatabase, SeedsHelpdesk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHelpdesk();
    }

    public function test_requesters_see_published_articles_only(): void
    {
        $published = KbArticle::factory()->create(['title' => 'Reset your password', 'is_published' => true]);
        $draft = KbArticle::factory()->create(['title' => 'Secret draft', 'is_published' => false]);
        $requester = $this->requester();

        $this->actingAs($requester)->get(route('kb.articles.show', $published))->assertOk()->assertSee('Reset your password');
        $this->actingAs($requester)->get(route('kb.articles.show', $draft))->assertForbidden();
        $this->actingAs($this->agent())->get(route('kb.articles.show', $draft))->assertOk();
    }

    public function test_search_uses_full_text_and_hides_drafts_from_requesters(): void
    {
        KbArticle::factory()->create(['title' => 'Connecting to the VPN', 'body' => '<p>Install the client</p>']);
        KbArticle::factory()->create(['title' => 'VPN draft notes', 'is_published' => false]);

        $this->actingAs($this->requester())->get(route('kb.index', ['q' => 'vpn']))
            ->assertSee('Connecting to the VPN')->assertDontSee('VPN draft notes');
    }

    public function test_agent_creates_article_and_html_is_sanitized(): void
    {
        $agent = $this->agent();
        $category = KbCategory::first();

        $this->actingAs($agent)->post(route('kb.articles.store'), [
            'kb_category_id' => $category->id,
            'title' => 'Printer setup',
            'body' => '<p>Hello</p><script>alert(1)</script><img src=x onerror=alert(2)>',
            'is_published' => 1,
        ])->assertRedirect();

        $article = KbArticle::where('title', 'Printer setup')->firstOrFail();
        $this->assertSame('printer-setup', $article->slug);
        $this->assertNotNull($article->published_at);
        $this->assertStringNotContainsString('<script', $article->body);
        $this->assertStringNotContainsString('onerror', $article->body);
    }

    public function test_requester_cannot_create_articles(): void
    {
        $this->actingAs($this->requester())->get(route('kb.articles.create'))->assertForbidden();
    }

    public function test_helpful_vote_counts_once_per_session(): void
    {
        $article = KbArticle::factory()->create();
        $user = $this->requester();

        $this->actingAs($user)->post(route('kb.articles.vote', $article), ['helpful' => 1]);
        $this->actingAs($user)->post(route('kb.articles.vote', $article), ['helpful' => 1]);

        $this->assertSame(1, $article->fresh()->helpful_yes);
    }
}
