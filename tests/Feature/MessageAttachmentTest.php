<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Support\MessageAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MessageAttachmentTest extends TestCase
{
    use RefreshDatabase;

    /** The chat posts over fetch(), so the controller only answers JSON here. */
    private const AJAX = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

    private function customer(): User
    {
        return User::factory()->create(['role' => 'user']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_customer_can_send_a_picture_without_any_caption(): void
    {
        Storage::fake(MessageAttachment::DISK);
        $customer = $this->customer();

        $this->actingAs($customer)
            ->post('/messages', ['attachment' => UploadedFile::fake()->create('garden.png', 10, 'image/png')], self::AJAX)
            ->assertCreated()
            ->assertJsonPath('message.attachment.is_image', true);

        $message = Conversation::where('customer_id', $customer->id)->firstOrFail()->messages()->sole();

        $this->assertNull($message->body);
        $this->assertSame('garden.png', $message->attachment_name);
        Storage::disk(MessageAttachment::DISK)->assertExists($message->attachment_path);
    }

    public function test_customer_can_send_a_document_with_a_caption(): void
    {
        Storage::fake(MessageAttachment::DISK);
        $customer = $this->customer();

        $this->actingAs($customer)->post('/messages', [
            'body' => 'Here is the quote',
            'attachment' => UploadedFile::fake()->create('quote.pdf', 200, 'application/pdf'),
        ], self::AJAX)->assertCreated()->assertJsonPath('message.attachment.is_image', false);

        $message = Conversation::where('customer_id', $customer->id)->firstOrFail()->messages()->sole();

        $this->assertSame('Here is the quote', $message->body);
        $this->assertSame('quote.pdf', $message->attachment_name);
    }

    public function test_a_message_with_neither_text_nor_file_is_rejected(): void
    {
        $this->actingAs($this->customer())
            ->post('/messages', [], self::AJAX)
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');
    }

    public function test_disallowed_file_type_is_rejected_and_nothing_is_stored(): void
    {
        Storage::fake(MessageAttachment::DISK);

        $this->actingAs($this->customer())
            ->post('/messages', ['attachment' => UploadedFile::fake()->create('shell.php', 10, 'text/x-php')], self::AJAX)
            ->assertStatus(422)
            ->assertJsonValidationErrors('attachment');

        $this->assertSame([], Storage::disk(MessageAttachment::DISK)->allFiles());
    }

    public function test_oversized_file_is_rejected(): void
    {
        Storage::fake(MessageAttachment::DISK);

        // Derived from the limit itself so raising the cap cannot silently
        // turn this into a test that uploads an allowed file.
        $tooBig = MessageAttachment::MAX_KB + 512;

        $this->actingAs($this->customer())
            ->post('/messages', ['attachment' => UploadedFile::fake()->create('huge.pdf', $tooBig, 'application/pdf')], self::AJAX)
            ->assertStatus(422)
            ->assertJsonValidationErrors('attachment');
    }

    public function test_a_typical_phone_photo_sized_file_is_accepted(): void
    {
        Storage::fake(MessageAttachment::DISK);

        // 8 MB is a normal camera photo and used to be rejected by the old cap.
        $this->actingAs($this->customer())
            ->post('/messages', ['attachment' => UploadedFile::fake()->create('photo.jpg', 8192, 'image/jpeg')], self::AJAX)
            ->assertCreated();
    }

    public function test_admin_can_reply_with_an_attachment_and_customer_sees_it(): void
    {
        Storage::fake(MessageAttachment::DISK);
        $customer = $this->customer();
        $conversation = Conversation::create(['customer_id' => $customer->id, 'last_message_at' => now()]);

        $this->actingAs($this->admin())
            ->post("/admin/conversations/{$conversation->id}/reply", [
                'attachment' => UploadedFile::fake()->create('plan.png', 10, 'image/png'),
            ])->assertRedirect();

        $this->assertTrue($conversation->messages()->sole()->attachmentIsImage());

        // The customer's polling endpoint must expose it too.
        $this->actingAs($customer)
            ->getJson('/messages/poll')
            ->assertOk()
            ->assertJsonPath('messages.0.attachment.name', 'plan.png');
    }

    public function test_admin_can_send_an_attachment_with_no_reply_text(): void
    {
        Storage::fake(MessageAttachment::DISK);
        $customer = $this->customer();
        $conversation = Conversation::create(['customer_id' => $customer->id, 'last_message_at' => now()]);

        $this->actingAs($this->admin())
            ->post("/admin/conversations/{$conversation->id}/reply", [
                'attachment' => UploadedFile::fake()->create('site.png', 10, 'image/png'),
            ], self::AJAX)
            ->assertCreated()
            ->assertJsonPath('message.attachment.is_image', true);

        $this->assertNull($conversation->messages()->sole()->body);
    }

    public function test_admin_reply_reports_a_rejected_upload_instead_of_redirecting(): void
    {
        Storage::fake(MessageAttachment::DISK);
        $conversation = Conversation::create(['customer_id' => $this->customer()->id, 'last_message_at' => now()]);

        $this->actingAs($this->admin())
            ->post("/admin/conversations/{$conversation->id}/reply", [
                'attachment' => UploadedFile::fake()->create('shell.php', 10, 'text/x-php'),
            ], self::AJAX)
            ->assertStatus(422)
            ->assertJsonValidationErrors('attachment');

        $this->assertSame(0, $conversation->messages()->count());
    }

    public function test_admin_inbox_returns_attachment_details(): void
    {
        Storage::fake(MessageAttachment::DISK);
        $customer = $this->customer();

        $this->actingAs($customer)->post('/messages', [
            'attachment' => UploadedFile::fake()->create('before.png', 10, 'image/png'),
        ], self::AJAX)->assertCreated();

        $conversation = Conversation::where('customer_id', $customer->id)->firstOrFail();

        $this->actingAs($this->admin())
            ->getJson("/admin/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('messages.0.attachment.name', 'before.png')
            ->assertJsonPath('messages.0.attachment.is_image', true);
    }

    /**
     * Attachments are private: these files are receipts, IDs and photos of
     * people's homes, and they used to be fetchable by URL with no session.
     */
    private function sendAttachmentAndGetUrl(User $customer): string
    {
        $this->actingAs($customer)->post('/messages', [
            'attachment' => UploadedFile::fake()->create('receipt.png', 10, 'image/png'),
        ], self::AJAX)->assertCreated();

        $message = Conversation::where('customer_id', $customer->id)->firstOrFail()->messages()->sole();

        return route('messages.attachment', $message);
    }

    public function test_attachment_is_not_written_to_the_public_disk(): void
    {
        Storage::fake(MessageAttachment::DISK);
        Storage::fake('public');

        $this->sendAttachmentAndGetUrl($this->customer());

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_owner_can_download_their_own_attachment(): void
    {
        Storage::fake(MessageAttachment::DISK);
        $customer = $this->customer();
        $url = $this->sendAttachmentAndGetUrl($customer);

        $this->actingAs($customer)->get($url)->assertOk();
    }

    public function test_staff_can_download_a_customer_attachment(): void
    {
        Storage::fake(MessageAttachment::DISK);
        $url = $this->sendAttachmentAndGetUrl($this->customer());

        $this->actingAs($this->admin())->get($url)->assertOk();
    }

    public function test_another_customer_cannot_download_someone_elses_attachment(): void
    {
        Storage::fake(MessageAttachment::DISK);
        $url = $this->sendAttachmentAndGetUrl($this->customer());

        $this->actingAs($this->customer())->get($url)->assertForbidden();
    }

    public function test_guest_cannot_download_an_attachment(): void
    {
        Storage::fake(MessageAttachment::DISK);
        $url = $this->sendAttachmentAndGetUrl($this->customer());

        $this->post('/logout');
        $this->get($url)->assertRedirect(route('login'));
    }

    public function test_a_missing_attachment_is_reported_instead_of_rendering_a_broken_image(): void
    {
        Storage::fake(MessageAttachment::DISK);
        Storage::fake(MessageAttachment::LEGACY_DISK);
        $customer = $this->customer();
        $conversation = Conversation::create(['customer_id' => $customer->id, 'last_message_at' => now()]);
        $message = $conversation->messages()->create([
            'sender_id' => $customer->id,
            'attachment_path' => 'messages/missing.jpg',
            'attachment_name' => 'missing.jpg',
            'attachment_mime' => 'image/jpeg',
            'attachment_size' => 1024,
        ]);

        $this->assertFalse($message->attachmentPayload()['available']);

        $this->actingAs($customer)
            ->get(route('messages'))
            ->assertOk()
            ->assertSee('Attachment unavailable')
            ->assertDontSee('src="'.$message->attachmentUrl().'"', false);
    }

    public function test_plain_text_messages_still_work(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->post('/messages', ['body' => 'Just text'], self::AJAX)
            ->assertCreated()
            ->assertJsonPath('message.attachment', null);

        $this->assertSame(
            'Just text',
            Conversation::where('customer_id', $customer->id)->firstOrFail()->messages()->sole()->body
        );
    }
}
