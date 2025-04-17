<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SmsGatewayService; // Your service
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SmsController extends Controller
{
    protected SmsGatewayService $smsGateway;

    public function __construct(SmsGatewayService $smsGateway)
    {
        $this->smsGateway = $smsGateway;
    }

    /**
     * API endpoint to send an SMS message using the authenticated user's settings.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function send(Request $request)
    {
        $user = $request->user(); // Get authenticated user via Sanctum token

        // Double-check if the user has their gateway configured
        if (!$user || !$user->hasSmsGatewayConfigured()) {
             Log::warning('API SMS Send attempt failed: User not found or gateway not configured.', ['user_id' => $user->id ?? 'N/A']);
             // Return 403 Forbidden as the user is authenticated but not authorized for this action yet
             return response()->json(['error' => 'SMS Gateway settings are not configured for your account.'], 403);
        }

        try {
            $validated = $request->validate([
                // Use 'recipients' to align better with multiple numbers possibility
                'recipients' => 'required|array|min:1',
                'recipients.*' => 'required|string|min:5', // Validate each recipient
                'message' => 'required|string|max:10000',
                'message_id' => 'nullable|string|max:36',
                'sim' => 'nullable|integer|min:1|max:3',
                'delivery_report' => 'nullable|boolean',
                'priority' => 'nullable|integer|min:-128|max:127',
            ]);

            // Call the service's 'send' (queueing) method, passing the authenticated user
            $queuedMessageId = $this->smsGateway->send(
                $user, // Pass the authenticated user object
                $validated['recipients'],
                $validated['message'],
                $validated['sim'] ?? null,
                isset($validated['delivery_report']) ? (bool)$validated['delivery_report'] : null,
                $validated['message_id'] ?? null,
                $validated['priority'] ?? null
            );

            // Return success response
            return response()->json([
                'message' => 'SMS queued successfully.',
                'queued_message_id' => $queuedMessageId,
            ], 202); // 202 Accepted is appropriate for queued jobs

        } catch (ValidationException $e) {
            // Laravel's validation exception
            return response()->json(['error' => 'Validation failed.', 'details' => $e->errors()], 422);
        } catch (\InvalidArgumentException $e) {
             // Catch specific errors like message ID too long from the service
             Log::warning('API SMS Send failed: Invalid argument.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
             return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            // Catch potential errors during job dispatch
            Log::error('API SMS Send failed: Could not dispatch job.', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Failed to queue SMS for sending.'], 500);
        }
    }

    // Optional: Add API endpoint for status checking if needed
    public function getStatus(Request $request, string $messageId)
    {
        $user = $request->user();
        if (!$user || !$user->hasSmsGatewayConfigured()) {
             return response()->json(['error' => 'SMS Gateway settings not configured or user not found.'], 403);
        }
        // ... Reuse logic from previous getSmsStatus, calling $this->smsGateway->getStatus($user, $messageId) ...
        // Remember to handle exceptions (NotFound, Auth, etc.) appropriately for an API response
    }
}