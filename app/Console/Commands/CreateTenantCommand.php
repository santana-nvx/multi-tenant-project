<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateTenantCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:create
        {domain : The domain the tenant will be identified by, e.g. acme.test}
        {--user-name=Player : Name of the initial player}
        {--user-email= : Email of the initial player (defaults to player@{domain})}
        {--user-password= : Password of the initial player (random if omitted)}
        {--admin-name=Admin : Name of the initial admin}
        {--admin-email= : Email of the initial admin (defaults to admin@{domain})}
        {--admin-password= : Password of the initial admin (random if omitted)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a tenant, its domain, and an initial player and admin';

    public function handle(): int
    {
        $domain = (string) $this->argument('domain');

        $userEmail = $this->option('user-email') ?? "player@{$domain}";
        $userPassword = $this->option('user-password') ?? Str::random(16);
        $adminEmail = $this->option('admin-email') ?? "admin@{$domain}";
        $adminPassword = $this->option('admin-password') ?? Str::random(16);

        $tenant = Tenant::create();

        $tenant->domains()->create(['domain' => $domain]);

        [$user, $admin] = $tenant->run(function () use ($userEmail, $userPassword, $adminEmail, $adminPassword) {
            $user = User::create([
                'name' => $this->option('user-name'),
                'email' => $userEmail,
                'password' => $userPassword,
            ]);

            $admin = Admin::create([
                'name' => $this->option('admin-name'),
                'email' => $adminEmail,
                'password' => $adminPassword,
            ]);

            return [$user, $admin];
        });

        $this->info("Tenant [{$tenant->id}] created for domain [{$domain}].");
        $this->line("Player login: {$user->email} / {$userPassword}");
        $this->line("Admin login:  {$admin->email} / {$adminPassword}");

        return self::SUCCESS;
    }
}
