<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!$request->user()) {
            \Log::warning('AdminMiddleware: User not authenticated', [
                'headers' => $request->headers->all(),
                'has_bearer' => $request->hasHeader('Authorization'),
                'auth_header' => $request->header('Authorization')
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Session expired. Please login again.',
                'error_code' => 'UNAUTHENTICATED'
            ], 401);
        }

        // Check if user is admin
        if (!$request->user()->isAdmin()) {
            \Log::warning('AdminMiddleware: User not admin', [
                'user_id' => $request->user()->id,
                'user_role' => $request->user()->role->role_name ?? 'no_role'
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
                'error_code' => 'UNAUTHORIZED'
            ], 403);
        }

        return $next($request);
    }
}
