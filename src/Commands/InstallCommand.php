<?php

namespace CasSystem\LaravelClient\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cas:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the Laravel CAS Client package';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Installing Laravel CAS Client...');

        // 1. Publish Configuration
        $this->info('Publishing configuration...');
        $this->call('vendor:publish', [
            '--tag' => 'cas-client-config',
            '--force' => true,
        ]);

        // 2. Setup User Model. The package migration is registered through
        // loadMigrationsFrom(), so it does not need to be published.
        $userModelPath = $this->ask('Where is your User model located?', 'app/Models/User.php');
        $this->setupUserModel($userModelPath);

        // 3. Update .env
        $this->updateEnvFile();

        $this->info('CAS Client installed successfully!');
        $this->comment('Please run "php artisan migrate" to add the necessary columns to your users table.');
    }

    protected function setupUserModel($path)
    {
        $file = base_path($path);

        if (!File::exists($file)) {
            $this->error("User model not found at {$path}");
            return;
        }

        $content = File::get($file);

        // Check if Trait is already imported
        if (strpos($content, 'CasUserTrait') !== false) {
            $this->info('CasUserTrait already added to User model.');
            return;
        }

        // Add Use Statement
        $traitNamespace = 'use CasSystem\\LaravelClient\\Traits\\CasUserTrait;';
        
        if (strpos($content, $traitNamespace) === false) {
            $content = preg_replace(
                '/^namespace\s+.*;/m',
                '$0' . "\n\n" . "use CasSystem\LaravelClient\Traits\CasUserTrait;",
                $content
            );
        }

        if (preg_match('/class\s+User\s+extends\s+Authenticatable\s*\{/', $content)) {
             $content = preg_replace(
                '/(class\s+User\s+extends\s+Authenticatable\s*\{)/',
                '$1' . "\n    use CasUserTrait;",
                $content
            );
            $this->info('Added CasUserTrait to User model.');
        } else {
            $this->warn('Could not automatically add CasUserTrait to class definition. Please add "use CasUserTrait;" manually.');
        }

        File::put($file, $content);
    }

    protected function updateEnvFile()
    {
        $envPath = base_path('.env');
        if (!File::exists($envPath)) {
            return;
        }

        $content = File::get($envPath);
        $newVars = [
            'CAS_SERVER_URL' => 'http://127.0.0.1:8001',
            'CAS_CLIENT_ID' => 'your_client_id',
            'CAS_CLIENT_SECRET' => 'your_client_secret',
            'CAS_CREATE_LOCAL_USERS' => 'true',
            'CAS_USER_DASHBOARD' => '/dashboard',
        ];

        foreach ($newVars as $key => $value) {
            if (strpos($content, $key) === false) {
                $content .= "\n{$key}={$value}";
                $this->info("Added {$key} to .env");
            }
        }

        File::put($envPath, $content);
    }
}
