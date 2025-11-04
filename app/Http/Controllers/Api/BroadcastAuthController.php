<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

class BroadcastAuthController extends Controller
{
    /**
     * Authenticate the request for channel access.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function authenticate(Request $request)
    {
        try {
            $user = $request->user();
            $channelName = $request->input('channel_name');
            $socketId = $request->input('socket_id');

            Log::info('Broadcast auth request', [
                'socket_id' => $socketId,
                'channel_name' => $channelName,
                'user_id' => $user?->id,
                'user_email' => $user?->email,
            ]);

            // Ensure user is authenticated
            if (!$user) {
                Log::warning('Broadcast auth: No authenticated user');
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            // Check if broadcasting driver is configured for Pusher
            $broadcastDriver = config('broadcasting.default');
            if ($broadcastDriver !== 'pusher') {
                Log::error('Broadcast auth: Wrong driver configured', [
                    'driver' => $broadcastDriver,
                    'required' => 'pusher'
                ]);
                return response()->json([
                    'error' => 'Broadcasting not configured for Pusher',
                    'message' => "Current driver is '{$broadcastDriver}'. Please set BROADCAST_DRIVER=pusher in .env file"
                ], 500);
            }

            // Use Laravel's BroadcastController which handles the response conversion properly
            $broadcastController = app(\Illuminate\Broadcasting\BroadcastController::class);
            $response = $broadcastController->authenticate($request);

            // The BroadcastController.authenticate() should return a Response
            // But if it returns an array, convert it to JSON response
            if (is_array($response)) {
                Log::info('Broadcast auth success (array converted)', [
                    'channel_name' => $channelName,
                ]);
                return response()->json($response);
            }

            // Handle Response object
            if ($response instanceof \Illuminate\Http\Response || 
                $response instanceof \Symfony\Component\HttpFoundation\Response) {
                Log::info('Broadcast auth success', [
                    'status' => $response->getStatusCode(),
                    'channel_name' => $channelName,
                ]);
                return $response;
            }

            // Handle null or unexpected types
            Log::error('Broadcast auth returned unexpected type', [
                'type' => gettype($response),
                'channel_name' => $channelName,
            ]);

            return response()->json([
                'error' => 'Broadcasting authentication failed',
                'message' => 'Invalid response from broadcaster'
            ], 500);
        } catch (\Exception $e) {
            Log::error('Broadcast auth error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'channel_name' => $request->input('channel_name'),
                'socket_id' => $request->input('socket_id'),
            ]);

            return response()->json([
                'error' => 'Authentication failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

