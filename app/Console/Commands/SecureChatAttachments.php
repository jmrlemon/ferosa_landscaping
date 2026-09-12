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
    protected $signature = 'messages:secure-attachments
        {--dry-run : List what would move without touching anything}
        {--source=* : Old storage/app/private or storage/app/public root to recover missing files from}';

    protected $description = 'Move chat attachments off the public disk onto the private one';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $from = Storage::disk(MessageAttachment::LEGACY_DISK);
        $to = Storage::disk(MessageAttachment::DISK);
        $sourceRoots = $this->sourceRoots();

        $moved = 0;
        $recovered = 0;
        $missing = 0;

        $messages = Message::query()->whereNotNull('attachment_path')->cursor();

        foreach ($messages as $message) {
            $path = $message->attachment_path;

            if ($to->exists($path)) {
                continue; // already private
            }

            if ($from->exists($path)) {
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

                continue;
            }

            $candidate = $this->findRecoveryCandidate($sourceRoots, $path);
            if ($candidate === null) {
                $this->warn("missing: {$path} (message #{$message->id})");
                $missing++;

                continue;
            }

            if ($dryRun) {
                $this->line("would recover: {$path} from {$candidate}");
                $recovered++;

                continue;
            }

            $contents = file_get_contents($candidate);
            if ($contents === false || ! $to->put($path, $contents)) {
                $this->error("recovery failed, source left untouched: {$path}");

                continue;
            }

            if (! $to->exists($path)) {
                $this->error("recovery could not be verified, source left untouched: {$path}");

                continue;
            }

            $this->line("recovered: {$path} from {$candidate}");
            $recovered++;
        }

        $this->newLine();
        $this->info($dryRun
            ? "{$moved} attachment(s) would move, {$recovered} would recover, {$missing} missing."
            : "{$moved} attachment(s) moved, {$recovered} recovered, {$missing} missing.");

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function sourceRoots(): array
    {
        $roots = [];

        foreach ((array) $this->option('source') as $source) {
            $root = realpath((string) $source);
            if ($root === false || ! is_dir($root)) {
                $this->warn("ignored invalid source root: {$source}");

                continue;
            }

            $roots[] = rtrim($root, DIRECTORY_SEPARATOR);
        }

        return array_values(array_unique($roots));
    }

    /** @param list<string> $sourceRoots */
    private function findRecoveryCandidate(array $sourceRoots, string $path): ?string
    {
        $normalizedPath = str_replace('\\', '/', $path);
        if (str_starts_with($normalizedPath, '/') || in_array('..', explode('/', $normalizedPath), true)) {
            return null;
        }

        foreach ($sourceRoots as $root) {
            $candidate = realpath($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath));
            $rootPrefix = strtolower($root.DIRECTORY_SEPARATOR);

            if ($candidate !== false
                && str_starts_with(strtolower($candidate), $rootPrefix)
                && is_file($candidate)
                && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
