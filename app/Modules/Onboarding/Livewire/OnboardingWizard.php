<?php

namespace App\Modules\Onboarding\Livewire;

use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Institute\Services\TenantProvisioningService;
use Livewire\Component;

class OnboardingWizard extends Component
{
    public int $currentStep = 1;
    public int $totalSteps = 9;

    // Step 1: Institute Type
    public string $type = 'school';

    // Step 2: Institute Legal Info
    public string $name = '';
    public string $slug = '';
    public string $registrationNo = '';
    public string $email = '';
    public string $phone = '';
    public string $city = 'Karachi';

    // Step 3: Branding & Locale
    public string $brandColor = '#1e40af';
    public string $defaultLocale = 'en';

    // Step 4: Primary Branch / Campus
    public string $branchName = 'Main Campus';
    public string $branchAddress = '';

    // Step 5: Academic Calendar
    public string $sessionName = 'Academic Session 2026-2027';
    public string $startDate = '2026-08-01';
    public string $endDate = '2027-05-31';
    public string $workingDays = 'mon_to_sat'; // Pakistani standard

    // Step 6: Cohorts & Starter Templates
    public array $selectedTemplates = [];

    // Step 7: Fee Configuration
    public int $feeDueDay = 10;
    public string $lateFeeType = 'fixed';
    public float $lateFeeAmount = 500.00;

    // Step 8: Staff Invitations
    public string $principalName = '';
    public string $principalEmail = '';
    public string $cashierName = '';
    public string $cashierEmail = '';

    // Step 9: Launch & Completion
    public ?string $workspaceUrl = null;
    public ?string $createdTenantId = null;

    protected function rules(): array
    {
        return match ($this->currentStep) {
            1 => ['type' => 'required|in:school,academy,hybrid'],
            2 => [
                'name' => 'required|string|min:3|max:100',
                'slug' => 'required|string|alpha_dash|unique:tenants,slug',
                'email' => 'required|email',
                'phone' => 'required|string|min:10',
                'city' => 'required|string|max:100',
            ],
            3 => [
                'brandColor' => 'required|string',
                'defaultLocale' => 'required|in:en,ur',
            ],
            4 => [
                'branchName' => 'required|string|min:3',
                'branchAddress' => 'nullable|string',
            ],
            5 => [
                'sessionName' => 'required|string|min:3',
                'startDate' => 'required|date',
                'endDate' => 'required|date|after:startDate',
            ],
            6 => ['selectedTemplates' => 'nullable|array'],
            7 => [
                'feeDueDay' => 'required|integer|between:1,28',
                'lateFeeAmount' => 'required|numeric|min:0',
            ],
            8 => [
                'principalEmail' => 'nullable|email',
                'cashierEmail' => 'nullable|email',
            ],
            default => [],
        };
    }

    public function mount(): void
    {
        $this->setDefaultTemplates();
    }

    public function updatedType(): void
    {
        $this->setDefaultTemplates();
    }

    public function updatedName(): void
    {
        if (empty($this->slug)) {
            $this->slug = \Illuminate\Support\Str::slug($this->name);
        }
    }

    protected function setDefaultTemplates(): void
    {
        if ($this->type === 'school') {
            $this->selectedTemplates = [
                'Primary Section (Grades 1 to 5)',
                'Middle Section (Grades 6 to 8)',
                'Matriculation (Grades 9 & 10)',
                'Cambridge O-Levels (Years 1 to 3)',
            ];
        } else {
            $this->selectedTemplates = [
                'MDCAT Comprehensive Test Prep',
                'ECAT Engineering Entry Test',
                'FSc / Intermediate Evening Coaching',
                'IELTS & Spoken English Fluency',
            ];
        }
    }

    public function nextStep(): void
    {
        $this->validate();

        if ($this->currentStep === 8) {
            $this->completeOnboarding();
            $this->currentStep = 9;
        } elseif ($this->currentStep < $this->totalSteps) {
            $this->currentStep++;
        }
    }

    public function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function completeOnboarding(): void
    {
        $provisioningService = app(TenantProvisioningService::class);

        $result = $provisioningService->provision([
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'default_locale' => $this->defaultLocale,
            'brand_color' => $this->brandColor,
            'owner_name' => $this->name . ' Admin',
            'owner_email' => $this->email,
            'owner_password' => 'Owner123456!',
            'owner_phone' => $this->phone,
            'branch_name' => $this->branchName,
            'city' => $this->city,
            'address' => $this->branchAddress,
        ]);

        $tenant = $result['tenant'];
        $tenant->update([
            'onboarding_step' => 9,
            'onboarding_completed' => true,
            'settings' => array_merge($tenant->settings ?? [], [
                'session_name' => $this->sessionName,
                'session_start' => $this->startDate,
                'session_end' => $this->endDate,
                'fee_due_day' => $this->feeDueDay,
                'late_fee_amount' => $this->lateFeeAmount,
                'selected_templates' => $this->selectedTemplates,
                'registration_no' => $this->registrationNo,
            ]),
        ]);

        $this->createdTenantId = $tenant->id;
        $baseDomain = config('app.base_domain', 'educore.test');
        $this->workspaceUrl = "http://{$tenant->slug}.{$baseDomain}:8000";
    }

    public function render()
    {
        return view('livewire.onboarding-wizard', [
            'isRtl' => $this->defaultLocale === 'ur',
        ]);
    }
}
