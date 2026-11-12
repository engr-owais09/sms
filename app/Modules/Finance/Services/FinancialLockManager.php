<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Exceptions\LockOrderViolationException;
use Illuminate\Support\Facades\DB;

class FinancialLockManager
{
    public const STAGE_NONE = 0;
    public const STAGE_TENANT_BRANCH = 1;
    public const STAGE_STUDENT = 2;
    public const STAGE_INVOICE = 3;
    public const STAGE_ADVANCE = 4;
    public const STAGE_WALLET = 5;
    public const STAGE_MUTATION_AND_GL = 6;

    protected static int $currentStage = self::STAGE_NONE;

    /**
     * Get the currently active lock stage.
     */
    public static function getCurrentStage(): int
    {
        return self::$currentStage;
    }

    /**
     * Enter a specific lock stage, enforcing sequential monotonic progression.
     *
     * @throws LockOrderViolationException
     */
    public static function enterStage(int $targetStage, string $context = ''): void
    {
        if ($targetStage < self::$currentStage) {
            $prev = self::getStageName(self::$currentStage);
            $next = self::getStageName($targetStage);
            $msg = "Lock order violation: Cannot acquire lock for stage {$next} ({$targetStage}) after entering stage {$prev} (" . self::$currentStage . "). Context: {$context}";
            throw new LockOrderViolationException($msg);
        }

        self::$currentStage = $targetStage;
    }

    /**
     * Reset stage to STAGE_NONE upon transaction completion or rollback.
     */
    public static function reset(): void
    {
        self::$currentStage = self::STAGE_NONE;
    }

    /**
     * Stage 2: Lock student(s) row(s) in deterministic order.
     */
    public static function lockStudents(array|string $studentIds): void
    {
        self::enterStage(self::STAGE_STUDENT, 'lockStudents');

        $ids = is_array($studentIds) ? array_unique($studentIds) : [$studentIds];
        sort($ids);

        if (!empty($ids) && DB::getDriverName() === 'pgsql') {
            DB::table('students')
                ->whereIn('id', $ids)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->pluck('id');
        }
    }

    /**
     * Stage 3: Lock invoice(s) row(s) in deterministic order.
     */
    public static function lockInvoices(array|string $invoiceIds): void
    {
        self::enterStage(self::STAGE_INVOICE, 'lockInvoices');

        $ids = is_array($invoiceIds) ? array_unique($invoiceIds) : [$invoiceIds];
        sort($ids);

        if (!empty($ids) && DB::getDriverName() === 'pgsql') {
            DB::table('fee_invoices')
                ->whereIn('id', $ids)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->pluck('id');
        }
    }

    /**
     * Stage 4: Lock advance prepayments in deterministic FIFO order (receipt_date ASC, id ASC).
     */
    public static function lockAdvances(array|string $advanceIds): void
    {
        self::enterStage(self::STAGE_ADVANCE, 'lockAdvances');

        $ids = is_array($advanceIds) ? array_unique($advanceIds) : [$advanceIds];

        if (!empty($ids) && DB::getDriverName() === 'pgsql') {
            DB::table('student_fee_advances')
                ->whereIn('id', $ids)
                ->orderBy('receipt_date', 'asc')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->pluck('id');
        }
    }

    /**
     * Stage 5: Lock student wallet(s) row(s).
     */
    public static function lockWallets(array|string $walletIds): void
    {
        self::enterStage(self::STAGE_WALLET, 'lockWallets');

        $ids = is_array($walletIds) ? array_unique($walletIds) : [$walletIds];
        sort($ids);

        if (!empty($ids) && DB::getDriverName() === 'pgsql') {
            DB::table('student_credit_wallets')
                ->whereIn('id', $ids)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->pluck('id');
        }
    }

    /**
     * Stage 6: Enter mutation and GL posting stage.
     */
    public static function enterMutationAndGL(): void
    {
        self::enterStage(self::STAGE_MUTATION_AND_GL, 'enterMutationAndGL');
    }

    public static function getStageName(int $stage): string
    {
        return match ($stage) {
            self::STAGE_NONE => 'NONE',
            self::STAGE_TENANT_BRANCH => 'TENANT_BRANCH',
            self::STAGE_STUDENT => 'STUDENT',
            self::STAGE_INVOICE => 'INVOICE',
            self::STAGE_ADVANCE => 'ADVANCE',
            self::STAGE_WALLET => 'WALLET',
            self::STAGE_MUTATION_AND_GL => 'MUTATION_AND_GL',
            default => "UNKNOWN_{$stage}",
        };
    }
}

