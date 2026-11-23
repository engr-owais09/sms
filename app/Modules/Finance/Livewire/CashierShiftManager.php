<?php

namespace App\Modules\Finance\Livewire;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Finance\Models\CashierSession;
use App\Modules\Finance\Services\CashierSessionService;
use App\Modules\Institute\Models\Branch;
use Livewire\Component;

class CashierShiftManager extends Component
{
    public ?CashierSession $activeSession = null;
    public float $openingFloat = 0.00;
    public string $openingNotes = '';

    public bool $showBlindCloseModal = false;

    // Denominations
    public int $note5000 = 0;
    public int $note1000 = 0;
    public int $note500 = 0;
    public int $note100 = 0;
    public int $note50 = 0;
    public int $note20 = 0;
    public int $note10 = 0;
    public float $coins = 0.00;

    public string $closingNotes = '';
    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->refreshActiveSession();
    }

    public function refreshActiveSession(): void
    {
        $tenant = app(TenantContext::class)->getTenant();
        $user = auth()->user();

        if ($tenant && $user) {
            $this->activeSession = CashierSession::where('tenant_id', $tenant->id)
                ->where('cashier_user_id', $user->id)
                ->where('status', 'open')
                ->latest()
                ->first();
        }
    }

    public function openShift(): void
    {
        $this->resetFeedback();
        $tenant = app(TenantContext::class)->getTenant();
        $branch = app(TenantContext::class)->getBranch() ?? Branch::where('tenant_id', $tenant->id)->first();
        $user = auth()->user();
        if (!$user) {
            throw new \Illuminate\Auth\AuthenticationException('Unauthenticated.');
        }

        if (!$branch) {
            $this->errorMessage = 'No active branch selected.';
            return;
        }

        try {
            $sessionService = app(CashierSessionService::class);
            $session = $sessionService->openSession($user, $branch, $this->openingFloat);

            $this->activeSession = $session;
            $this->openingFloat = 0.00;
            $this->openingNotes = '';
            $this->successMessage = "Cashier shift opened successfully! Session code: #{$session->session_code}";
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to open shift: ' . $e->getMessage();
        }
    }

    public function openCloseModal(): void
    {
        $this->resetFeedback();
        $this->showBlindCloseModal = true;
        $this->note5000 = 0;
        $this->note1000 = 0;
        $this->note500 = 0;
        $this->note100 = 0;
        $this->note50 = 0;
        $this->note20 = 0;
        $this->note10 = 0;
        $this->coins = 0.00;
        $this->closingNotes = '';
    }

    public function getCountedTotalProperty(): float
    {
        return round(
            ($this->note5000 * 5000) +
            ($this->note1000 * 1000) +
            ($this->note500 * 500) +
            ($this->note100 * 100) +
            ($this->note50 * 50) +
            ($this->note20 * 20) +
            ($this->note10 * 10) +
            $this->coins,
            2
        );
    }

    public function submitBlindClose(): void
    {
        $this->resetFeedback();

        if (!$this->activeSession) {
            $this->errorMessage = 'No active session found to close.';
            return;
        }

        $denominations = [
            5000 => $this->note5000,
            1000 => $this->note1000,
            500 => $this->note500,
            100 => $this->note100,
            50 => $this->note50,
            20 => $this->note20,
            10 => $this->note10,
            1 => $this->coins,
        ];

        try {
            $sessionService = app(CashierSessionService::class);
            $closedSession = $sessionService->closeSessionBlind(
                $this->activeSession,
                $denominations,
                $this->closingNotes
            );

            $this->showBlindCloseModal = false;
            $this->activeSession = null;
            $this->successMessage = "Shift closed successfully with declared cash PKR " . number_format($closedSession->actual_counted_cash, 2) . ". Ready for supervisor verification.";
            $this->refreshActiveSession();
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to close shift: ' . $e->getMessage();
        }
    }

    public function verifySession(string $sessionId, string $depositAccountCode = '1021'): void
    {
        $this->resetFeedback();
        $user = auth()->user();
        if (!$user) {
            throw new \Illuminate\Auth\AuthenticationException('Unauthenticated.');
        }
        $session = CashierSession::findOrFail($sessionId);

        try {
            $sessionService = app(CashierSessionService::class);
            $sessionService->verifyAndPostClosing($session, $user, $depositAccountCode);
            $this->successMessage = "Session #{$session->session_code} verified and posted by supervisor successfully!";
            $this->refreshActiveSession();
        } catch (\Throwable $e) {
            $this->errorMessage = 'Verification failed: ' . $e->getMessage();
        }
    }

    protected function resetFeedback(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    public function render()
    {
        $tenant = app(TenantContext::class)->getTenant();
        $recentSessions = CashierSession::with(['cashier', 'branch', 'reconciledByUser'])
            ->where('tenant_id', $tenant ? $tenant->id : null)
            ->latest('opened_at')
            ->limit(10)
            ->get();

        return view('livewire.finance.cashier-shift-manager', [
            'tenant' => $tenant,
            'recentSessions' => $recentSessions,
            'countedTotal' => $this->countedTotal,
            'isRtl' => app()->getLocale() === 'ur',
        ]);
    }
}
