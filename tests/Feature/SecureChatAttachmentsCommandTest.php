<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\MessageAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecureChatAttachmentsCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $sourceRoot;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(MessageAttachment::DISK);
        Storage::fake(MessageAttachment::LEGACY_DISK);
        $this->sourceRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ferosa-attachments-'.Str::uuid();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sourceRoot);

        parent::tearDown();
    }

    public function test_missing_attachments_can_be_recovered_from_an_explicit_old_storage_root(): void
    {
        $message = $this->messageWithAttachment('messages/recovered.jpg');
        File::ensureDirectoryExists($this->sourceRoot.DIRECTORY_SEPARATOR.'messages');
        File::put($this->sourceRoot.DIRECTORY_SEPARATOR.$message->attachment_path, 'restored-image');

        $exitCode = Artisan::call('messages:secure-attachments', [
            '--source' => [$this->sourceRoot],
        ]);

        $this->assertSame(0, $exitCode);
        Storage::disk(MessageAttachment::DISK)->assertExists($message->attachment_path);
        $this->assertSame('restored-image', Storage::disk(MessageAttachment::DISK)->get($message->attachment_path));
        $this->assertStringContainsString('recovered:', Artisan::output());
    }

    public function test_dry_run_reports_recoverable_files_without_copying_them(): void
    {
        $message = $this->messageWithAttachment('messages/dry-run.jpg');
        File::ensureDirectoryExists($this->sourceRoot.DIRECTORY_SEPARATOR.'messages');
        File::put($this->sourceRoot.DIRECTORY_SEPARATOR.$message->attachment_path, 'candidate');

        Artisan::call('messages:secure-attachments', [
            '--dry-run' => true,
            '--source' => [$this->sourceRoot],
        ]);

        Storage::disk(MessageAttachment::DISK)->assertMissing($message->attachment_path);
        $this->assertStringContainsString('would recover:', Artisan::output());
    }

    private function messageWithAttachment(string $path): Message
    {
        $customer = User::factory()->create();
        $conversation = Conversation::query()->create([
            'customer_id' => $customer->id,
            'last_message_at' => now(),
        ]);

        return $conversation->messages()->create([
            'sender_id' => $customer->id,
            'attachment_path' => $path,
            'attachment_name' => basename($path),
            'attachment_mime' => 'image/jpeg',
            'attachment_size' => 14,
        ]);
    }
}
