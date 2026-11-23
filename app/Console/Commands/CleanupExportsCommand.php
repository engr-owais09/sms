<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupExportsCommand extends Command
{
    protected $signature = 'educore:cleanup-exports {--hours=24 : Delete temporary export archives older than N hours}';

    protected $description = 'Idempotently remove expired temporary tenant export archives';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $cutoff = now()->subHours($hours)->timestamp;
        $disk = Storage::disk('local');
        $deleted = 0;

        foreach ($disk->allFiles('exports') as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
                $deleted++;
            }
        }

        $this->info("Removed {$deleted} expired export file(s).");

        return self::SUCCESS;
    }
}
