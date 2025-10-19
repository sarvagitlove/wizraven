<?php

namespace App\Services;

use Google_Client;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;
use Google_Service_Gmail_MessagePart;
use Google_Service_Gmail_MessagePartHeader;
use Illuminate\Support\Facades\Log;
use Exception;

class GmailService
{
    private $client;
    private $gmail;
    private $config;

    public function __construct($skipTokenRefresh = false)
    {
        $this->config = config('gmail');
        $this->initializeClient($skipTokenRefresh);
    }

    /**
     * Initialize Google Client with OAuth 2.0 credentials
     */
    private function initializeClient($skipTokenRefresh = false)
    {
        try {
            $this->client = new Google_Client();
            $this->client->setClientId($this->config['client_id']);
            $this->client->setClientSecret($this->config['client_secret']);
            $this->client->setRedirectUri($this->config['redirect_uri']);
            $this->client->setScopes($this->config['scopes']);
            $this->client->setAccessType('offline');
            $this->client->setApprovalPrompt('force');

            // Set refresh token and get access token
            if (!$skipTokenRefresh && $this->config['refresh_token']) {
                try {
                    $this->logActivity('Attempting to refresh token...');
                    
                    // Fetch access token using refresh token
                    $token = $this->client->fetchAccessTokenWithRefreshToken($this->config['refresh_token']);
                    
                    if (isset($token['error'])) {
                        $this->logActivity('Token refresh error: ' . json_encode($token), 'error');
                        throw new Exception('Failed to refresh access token: ' . ($token['error_description'] ?? $token['error']));
                    }
                    
                    $this->client->setAccessToken($token);
                    $this->logActivity('Access token set successfully');
                    
                } catch (Exception $e) {
                    $this->logActivity('Exception during token refresh: ' . $e->getMessage(), 'error');
                    throw $e;
                }
            } else {
                if ($skipTokenRefresh) {
                    $this->logActivity('Skipping token refresh for auth operation');
                } else {
                    $this->logActivity('No refresh token available', 'warning');
                }
            }

            $this->gmail = new Google_Service_Gmail($this->client);

            $this->logActivity('Gmail client initialized successfully');
        } catch (Exception $e) {
            $this->logActivity('Failed to initialize Gmail client: ' . $e->getMessage(), 'error');
            throw new Exception('Gmail service initialization failed: ' . $e->getMessage());
        }
    }

    /**
     * Send an email using Gmail API
     *
     * @param string $to
     * @param string $subject
     * @param string $message
     * @param array $options
     * @return array
     */
    public function sendEmail($to, $subject, $message, $options = [])
    {
        try {
            // Validate inputs
            if (empty($to) || empty($subject) || empty($message)) {
                throw new Exception('Missing required fields: to, subject, or message');
            }

            // Prepare email data
            $fromEmail = $options['from_email'] ?? $this->config['from_email'];
            $fromName = $options['from_name'] ?? $this->config['from_name'];
            $isHtml = $options['is_html'] ?? true;
            $attachments = $options['attachments'] ?? [];

            // Create the email message
            $emailMessage = $this->createEmailMessage($to, $fromEmail, $fromName, $subject, $message, $isHtml, $attachments);

            // Send the email
            $result = $this->gmail->users_messages->send('me', $emailMessage);

            $this->logActivity("Email sent successfully to {$to}. Message ID: " . $result->getId());

            return [
                'success' => true,
                'message' => 'Email sent successfully',
                'message_id' => $result->getId(),
                'to' => $to,
                'subject' => $subject
            ];

        } catch (Exception $e) {
            $this->logActivity("Failed to send email to {$to}: " . $e->getMessage(), 'error');
            
            return [
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage(),
                'to' => $to,
                'subject' => $subject
            ];
        }
    }

    /**
     * Create email message for Gmail API
     *
     * @param string $to
     * @param string $fromEmail
     * @param string $fromName
     * @param string $subject
     * @param string $message
     * @param bool $isHtml
     * @param array $attachments
     * @return Google_Service_Gmail_Message
     */
    private function createEmailMessage($to, $fromEmail, $fromName, $subject, $message, $isHtml = true, $attachments = [])
    {
        $headers = [
            'To' => $to,
            'From' => "{$fromName} <{$fromEmail}>",
            'Subject' => $subject,
            'Content-Type' => $isHtml ? 'text/html; charset=utf-8' : 'text/plain; charset=utf-8',
            'MIME-Version' => '1.0'
        ];

        $headerString = '';
        foreach ($headers as $key => $value) {
            $headerString .= "{$key}: {$value}\r\n";
        }

        $email = $headerString . "\r\n" . $message;

        // Base64 encode the email (URL-safe)
        $encodedEmail = rtrim(strtr(base64_encode($email), '+/', '-_'), '=');

        $gmailMessage = new Google_Service_Gmail_Message();
        $gmailMessage->setRaw($encodedEmail);

        return $gmailMessage;
    }

