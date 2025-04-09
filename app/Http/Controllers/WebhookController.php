<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessIncomingSmsJob; // We'll create this next
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str; // For Str::startsWith
use Illuminate\Support\Facades\Crypt; // If you need to decrypt anything later
use Illuminate\Support\Carbon; // For timestamp validation

class WebhookController extends Controller
{
    /**
     * Handle incoming SMS webhooks from SMS Gateway.
     */
    public function handleIncomingSms(Request $request)
    {
        $webhookSecret = config('services.smsgateway.webhook_secret');
        $tolerance = config('services.smsgateway.webhook_tolerance');

        // 1. Verify Signature (Highly Recommended)
        if (!empty($webhookSecret)) {
            $signature = $request->header('X-Signature');
            $timestamp = $request->header('X-Timestamp');
            $rawPayload = $request->getContent(); // Get the raw body

            if (!$signature || !$timestamp) {
                Log::warning('SMS Gateway Webhook: Missing signature or timestamp header.');
                return response()->json(['error' => 'Missing signature headers'], 400);
            }

            // Optional: Validate timestamp to prevent replay attacks
            try {
                $requestTime = Carbon::createFromTimestamp($timestamp);
                if (Carbon::now()->diffInSeconds($requestTime, false) > $tolerance) { // Check if timestamp is too old or in the future
                     Log::warning('SMS Gateway Webhook: Timestamp validation failed.', ['timestamp' => $timestamp, 'now' => Carbon::now()->unix()]);
                     return response()->json(['error' => 'Timestamp validation failed'], 400);
                }
            } catch (\Exception $e) {
                 Log::warning('SMS Gateway Webhook: Invalid timestamp format.', ['timestamp' => $timestamp]);
                 return response()->json(['error' => 'Invalid timestamp format'], 400);
            }


            $expectedSignature = hash_hmac('sha256', $rawPayload . $timestamp, $webhookSecret);

            if (!hash_equals($expectedSignature, $signature)) {
                Log::error('SMS Gateway Webhook: Invalid signature.', ['expected' => $expectedSignature, 'received' => $signature]);
                return response()->json(['error' => 'Invalid signature'], 403); // Use 403 Forbidden
            }

            Log::info('SMS Gateway Webhook: Signature verified successfully.');
        } else {
            Log::warning('SMS Gateway Webhook: Signature verification skipped (no secret configured).');
            // Consider returning an error or logging more prominently if security is critical
        }

        // 2. Process Payload
        $payload = $request->json()->all(); // Get parsed JSON

        // Basic validation of payload structure
        if (!isset($payload['event']) || !isset($payload['payload'])) {
             Log::warning('SMS Gateway Webhook: Invalid payload structure.', ['payload' => $payload]);
             return response()->json(['error' => 'Invalid payload structure'], 400);
        }

        $eventType = $payload['event'];
        $eventData = $payload['payload'];

        Log::info('SMS Gateway Webhook: Received event.', ['type' => $eventType, 'data' => $eventData]);

        // 3. Handle Specific Event (e.g., sms:received)
        // You might want different handlers or jobs for different event types
        if ($eventType === 'sms:received') {
            if (!isset($eventData['phoneNumber']) || !isset($eventData['message'])) {
                 Log::warning('SMS Gateway Webhook: Missing required fields for sms:received.', ['data' => $eventData]);
                 return response()->json(['error' => 'Missing required fields for sms:received event'], 400);
            }

            // Dispatch a job for background processing to keep the response fast
            ProcessIncomingSmsJob::dispatch($eventData);

            // 4. Respond Quickly
            return response()->json(['message' => 'Webhook received successfully'], 200);
        }
        else if ($eventType === 'system:ping') {
             // Just acknowledge the ping
             Log::info('SMS Gateway Webhook: Received system ping.');
             return response()->json(['message' => 'Ping received'], 200);
        }
        // Add handling for other events (sms:sent, sms:delivered, sms:failed) if needed
        else {
             Log::info('SMS Gateway Webhook: Received unhandled event type.', ['type' => $eventType]);
             return response()->json(['message' => 'Event received but not processed'], 200); // Still acknowledge
        }
    }
}