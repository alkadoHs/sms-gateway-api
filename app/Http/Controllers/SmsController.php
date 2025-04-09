<?php

namespace App\Http\Controllers;

use App\Services\SmsGatewayService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessIncomingSmsJob; // Job for webhook processing

// Import specific exceptions if you want to catch them individually for status check
use App\Exceptions\SmsGatewayNotFoundException;
use App\Exceptions\SmsGatewayAuthenticationException;
use App\Exceptions\SmsGatewayException; // Base exception for catch-all

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

    public function index()
    {
        return view('sms.index'); // Example view, adjust as needed
    }

    /**
     * Endpoint to initiate sending an SMS message.
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
            'message_id' => 'nullable|string|max:36',
            'sim' => 'nullable|integer|min:1|max:3',
            'delivery_report' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:-128|max:127',
        ]);

        $phoneNumber = $validated['phone'];
        $messageText = $validated['message'];
        $customMessageId = $validated['message_id'] ?? null;
        $simNumber = $validated['sim'] ?? null;
        $withDeliveryReport = isset($validated['delivery_report']) ? (bool)$validated['delivery_report'] : null;
        $priority = $validated['priority'] ?? null;


        try {
            // Call the service's 'send' method which queues the job
            $queuedMessageId = $this->smsGateway->send(
                $phoneNumber,
                $messageText,
                $simNumber,
                $withDeliveryReport,
                $customMessageId, // Pass custom ID if provided
                $priority
            );

            // Return immediate success, indicating the job was queued
            return response()->json([
                'message' => 'SMS queued for background sending.',
                'queued_message_id' => $queuedMessageId, // Return the ID that was queued
            ], 202); // Use 202 Accepted

        } catch (\InvalidArgumentException $e) {
            // Catch specific validation errors from the service (e.g., ID too long)
            Log::warning('Failed to queue SMS job due to invalid argument', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            // Catch potential errors during the *dispatch* process itself (rare)
            Log::error('Failed to dispatch SMS job', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to queue SMS for sending.'], 500);
        }
    }

    /**
     * Endpoint to get the status of a specific SMS message.
     * Calls the gateway API directly.
     *
     * @param string $messageId The ID of the message (provided during send or returned by API).
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSmsStatus(string $messageId)
    {
        if (empty($messageId)) {
             return response()->json(['error' => 'Message ID is required.'], 400);
        }

        try {
            $statusData = $this->smsGateway->getStatus($messageId);

            // Status fetched successfully
            return response()->json($statusData); // Return the full status object from the gateway

        } catch (SmsGatewayNotFoundException $e) {
            Log::info('SMS Status check: Message not found.', ['message_id' => $messageId]);
            return response()->json(['error' => "SMS message with ID '{$messageId}' not found."], 404);
        } catch (SmsGatewayAuthenticationException $e) {
            // Logged in service
            return response()->json(['error' => 'SMS Gateway authentication failed. Check credentials.'], 401);
        } catch (SmsGatewayException $e) { // Catch base and other specific ones (Network, Server, Client)
            // Already logged in the service
             return response()->json(['error' => 'Failed to get status from SMS Gateway: ' . $e->getMessage()], 503); // 503 Service Unavailable might be appropriate
        } catch (\Exception $e) {
             Log::error('Unexpected error during SMS status check', ['exception' => $e, 'message_id' => $messageId]);
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
                // Allow timestamps slightly in the future (e.g., 5 seconds) due to potential clock skew
                if ($requestTime->isAfter(Carbon::now()->addSeconds(5)) || $requestTime->isBefore(Carbon::now()->subSeconds($tolerance))) {
                    Log::warning('SMS Gateway Webhook: Timestamp validation failed.', [
                        'timestamp' => $timestamp,
                        'now' => Carbon::now()->unix(),
                        'tolerance_sec' => $tolerance
                    ]);
                    return response()->json(['error' => 'Timestamp validation failed'], 400);
                }
            } catch (\Exception $e) {
                 Log::warning('SMS Gateway Webhook: Invalid timestamp format.', ['timestamp' => $timestamp]);
                 return response()->json(['error' => 'Invalid timestamp format'], 400);
            }

            $expectedSignature = hash_hmac('sha256', $rawPayload . $timestamp, $webhookSecret);

            if (!hash_equals($expectedSignature, $signature)) {
                Log::error('SMS Gateway Webhook: Invalid signature.'); // Don't log expected/received in production
                return response()->json(['error' => 'Invalid signature'], 403); // Use 403 Forbidden
            }

            Log::debug('SMS Gateway Webhook: Signature verified successfully.'); // Use debug for successful verification
        } else {
            Log::warning('SMS Gateway Webhook: Signature verification skipped (no webhook secret configured). THIS IS INSECURE.');
            // In production, you might want to return an error if the secret is missing.
            // return response()->json(['error' => 'Webhook secret not configured'], 500);
        }

        // 2. Process Payload
        $payload = $request->json()->all();

        if (!isset($payload['event']) || !isset($payload['payload'])) {
             Log::warning('SMS Gateway Webhook: Invalid payload structure.', ['payload' => $payload]);
             return response()->json(['error' => 'Invalid payload structure'], 400);
        }

        $eventType = $payload['event'];
        $eventData = $payload['payload'];

        Log::info('SMS Gateway Webhook: Received event.', ['type' => $eventType]);
        // Avoid logging full $eventData in production if it contains sensitive info (like message body)
        // Log only necessary identifiers if possible: Log::info('...', ['type' => $eventType, 'webhook_id' => $payload['webhookId'] ?? 'N/A']);

        // 3. Handle Specific Event Types
        switch ($eventType) {
            case 'sms:received':
                if (!isset($eventData['phoneNumber']) || !isset($eventData['message'])) {
                    Log::warning('SMS Gateway Webhook: Missing required fields for sms:received.', ['data' => $eventData]);
                    return response()->json(['error' => 'Missing required fields for sms:received event'], 400);
                }
                ProcessIncomingSmsJob::dispatch($eventData);
                Log::debug('SMS Gateway Webhook: Dispatched ProcessIncomingSmsJob.');
                break;

            case 'system:ping':
                Log::info('SMS Gateway Webhook: Received system ping.');
                break;

            // Add cases for 'sms:sent', 'sms:delivered', 'sms:failed' if needed
            // case 'sms:delivered':
            //     ProcessSmsStatusUpdateJob::dispatch($eventType, $eventData); // Example
            //     Log::debug('SMS Gateway Webhook: Dispatched ProcessSmsStatusUpdateJob for delivered.');
            //     break;

            default:
                Log::info('SMS Gateway Webhook: Received unhandled event type.', ['type' => $eventType]);
                break;
        }

        // 4. Respond Quickly
        return response()->json(['message' => 'Webhook received successfully'], 200);
    }
}