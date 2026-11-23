<?php

namespace App\Console\Commands;

use App\Modules\Student\Models\StudentDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateDocumentsToS3Command extends Command
{
    protected $signature = 'educore:migrate-documents-to-s3 {--dry-run : List files without uploading}';

    protected $description = 'Migrate tenant-scoped student documents from local private disk to S3-compatible object storage';

    public function handle(): int
    {
        $source = Storage::disk('local');
        $target = $this->option('dry-run') ? null : Storage::disk('s3');
        $migrated = 0;

        StudentDocument::withoutGlobalScopes()
            ->orderBy('created_at')
            ->chunk(100, function ($docs) use ($source, $target, &$migrated): void {
                foreach ($docs as $doc) {
                    if (!$source->exists($doc->file_path)) {
                        continue;
                    }

                    if ($this->option('dry-run')) {
                        $this->line("[DRY-RUN] Would upload: {$doc->file_path}");
                        $migrated++;
                        continue;
                    }

                    $stream = $source->readStream($doc->file_path);
                    if ($stream !== false) {
                        $target->writeStream($doc->file_path, $stream, ['visibility' => 'private']);
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                        $migrated++;
                    }
                }
            });

        $this->info("Processed {$migrated} tenant document(s).");

        return self::SUCCESS;
    }
}
