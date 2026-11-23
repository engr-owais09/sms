<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'educore:backup-database
        {--output= : Custom output directory}
        {--retention-days=14 : Delete backups older than N days}
        {--dry-run : Validate configuration without executing pg_dump}';

    protected $description = 'Create an encrypted-capable PostgreSQL logical backup (pg_dump) with retention pruning';

    public function handle(): int
    {
        $connName = config('database.default', 'pgsql');
        $conn = config("database.connections.{$connName}");

        if (($conn['driver'] ?? null) !== 'pgsql') {
            $this->error('Backup command only supports PostgreSQL connections.');
            return self::FAILURE;
        }

        $backupDir = $this->option('output') ?: storage_path('app/backups/db');
        File::ensureDirectoryExists($backupDir);

        $timestamp = now()->format('Ymd_His');
        $database = (string) ($conn['database'] ?? 'educore_saas');
        $dumpPath = rtrim($backupDir, '/\\').DIRECTORY_SEPARATOR."{$database}_{$timestamp}.dump";

        if ($this->option('dry-run')) {
            $this->info("Dry-run OK. Target backup path: {$dumpPath}");
            return self::SUCCESS;
        }

        $pgDumpBin = env('EDUCORE_PG_DUMP_PATH');
        if (empty($pgDumpBin) && PHP_OS_FAMILY === 'Windows' && File::exists('C:\\Program Files\\PostgreSQL\\16\\bin\\pg_dump.exe')) {
            $pgDumpBin = 'C:\\Program Files\\PostgreSQL\\16\\bin\\pg_dump.exe';
        }
        $pgDumpBin = $pgDumpBin ?: 'pg_dump';

        $process = new Process([
            $pgDumpBin,
            '-Fc',
            '-h', (string) ($conn['host'] ?? '127.0.0.1'),
            '-p', (string) ($conn['port'] ?? '5432'),
            '-U', (string) ($conn['username'] ?? 'educore_migrator'),
            '-d', $database,
            '-f', $dumpPath,
        ], null, [
            'PGPASSWORD' => (string) ($conn['password'] ?? ''),
        ], null, 600);

        $process->run();

        if (!$process->isSuccessful()) {
            Log::error('Database backup failed.', ['error' => $process->getErrorOutput()]);
            $this->error('pg_dump failed: '.$process->getErrorOutput());
            return self::FAILURE;
        }

        $finalPath = $dumpPath;
        $passphrase = env('EDUCORE_BACKUP_PASSPHRASE');
        if (is_string($passphrase) && $passphrase !== '') {
            $encPath = $dumpPath.'.enc';
            $iv = random_bytes(16);
            $key = hash('sha256', $passphrase, true);
            $encrypted = openssl_encrypt(File::get($dumpPath), 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
            if ($encrypted === false) {
                Log::error('Database backup encryption failed.');
                $this->error('Encryption failed.');
                return self::FAILURE;
            }
            File::put($encPath, $iv.$encrypted);
            File::delete($dumpPath);
            $finalPath = $encPath;
        }

        $retentionDays = max(1, (int) $this->option('retention-days'));
        $cutoff = now()->subDays($retentionDays)->timestamp;
        foreach (File::files($backupDir) as $file) {
            if ($file->getMTime() < $cutoff) {
                File::delete($file->getPathname());
            }
        }

        Log::info('Database backup completed.', ['path' => $finalPath, 'size_bytes' => File::size($finalPath)]);
        $this->info("Backup created: {$finalPath}");

        return self::SUCCESS;
    }
}
