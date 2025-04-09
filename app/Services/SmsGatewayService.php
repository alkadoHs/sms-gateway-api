<?php

// app/Services/SmsGatewayService.php

namespace App\Services;

use App\Exceptions\SmsGatewayAuthenticationException;
use App\Exceptions\SmsGatewayBadRequestException;
use App\Exceptions\SmsGatewayClientException;
use App\Exceptions\SmsGatewayConflictException;
use App\Exceptions\SmsGatewayException; // Import base exception
use App\Exceptions\SmsGatewayNetworkException;
use App\Exceptions\SmsGatewayNotFoundException;
use App\Exceptions\SmsGatewayRateLimitException;
use App\Exceptions\SmsGatewayServerException;
use App\Jobs\SendSmsViaGatewayJob; // Import the job
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str; // For generating UUIDs
use Throwable; // Import base Throwable

class SmsGatewayService
{
    protected string $baseUrl; // Base URL for the API (e.g., https://api.sms-gate.app/3rdparty/v1 or http://device:8080)
    protected string $username;
    protected string $password;
    protected ?int $defaultSim;
    protected ?bool $deliveryReport;

    /**
     * SmsGatewayService constructor.
     * Reads configuration from config/services.php (which should read from .env).
     *
     * @throws \InvalidArgumentException If required configuration is missing.
     */
    public function __construct()
    {
        // Use 'base_url' from config
        $this->baseUrl = config('services.smsgateway.base_url');
        $this->username = config('services.smsgateway.username');
        $this->password = config('services.smsgateway.password');
        $this->defaultSim = config('services.smsgateway.default_sim') ?: null;

        // Handle boolean conversion carefully for null values from env()
        $deliveryReportConfig = config('services.smsgateway.delivery_report');
        $this->deliveryReport = $deliveryReportConfig === null ? null : filter_var($deliveryReportConfig, FILTER_VALIDATE_BOOLEAN);

        // Check the base URL now
        if (empty($this->baseUrl) || empty($this->username) || empty($this->password)) {
            throw new \InvalidArgumentException('SMS Gateway Base URL, Username, or Password not configured in config/services.php or .env file.');
        }
    }

    /**
     * Queue an SMS message for sending in the background.
     * Generates a message ID if one is not provided.
     *
     * @param string|array $phoneNumbers Recipient phone number(s). Use international format (e.g., +1...).
     * @param string $message The message text.
     * @param int|null $simNumber Specify SIM slot (1, 2, etc.), null for default. Overrides config.
     * @param bool|null $withDeliveryReport Request delivery report? Overrides config.
     * @param string|null $messageId Optional: Provide your own ID, otherwise one will be generated (UUID). Max 36 chars.
     * @param int|null $priority Optional message priority (-128 to 127). >= 100 bypasses limits/delays.
     * @return string The message ID (either provided or generated) that was queued.
     * @throws \InvalidArgumentException If provided messageId exceeds 36 characters.
     */
    public function send(
        string|array $phoneNumbers,
        string $message,
        ?int $simNumber = null,
        ?bool $withDeliveryReport = null,
        ?string $messageId = null,
        ?int $priority = null
    ): string {
        // Validate provided messageId length if present
        if ($messageId !== null && strlen($messageId) > 36) {
            throw new \InvalidArgumentException('Provided message ID cannot exceed 36 characters.');
        }

        // Generate a UUID if an ID wasn't provided
        $finalMessageId = $messageId ?? (string) Str::uuid();

        Log::info('SmsGatewayService: Queuing SMS job', ['message_id' => $finalMessageId]);

        // Dispatch the job to the default queue
        SendSmsViaGatewayJob::dispatch(
            $phoneNumbers,
            $message,
            $simNumber,
            $withDeliveryReport,
            $finalMessageId, // Pass the final ID to the job
            $priority
        );
        // ->onQueue('sms'); // Optional: specify a dedicated queue
        // ->delay(now()->addSeconds(5)); // Optional: delay the job

        return $finalMessageId; // Return the ID immediately
    }


