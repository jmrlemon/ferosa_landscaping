<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Writes a timestamped mysqldump of the application database.
 *
 * XAMPP's MySQL runs without binary logging, so there is no point-in-time
 * recovery to fall back on. A dump taken before risky work is the only way back.
 */
class BackupDatabase extends Command
{
    protected $signature = 'db:backup
                            {--path= : Directory to write the dump into (default: storage/app/backups)}
                            {--keep=0 : Delete older dumps of this database, keeping the newest N (0 keeps all)}';

    protected $description = 'Write a timestamped SQL dump of the application database';

    public function handle(): int
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? null) !== 'mysql') {
            $this->components->error(
                "db:backup supports MySQL only; the '{$connection}' connection uses '".($config['driver'] ?? 'none')."'."
            );

            return self::FAILURE;
        }

        $binary = $this->resolveMysqldump($config);

        if ($binary === null) {
            $this->components->error('Could not find mysqldump.');
            $this->line('  Set MYSQLDUMP_PATH in your .env to its full path, e.g.');
            $this->line('  MYSQLDUMP_PATH="'.$this->xamppGuess().'"');

            return self::FAILURE;
        }

        $directory = $this->option('path') ?: storage_path('app/backups');
        File::ensureDirectoryExists($directory);

        $database = $config['database'];
        $target = $directory.DIRECTORY_SEPARATOR.$database.'-'.now()->format('Y-m-d_His').'.sql';

        $this->components->task("Dumping '{$database}' via ".basename($binary), function () use ($binary, $config, $database, $target) {
            $process = new Process([
                $binary,
                '--host='.$config['host'],
                '--port='.$config['port'],
                '--user='.$config['username'],
                '--password='.$config['password'],
                '--default-character-set='.($config['charset'] ?? 'utf8mb4'),
                // Consistent snapshot without locking the tables the app is using.
                '--single-transaction',
                '--routines',
                '--triggers',
                // Write straight to disk so large dumps never sit in PHP memory.
                '--result-file='.$target,
                $database,
            ], timeout: 900);

            $process->run();

            if (! $process->isSuccessful()) {
                // Never leave a half-written file that looks like a usable backup.
                File::delete($target);

                throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'mysqldump failed.');
            }

            return true;
        });

        if (! File::exists($target)) {
            return self::FAILURE;
        }

        $this->components->info(sprintf('Backup written: %s (%s)', $target, $this->humanSize(File::size($target))));

        $this->prune($directory, $database);

        return self::SUCCESS;
    }

    /**
     * Delete the oldest dumps of this database, keeping the newest --keep files.
     */
    private function prune(string $directory, string $database): void
    {
        $keep = (int) $this->option('keep');

        if ($keep < 1) {
            return;
        }

        // Only ever considers files this command itself writes.
        $dumps = collect(File::glob($directory.DIRECTORY_SEPARATOR.$database.'-*.sql'))
            ->sortByDesc(fn (string $path) => File::lastModified($path))
            ->values();

        $stale = $dumps->slice($keep);

        foreach ($stale as $path) {
            File::delete($path);
            $this->line('  <fg=gray>pruned '.basename($path).'</>');
        }

        $this->components->info(sprintf('Keeping %d backup(s), pruned %d.', min($keep, $dumps->count()), $stale->count()));
    }

    /**
     * Locate mysqldump: explicit config first, then any mysql/bin beside this
     * install or above the project, then whatever is on PATH.
     */
    private function resolveMysqldump(array $config): ?string
    {
        $configured = $config['dump_binary'] ?? null;

        if ($configured && is_file($configured)) {
            return $configured;
        }

        foreach ($this->candidatePaths() as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $locator = windows_os() ? 'where' : 'which';
        $process = new Process([$locator, 'mysqldump']);
        $process->run();

        if ($process->isSuccessful()) {
            $first = trim(strtok($process->getOutput(), "\n"));

            if ($first !== '' && is_file($first)) {
                return $first;
            }
        }

        return null;
    }

    /**
     * Places a stack keeps mysqldump. The CLI PHP running artisan is often not
     * the stack's own PHP (Herd, a standalone build), so the project path is the
     * more reliable anchor: XAMPP and WAMP both keep mysql/ above htdocs/.
     *
     * @return list<string>
     */
    private function candidatePaths(): array
    {
        $suffix = DIRECTORY_SEPARATOR.'mysql'.DIRECTORY_SEPARATOR.'bin'
            .DIRECTORY_SEPARATOR.'mysqldump'.(windows_os() ? '.exe' : '');

        $roots = [dirname(PHP_BINDIR)];

        $directory = base_path();

        while (($parent = dirname($directory)) !== $directory) {
            $roots[] = $parent;
            $directory = $parent;
        }

        return array_map(fn (string $root): string => $root.$suffix, $roots);
    }

    /**
     * Best-effort path to show when nothing was found.
     */
    private function xamppGuess(): string
    {
        return $this->candidatePaths()[1] ?? $this->candidatePaths()[0];
    }

    private function humanSize(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024 || $unit === 'GB') {
                return round($bytes, $unit === 'B' ? 0 : 1).' '.$unit;
            }

            $bytes /= 1024;
        }

        return $bytes.' B';
    }
}
