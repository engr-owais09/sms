<?php

namespace App\Modules\SuperAdmin\Livewire;

use App\Modules\SuperAdmin\Models\SubscriptionPlan;
use App\Modules\SuperAdmin\Services\SubscriptionPlanService;
use Livewire\Component;

class SubscriptionPlanManager extends Component
{
    public string $code = '';
    public string $name = '';
    public string $billingCycle = 'monthly';
    public float $pricePkr = 15000.00;
    public float $perStudentRatePkr = 0.00;
    public int $maxBranches = 1;
    public int $maxStudents = 250;
    public int $maxStaff = 30;

    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    public function seedDefaults(): void
    {
        (new SubscriptionPlanService())->seedDefaultPlans();
        $this->successMessage = 'Default SaaS plans seeded successfully!';
    }

    public function createPlan(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;

        if (!$this->code || !$this->name) {
            $this->errorMessage = 'Plan Code and Name are required.';
            return;
        }

        try {
            SubscriptionPlan::create([
                'code' => strtolower(trim($this->code)),
                'name' => trim($this->name),
                'billing_cycle' => $this->billingCycle,
                'price_pkr' => $this->pricePkr,
                'per_student_rate_pkr' => $this->perStudentRatePkr,
                'max_branches' => $this->maxBranches,
                'max_students' => $this->maxStudents,
                'max_staff' => $this->maxStaff,
                'features_json' => [
                    'finance' => true,
                    'attendance' => true,
                    'exams' => true,
                    'bilingual_pdf' => true,
                ],
                'is_active' => true,
            ]);

            $this->code = '';
            $this->name = '';
            $this->successMessage = 'Subscription plan created successfully!';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.super-admin.subscription-plan-manager', [
            'plans' => SubscriptionPlan::orderBy('price_pkr')->get(),
        ]);
    }
}
