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

        // 2. Update .env. CAS state is stored in the session/cache, so the
        // package does not alter the host application's database schema.
        $this->updateEnvFile();

        $this->info('CAS Client installed successfully!');
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
