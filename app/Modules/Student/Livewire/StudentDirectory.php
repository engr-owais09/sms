<?php

namespace App\Modules\Student\Livewire;

use App\Core\Tenancy\TenantContext;
use App\Modules\Student\Models\Student;
use Livewire\Component;
use Livewire\WithPagination;

class StudentDirectory extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $tenant = app(TenantContext::class)->getTenant();

        $students = Student::with(['branch', 'guardians', 'schoolEnrollments.schoolSection.schoolClass', 'academyEnrollments.batch.course'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('first_name', 'ILIKE', "%{$this->search}%")
                        ->orWhere('last_name', 'ILIKE', "%{$this->search}%")
                        ->orWhere('admission_number', 'ILIKE', "%{$this->search}%");
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->latest('admission_date')
            ->paginate(15);

        return view('livewire.student-directory', [
            'tenant' => $tenant,
            'students' => $students,
            'isRtl' => app()->getLocale() === 'ur',
        ]);
    }
}

