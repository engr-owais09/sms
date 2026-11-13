<?php

namespace App\Modules\Finance\Services;

use App\Core\Tenancy\TenantTransactionManager;
use App\Modules\Finance\Models\FeeCategory;
use App\Modules\Finance\Models\FeeDiscount;
use App\Modules\Finance\Models\StudentFeeDiscount;
use App\Modules\Student\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StudentConcessionService
{
    /**
     * Assign a concession discount to a student.
     */
    public function assignConcession(Student $student, FeeDiscount $discount, array $attributes = []): StudentFeeDiscount
    {
        return TenantTransactionManager::run(function () use ($student, $discount, $attributes) {
            $validFrom = $attributes['valid_from'] ?? now()->toDateString();
            $validUntil = $attributes['valid_until'] ?? null;

            if ($validUntil !== null && $validUntil < $validFrom) {
                throw new InvalidArgumentException('Concession valid_until date cannot precede valid_from date.');
            }

            return StudentFeeDiscount::updateOrCreate(
                [
                    'tenant_id' => $student->tenant_id,
                    'student_id' => $student->id,
                    'fee_discount_id' => $discount->id,
                ],
                [
                    'custom_value' => $attributes['custom_value'] ?? null,
                    'valid_from' => $validFrom,
                    'valid_until' => $validUntil,
                    'status' => $attributes['status'] ?? 'active',
                    'approved_by_user_id' => $attributes['approved_by_user_id'] ?? null,
                    'approved_at' => $attributes['approved_at'] ?? ($discount->requires_approval ? null : now()),
                    'reason' => $attributes['reason'] ?? null,
                ]
            );
        });
    }

    /**
     * Determine a student's kinship position (1 = first child, 2 = second child, etc.)
     * among active siblings sharing a primary or financial guardian.
     */
    public function evaluateSiblingKinship(Student $student): int
    {
        $guardianIds = DB::table('student_guardians')
            ->where('tenant_id', $student->tenant_id)
            ->where('student_id', $student->id)
            ->where(function ($q) {
                $q->where('is_primary', true)->orWhere('is_financial_sponsor', true);
            })
            ->pluck('guardian_id');

        if ($guardianIds->isEmpty()) {
            return 1;
        }

        $allSiblingIds = DB::table('student_guardians')
            ->where('tenant_id', $student->tenant_id)
            ->whereIn('guardian_id', $guardianIds)
            ->pluck('student_id')
            ->unique();

        $orderedSiblingIds = Student::withoutGlobalScopes()
            ->where('tenant_id', $student->tenant_id)
            ->whereIn('id', $allSiblingIds)
            ->where('status', 'active')
            ->orderBy('admission_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->pluck('id')
            ->toArray();

        $index = array_search($student->id, $orderedSiblingIds, true);

        return ($index === false) ? 1 : ($index + 1);
    }

    /**
     * Calculate item concession for a fee category and gross amount given student's active discounts.
     *
     * @param float $grossAmount
     * @param FeeCategory $category
     * @param Collection<int, StudentFeeDiscount> $studentDiscounts
     * @param string|null $date
     * @return array{gross_amount: float, discount_amount: float, net_amount: float, applied_discounts: array}
     */
    public function calculateItemConcession(
        float $grossAmount,
        FeeCategory $category,
        Collection $studentDiscounts,
        ?string $date = null
    ): array {
        $evalDate = $date ?? now()->toDateString();

        // Filter applicable discounts that are valid on the date and match category (or category is null / universal)
        $applicableDiscounts = $studentDiscounts->filter(function (StudentFeeDiscount $sfd) use ($category, $evalDate) {
            if (!$sfd->isValidOn($evalDate)) {
                return false;
            }

            $discount = $sfd->feeDiscount;
            if (!$discount || !$discount->is_active) {
                return false;
            }

            if ($discount->requires_approval && $sfd->approved_at === null) {
                return false;
            }

            if ($discount->applicable_category_id !== null && $discount->applicable_category_id !== $category->id) {
                return false;
            }

            return true;
        });

        if ($applicableDiscounts->isEmpty() || $grossAmount <= 0) {
            return [
                'gross_amount' => $grossAmount,
                'discount_amount' => 0.00,
                'net_amount' => $grossAmount,
                'applied_discounts' => [],
            ];
        }

        // Determine dominant stacking rule among applicable discounts
        $hasHighestWins = $applicableDiscounts->contains(fn($d) => $d->feeDiscount->stacking_rule === 'highest_wins');
        $hasCompound = $applicableDiscounts->contains(fn($d) => $d->feeDiscount->stacking_rule === 'compound');

        $totalDiscount = 0.00;
        $appliedList = [];

        if ($hasHighestWins && !$hasCompound) {
            // Highest single discount wins
            $bestDiscountAmount = 0.00;
            $bestDiscountRecord = null;
            $bestSfd = null;

            foreach ($applicableDiscounts as $sfd) {
                $discount = $sfd->feeDiscount;
                $value = $sfd->custom_value ?? $discount->discount_value;
                $calc = ($discount->discount_type === 'percentage')
                    ? round(($grossAmount * ($value / 100.0)), 2)
                    : min($value, $grossAmount);

                if ($discount->max_discount_cap !== null) {
                    $calc = min($calc, (float) $discount->max_discount_cap);
                }

                if ($calc > $bestDiscountAmount) {
                    $bestDiscountAmount = $calc;
                    $bestDiscountRecord = $discount;
                    $bestSfd = $sfd;
                }
            }

            if ($bestDiscountRecord !== null && $bestDiscountAmount > 0) {
                $totalDiscount = min($bestDiscountAmount, $grossAmount);
                $appliedList[] = [
                    'student_fee_discount_id' => $bestSfd->id,
                    'discount_id' => $bestDiscountRecord->id,
                    'code' => $bestDiscountRecord->code,
                    'name' => $bestDiscountRecord->name,
                    'contra_account_id' => $bestDiscountRecord->contra_account_id,
                    'amount' => $totalDiscount,
                ];
            }
        } elseif ($hasCompound) {
            // Compound discounts applied sequentially
            $currentNet = $grossAmount;
            foreach ($applicableDiscounts as $sfd) {
                if ($currentNet <= 0) {
                    break;
                }

                $discount = $sfd->feeDiscount;
                $value = $sfd->custom_value ?? $discount->discount_value;
                $calc = ($discount->discount_type === 'percentage')
                    ? round(($currentNet * ($value / 100.0)), 2)
                    : min($value, $currentNet);

                if ($discount->max_discount_cap !== null) {
                    $calc = min($calc, (float) $discount->max_discount_cap);
                }

                $calc = min($calc, $currentNet);
                if ($calc > 0) {
                    $totalDiscount += $calc;
                    $currentNet -= $calc;
                    $appliedList[] = [
                        'student_fee_discount_id' => $sfd->id,
                        'discount_id' => $discount->id,
                        'code' => $discount->code,
                        'name' => $discount->name,
                        'contra_account_id' => $discount->contra_account_id,
                        'amount' => $calc,
                    ];
                }
            }
        } else {
            // Default: sum_capped
            foreach ($applicableDiscounts as $sfd) {
                $discount = $sfd->feeDiscount;
                $value = $sfd->custom_value ?? $discount->discount_value;
                $calc = ($discount->discount_type === 'percentage')
                    ? round(($grossAmount * ($value / 100.0)), 2)
                    : (float) $value;

                if ($discount->max_discount_cap !== null) {
                    $calc = min($calc, (float) $discount->max_discount_cap);
                }

                $availableRoom = max(0.00, $grossAmount - $totalDiscount);
                $effective = min($calc, $availableRoom);

                if ($effective > 0) {
                    $totalDiscount += $effective;
                    $appliedList[] = [
                        'student_fee_discount_id' => $sfd->id,
                        'discount_id' => $discount->id,
                        'code' => $discount->code,
                        'name' => $discount->name,
                        'contra_account_id' => $discount->contra_account_id,
                        'amount' => $effective,
                    ];
                }
            }
        }

        $netAmount = max(0.00, round($grossAmount - $totalDiscount, 2));

        return [
            'gross_amount' => $grossAmount,
            'discount_amount' => round($totalDiscount, 2),
            'net_amount' => $netAmount,
            'applied_discounts' => $appliedList,
        ];
    }
}

