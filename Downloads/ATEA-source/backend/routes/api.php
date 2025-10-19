<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ActivationController;
use App\Http\Controllers\GmailController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes (no authentication required)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'loginWithEmail']);
    Route::post('/google', [AuthController::class, 'loginWithGoogle']);
});

// Member login route
Route::prefix('member')->group(function () {
    Route::post('/login', [AuthController::class, 'loginWithEmail']);
});

// Activation routes (public)
Route::prefix('activate')->group(function () {
    Route::get('/{token}', [ActivationController::class, 'activate']);
    Route::get('/check/{token}', [ActivationController::class, 'checkLink']);
    Route::post('/resend', [ActivationController::class, 'resendLink']);
    // Public endpoint to submit profile using activation token
    Route::post('/submit-profile', [ActivationController::class, 'submitProfile']);
});

// Public member directory
Route::prefix('members')->group(function () {
    Route::get('/directory', [MemberController::class, 'getApprovedProfiles']);
    Route::get('/profile/{id}', [MemberController::class, 'getPublicProfile']);
});

// Protected routes (authentication required)
Route::middleware('auth:sanctum')->group(function () {
    
    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
    });

    // Member profile routes
    Route::prefix('member')->group(function () {
        Route::get('/profile', [MemberController::class, 'getProfile']);
        Route::post('/profile', [MemberController::class, 'updateProfile']);
        Route::post('/profile/submit', [MemberController::class, 'submitForApproval']);
        Route::get('/stats', [MemberController::class, 'getStats']);
    });

    // Admin routes (admin access required)
    Route::middleware('admin')->prefix('admin')->group(function () {
        
        // Debug endpoint (temporary)
        Route::get('/debug', [AdminController::class, 'debug']);
        
        // Dashboard
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/activity', [AdminController::class, 'getActivity']);
        
        // User management
        Route::prefix('users')->group(function () {
            Route::get('/', [AdminController::class, 'getUsers']);
            Route::post('/', [AdminController::class, 'createUser']);
            Route::patch('/{user}/status', [AdminController::class, 'updateUserStatus']);
            Route::delete('/{user}', [AdminController::class, 'deleteUser']);
            Route::post('/{user}/resend-activation', [AdminController::class, 'resendActivationLink']);
        });
        
        // Email management
        Route::post('/send-invitation-email', [AdminController::class, 'sendInvitationEmail']);
        
        // Member profile approval
        Route::prefix('profiles')->group(function () {
            Route::get('/pending', [AdminController::class, 'getPendingProfiles']);
            Route::post('/{profile}/approve', [AdminController::class, 'approveProfile']);
            Route::post('/{profile}/reject', [AdminController::class, 'rejectProfile']);
        });
        
        // Activation link management
        Route::prefix('activation')->group(function () {
            Route::get('/user/{user}/links', [ActivationController::class, 'getUserLinks']);
            Route::post('/user/{user}/generate', [ActivationController::class, 'generateLink']);
            Route::delete('/link/{link}', [ActivationController::class, 'deactivateLink']);
        });

        // Gmail API routes (admin only)
        Route::prefix('gmail')->group(function () {
            Route::post('/send', [GmailController::class, 'sendMail']);
            Route::post('/test', [GmailController::class, 'sendTestMail']);
            Route::get('/status', [GmailController::class, 'getStatus']);
            Route::get('/auth/url', [GmailController::class, 'getAuthUrl']);
            Route::post('/auth/callback', [GmailController::class, 'handleAuthCallback']);
        });
    });
});

// Fallback route for undefined API endpoints
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API endpoint not found'
    ], 404);
});