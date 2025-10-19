<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\MemberProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MemberController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get current user's member profile
     */
    public function getProfile(Request $request)
    {
        $user = $request->user();
        $profile = $user->memberProfile;

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profile not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'profile' => $profile
        ]);
    }

    /**
     * Create or update member profile
     */
    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'business_name' => 'required|string|max:255',
            'business_type' => 'nullable|string|max:100',
            'industry' => 'nullable|string|max:100',
            'business_description' => 'nullable|string|max:1000',
            'website' => 'nullable|url|max:255',
            'phone' => 'nullable|string|max:20',
            'business_email' => 'nullable|email|max:255',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'year_established' => 'nullable|integer|min:1800|max:' . date('Y'),
            'employees_count' => 'nullable|string|max:50',
            'services_products' => 'nullable|string|max:1000',
            'target_market' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        $user = $request->user();
        
        // Prepare data for profile
        $profileData = $request->only([
            'business_name', 'business_type', 'industry', 'business_description',
            'website', 'phone', 'business_email', 'address_line_1', 'address_line_2',
            'city', 'state', 'zip_code', 'country', 'year_established',
            'employees_count', 'services_products', 'target_market'
        ]);

        // Add user_id and set status
        $profileData['user_id'] = $user->id;
        
        // Set status based on whether user has completed signup
        if (empty($user->password)) {
            $profileData['profile_status'] = 'signup_pending';
        } else {
            $profileData['profile_status'] = 'approval_pending';
        }

        // Create or update profile
        $profile = MemberProfile::updateOrCreate(
            ['user_id' => $user->id],
            $profileData
        );

        $message = $isComplete 
            ? 'Profile submitted for approval successfully'
            : 'Profile saved as draft';

        return response()->json([
            'success' => true,
            'message' => $message,
            'profile' => $profile,
            'is_complete' => $isComplete
        ]);
    }

    /**
     * Submit profile for approval (if it was saved as draft)
     */
    public function submitForApproval(Request $request)
    {
        $user = $request->user();
        $profile = $user->memberProfile;

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profile not found'
            ], 404);
        }

        if ($profile->profile_status !== 'incomplete') {
            return response()->json([
                'success' => false,
                'message' => 'Profile is not in draft status'
            ], 400);
        }

        // Check if profile is complete
        if (!$this->isProfileComplete($profile->toArray())) {
            return response()->json([
                'success' => false,
                'message' => 'Profile is incomplete. Please fill in all required fields.'
            ], 400);
        }

    $profile->update(['profile_status' => 'approval_pending']);
    // Also flag the user's overall status for admin visibility
    $user->status = 'approval_pending';
    $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile submitted for approval',
            'profile' => $profile
        ]);
    }

    /**
     * Get all approved member profiles (public directory)
     */
    public function getApprovedProfiles(Request $request)
    {
        $query = MemberProfile::with(['user'])
            ->where('profile_status', 'approved');

        // Search functionality
        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'like', "%{$search}%")
                  ->orWhere('industry', 'like', "%{$search}%")
                  ->orWhere('services_products', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQ) use ($search) {
                      $userQ->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by industry
        if ($request->has('industry') && $request->industry !== '') {
            $query->where('industry', 'like', "%{$request->industry}%");
        }

        // Filter by city/location
        if ($request->has('city') && $request->city !== '') {
            $query->where('city', 'like', "%{$request->city}%");
        }

        $profiles = $query->paginate($request->get('per_page', 12));

        return response()->json([
            'success' => true,
            'profiles' => $profiles
        ]);
    }

    /**
     * Get specific approved member profile (public view)
     */
    public function getPublicProfile($id)
    {
        $profile = MemberProfile::with(['user'])
            ->where('id', $id)
            ->where('profile_status', 'approved')
            ->first();

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profile not found or not approved'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'profile' => $profile
        ]);
    }

    /**
     * Get member profile statistics
     */
    public function getStats(Request $request)
    {
        $user = $request->user();
        
        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $stats = [
            'total_profiles' => MemberProfile::count(),
            'approved_profiles' => MemberProfile::where('profile_status', 'approved')->count(),
            'pending_profiles' => MemberProfile::where('profile_status', 'approval_pending')->count(),
            'incomplete_profiles' => MemberProfile::where('profile_status', 'incomplete')->count(),
            'rejected_profiles' => MemberProfile::where('profile_status', 'rejected')->count(),
            'by_industry' => MemberProfile::where('profile_status', 'approved')
                ->whereNotNull('industry')
                ->selectRaw('industry, COUNT(*) as count')
                ->groupBy('industry')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }

    /**
     * Check if profile data is complete
     */
    private function isProfileComplete($profileData)
    {
        $requiredFields = [
            'business_name',
            'industry',
            'business_description',
            'city',
            'state',
            'country'
        ];

        foreach ($requiredFields as $field) {
            if (empty($profileData[$field])) {
                return false;
            }
        }

        return true;
    }
}
