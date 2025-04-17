<?php

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
use App\Models\User; // Import User model
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt; // For potential decryption issues logging
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str; // For generating UUIDs
use Throwable; // Import base Throwable

class SmsGatewayService
{
    // Optional global defaults (can still be read from config)
    protected ?int $defaultSim;
    protected ?bool $deliveryReport;

    /**
     * SmsGatewayService constructor.
     * Reads optional global defaults from config/services.php.
     */
    public function __construct()
    {
        // Read only global defaults now
        $this->defaultSim = config('services.smsgateway.default_sim') ?: null;
        $deliveryReportConfig = config('services.smsgateway.delivery_report');
        $this->deliveryReport = $deliveryReportConfig === null ? null : filter_var($deliveryReportConfig, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Helper to get configured and decrypted credentials for a user.
     * Uses the accessor/mutator defined in the User model.
     *
     * @param User $user
     * @return array{url: string, username: string, password: string}|null Returns null if not configured or decryption fails.
     */
    private function getUserCredentials(User $user): ?array
    {
        if (!$user->hasSmsGatewayConfigured()) {
            Log::debug('User does not have SMS gateway configured.', ['user_id' => $user->id]);
            return null;
        }

        try {
            // Use the accessor to get the decrypted password
            $decryptedPassword = $user->sms_gateway_password; // Accessor handles decryption

            if ($decryptedPassword === null || $user->sms_gateway_url === null || $user->sms_gateway_username === null) {
                 // This might happen if decryption failed in the accessor or fields are null
                 Log::error('Failed to retrieve complete/decrypted SMS Gateway credentials for user.', ['user_id' => $user->id]);
                 return null;
            }

            return [
                'url' => $user->sms_gateway_url,
                'username' => $user->sms_gateway_username,
                'password' => $decryptedPassword,
            ];
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            // Catch potential decryption error if not handled properly elsewhere or if field is directly accessed
            Log::critical('Could not decrypt SMS Gateway password for user during credential retrieval.', [
                'user_id' => $user->id,
                'exception' => $e->getMessage()
            ]);
            return null; // Return null on decryption failure
        } catch (Throwable $e) {
            Log::critical('Unexpected error retrieving SMS Gateway credentials for user.', [
                 'user_id' => $user->id,
                 'exception' => $e->getMessage()
             ]);
             return null;
        }
    }


    /**
     * Queue an SMS message for sending in the background using the specified user's settings.
     *
     * @param User $user The user whose settings should be used.
     * @param string|array $phoneNumbers Recipient phone number(s).
     * @param string $message The message text.
     * @param int|null $simNumber Specify SIM slot (1, 2, etc.), null for service default.
     * @param bool|null $withDeliveryReport Request delivery report? null for service default.
     * @param string|null $messageId Optional: Provide your own ID (max 36 chars), otherwise one will be generated.
     * @param int|null $priority Optional message priority (-128 to 127).
     * @return string The message ID (either provided or generated) that was queued.
     * @throws \InvalidArgumentException If the user has not configured SMS gateway settings or messageId is too long.
     * @throws \Exception If there's an error dispatching the job.
     */
    public function send(
        User $user, // Pass the User object
        string|array $phoneNumbers,
        string $message,
        ?int $simNumber = null,
        ?bool $withDeliveryReport = null,
        ?string $messageId = null,
        ?int $priority = null
    ): string {
        // Check if user has settings
        if (!$user->hasSmsGatewayConfigured()) {
             throw new \InvalidArgumentException("User {$user->id} has not configured SMS Gateway settings.");
        }

        if ($messageId !== null && strlen($messageId) > 36) {
            throw new \InvalidArgumentException('Provided message ID cannot exceed 36 characters.');
        }
        $finalMessageId = $messageId ?? (string) Str::uuid();

        Log::info('SmsGatewayService: Queuing SMS job', [
            'user_id' => $user->id,
            'message_id' => $finalMessageId
        ]);

        try {
            // Dispatch the job to the default queue, passing the user ID
            SendSmsViaGatewayJob::dispatch(
                $user->id, // Pass user ID
                $phoneNumbers,
                $message,
                $simNumber,
                $withDeliveryReport,
                $finalMessageId, // Pass the final ID to the job
                $priority
            );
            // ->onQueue('sms'); // Optional: specify a dedicated queue
            // ->delay(now()->addSeconds(5)); // Optional: delay the job
        } catch (Throwable $e) {
            Log::error('SmsGatewayService: Failed to dispatch job.', [
                'user_id' => $user->id,
                'message_id' => $finalMessageId,
                'exception' => $e->getMessage(),
            ]);
            throw new \Exception("Failed to queue SMS job: " . $e->getMessage(), 0, $e);
        }

        return $finalMessageId; // Return the ID immediately
    }

    /**
     * Send an SMS message Directly (Synchronously) using provided credentials.
     * Intended for use by the background job.
     *
     * @param string $url The gateway base URL.
     * @param string $username The gateway username.
     * @param string $password The gateway password (decrypted).
     * @param string|array $phoneNumbers
     * @param string $message
     * @param int|null $simNumber If null, uses service default (if set).
     * @param bool|null $withDeliveryReport If null, uses service default (if set).
     * @param string|null $messageId The ID to be sent to the gateway API.
     * @param int|null $priority
     * @return Response The successful HTTP response object from the gateway (status 202).
     *
     * @throws SmsGatewayBadRequestException|SmsGatewayAuthenticationException|SmsGatewayConflictException|SmsGatewayRateLimitException|SmsGatewayServerException|SmsGatewayClientException|SmsGatewayNetworkException|\Exception
     */
    public function sendDirect(
        string $url,
        string $username,
        string $password,
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

        // Apply defaults if specific values not passed (job should pass its stored values)
        $sim = $simNumber ?? $this->defaultSim;
        if ($sim !== null) $payload['simNumber'] = $sim;

        $delivery = $withDeliveryReport ?? $this->deliveryReport;
        if ($delivery !== null) $payload['withDeliveryReport'] = $delivery;

        if ($messageId !== null) $payload['id'] = $messageId;

        if ($priority !== null) $payload['priority'] = max(-128, min(127, $priority));

        // Sanitize payload for logging
        $logPayload = $payload; // Consider masking phone numbers or message if needed

        $sendUrl = rtrim($url, '/') . '/messages'; // Ensure trailing slash is handled

        try {
            $response = Http::withBasicAuth($username, $password)
                ->timeout(15)
                ->acceptJson()
                ->asJson()
                ->post($sendUrl, $payload);

            // Handle specific statuses using helper methods
            return match ($response->status()) {
                202 => $this->handleSendSuccess($response, $messageId, $logPayload),
                400 => $this->handleSendBadRequest($response, $logPayload, $sendUrl),
                401 => $this->handleSendUnauthorized($response, $logPayload, $username, $sendUrl),
                409 => $this->handleSendConflict($response, $logPayload, $sendUrl),
                429 => $this->handleSendRateLimit($response, $logPayload, $sendUrl),
                default => $this->handleOtherSendError($response, $logPayload, $sendUrl),
            };

        } catch (ConnectionException $e) {
            Log::error('SMS Gateway Connection Exception (SendDirect)', ['message' => $e->getMessage(), 'url' => $sendUrl]);
            throw new SmsGatewayNetworkException("Connection failed while sending SMS directly: " . $e->getMessage(), 0, null, $e);
        } catch (Throwable $e) {
             Log::error('SMS Gateway Generic Exception (SendDirect)', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw new \Exception("Failed to send SMS directly via gateway: " . $e->getMessage(), 0, $e);
        }
    }


    /**
     * Get the status of a specific message using the specified user's settings.
     *
     * @param User $user The user whose settings should be used.
     * @param string $messageId The ID of the message to check.
     * @return array The status data array returned by the gateway API.
     *
     * @throws \InvalidArgumentException If user settings are missing or message ID is empty.
     * @throws SmsGatewayNotFoundException|SmsGatewayAuthenticationException|SmsGatewayServerException|SmsGatewayClientException|SmsGatewayNetworkException|\Exception
     */
    public function getStatus(User $user, string $messageId): array
    {
        if (empty($messageId)) {
            throw new \InvalidArgumentException('Message ID cannot be empty.');
        }

        $credentials = $this->getUserCredentials($user);
        if ($credentials === null) {
            // The helper already logged the error if decryption failed
            throw new \InvalidArgumentException("User {$user->id} has not configured SMS Gateway settings or credentials could not be retrieved.");
        }

        $statusUrl = rtrim($credentials['url'], '/') . '/messages/' . urlencode($messageId);

        try {
            $response = Http::withBasicAuth($credentials['username'], $credentials['password']) // Use user credentials
                ->timeout(10)
                ->acceptJson()
                ->get($statusUrl);

            // Use helper methods, passing correct URL/username for logging
            return match ($response->status()) {
                200 => $this->handleStatusSuccess($response, $messageId),
                401 => $this->handleStatusUnauthorized($response, $messageId, $credentials['username'], $statusUrl),
                404 => $this->handleStatusNotFound($response, $messageId, $statusUrl),
                default => $this->handleOtherStatusError($response, $messageId, $statusUrl),
            };

        } catch (ConnectionException $e) {
             Log::error('SMS Gateway Connection Exception (Get Status)', [
                'message' => $e->getMessage(),
                'url' => $statusUrl,
                'user_id' => $user->id,
                'message_id' => $messageId,
            ]);
             throw new SmsGatewayNetworkException("Connection failed while getting SMS status: " . $e->getMessage(), 0, null, $e);
        } catch (Throwable $e) {
             Log::error('SMS Gateway Generic Exception (Get Status)', [
                 'message' => $e->getMessage(),
                 'user_id' => $user->id,
                 'message_id' => $messageId,
                 'trace' => $e->getTraceAsString()
             ]);
            throw new \Exception("Failed to get SMS status from gateway: " . $e->getMessage(), 0, $e);
        }
    }

    // --- Helper methods for handling responses ---
    // (These include logging and throwing specific exceptions)

    protected function handleSendSuccess(Response $response, ?string $inputMessageId, array $logPayload): Response {
        Log::info('SMS Gateway SendDirect Successful', [
            'status' => $response->status(),
            'input_message_id' => $inputMessageId,
            'gateway_message_id' => $response->json('id'),
            'state' => $response->json('state')
        ]);
        return $response;
    }
    protected function handleSendBadRequest(Response $response, array $logPayload, string $url): never {
        Log::warning('SMS Gateway Bad Request (SendDirect)', ['status' => $response->status(), 'url' => $url, 'payload' => $logPayload, 'response_body' => $response->body()]);
        $errorMessage = $response->json('message') ?? 'Bad request';
        throw new SmsGatewayBadRequestException("Bad request sending SMS: {$errorMessage}", $response->status(), $response);
    }
    protected function handleSendUnauthorized(Response $response, array $logPayload, string $username, string $url): never {
         Log::error('SMS Gateway Authentication Failed (SendDirect)', ['status' => $response->status(), 'url' => $url, 'username' => $username, 'response_body' => $response->body()]);
         $errorMessage = $response->json('message') ?? 'Authentication failed';
         throw new SmsGatewayAuthenticationException("Authentication failed sending SMS: {$errorMessage}", $response->status(), $response);
    }
     protected function handleSendConflict(Response $response, array $logPayload, string $url): never {
          Log::warning('SMS Gateway Conflict (SendDirect)', ['status' => $response->status(), 'url' => $url, 'payload' => $logPayload, 'response_body' => $response->body()]);
          $errorMessage = $response->json('message') ?? 'Conflict detected (e.g., duplicate message ID)';
          throw new SmsGatewayConflictException("Conflict sending SMS: {$errorMessage}", $response->status(), $response);
     }
     protected function handleSendRateLimit(Response $response, array $logPayload, string $url): never {
          Log::warning('SMS Gateway Rate Limited (SendDirect)', ['status' => $response->status(), 'url' => $url, 'response_body' => $response->body()]);
          $errorMessage = $response->json('message') ?? 'Too many requests';
          throw new SmsGatewayRateLimitException("Rate limit exceeded sending SMS: {$errorMessage}", $response->status(), $response);
     }
     protected function handleOtherSendError(Response $response, array $logPayload, string $url): never {
           $logLevel = $response->serverError() ? 'error' : 'warning';
           Log::log($logLevel, 'SMS Gateway Request Failed (SendDirect)', ['status' => $response->status(), 'url' => $url, 'payload' => $logPayload, 'response_body' => $response->body()]);
           $errorMessage = $response->json('message') ?? "Request failed with status {$response->status()}";
           if ($response->serverError()) {
               throw new SmsGatewayServerException("Gateway server error (Send): {$errorMessage}", $response->status(), $response);
           } else {
               throw new SmsGatewayClientException("Gateway client error (Send): {$errorMessage}", $response->status(), $response);
           }
     }
    protected function handleStatusSuccess(Response $response, string $messageId): array {
        Log::info('SMS Gateway Get Status Successful', ['status' => $response->status(),'message_id' => $messageId]);
        return $response->json() ?? [];
    }
    protected function handleStatusUnauthorized(Response $response, string $messageId, string $username, string $url): never {
         Log::error('SMS Gateway Authentication Failed (Get Status)', ['status' => $response->status(), 'url' => $url, 'username' => $username, 'response_body' => $response->body()]);
         $errorMessage = $response->json('message') ?? 'Authentication failed';
         throw new SmsGatewayAuthenticationException("Authentication failed getting status for '{$messageId}': {$errorMessage}", $response->status(), $response);
    }
     protected function handleStatusNotFound(Response $response, string $messageId, string $url): never {
          Log::warning('SMS Gateway Message Not Found (Get Status)', ['status' => $response->status(), 'url' => $url, 'message_id' => $messageId, 'response_body' => $response->body()]);
          $errorMessage = $response->json('message') ?? 'Message not found';
          throw new SmsGatewayNotFoundException("Message '{$messageId}' not found: {$errorMessage}", $response->status(), $response);
     }
    protected function handleOtherStatusError(Response $response, string $messageId, string $url): never {
        $logLevel = $response->serverError() ? 'error' : 'warning';
        Log::log($logLevel, 'SMS Gateway Get Status Failed', ['status' => $response->status(), 'url' => $url, 'message_id' => $messageId, 'response_body' => $response->body()]);
        $errorMessage = $response->json('message') ?? "Request failed with status {$response->status()}";
        if ($response->serverError()) {
            throw new SmsGatewayServerException("Gateway server error getting status for '{$messageId}': {$errorMessage}", $response->status(), $response);
        } else {
            throw new SmsGatewayClientException("Gateway client error getting status for '{$messageId}': {$errorMessage}", $response->status(), $response);
        }
    }
}