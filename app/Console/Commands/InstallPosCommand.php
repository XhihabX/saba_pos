<?php

namespace App\Console\Commands;

use App\Models\PlatformSetting;
use App\Models\User;
use Database\Seeders\SaaSPlanSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class InstallPosCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:install 
                            {--email= : Super Admin email address} 
                            {--password= : Super Admin password (min 12 characters)} 
                            {--force : Force operation without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform fresh production installation: runs migrations, seeds SaaS plans only, and sets up the primary Super Admin account.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('====================================================');
        $this->info('  IOT POS / Saba POS - Production Installation Tool');
        $this->info('====================================================');

        // 1. Run Database Schema Migrations
        $this->info("\n--> Step 1/3: Running database migrations...");
        $this->call('migrate', ['--force' => true]);

        // 2. Seed SaaS Plans ONLY (No demo accounts, no fake transactions)
        $this->info("\n--> Step 2/3: Seeding SaaS subscription plans...");
        $this->call('db:seed', [
            '--class' => SaaSPlanSeeder::class,
            '--force' => true,
        ]);

        // Initialize empty platform settings keys if not present
        $emptyKeys = [
            'bkash_number',
            'nagad_number',
            'rocket_number',
            'bank_name',
            'bank_account_name',
            'bank_account_number',
            'bank_branch_name',
            'bank_routing_number',
            'bank_instructions',
            'support_phone',
            'support_email',
        ];
        foreach ($emptyKeys as $key) {
            PlatformSetting::firstOrCreate(['key' => $key], ['value' => null]);
        }

        // 3. Prompt for Super Admin Credentials
        $this->info("\n--> Step 3/3: Super Admin Account Setup");

        $email = $this->option('email');
        if (empty($email)) {
            $email = $this->ask('Enter Super Admin Email Address');
        }

        $emailValidator = Validator::make(['email' => $email], [
            'email' => 'required|email',
        ]);

        if ($emailValidator->fails()) {
            $this->error('Invalid email address: '.$emailValidator->errors()->first('email'));
            return Command::FAILURE;
        }

        $password = $this->option('password');
        if (empty($password)) {
            $password = $this->secret('Enter Super Admin Password (min 12 characters)');
            $confirmPassword = $this->secret('Confirm Super Admin Password');

            if ($password !== $confirmPassword) {
                $this->error('Passwords do not match.');
                return Command::FAILURE;
            }
        }

        $passValidator = Validator::make(['password' => $password], [
            'password' => 'required|string|min:12',
        ]);

        if ($passValidator->fails()) {
            $this->error('Password validation failed: '.$passValidator->errors()->first('password'));
            return Command::FAILURE;
        }

        // Create or update Super Admin
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Administrator',
                'password' => Hash::make($password),
                'role' => 'super_admin',
                'tenant_id' => null,
                'store_id' => null,
            ]
        );

        $this->newLine();
        $this->info('====================================================');
        $this->info('✓ Production installation completed successfully!');
        $this->info("✓ Super Admin: {$user->email}");
        $this->info('✓ SaaS Plans provisioned (Starter, Growth, Enterprise).');
        $this->info('✓ Platform payment settings initialized as empty.');
        $this->info('====================================================');

        return Command::SUCCESS;
    }
}