    /**
     * Send an SMS message Directly (Synchronously).
     * This method performs the actual HTTP call and is intended to be called by the background job.
     *
     * @param string|array $phoneNumbers
     * @param string $message
     * @param int|null $simNumber
     * @param bool|null $withDeliveryReport
     * @param string|null $messageId // The ID to be sent to the gateway API.
     * @param int|null $priority
     * @return Response The successful HTTP response object from the gateway (status 202).
     *
     * @throws SmsGatewayBadRequestException
     * @throws SmsGatewayAuthenticationException
     * @throws SmsGatewayConflictException
     * @throws SmsGatewayRateLimitException
     * @throws SmsGatewayServerException
     * @throws SmsGatewayClientException
     * @throws SmsGatewayNetworkException
     * @throws \Exception For other unexpected errors.
     */
    public function sendDirect(
        string|array $phoneNumbers,
        string $message,
        ?int $simNumber = null,
        ?bool $withDeliveryReport = null,
        ?string $messageId = null,
        ?int $priority = null
    ): Response {
         // Payload construction logic
         $payload = [
            'message' => $message,
            'phoneNumbers' => is_array($phoneNumbers) ? $phoneNumbers : [$phoneNumbers],
        ];

        $sim = $simNumber ?? $this->defaultSim;
        if ($sim !== null) {
            $payload['simNumber'] = $sim;
        }

        $delivery = $withDeliveryReport ?? $this->deliveryReport;
        if ($delivery !== null) {
            $payload['withDeliveryReport'] = $delivery;
        }

        if ($messageId !== null) {
            $payload['id'] = $messageId;
        }

         if ($priority !== null) {
             // Clamp priority within valid range
             $payload['priority'] = max(-128, min(127, $priority));
         }

        // Sanitize payload for logging (remove sensitive parts if necessary)
        $logPayload = $payload; // Adjust if message content is sensitive (e.g., mask parts)

        $sendUrl = $this->baseUrl . '/messages'; // Construct full URL for sending

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->timeout(15) // Set a reasonable timeout (in seconds)
                ->acceptJson()
                ->asJson()
                ->post($sendUrl, $payload); // Use sendUrl

            // Handle specific statuses using helper methods
            return match ($response->status()) {
                202 => $this->handleSendSuccess($response, $messageId, $logPayload),
                400 => $this->handleSendBadRequest($response, $logPayload),
                401 => $this->handleSendUnauthorized($response, $logPayload),
                409 => $this->handleSendConflict($response, $logPayload),
                429 => $this->handleSendRateLimit($response, $logPayload),
                // Default cases for other errors
                default => $this->handleOtherSendError($response, $logPayload),
            };

        } catch (ConnectionException $e) {
            Log::error('SMS Gateway Connection Exception (SendDirect)', [
                'message' => $e->getMessage(),
                'url' => $sendUrl,
                'message_id_requested' => $messageId,
            ]);
            // Wrap in our specific network exception
            throw new SmsGatewayNetworkException("Connection failed while sending SMS directly: " . $e->getMessage(), 0, null, $e);
        } catch (Throwable $e) {
            // Catch other potential exceptions from Http client or elsewhere
             Log::error('SMS Gateway Generic Exception (SendDirect)', [
                 'message' => $e->getMessage(),
                 'message_id_requested' => $messageId,
                 'trace' => $e->getTraceAsString() // Be cautious with trace in production logs
             ]);
            // Re-throw as a generic Exception or a specific SmsGatewayException
            throw new \Exception("Failed to send SMS directly via gateway: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the status of a specific message from the gateway.
     *
     * @param string $messageId The ID of the message to check.
     * @return array The status data returned by the gateway API (usually includes id, state, recipients, states).
     *
     * @throws SmsGatewayNotFoundException If the message ID is not found (404).
     * @throws SmsGatewayAuthenticationException If credentials are wrong (401).
     * @throws SmsGatewayServerException For 5xx errors from the gateway.
     * @throws SmsGatewayClientException For other 4xx errors.
     * @throws SmsGatewayNetworkException For connection issues.
     * @throws \InvalidArgumentException If messageId is empty.
     * @throws \Exception For other unexpected errors.
     */
    public function getStatus(string $messageId): array
    {
        if (empty($messageId)) {
            throw new \InvalidArgumentException('Message ID cannot be empty.');
        }

        $statusUrl = $this->baseUrl . '/messages/' . urlencode($messageId);

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->timeout(10) // Shorter timeout for status checks might be okay
                ->acceptJson()
                ->get($statusUrl);

            // Handle specific statuses for GET request using helper methods
            return match ($response->status()) {
                200 => $this->handleStatusSuccess($response, $messageId),
                401 => $this->handleStatusUnauthorized($response, $messageId),
                404 => $this->handleStatusNotFound($response, $messageId),
                // Default cases for other errors
                default => $this->handleOtherStatusError($response, $messageId),
            };

        } catch (ConnectionException $e) {
            Log::error('SMS Gateway Connection Exception (Get Status)', [
                'message' => $e->getMessage(),
                'url' => $statusUrl,
                'message_id' => $messageId,
            ]);
            throw new SmsGatewayNetworkException("Connection failed while getting SMS status: " . $e->getMessage(), 0, null, $e);
        } catch (Throwable $e) {
             Log::error('SMS Gateway Generic Exception (Get Status)', [
                 'message' => $e->getMessage(),
                 'message_id' => $messageId,
                 'trace' => $e->getTraceAsString()
             ]);
            throw new \Exception("Failed to get SMS status from gateway: " . $e->getMessage(), 0, $e);
        }
    }

    // --- Helper methods for handling POST (Send) responses ---

    protected function handleSendSuccess(Response $response, ?string $inputMessageId, array $logPayload): Response
    {
        Log::info('SMS Gateway Send Successful', [
            'status' => $response->status(),
            'input_message_id' => $inputMessageId, // ID sent in the request
            'gateway_message_id' => $response->json('id'), // ID returned by API
            'state' => $response->json('state'),
        ]);
        return $response; // Return the successful response object
    }

    protected function handleSendBadRequest(Response $response, array $logPayload): never
    {
        Log::warning('SMS Gateway Bad Request (Send)', [
            'status' => $response->status(),
            'url' => $this->baseUrl . '/messages',
            'payload' => $logPayload,
            'response_body' => $response->body(),
        ]);
        $errorMessage = $response->json('message') ?? 'Bad request';
        throw new SmsGatewayBadRequestException("Bad request sending SMS: {$errorMessage}", $response->status(), $response);
    }

    protected function handleSendUnauthorized(Response $response, array $logPayload): never
    {
        Log::error('SMS Gateway Authentication Failed (Send)', [
            'status' => $response->status(),
            'url' => $this->baseUrl . '/messages',
            'username' => $this->username, // Log username for identification
            'response_body' => $response->body(),
        ]);
        $errorMessage = $response->json('message') ?? 'Authentication failed';
        throw new SmsGatewayAuthenticationException("Authentication failed sending SMS: {$errorMessage}", $response->status(), $response);
    }

     protected function handleSendConflict(Response $response, array $logPayload): never
     {
         Log::warning('SMS Gateway Conflict (Send)', [
             'status' => $response->status(),
             'url' => $this->baseUrl . '/messages',
             'payload' => $logPayload, // Contains the potentially duplicate ID
             'response_body' => $response->body(),
         ]);
         $errorMessage = $response->json('message') ?? 'Conflict detected (e.g., duplicate message ID)';
         throw new SmsGatewayConflictException("Conflict sending SMS: {$errorMessage}", $response->status(), $response);
     }

     protected function handleSendRateLimit(Response $response, array $logPayload): never
     {
         Log::warning('SMS Gateway Rate Limited (Send)', [
             'status' => $response->status(),
             'url' => $this->baseUrl . '/messages',
             'response_body' => $response->body(),
         ]);
         $errorMessage = $response->json('message') ?? 'Too many requests';
         throw new SmsGatewayRateLimitException("Rate limit exceeded sending SMS: {$errorMessage}", $response->status(), $response);
     }

     protected function handleOtherSendError(Response $response, array $logPayload): never
     {
         $logLevel = $response->serverError() ? 'error' : 'warning';
         Log::log($logLevel, 'SMS Gateway Request Failed (Send)', [
             'status' => $response->status(),
             'url' => $this->baseUrl . '/messages',
             'payload' => $logPayload,
             'response_body' => $response->body(),
         ]);

         $errorMessage = $response->json('message') ?? "Request failed with status {$response->status()}";

         if ($response->serverError()) {
             throw new SmsGatewayServerException("Gateway server error (Send): {$errorMessage}", $response->status(), $response);
         } else {
             // Other 4xx errors
             throw new SmsGatewayClientException("Gateway client error (Send): {$errorMessage}", $response->status(), $response);
         }
     }

    // --- Helper methods for handling GET Status responses ---

    protected function handleStatusSuccess(Response $response, string $messageId): array
    {
        Log::info('SMS Gateway Get Status Successful', [
            'status' => $response->status(),
            'message_id' => $messageId,
        ]);
        // Return the parsed JSON body, or an empty array if parsing fails
        return $response->json() ?? [];
    }

    protected function handleStatusUnauthorized(Response $response, string $messageId): never
    {
        Log::error('SMS Gateway Authentication Failed (Get Status)', [
            'status' => $response->status(),
            'url' => $this->baseUrl . '/messages/' . $messageId,
            'username' => $this->username,
            'response_body' => $response->body(),
        ]);
        $errorMessage = $response->json('message') ?? 'Authentication failed';
        throw new SmsGatewayAuthenticationException("Authentication failed getting status for '{$messageId}': {$errorMessage}", $response->status(), $response);
    }

     protected function handleStatusNotFound(Response $response, string $messageId): never
     {
         Log::warning('SMS Gateway Message Not Found (Get Status)', [
             'status' => $response->status(),
             'url' => $this->baseUrl . '/messages/' . $messageId,
             'message_id' => $messageId,
             'response_body' => $response->body(),
         ]);
         $errorMessage = $response->json('message') ?? 'Message not found';
         throw new SmsGatewayNotFoundException("Message '{$messageId}' not found: {$errorMessage}", $response->status(), $response);
     }

    protected function handleOtherStatusError(Response $response, string $messageId): never
    {
        $logLevel = $response->serverError() ? 'error' : 'warning';
        Log::log($logLevel, 'SMS Gateway Get Status Failed', [
            'status' => $response->status(),
            'url' => $this->baseUrl . '/messages/' . $messageId,
            'message_id' => $messageId,
            'response_body' => $response->body(),
        ]);

        $errorMessage = $response->json('message') ?? "Request failed with status {$response->status()}";

        if ($response->serverError()) {
            throw new SmsGatewayServerException("Gateway server error getting status for '{$messageId}': {$errorMessage}", $response->status(), $response);
        } else {
            // Other 4xx errors (like 400 if ID format is wrong)
            throw new SmsGatewayClientException("Gateway client error getting status for '{$messageId}': {$errorMessage}", $response->status(), $response);
        }
    }
}