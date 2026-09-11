<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Support\MessageAttachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Move chat attachments written before they were made private.
 *
 * Attachments used to land on the public disk, where anyone holding the URL
 * could read them without logging in. New uploads go to the private disk; this
 * relocates the historical ones so the old URLs stop resolving.
 *
 * Reading already works either way (MessageAttachment::diskFor falls back to
 * the public disk), so running this is safe at any time and is a no-op once
 * everything has moved.
 */
class SecureChatAttachments extends Command
{
    protected $signature = 'messages:secure-attachments {--dry-run : List what would move without touching anything}';

    protected $description = 'Move chat attachments off the public disk onto the private one';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $from = Storage::disk(MessageAttachment::LEGACY_DISK);
        $to = Storage::disk(MessageAttachment::DISK);

        $moved = 0;
        $missing = 0;

        $messages = Message::query()->whereNotNull('attachment_path')->cursor();

        foreach ($messages as $message) {
            $path = $message->attachment_path;

            if ($to->exists($path)) {
                continue; // already private
            }

            if (! $from->exists($path)) {
                $this->warn("missing: {$path} (message #{$message->id})");
                $missing++;

                continue;
            }

            if ($dryRun) {
                $this->line("would move: {$path}");
                $moved++;

                continue;
            }

            // Copy first, verify, then remove the public original - a failed
            // move must never leave the attachment unreadable.
            $to->put($path, $from->get($path));

            if (! $to->exists($path)) {
                $this->error("copy failed, left in place: {$path}");

                continue;
            }

            $from->delete($path);
            $this->line("moved: {$path}");
            $moved++;
        }

        $this->newLine();
        $this->info($dryRun
            ? "{$moved} attachment(s) would move, {$missing} missing."
            : "{$moved} attachment(s) moved to the private disk, {$missing} missing.");

        return self::SUCCESS;
    }
}