    /**
     * Get OAuth 2.0 authorization URL
     *
     * @return string
     */
    public function getAuthUrl()
    {
        return $this->client->createAuthUrl();
    }

    /**
     * Exchange authorization code for access and refresh tokens
     *
     * @param string $code
     * @return array
     */
    public function handleAuthCallback($code)
    {
        try {
            $token = $this->client->fetchAccessTokenWithAuthCode($code);
            
            if (isset($token['error'])) {
                throw new Exception('Error fetching access token: ' . $token['error']);
            }

            $this->logActivity('OAuth tokens obtained successfully');

            return [
                'success' => true,
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'] ?? null,
                'expires_in' => $token['expires_in'] ?? null
            ];

        } catch (Exception $e) {
            $this->logActivity('Failed to handle auth callback: ' . $e->getMessage(), 'error');
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Send a test email
     *
     * @param string $to
     * @return array
     */
    public function sendTestEmail($to)
    {
        $subject = 'ATEA Seattle - Gmail API Test Email';
        $message = '
        <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <div style="text-align: center; margin-bottom: 30px;">
                    <h1 style="color: #2c3e50;">ATEA Seattle</h1>
                    <h2 style="color: #3498db;">Gmail API Test Email</h2>
                </div>
                
                <p>Hello!</p>
                
                <p>This is a test email sent through the Gmail API integration for ATEA Seattle.</p>
                
                <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <h3 style="margin-top: 0; color: #27ae60;">✅ Gmail API Status: Working</h3>
                    <ul>
                        <li>OAuth 2.0 authentication: Active</li>
                        <li>Email delivery: Successful</li>
                        <li>HTML content: Supported</li>
                        <li>Timestamp: ' . now()->format('Y-m-d H:i:s T') . '</li>
                    </ul>
                </div>
                
                <p>If you received this email, the Gmail API integration is working correctly!</p>
                
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; color: #666;">
                    <p><strong>Asian Trade & Entrepreneur Association - Seattle Chapter</strong><br>
                    Building bridges, creating opportunities, fostering success</p>
                </div>
            </div>
        </body>
        </html>';

        return $this->sendEmail($to, $subject, $message, ['is_html' => true]);
    }

        /**
     * Check Gmail service configuration
     */
    public function checkConfiguration()
    {
        $config = $this->config;
        
        // Debug: Check if config is loaded
        if (!$config) {
            Log::error('Gmail config is null or empty');
            $config = []; // Provide empty array as fallback
        }

        $issues = [];

        if (empty($config['client_id'])) {
            $issues[] = 'GOOGLE_CLIENT_ID is not set';
        }

        if (empty($config['client_secret'])) {
            $issues[] = 'GOOGLE_CLIENT_SECRET is not set';
        }

        if (empty($config['refresh_token'])) {
            $issues[] = 'GOOGLE_REFRESH_TOKEN is not set';
        }

        if (empty($config['from_email'])) {
            $issues[] = 'GMAIL_FROM_EMAIL is not set';
        }

        return [
            'configured' => empty($issues),
            'issues' => $issues,
            'client_id' => !empty($config['client_id']),
            'client_secret' => !empty($config['client_secret']),
            'refresh_token' => !empty($config['refresh_token']),
            'redirect_uri' => $config['redirect_uri'] ?? null,
            'from_email' => $config['from_email'] ?? null,
            'from_name' => $config['from_name'] ?? null
        ];
    }

    /**
     * Log Gmail service activities
     *
     * @param string $message
     * @param string $level
     */
    private function logActivity($message, $level = 'info')
    {
        if (!$this->config['enable_logging']) {
            return;
        }

        $timestamp = now()->format('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] Gmail Service: {$message}";

        // Log to Laravel log
        Log::channel('single')->{$level}($logMessage);

        // Log to dedicated Gmail log file
        try {
            file_put_contents(
                $this->config['log_file'],
                $logMessage . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        } catch (Exception $e) {
            Log::error('Failed to write to Gmail log file: ' . $e->getMessage());
        }
    }
}