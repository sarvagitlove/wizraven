<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GmailService;
use Exception;

class TestGmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gmail:test {email : The email address to send test email to} {--config : Check configuration only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Gmail API integration by sending a test email or checking configuration';

    private $gmailService;

    /**
     * Create a new command instance.
     */
    public function __construct(GmailService $gmailService)
    {
        parent::__construct();
        $this->gmailService = $gmailService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $configOnly = $this->option('config');

        $this->info('🚀 Gmail API Test Started');
        $this->newLine();

        // Check configuration first
        $this->info('📋 Checking Gmail configuration...');
        
        try {
            $config = $this->gmailService->checkConfiguration();
            
            $this->line('Client ID: ' . ($config['client_id'] ? '✅ Set' : '❌ Missing'));
            $this->line('Client Secret: ' . ($config['client_secret'] ? '✅ Set' : '❌ Missing'));
            $this->line('Refresh Token: ' . ($config['refresh_token'] ? '✅ Set' : '❌ Missing'));
            $this->line('Redirect URI: ' . ($config['redirect_uri'] ?: '❌ Not set'));
            $this->line('From Email: ' . ($config['from_email'] ?: '❌ Not set'));
            $this->line('From Name: ' . ($config['from_name'] ?: '❌ Not set'));
            
            $this->newLine();

            if (!$config['client_id'] || !$config['client_secret']) {
                $this->error('❌ Missing required Gmail credentials!');
                $this->info('Please set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in your .env file');
                return 1;
            }

            if (!$config['refresh_token']) {
                $this->warn('⚠️  Refresh token missing!');
                $this->info('You need to authorize the application first:');
                $this->info('1. Run: php artisan gmail:auth');
                $this->info('2. Or visit the auth URL via API: GET /api/gmail/auth/url');
                
                if (!$configOnly) {
                    return 1;
                }
            }

            if ($configOnly) {
                $this->info('✅ Configuration check complete');
                return 0;
            }

            // Test email sending
            $this->info("📧 Sending test email to: {$email}");
            
            $result = $this->gmailService->sendTestEmail($email);
            
            if ($result['success']) {
                $this->info('✅ Test email sent successfully!');
                $this->line('Message ID: ' . $result['message_id']);
                if (isset($result['gmail_id'])) {
                    $this->line('Gmail ID: ' . $result['gmail_id']);
                }
            } else {
                $this->error('❌ Failed to send test email');
                $this->error('Error: ' . $result['message']);
                return 1;
            }

        } catch (Exception $e) {
            $this->error('❌ Test failed with exception: ' . $e->getMessage());
            $this->newLine();
            $this->line('Stack trace:');
            $this->line($e->getTraceAsString());
            return 1;
        }

        $this->newLine();
        $this->info('🎉 Gmail test completed successfully!');
        return 0;
    }
}
