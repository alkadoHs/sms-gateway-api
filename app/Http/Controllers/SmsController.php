<?php

namespace App\Http\Controllers;

use App\Exceptions\SmsGatewayAuthenticationException;
use App\Exceptions\SmsGatewayException; // Base exception for catch-all
use App\Exceptions\SmsGatewayNotFoundException;
use App\Jobs\ProcessIncomingSmsJob; // Job for webhook processing
use App\Services\SmsGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Import Auth facade
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon; // For timestamp validation

class SmsController extends Controller
{
    protected SmsGatewayService $smsGateway;

    /**
     * SmsController constructor.
     * Injects the SmsGatewayService.
     *
     * @param SmsGatewayService $smsGateway
     */
    public function __construct(SmsGatewayService $smsGateway)
    {
        $this->smsGateway = $smsGateway;
    }

    /**
     * Endpoint to queue sending an SMS message.
     * Dispatches a job to handle the actual sending in the background.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendSms(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string|min:5', // Basic validation, adjust as needed
            'message' => 'required|string|max:10000', // Allow longer messages
            'message_id' => 'nullable|string|max:36', // Optional: client can provide ID
            'sim' => 'nullable|integer|min:1|max:3',
            'delivery_report' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:-128|max:127',
        ]);

        $user = auth()->user(); // Get the authenticated user

        if (!$user) {
            // This should ideally be handled by auth middleware
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        // Check if user has configured settings before even queuing
        if (!$user->hasSmsGatewayConfigured()) {
             return response()->json(['error' => 'SMS Gateway settings not configured for your account.'], 400);
        }

        try {
            // Call the service's 'send' method which queues the job
            $queuedMessageId = $this->smsGateway->send(
                $user, // Pass the user object
                $validated['phone'],
                $validated['message'],
                $validated['sim'] ?? null,
                isset($validated['delivery_report']) ? (bool)$validated['delivery_report'] : null,
                $validated['message_id'] ?? null, // Pass custom ID if provided
                $validated['priority'] ?? null
            );

            // Return immediate success, indicating the job was queued
            return response()->json([
                'message' => 'SMS queued for background sending.',
                'queued_message_id' => $queuedMessageId, // Return the ID that was queued
            ], 202); // Use 202 Accepted

        } catch (\InvalidArgumentException $e) {
             // Catch validation errors from the service (e.g., user not configured, bad ID)
             Log::warning('Failed to queue SMS job due to invalid argument', ['error' => $e->getMessage(), 'user_id' => $user->id]);
             return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            // Catch potential errors during the *dispatch* process itself (rare)
            Log::error('Failed to dispatch SMS job', ['error' => $e->getMessage(), 'user_id' => $user->id]);
            return response()->json(['error' => 'Failed to queue SMS for sending.'], 500);
        }
    }

    /**
     * Endpoint to get the status of a specific SMS message.
     * Calls the gateway API directly using the authenticated user's credentials.
     *
     * @param string $messageId The ID of the message (provided during send or returned by API).
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSmsStatus(string $messageId)
    {
        $user = Auth::user(); // Get the authenticated user

        if (!$user) {
            // Handled by middleware ideally
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        if (empty($messageId)) {
             return response()->json(['error' => 'Message ID is required.'], 400);
        }

        // Check if user has configured settings before checking status
        if (!$user->hasSmsGatewayConfigured()) {
              return response()->json(['error' => 'SMS Gateway settings not configured for your account.'], 400);
        }

        try {
            // Pass the user object to getStatus
            $statusData = $this->smsGateway->getStatus($user, $messageId);

            // Status fetched successfully
            return response()->json($statusData); // Return the full status object from the gateway

        } catch (SmsGatewayNotFoundException $e) {
            // Logged in service
            return response()->json(['error' => "SMS message with ID '{$messageId}' not found."], 404); // Use the exception message if desired
        } catch (SmsGatewayAuthenticationException $e) {
            // Logged in service
            return response()->json(['error' => 'SMS Gateway authentication failed. Please check your configured settings.'], 401); // 401 implies the creds used were bad
        } catch (SmsGatewayException $e) { // Catch base and other specific ones (Network, Server, Client)
            // Already logged in the service
             Log::warning('SMS Gateway Exception during status check', ['message_id' => $messageId, 'user_id' => $user->id, 'error' => $e->getMessage()]);
             return response()->json(['error' => 'Failed to get status from SMS Gateway: ' . $e->getMessage()], 503); // 503 Service Unavailable might be appropriate
        } catch (\InvalidArgumentException $e) {
            // Catch config error from getStatus if user somehow passed initial check but failed later
             Log::warning('Invalid argument during status check', ['message_id' => $messageId, 'user_id' => $user->id, 'error' => $e->getMessage()]);
             return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
             Log::error('Unexpected error during SMS status check', ['exception' => $e, 'message_id' => $messageId, 'user_id' => $user->id]);
             return response()->json(['error' => 'An unexpected error occurred while checking SMS status.'], 500);
        }
    }

    /**
     * Handle incoming SMS webhooks from SMS Gateway.
     * Verifies signature (if configured) and dispatches job for processing.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleIncomingSms(Request $request)
    {
        // Note: This endpoint should likely NOT require user authentication (Auth::user())
        // as it's called by the Android device/gateway directly.
        // Rely solely on the HMAC signature for verification if security is needed.

        $webhookSecret = config('services.smsgateway.webhook_secret');
        $tolerance = config('services.smsgateway.webhook_tolerance');

        // 1. Verify Signature (Highly Recommended)
        if (!empty($webhookSecret)) {
            $signature = $request->header('X-Signature');
            $timestamp = $request->header('X-Timestamp');
            $rawPayload = $request->getContent();

            if (!$signature || !$timestamp) {
                Log::warning('SMS Gateway Webhook: Missing signature or timestamp header.');
                return response()->json(['error' => 'Missing signature headers'], 400);
            }

            // Optional: Validate timestamp to prevent replay attacks
            try {
                $requestTime = Carbon::createFromTimestamp($timestamp);
                 if ($requestTime->isAfter(Carbon::now()->addSeconds(5)) || $requestTime->isBefore(Carbon::now()->subSeconds($tolerance))) {
                    Log::warning('SMS Gateway Webhook: Timestamp validation failed.', ['timestamp' => $timestamp, 'now' => Carbon::now()->unix(), 'tolerance_sec' => $tolerance]);
                    return response()->json(['error' => 'Timestamp validation failed'], 400);
                 }
            } catch (\Exception $e) {
                 Log::warning('SMS Gateway Webhook: Invalid timestamp format.', ['timestamp' => $timestamp]);
                 return response()->json(['error' => 'Invalid timestamp format'], 400);
            }

            $expectedSignature = hash_hmac('sha256', $rawPayload . $timestamp, $webhookSecret);

            if (!hash_equals($expectedSignature, $signature)) {
                Log::error('SMS Gateway Webhook: Invalid signature.');
                return response()->json(['error' => 'Invalid signature'], 403); // Use 403 Forbidden
            }

            Log::debug('SMS Gateway Webhook: Signature verified successfully.');
        } else {
            Log::warning('SMS Gateway Webhook: Signature verification skipped (no webhook secret configured). THIS IS INSECURE.');
        }

        // 2. Process Payload
        $payload = $request->json()->all();

        if (!isset($payload['event']) || !isset($payload['payload'])) {
             Log::warning('SMS Gateway Webhook: Invalid payload structure.', ['payload' => $payload]);
             return response()->json(['error' => 'Invalid payload structure'], 400);
        }

        $eventType = $payload['event'];
        $eventData = $payload['payload'];
        // You might also want $deviceId = $payload['deviceId'] ?? null;

        Log::info('SMS Gateway Webhook: Received event.', ['type' => $eventType, 'deviceId' => $payload['deviceId'] ?? null]);

        // 3. Handle Specific Event Types
        switch ($eventType) {
            case 'sms:received':
                if (!isset($eventData['phoneNumber']) || !isset($eventData['message'])) {
                    Log::warning('SMS Gateway Webhook: Missing required fields for sms:received.', ['data' => $eventData]);
                    return response()->json(['error' => 'Missing required fields for sms:received event'], 400);
                }
                // Add device ID to the job data if needed for multi-device setups
                $jobData = $eventData;
                $jobData['gateway_device_id'] = $payload['deviceId'] ?? null;
                ProcessIncomingSmsJob::dispatch($jobData);
                Log::debug('SMS Gateway Webhook: Dispatched ProcessIncomingSmsJob.');
                break;

            case 'system:ping':
                Log::info('SMS Gateway Webhook: Received system ping.');
                break;

            // Example: Handle delivery status updates
            // case 'sms:delivered':
            // case 'sms:sent':
            // case 'sms:failed':
            //     $jobData = $eventData;
            //     $jobData['gateway_device_id'] = $payload['deviceId'] ?? null;
            //     ProcessSmsStatusUpdateJob::dispatch($eventType, $jobData); // Create this job
            //     Log::debug('SMS Gateway Webhook: Dispatched ProcessSmsStatusUpdateJob.', ['event' => $eventType]);
            //     break;

            default:
                Log::info('SMS Gateway Webhook: Received unhandled event type.', ['type' => $eventType]);
                break;
        }

        // 4. Respond Quickly
        return response()->json(['message' => 'Webhook received successfully'], 200);
    }
}