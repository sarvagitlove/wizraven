<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SecureEmailService
{
    /**
     * Send email using external API (no stored credentials)
     * This can integrate with services like Mailgun, SendGrid, etc.
     */
    public function sendInvitationEmail($to, $name, $signupLink, $membershipId)
    {
        try {
            // Option 1: Use a webhook/external service
            $response = Http::post(env('EMAIL_WEBHOOK_URL'), [
                'to' => $to,
                'name' => $name,
                'signup_link' => $signupLink,
                'membership_id' => $membershipId,
                'template' => 'member_invitation'
            ]);

            if ($response->successful()) {
                Log::info("Email sent successfully to {$to}");
                return true;
            }

            Log::error("Email webhook failed", ['response' => $response->body()]);
            return false;

        } catch (\Exception $e) {
            Log::error("Email service error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Alternative: Use environment-based configuration
     */
    public function sendViaEnvironmentConfig($to, $subject, $content)
    {
        $apiKey = env('EMAIL_API_KEY'); // Set via system environment, not .env file
        
        if (!$apiKey) {
            Log::warning("Email API key not configured");
            return false;
        }

        // Use the API key to send email via service like Mailgun
        return $this->sendViaMailgun($to, $subject, $content, $apiKey);
    }

    private function sendViaMailgun($to, $subject, $content, $apiKey)
    {
        $domain = env('MAILGUN_DOMAIN');
        
        $response = Http::withBasicAuth('api', $apiKey)
            ->asForm()
            ->post("https://api.mailgun.net/v3/{$domain}/messages", [
                'from' => 'ATEA Seattle <noreply@atea-seattle.org>',
                'to' => $to,
                'subject' => $subject,
                'html' => $content
            ]);

        return $response->successful();
    }
}