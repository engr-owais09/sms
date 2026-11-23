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

    // Step 2: Institute Legal Info & Owner Credentials
    public string $name = '';
    public string $slug = '';
    public string $registrationNo = '';
    public string $email = '';
    public string $phone = '';
    public string $city = 'Karachi';
    public string $ownerPassword = '';

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
            1 => ['type' => 'required|in:school,academy,hybrid,both'],
            2 => [
                'name' => 'required|string|min:3|max:100',
                'slug' => [
                    'required',
                    'string',
                    'alpha_dash',
                    \Illuminate\Validation\Rule::unique('tenants', 'slug')->ignore($this->createdTenantId),
                ],
                'email' => 'required|email',
                'phone' => 'required|string|min:10',
                'city' => 'required|string|max:100',
                'ownerPassword' => 'required|string|min:8',
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
                'principalName' => 'nullable|required_with:principalEmail|string|min:2|max:100',
                'principalEmail' => 'nullable|email|different:email',
                'cashierName' => 'nullable|required_with:cashierEmail|string|min:2|max:100',
                'cashierEmail' => 'nullable|email|different:email|different:principalEmail',
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
        $schoolTemplates = [
            'Primary Section (Grades 1 to 5)',
            'Middle Section (Grades 6 to 8)',
            'Matriculation (Grades 9 & 10)',
            'Cambridge O-Levels (Years 1 to 3)',
        ];

        $academyTemplates = [
            'MDCAT Comprehensive Test Prep',
            'ECAT Engineering Entry Test',
            'FSc / Intermediate Evening Coaching',
            'IELTS & Spoken English Fluency',
        ];

        if ($this->type === 'school') {
            $this->selectedTemplates = $schoolTemplates;
        } elseif ($this->type === 'academy') {
            $this->selectedTemplates = $academyTemplates;
        } else {
            $this->selectedTemplates = [
                'Matriculation (Grades 9 & 10)',
                'Cambridge O-Levels (Years 1 to 3)',
                'MDCAT Comprehensive Test Prep',
                'ECAT Engineering Entry Test',
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
        $normalizedType = $this->type === 'both' ? 'hybrid' : $this->type;

        $result = $provisioningService->provision([
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $normalizedType,
            'default_locale' => $this->defaultLocale,
            'brand_color' => $this->brandColor,
            'owner_name' => $this->name . ' Admin',
            'owner_email' => $this->email,
            'owner_password' => $this->ownerPassword,
            'owner_phone' => $this->phone,
            'branch_name' => $this->branchName,
            'city' => $this->city,
            'address' => $this->branchAddress,
            'registration_no' => $this->registrationNo,
            'session_name' => $this->sessionName,
            'session_start' => $this->startDate,
            'session_end' => $this->endDate,
            'working_days' => $this->workingDays,
            'selected_templates' => $this->selectedTemplates,
            'fee_due_day' => $this->feeDueDay,
            'late_fee_type' => $this->lateFeeType,
            'late_fee_amount' => $this->lateFeeAmount,
            'principal_name' => $this->principalName ?: null,
            'principal_email' => $this->principalEmail ?: null,
            'cashier_name' => $this->cashierName ?: null,
            'cashier_email' => $this->cashierEmail ?: null,
            'seed_financial_defaults' => true,
            'onboarding_step' => 9,
            'onboarding_completed' => true,
        ]);

        $tenant = $result['tenant'];
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
