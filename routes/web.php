<?php

use App\Core\Tenancy\TenantContext;
use App\Modules\Onboarding\Livewire\OnboardingWizard;
use App\Modules\SuperAdmin\Controllers\SuperAdminDashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Main Domain / Subdomain Router
Route::get('/', function (Request $request) {
    $tenant = app(TenantContext::class)->getTenant();

    if ($tenant) {
        $currentBranch = app(TenantContext::class)->getBranch();
        return view('tenant.dashboard', compact('tenant', 'currentBranch'));
    }

    // Central Platform Root
    return redirect()->route('super-admin.dashboard');
})->name('home');

// Institute Onboarding Wizard
Route::get('/onboarding', function () {
    return view('onboarding.index');
})->name('onboarding.index');

// Super Admin Platform Control Plane
Route::prefix('super-admin')->name('super-admin.')->group(function () {
    Route::get('/', [SuperAdminDashboardController::class, 'index'])->name('dashboard');
    Route::post('/tenants/{tenant}/toggle-status', [SuperAdminDashboardController::class, 'toggleStatus'])->name('tenants.toggle-status');
});
