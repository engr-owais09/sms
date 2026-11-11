<?php

namespace App\Modules\SuperAdmin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SuperAdminDashboardController extends Controller
{
    public function index(): View
    {
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('status', 'active')->count();
        $suspendedTenants = Tenant::where('status', 'suspended')->count();
        $trialTenants = Tenant::where('status', 'trial')->count();
        $totalBranches = Branch::withoutGlobalScopes()->count();

        $tenants = Tenant::with(['branches' => fn($q) => $q->withoutGlobalScopes()])
            ->latest()
            ->paginate(15);

        return view('super-admin.dashboard', compact(
            'totalTenants',
            'activeTenants',
            'suspendedTenants',
            'trialTenants',
            'totalBranches',
            'tenants'
        ));
    }

    public function toggleStatus(Tenant $tenant): RedirectResponse
    {
        $newStatus = ($tenant->status === 'active') ? 'suspended' : 'active';
        $tenant->update(['status' => $newStatus]);

        return redirect()->back()->with('success', "Tenant [{$tenant->name}] status updated to {$newStatus}.");
    }
}
