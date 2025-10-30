<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleLoginAttempts
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $email = $request->input('email');
        $key = 'login_attempts:' . $email;
        $lockoutKey = 'login_lockout:' . $email;
        
        // Check if account is locked
        if (Cache::has($lockoutKey)) {
            $remainingTime = Cache::get($lockoutKey) - now()->timestamp;
            $minutes = ceil($remainingTime / 60);
            
            return response()->json([
                'success' => false,
                'message' => "Too many login attempts. Account locked for {$minutes} more minute(s).",
                'locked_until' => Cache::get($lockoutKey),
                'retry_after' => $remainingTime
            ], 429);
        }
        
        // Get current failed attempts
        $attempts = Cache::get($key, 0);
        
        // If attempts exceed 5, lock the account for 1 minute
        if ($attempts >= 5) {
            $lockoutTime = now()->addMinutes(1)->timestamp;
            Cache::put($lockoutKey, $lockoutTime, 60); // 1 minute in seconds
            Cache::forget($key); // Reset attempts counter
            
            return response()->json([
                'success' => false,
                'message' => 'Too many failed login attempts. Account locked for 1 minute.',
                'locked_until' => $lockoutTime,
                'retry_after' => 60
            ], 429);
        }
        
        return $next($request);
    }
    
    /**
     * Increment failed login attempts
     */
    public static function incrementAttempts(string $email): void
    {
        $key = 'login_attempts:' . $email;
        $attempts = Cache::get($key, 0);
        Cache::put($key, $attempts + 1, 60); // Store for 1 minute
    }
    
    /**
     * Clear login attempts on successful login
     */
    public static function clearAttempts(string $email): void
    {
        $key = 'login_attempts:' . $email;
        $lockoutKey = 'login_lockout:' . $email;
        Cache::forget($key);
        Cache::forget($lockoutKey);
    }
}
