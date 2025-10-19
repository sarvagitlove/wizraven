<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\ActivationLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth as FirebaseAuth;
use Google_Client;

class AuthController extends Controller
{
    private $firebaseAuth;

    public function __construct()
    {
        // Firebase Auth initialization is optional for email/password login
        // Only initialize if Firebase is properly configured
        try {
            $firebaseConfig = config('firebase');
            
            if ($firebaseConfig && !empty($firebaseConfig['project_id']) && $firebaseConfig['project_id'] !== 'your-firebase-project-id') {
                $firebase = (new Factory)
                    ->withServiceAccount($firebaseConfig);
                
                $this->firebaseAuth = $firebase->createAuth();
            }
        } catch (\Exception $e) {
            // Firebase not configured, will only support email/password login
            \Log::info('Firebase not configured, using email/password authentication only');
            $this->firebaseAuth = null;
        }
    }

    /**
     * Login with Google ID Token
     */
    public function loginWithGoogle(Request $request)
    {
        if (!$this->firebaseAuth) {
            return response()->json([
                'success' => false,
                'message' => 'Google authentication not configured'
            ], 503);
        }

        $validator = Validator::make($request->all(), [
            'id_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            // Verify the ID token with Firebase
            $verifiedIdToken = $this->firebaseAuth->verifyIdToken($request->id_token);
            $uid = $verifiedIdToken->claims()->get('sub');
            $email = $verifiedIdToken->claims()->get('email');
            $name = $verifiedIdToken->claims()->get('name');
            $picture = $verifiedIdToken->claims()->get('picture');

            // Find or create user
            $user = User::where('google_id', $uid)
                       ->orWhere('email', $email)
                       ->first();

            if (!$user) {
                // Create new user
                $userRole = Role::where('role_name', 'user')->first();
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'google_id' => $uid,
                    'avatar' => $picture,
                    'role_id' => $userRole->id,
                    'status' => 'pending',
                    'email_verified_at' => now(),
                ]);
            } else {
                // Update existing user with Google data
                $user->update([
                    'google_id' => $uid,
                    'avatar' => $picture,
                    'email_verified_at' => now(),
                ]);
            }

            // Create Sanctum token
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'user' => $user->load('role', 'memberProfile'),
                'token' => $token,
                'message' => 'Login successful'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid ID token: ' . $e->getMessage()
            ], 401);
        }
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Get authenticated user
     */
    public function user(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $request->user()->load('role', 'memberProfile')
        ]);
    }

    /**
     * Activate user account via activation link
     */
    public function activate(Request $request, $token)
    {
        $activationLink = ActivationLink::where('token', $token)->first();

        if (!$activationLink) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid activation link'
            ], 404);
        }

        if (!$activationLink->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Activation link has expired or already been used'
            ], 400);
        }

        // Mark activation link as used
        $activationLink->markAsUsed();

        // Activate the user
        $user = $activationLink->user;
        $user->update(['status' => 'active']);

        return response()->json([
            'success' => true,
            'message' => 'Account activated successfully',
            'user' => $user->load('role', 'memberProfile')
        ]);
    }

    /**
     * Login with email and password
     */
    public function loginWithEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        // Check credentials
        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            $user = Auth::user();
            
            // Create Sanctum token
            $token = $user->createToken('auth-token')->plainTextToken;
            
            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'user' => $user->load('role', 'memberProfile'),
                'token' => $token
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid credentials'
        ], 401);
    }
}
