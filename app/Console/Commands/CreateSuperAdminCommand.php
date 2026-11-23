<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class CreateSuperAdminCommand extends Command
{
    protected $signature = 'educore:create-super-admin
        {--name= : Display name for the Super Admin}
        {--email= : Email address for the Super Admin}
        {--password= : Strong password (min 12 characters)}';

    protected $description = 'Provision a platform Super Admin user without hardcoded seeder credentials';

    public function handle(): int
    {
        (new RoleAndPermissionSeeder())->run();

        $name = $this->option('name')
            ?: env('EDUCORE_SUPER_ADMIN_NAME')
            ?: $this->ask('Super Admin name', 'EduCore Super Admin');

        $email = $this->option('email')
            ?: env('EDUCORE_SUPER_ADMIN_EMAIL')
            ?: $this->ask('Super Admin email');

        $password = $this->option('password')
            ?: env('EDUCORE_SUPER_ADMIN_PASSWORD')
            ?: $this->secret('Super Admin password (min 12 characters)');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => strtolower(trim((string) $email))],
            [
                'name' => trim((string) $name),
                'password' => Hash::make((string) $password),
                'is_super_admin' => true,
                'tenant_id' => null,
                'branch_id' => null,
                'status' => 'active',
                'preferred_locale' => 'en',
            ]
        );

        $superAdminRole = Role::where('name', 'super_admin')->where('guard_name', 'web')->firstOrFail();
        setPermissionsTeamId(null);
        $user->assignRole($superAdminRole);

        $this->info("Super Admin provisioned: {$user->email}");

        return self::SUCCESS;
    }
}
