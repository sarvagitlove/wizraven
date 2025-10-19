<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ActivationLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Models\MemberProfile;

class ActivationController extends Controller
{
    /**
     * Activate user account via token (public endpoint)
     */
    public function activate($token)
    {
        $activationLink = ActivationLink::where('token', $token)->first();

        if (!$activationLink) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid activation link'
            ], 404);
        }

        if (!$activationLink->isValid()) {
            $message = 'Activation link has ';
            if ($activationLink->isExpired()) {
                $message .= 'expired';
            } elseif ($activationLink->isUsed()) {
                $message .= 'already been used';
            } else {
                $message .= 'been deactivated';
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'expired' => $activationLink->isExpired(),
                'used' => $activationLink->isUsed()
            ], 400);
        }

        // Mark activation link as used
        $activationLink->markAsUsed();

        // Activate the user
        $user = $activationLink->user;
        $user->update(['status' => 'active']);

        return response()->json([
            'success' => true,
            'message' => 'Account activated successfully! You can now log in with Google.',
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status
            ]
        ]);
    }

    /**
     * Get activation link details (for frontend to check validity)
     */
    public function checkLink($token)
    {
        $activationLink = ActivationLink::with(['user'])
            ->where('token', $token)
            ->first();

        if (!$activationLink) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid activation link'
            ], 404);
        }

        $isValid = $activationLink->isValid();
        $isExpired = $activationLink->isExpired();
        $isUsed = $activationLink->isUsed();

        return response()->json([
            'success' => true,
            'link' => [
                'token' => $activationLink->token,
                'email' => $activationLink->email,
                'expires_at' => $activationLink->expires_at,
                'used_at' => $activationLink->used_at,
                'is_valid' => $isValid,
                'is_expired' => $isExpired,
                'is_used' => $isUsed,
                'user' => [
                    'name' => $activationLink->user->name,
                    'email' => $activationLink->user->email,
                    'status' => $activationLink->user->status
                ]
            ]
        ]);
    }

    /**
     * Resend activation link (public endpoint with rate limiting)
     */
    public function resendLink(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        $user = User::where('email', $request->email)->first();

        // Check if user is already active
        if ($user->status === 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Account is already activated'
            ], 400);
        }

        // Check if user is disabled
        if ($user->status === 'disabled') {
            return response()->json([
                'success' => false,
                'message' => 'Account has been disabled. Please contact support.'
            ], 400);
        }

        // Check for recent activation link (rate limiting)
        $recentLink = $user->activationLinks()
            ->where('created_at', '>', now()->subMinutes(5))
            ->first();

        if ($recentLink) {
            return response()->json([
                'success' => false,
                'message' => 'Activation link was recently sent. Please wait before requesting another.'
            ], 429);
        }

        // Deactivate old links
        $user->activationLinks()->update(['is_active' => false]);

        // Generate new activation link
        $activationLink = ActivationLink::generate($user, $request->email);

        // TODO: Send activation email here
        // $this->sendActivationEmail($user, $activationLink);

        return response()->json([
            'success' => true,
            'message' => 'New activation link has been sent to your email',
            // Remove this in production - only for testing
            'activation_link' => url('/api/activate/' . $activationLink->token)
        ]);
    }

    /**
     * Submit a member profile using an activation token (public flow)
     */
    public function submitProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|uuid',
            'profile' => 'required|array',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please correct the highlighted errors and try again.',
                'errors' => $validator->errors()
            ], 422);
        }

        $activationLink = ActivationLink::where('token', $request->token)->first();
        if (!$activationLink) {
            return response()->json([
                'success' => false,
                'message' => 'Your signup link is invalid. Please request a new invitation from the admin.'
            ], 404);
        }

        if ($activationLink->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Your signup link has expired. Please request a new invitation from the admin.'
            ], 400);
        }
        if ($activationLink->isUsed()) {
            return response()->json([
                'success' => false,
                'message' => 'This signup link has already been used. Please request a new invitation from the admin.'
            ], 400);
        }
        if (!$activationLink->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your signup link has been deactivated. Please contact the admin for a new invitation.'
            ], 400);
        }

        $user = $activationLink->user;
        // Set and hash password if provided
        if ($request->filled('password')) {
            $user->password = bcrypt($request->password);
            $user->save();
        }

        // Map frontend profile fields to backend schema
        $p = $request->input('profile', []);
        $profileData = [
            'user_id' => $user->id,
            'business_name' => $p['businessName'] ?? null,
            'business_type' => $p['businessType'] ?? null,
            'industry' => $p['industry'] ?? 'Other',
            'business_description' => $p['businessDescription'] ?? null,
            'website' => $p['website'] ?? null,
            'phone' => $p['phone'] ?? null,
            'business_email' => $p['businessEmail'] ?? ($p['email'] ?? null),
            'address_line_1' => $p['address'] ?? ($p['address_line_1'] ?? null),
            'address_line_2' => $p['address_line_2'] ?? null,
            'city' => $p['city'] ?? null,
            'state' => $p['state'] ?? null,
            'zip_code' => $p['zipCode'] ?? null,
            'country' => $p['country'] ?? 'USA',
            'year_established' => $p['yearEstablished'] ?? null,
            'employees_count' => $p['employeesCount'] ?? null,
            'services_products' => $p['servicesProducts'] ?? null,
            'target_market' => $p['targetMarket'] ?? null,
        ];

        // Try to infer city/state from address if not provided
        if ((!$profileData['city'] || !$profileData['state']) && !empty($profileData['address_line_1'])) {
            $parts = array_map('trim', explode(',', $profileData['address_line_1']));
            if (count($parts) >= 2) {
                $profileData['city'] = $profileData['city'] ?? $parts[count($parts)-2];
                $profileData['state'] = $profileData['state'] ?? $parts[count($parts)-1];
            }
        }

        // Create or update profile and mark approval_pending
        try {
            $profileData['profile_status'] = 'approval_pending';
            $profile = MemberProfile::updateOrCreate(
                ['user_id' => $user->id],
                $profileData
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'There was a problem saving your profile. Please check your information and try again, or contact support if the issue persists.',
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);
        }

        // Only mark activation link as used and update status if profile was saved
        $activationLink->markAsUsed();
        $user->status = 'approval_pending';
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile submitted successfully and is pending approval',
            'profile' => $profile
        ]);
    }

    /**
     * Get all activation links for a user (admin only)
     */
    public function getUserLinks(Request $request, User $user)
    {
        // This endpoint should be protected by admin middleware
        $this->authorize('viewAny', ActivationLink::class);

        $links = $user->activationLinks()
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'links' => $links
        ]);
    }

    /**
     * Generate new activation link for user (admin only)
     */
    public function generateLink(Request $request, User $user)
    {
        // This endpoint should be protected by admin middleware
        $this->authorize('create', ActivationLink::class);

        $validator = Validator::make($request->all(), [
            'email' => 'nullable|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        // Deactivate old links
        $user->activationLinks()->update(['is_active' => false]);

        // Generate new activation link
        $email = $request->email ?? $user->email;
        $activationLink = ActivationLink::generate($user, $email);

        // TODO: Send activation email here
        // $this->sendActivationEmail($user, $activationLink);

        return response()->json([
            'success' => true,
            'message' => 'Activation link generated and sent successfully',
            'link' => $activationLink,
            // Remove this in production - only for testing
            'activation_url' => url('/api/activate/' . $activationLink->token)
        ]);
    }

    /**
     * Deactivate an activation link (admin only)
     */
    public function deactivateLink(ActivationLink $link)
    {
        // This endpoint should be protected by admin middleware
        $this->authorize('delete', ActivationLink::class);

        $link->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Activation link deactivated successfully'
        ]);
    }

    /**
     * Send activation email (to be implemented with actual email service)
     */
    private function sendActivationEmail(User $user, ActivationLink $activationLink)
    {
        // TODO: Implement actual email sending
        // This is a placeholder for email implementation
        
        /*
        Mail::send('emails.activation', [
            'user' => $user,
            'activationUrl' => url('/activate/' . $activationLink->token),
            'expiresAt' => $activationLink->expires_at
        ], function ($message) use ($user, $activationLink) {
            $message->to($activationLink->email)
                   ->subject('Activate Your ATEA Seattle Account');
        });
        */

        // Update sent_at timestamp
        $activationLink->update(['sent_at' => now()]);
    }
}
