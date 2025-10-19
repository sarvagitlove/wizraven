<?php

namespace App\Http\Controllers;

use App\Services\GmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class GmailController extends Controller
{
    private $gmailService;

    public function __construct(GmailService $gmailService)
    {
        $this->gmailService = $gmailService;
    }

    /**
     * Send email via Gmail API
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendMail(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'to' => 'required|email',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'is_html' => 'boolean',
            'from_email' => 'email',
            'from_name' => 'string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            // Prepare options
            $options = [
                'is_html' => $request->get('is_html', true),
                'from_email' => $request->get('from_email'),
                'from_name' => $request->get('from_name')
            ];

            // Remove null values
            $options = array_filter($options, function($value) {
                return $value !== null;
            });

            // Send email
            $result = $this->gmailService->sendEmail(
                $request->to,
                $request->subject,
                $request->message,
                $options
            );

            return response()->json($result, $result['success'] ? 200 : 500);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Email sending failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send test email
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendTestMail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'to' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $result = $this->gmailService->sendTestEmail($request->to);
            return response()->json($result, $result['success'] ? 200 : 500);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Test email failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Gmail service configuration status
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatus()
    {
        try {
            $status = $this->gmailService->checkConfiguration();
            return response()->json([
                'success' => true,
                'gmail_service' => $status
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get OAuth authorization URL
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAuthUrl()
    {
        try {
            $url = $this->gmailService->getAuthUrl();
            
            return response()->json([
                'success' => true,
                'auth_url' => $url,
                'instructions' => [
                    '1. Visit the authorization URL',
                    '2. Sign in with your Gmail account',
                    '3. Grant permissions to the application',
                    '4. Copy the authorization code from the callback',
                    '5. Use the code with /gmail/auth/callback endpoint'
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate auth URL: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle OAuth callback and exchange code for tokens
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleAuthCallback(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Authorization code is required',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $result = $this->gmailService->handleAuthCallback($request->code);
            
            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Authorization successful',
                    'tokens' => $result,
                    'next_steps' => [
                        '1. Copy the refresh_token from the response',
                        '2. Add it to your .env file as GOOGLE_REFRESH_TOKEN',
                        '3. Restart your Laravel application',
                        '4. Test email sending with /gmail/test endpoint'
                    ]
                ]);
            } else {
                return response()->json($result, 400);
            }

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Authorization failed: ' . $e->getMessage()
            ], 500);
        }
    }
}