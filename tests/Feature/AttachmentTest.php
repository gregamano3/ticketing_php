<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsHelpdesk;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase, SeedsHelpdesk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHelpdesk();
        Storage::fake('local');
        Notification::fake();
    }

    public function test_files_upload_with_ticket_and_download_is_authorized(): void
    {
        $requester = $this->requester();

        $this->actingAs($requester)->post(route('tickets.store'), [
            'subject' => 'Screenshot attached',
            'description' => 'See file',
            'attachments' => [UploadedFile::fake()->create('error.pdf', 100, 'application/pdf')],
        ]);

        $attachment = Attachment::firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertSame('error.pdf', $attachment->original_name);

        $this->actingAs($requester)->get(route('attachments.show', $attachment))->assertOk();
        $this->actingAs($this->requester())->get(route('attachments.show', $attachment))->assertForbidden();
    }

    public function test_attachments_on_internal_notes_are_staff_only(): void
    {
        $agent = $this->agent();
        $requester = $this->requester();
        $ticket = Ticket::factory()->create(['requester_id' => $requester->id, 'department_id' => $agent->department_id]);

        $this->actingAs($agent)->post(route('tickets.replies.store', $ticket), [
            'body' => 'logs',
            'is_internal' => 1,
            'attachments' => [UploadedFile::fake()->create('server.log', 10, 'text/plain')],
        ]);

        $attachment = Attachment::firstOrFail();
        $this->actingAs($agent)->get(route('attachments.show', $attachment))->assertOk();
        $this->actingAs($requester)->get(route('attachments.show', $attachment))->assertForbidden();
    }

    public function test_disallowed_file_types_are_rejected(): void
    {
        $this->actingAs($this->requester())->post(route('tickets.store'), [
            'subject' => 'x', 'description' => 'y',
            'attachments' => [UploadedFile::fake()->create('evil.exe', 10, 'application/x-msdownload')],
        ])->assertSessionHasErrors('attachments.0');
    }
}
