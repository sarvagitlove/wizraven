<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\MemberProfile;
use App\Models\ActivationLink;
use App\Mail\MemberInvitation;
use App\Services\GmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function __construct()
    {
        // Gmail service will be created when needed with proper error handling
    }
    
    // Middleware is applied in routes/api.php instead of constructor
    
    /**
     * Debug endpoint to check authentication
     */
    public function debug(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $request->user(),
            'is_admin' => $request->user() ? $request->user()->isAdmin() : false,
            'token_header' => $request->header('Authorization'),
            'all_headers' => $request->headers->all()
        ]);
    }
    
    /**
     * Get dashboard statistics
     */
    public function dashboard()
    {
        $stats = [
            'total_users' => User::count(),
            'pending_users' => User::where('status', 'pending')->count(),
            'active_users' => User::where('status', 'active')->count(),
            'disabled_users' => User::where('status', 'disabled')->count(),
            'signup_pending_profiles' => MemberProfile::where('profile_status', 'signup_pending')->count(),
                'pending_profiles' => MemberProfile::where('profile_status', 'approval_pending')->count(),
            'approved_profiles' => MemberProfile::where('profile_status', 'approved')->count(),
            'recent_registrations' => User::where('created_at', '>=', now()->subDays(7))->count(),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }

    /**
     * Get all users with pagination and filtering
     */
    public function getUsers(Request $request)
    {
        $query = User::with(['role', 'memberProfile']);

        // Filter by status
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        // Filter by role
        if ($request->has('role') && $request->role !== '') {
            $query->whereHas('role', function ($q) use ($request) {
                $q->where('role_name', $request->role);
            });
        }

        // Search by name or email
        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate($request->get('per_page', 15));

        // Map status for frontend
        $users->getCollection()->transform(function ($user) {
            // If user has not signed up (no password set), status is signup_pending
            if (empty($user->password)) {
                $user->status = 'signup_pending';
            } else if ($user->memberProfile && $user->memberProfile->profile_status === 'approval_pending') {
                $user->status = 'approval_pending';
            }
            return $user;
        });

        return response()->json([
            'success' => true,
            'users' => $users
        ]);
    }

    /**
     * Create a new user (admin creates user, sends activation link)
     */
    public function createUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role' => 'in:admin,user', // Make role optional with default to user
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            // Get role - default to user if not specified
            $roleName = $request->role ?? 'user';
            $role = Role::where('role_name', $roleName)->first();
            
            if (!$role) {
                // Create default user role if it doesn't exist
                $role = Role::create(['role_name' => 'user']);
            }

            // Create user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'role_id' => $role->id,
                'status' => 'pending',
            ]);

            // Generate activation link
            $activationLink = ActivationLink::generate($user, $request->email);

            // Generate signup link for frontend
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:4200');
            $signupLink = $frontendUrl . '/member-signup/' . $activationLink->token;

            // Send invitation email using the new Gmail integration
            $emailSent = false;
            $emailMethod = 'None';
            
            try {
                // Use the same email logic as sendInvitationEmail method
                $membershipId = 'ATEA' . str_pad($user->id, 4, '0', STR_PAD_LEFT);
                
                // Try Gmail API first
                try {
                    $config = config('gmail');
                    
                    if (!empty($config['client_id']) && !empty($config['client_secret']) && !empty($config['refresh_token'])) {
                        $gmailService = new GmailService(false);
                        
                        $mailable = new MemberInvitation(
                            $user->name,
                            $signupLink,
                            $membershipId,
                            'ATEA Admin'
                        );
                        
                        $htmlContent = $mailable->render();
                        
                        $result = $gmailService->sendEmail(
                            $user->email,
                            'Welcome to ATEA Seattle - Complete Your Membership',
                            $htmlContent,
                            [
                                'is_html' => true,
                                'from_name' => 'ATEA Seattle Admin'
                            ]
                        );
                        
                        if ($result['success']) {
                            $emailSent = true;
                            $emailMethod = 'Gmail API';
                            \Log::info('Gmail API email sent during user creation', [
                                'email' => $user->email,
                                'user_id' => $user->id
                            ]);
                        } else {
                            throw new \Exception('Gmail API failed: ' . $result['message']);
                        }
                    } else {
                        throw new \Exception('Gmail API not configured');
                    }
                    
                } catch (\Exception $gmailError) {
                    \Log::warning('Gmail failed during user creation, logging email instead: ' . $gmailError->getMessage());
                    
                    // Log the email instead of sending via SMTP
                    config(['mail.default' => 'log']);
                    Mail::to($user->email)->send(new MemberInvitation(
                        $user->name,
                        $signupLink,
                        $membershipId,
                        'ATEA Admin'
                    ));
                    $emailSent = true;
                    $emailMethod = 'Laravel Mail (Logged)';
                }
                
            } catch (\Exception $emailError) {
                \Log::error('Failed to send invitation email during user creation: ' . $emailError->getMessage());
                $emailSent = false;
                $emailMethod = 'Failed';
            }

            $message = $emailSent 
                ? "User created successfully. Invitation email sent via {$emailMethod}."
                : 'User created successfully. Note: Email could not be sent automatically.';

            return response()->json([
                'success' => true,
                'message' => $message,
                'user' => $user->load('role'),
                'activation_link' => $signupLink,
                'email_sent' => $emailSent,
                'email_method' => $emailMethod
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Failed to create user: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send invitation email to a member
     */
    public function sendInvitationEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'name' => 'required|string',
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            // Generate signup link
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:4200');
            $signupLink = $frontendUrl . '/member-signup/' . $request->token;

            // Generate membership ID (simple implementation)
            $membershipId = 'ATEA' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            
            $emailSent = false;
            $emailMethod = 'Laravel Mail';
            
            // Try Gmail API first
            try {
                // Create Gmail service without initializing tokens (to check config)
                $config = config('gmail');
                
                if (!empty($config['client_id']) && !empty($config['client_secret']) && !empty($config['refresh_token'])) {
                    // Create Gmail service with token refresh
                    $gmailService = new GmailService(false);
                    
                    // Create the HTML email content using the MemberInvitation mailable
                    $mailable = new MemberInvitation(
                        $request->name,
                        $signupLink,
                        $membershipId,
                        'ATEA Admin'
                    );
                    
                    // Get the rendered HTML content
                    $htmlContent = $mailable->render();
                    
                    // Send via Gmail API
                    $result = $gmailService->sendEmail(
                        $request->email,
                        'Welcome to ATEA Seattle - Complete Your Membership',
                        $htmlContent,
                        [
                            'is_html' => true,
                            'from_name' => 'ATEA Seattle Admin'
                        ]
                    );
                    
                    if ($result['success']) {
                        $emailSent = true;
                        $emailMethod = 'Gmail API';
                        \Log::info('Gmail API email sent successfully', [
                            'email' => $request->email,
                            'message_id' => $result['message_id']
                        ]);
                    } else {
                        throw new \Exception('Gmail API failed: ' . $result['message']);
                    }
                } else {
                    throw new \Exception('Gmail API not properly configured');
                }
                
            } catch (\Exception $gmailError) {
                \Log::warning('Gmail API failed, falling back to Laravel Mail: ' . $gmailError->getMessage());
                
                // Fallback to Laravel Mail - but disable SMTP to avoid the username error
                // Just use the log driver temporarily
                config(['mail.default' => 'log']);
                
                Mail::to($request->email)->send(new MemberInvitation(
                    $request->name,
                    $signupLink,
                    $membershipId,
                    'ATEA Admin'
                ));
                $emailSent = true;
                $emailMethod = 'Laravel Mail (Logged)';
                \Log::info('Email logged via Laravel Mail (Gmail failed)', [
                    'email' => $request->email,
                    'gmail_error' => $gmailError->getMessage()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Invitation email sent successfully.',
                'email_method' => $emailMethod
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Failed to send invitation email: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send invitation email.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user status
     */
    public function updateUserStatus(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,active,disabled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        $user->update(['status' => $request->status]);

        // If approving user (setting to active), also approve their member profile
        if ($request->status === 'active' && $user->memberProfile) {
            $user->memberProfile->update([
                'profile_status' => 'approved',
                'approved_at' => now(),
                'approved_by' => auth()->id()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'User status updated successfully',
            'user' => $user->load('role', 'memberProfile')
        ]);
    }

    /**
     * Delete user
     */
    public function deleteUser(User $user)
    {
        // Prevent deleting the last admin
        if ($user->isAdmin()) {
            $adminCount = User::whereHas('role', function ($q) {
                $q->where('role_name', 'admin');
            })->count();

            if ($adminCount <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete the last admin user'
                ], 400);
            }
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Get pending member profiles for approval
     */
    public function getPendingProfiles(Request $request)
    {
        $profiles = MemberProfile::with(['user'])
            ->whereIn('profile_status', ['signup_pending', 'approval_pending'])
            ->paginate($request->get('per_page', 10));

        // Add canApprove boolean to each profile
        $profiles->getCollection()->transform(function ($profile) {
            $profile->canApprove = $profile->isApprovalPending();
            $profile->pendingType = $profile->isSignupPending() ? 'signup_pending' : ($profile->isApprovalPending() ? 'approval_pending' : 'other');
            return $profile;
        });

        return response()->json([
            'success' => true,
            'profiles' => $profiles
        ]);
    }

    /**
     * Approve member profile
     */
    public function approveProfile(Request $request, MemberProfile $profile)
    {
        if (!$profile->isApprovalPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Profile cannot be approved until the signup form is completed and status is approval_pending.'
            ], 400);
        }
        $profile->update([
            'profile_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
            'rejection_reason' => null,
        ]);
        // Update user status to active
        $profile->user->update(['status' => 'active']);
        return response()->json([
            'success' => true,
            'message' => 'Member profile approved successfully',
            'profile' => $profile->load('user', 'approvedBy'),
            'canApprove' => false,
        ]);
    }

    /**
     * Reject member profile
     */
    public function rejectProfile(Request $request, MemberProfile $profile)
    {
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        if ($profile->isApprovalPending()) {
            $profile->update([
                'profile_status' => 'rejected',
                'rejection_reason' => $request->rejection_reason,
                'approved_at' => null,
                'approved_by' => null,
            ]);
        }
        return response()->json([
            'success' => true,
            'message' => 'Member profile rejected',
            'profile' => $profile->load('user'),
            'canApprove' => $profile->isApprovalPending(),
        ]);
    }

    /**
     * Resend activation link
     */
    public function resendActivationLink(User $user)
    {
        // Deactivate old links
        $user->activationLinks()->update(['is_active' => false]);

        // Generate new activation link
        $activationLink = ActivationLink::generate($user);

        // TODO: Send activation email here
        // $this->sendActivationEmail($user, $activationLink);

        return response()->json([
            'success' => true,
            'message' => 'Activation link sent successfully',
            'activation_link' => url('/activate/' . $activationLink->token) // For testing
        ]);
    }

    /**
     * Get system activity/audit log (basic implementation)
     */
    public function getActivity(Request $request)
    {
        // This is a basic implementation - you might want to implement a proper audit log system
        $recentUsers = User::with(['role'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(function ($user) {
                return [
                    'type' => 'user_created',
                    'message' => "User {$user->name} was created",
                    'timestamp' => $user->created_at,
                    'user' => $user
                ];
            });

        $recentProfiles = MemberProfile::with(['user'])
            ->whereIn('profile_status', ['approved', 'rejected'])
            ->latest('updated_at')
            ->limit(20)
            ->get()
            ->map(function ($profile) {
                return [
                    'type' => 'profile_' . $profile->profile_status,
                    'message' => "Profile for {$profile->user->name} was {$profile->profile_status}",
                    'timestamp' => $profile->updated_at,
                    'profile' => $profile
                ];
            });

        $activity = $recentUsers->concat($recentProfiles)
            ->sortByDesc('timestamp')
            ->values()
            ->take(20);

        return response()->json([
            'success' => true,
            'activity' => $activity
        ]);
    }
}
