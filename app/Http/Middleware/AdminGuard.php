<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Admin;
use Laravel\Sanctum\PersonalAccessToken;

class AdminGuard
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if there's a bearer token
        $token = $request->bearerToken();
        
        // Also try to get from Authorization header directly
        if (!$token) {
            $authHeader = $request->header('Authorization');
            if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
                $token = substr($authHeader, 7);
            }
        }
        
        // Trim whitespace from token
        if ($token) {
            $token = trim($token);
        }
        
        \Log::info('AdminGuard: Token check', [
            'has_token' => !!$token,
            'token_length' => $token ? strlen($token) : 0,
            'url' => $request->url(),
            'auth_header' => $request->header('Authorization'),
        ]);
        
        if (!$token) {
            \Log::warning('AdminGuard: No bearer token found');
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 401);
        }
        
        // Find the token in the database
        $accessToken = PersonalAccessToken::findToken($token);
        
        \Log::info('AdminGuard: Token lookup', [
            'token_found' => !!$accessToken,
            'tokenable_type' => $accessToken ? $accessToken->tokenable_type : null,
            'expected_type' => Admin::class,
        ]);
        
        if (!$accessToken) {
            \Log::warning('AdminGuard: Token not found in database');
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Invalid token.',
            ], 401);
        }
        
        // Check if token belongs to an Admin model
        if ($accessToken->tokenable_type !== Admin::class) {
            \Log::warning('AdminGuard: Token does not belong to Admin', [
                'tokenable_type' => $accessToken->tokenable_type,
                'expected_type' => Admin::class,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }
        
        // Check if token has expired
        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            \Log::warning('AdminGuard: Token expired');
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token has expired.',
            ], 401);
        }
        
        // Get the admin from the token
        $admin = $accessToken->tokenable;
        
        if (!$admin || !($admin instanceof Admin)) {
            \Log::error('AdminGuard: Admin not found or invalid instance', [
                'admin_found' => !!$admin,
                'is_admin_instance' => $admin instanceof Admin,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin not found.',
            ], 403);
        }
        
        \Log::info('AdminGuard: Authentication successful', [
            'admin_id' => $admin->id,
            'admin_email' => $admin->email,
        ]);
        
        // Update token's last_used_at
        $accessToken->forceFill(['last_used_at' => now()])->save();
        
        // Set the admin as the authenticated user for this request
        $request->setUserResolver(function () use ($admin) {
            return $admin;
        });
        
        return $next($request);
    }
}
