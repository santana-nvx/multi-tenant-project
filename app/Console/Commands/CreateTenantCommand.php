<?php

namespace App\Console\Commands;

use App\Services\TenantCreationService;
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

        $creation = new TenantCreationService;

        $tenant = $creation->create(
            domain: $domain,
            tenantName: $domain,
            userName: $this->option('user-name'),
            userEmail: $userEmail,
            userPassword: $userPassword,
            adminName: $this->option('admin-name'),
            adminEmail: $adminEmail,
            adminPassword: $adminPassword,
        );

        $this->info("Tenant [{$tenant->id}] created for domain [{$domain}].");
        $this->line("Player login: {$userEmail} / {$userPassword}");
        $this->line("Admin login:  {$adminEmail} / {$adminPassword}");

        return self::SUCCESS;
    }
}
