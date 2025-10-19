<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GmailService;
use Exception;

class GmailAuthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gmail:auth {--code= : Authorization code from Google OAuth}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Authorize Gmail API access using Google OAuth 2.0';

    private $gmailService;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        // We'll create the Gmail service with skipTokenRefresh when needed
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔐 Gmail OAuth Authorization');
        $this->newLine();

        $code = $this->option('code');

        if (!$code) {
            // Generate auth URL
            try {
                $this->info('📋 Checking configuration...');
                
                // Create Gmail service without token refresh for auth operations
                $gmailService = new GmailService(true);
                $config = $gmailService->checkConfiguration();
                
                if (!$config['client_id'] || !$config['client_secret']) {
                    $this->error('❌ Missing Gmail credentials!');
                    $this->info('Please add these to your .env file:');
                    $this->info('GOOGLE_CLIENT_ID=your_client_id');
                    $this->info('GOOGLE_CLIENT_SECRET=your_client_secret');
                    $this->info('GOOGLE_REDIRECT_URI=urn:ietf:wg:oauth:2.0:oob');
                    return 1;
                }

                $this->info('✅ Configuration looks good');
                $this->newLine();

                $this->info('🌐 Generating authorization URL...');
                $authUrl = $gmailService->getAuthUrl();
                
                $this->newLine();
                $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                $this->info('🔗 AUTHORIZATION URL:');
                $this->line($authUrl);
                $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                $this->newLine();

                $this->info('📝 INSTRUCTIONS:');
                $this->line('1. Copy the URL above and open it in your browser');
                $this->line('2. Sign in with your Gmail account');
                $this->line('3. Grant permissions to the application');
                $this->line('4. Copy the authorization code from the page');
                $this->line('5. Run this command again with the code:');
                $this->line('   php artisan gmail:auth --code=YOUR_AUTHORIZATION_CODE');
                
                return 0;

            } catch (Exception $e) {
                $this->error('❌ Failed to generate auth URL: ' . $e->getMessage());
                return 1;
            }
        }

        // Exchange code for tokens
        try {
            $this->info("🔄 Exchanging authorization code for tokens...");
            
            // Create Gmail service without token refresh for auth operations
            $gmailService = new GmailService(true);
            $result = $gmailService->handleAuthCallback($code);
            
            if ($result['success']) {
                $this->info('✅ Authorization successful!');
                $this->newLine();
                
                $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                $this->info('🔑 TOKENS RECEIVED:');
                $this->line('Access Token: ' . substr($result['access_token'], 0, 20) . '...');
                $this->line('Refresh Token: ' . substr($result['refresh_token'], 0, 20) . '...');
                $this->line('Expires In: ' . $result['expires_in'] . ' seconds');
                $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                $this->newLine();

                $this->info('📝 NEXT STEPS:');
                $this->line('1. Add this refresh token to your .env file:');
                $this->line('   GOOGLE_REFRESH_TOKEN=' . $result['refresh_token']);
                $this->line('2. Restart your Laravel application');
                $this->line('3. Test email sending:');
                $this->line('   php artisan gmail:test your@email.com');
                
                return 0;
            } else {
                $this->error('❌ Authorization failed: ' . $result['message']);
                return 1;
            }

        } catch (Exception $e) {
            $this->error('❌ Authorization failed: ' . $e->getMessage());
            return 1;
        }
    }
}
