<?php

// Example in a Controller: app/Http/Controllers/SmsController.php

namespace App\Http\Controllers;

use App\Exceptions\SmsGatewayNotFoundException;
use App\Services\SmsGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

// Import specific exceptions if you want to catch them individually
use App\Exceptions\SmsGatewayAuthenticationException;
use App\Exceptions\SmsGatewayBadRequestException;
use App\Exceptions\SmsGatewayRateLimitException;
use App\Exceptions\SmsGatewayException; // Base exception

class SmsController extends Controller
{
    protected SmsGatewayService $smsGateway;

    public function __construct(SmsGatewayService $smsGateway)
    {
        $this->smsGateway = $smsGateway;
    }

    public function index()
    {
        return view('sms.index'); // Assuming you have a view for sending SMS
    }

    public function sendTestSms(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string|max:1000',
        ]);

        $phoneNumber = $request->input('phone');
        $messageText = $request->input('message');

        try {
            $response = $this->smsGateway->send($phoneNumber, $messageText); // Only receives 202 now

            // Response guaranteed to be 202 if no exception was thrown
            $gatewayMessageId = $response->json('id');
            Log::info("SMS enqueued with Gateway ID: {$gatewayMessageId}");

            return response()->json([
                'message' => 'SMS successfully queued for sending.',
                'gateway_message_id' => $gatewayMessageId,
                'status' => $response->json('state', 'Unknown')
            ], 202);

        }
        // --- Optional: Catch specific exceptions for different user feedback ---
        catch (SmsGatewayAuthenticationException $e) {
            // Logged in service, just return user-friendly error
            return response()->json(['error' => 'SMS Gateway authentication failed. Check credentials.'], 401);
        }
        catch (SmsGatewayBadRequestException $e) {
            // Logged in service
            return response()->json(['error' => 'Invalid request sent to SMS Gateway: ' . $e->getMessage()], 400);
        }
        catch (SmsGatewayRateLimitException $e) {
             // Logged in service
             return response()->json(['error' => 'SMS Gateway rate limit exceeded. Please try again later.'], 429);
        }
        // --- Catch remaining gateway or network exceptions ---
        catch (SmsGatewayException $e) { // Catches base and other specific ones (Conflict, Server, Client, Network)
            // Already logged in the service
             return response()->json(['error' => 'Failed to communicate with SMS Gateway: ' . $e->getMessage()], 500);
        }
        // --- Catch any other unexpected errors ---
        catch (\Exception $e) {
             Log::error('Unexpected error during SMS send', ['exception' => $e]); // Log details if not already done
             return response()->json(['error' => 'An unexpected error occurred while sending the SMS.'], 500);
        }
    }

    public function getSmsStatus(string $messageId)
    {
        if (empty($messageId)) {
             return response()->json(['error' => 'Message ID is required.'], 400);
        }

        try {
            $statusData = $this->smsGateway->getStatus($messageId);

            return response()->json($statusData); // Return the full status object

        } catch (SmsGatewayNotFoundException $e) {
            return response()->json(['error' => "SMS message with ID '{$messageId}' not found."], 404);
        } catch (SmsGatewayAuthenticationException $e) {
            return response()->json(['error' => 'SMS Gateway authentication failed.'], 401);
        } catch (SmsGatewayException $e) { // Catch base and other specific ones
            // Already logged in service
             return response()->json(['error' => 'Failed to get status from SMS Gateway: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
             Log::error('Unexpected error during SMS status check', ['exception' => $e, 'message_id' => $messageId]);
             return response()->json(['error' => 'An unexpected error occurred while checking SMS status.'], 500);
        }
    }
}
