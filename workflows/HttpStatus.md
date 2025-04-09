Okay, let's refine the `SmsGatewayService` to handle different HTTP response statuses more specifically. This gives you better insight into *why* a request might have failed.

**1. Define Custom Exceptions (Optional but Recommended):**

Create custom exceptions to represent specific failure scenarios. This allows calling code to catch specific errors if needed.

```php
// app/Exceptions/SmsGatewayException.php
namespace App\Exceptions;

use Illuminate\Http\Client\Response;

class SmsGatewayException extends \Exception
{
    public ?Response $response;

    public function __construct(string $message = "", int $code = 0, ?Response $response = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->response = $response;
    }
}

// Create other specific exceptions extending the base one:
// app/Exceptions/SmsGatewayBadRequestException.php
namespace App\Exceptions;
class SmsGatewayBadRequestException extends SmsGatewayException {}

// app/Exceptions/SmsGatewayAuthenticationException.php
namespace App\Exceptions;
class SmsGatewayAuthenticationException extends SmsGatewayException {}

// app/Exceptions/SmsGatewayConflictException.php
namespace App\Exceptions;
class SmsGatewayConflictException extends SmsGatewayException {}

// app/Exceptions/SmsGatewayRateLimitException.php
namespace App\Exceptions;
class SmsGatewayRateLimitException extends SmsGatewayException {}

// app/Exceptions/SmsGatewayServerException.php
namespace App\Exceptions;
class SmsGatewayServerException extends SmsGatewayException {}

// app/Exceptions/SmsGatewayClientException.php
namespace App\Exceptions;
class SmsGatewayClientException extends SmsGatewayException {} // For other 4xx errors

// app/Exceptions/SmsGatewayNetworkException.php
namespace App\Exceptions;
class SmsGatewayNetworkException extends SmsGatewayException {} // For connection errors
```

**2. Update the `SmsGatewayService::send` Method:**

Modify the `send` method to check the status code and throw the appropriate exception.

```php
// app/Services/SmsGatewayService.php

namespace App\Services;

use App\Exceptions\SmsGatewayAuthenticationException;
use App\Exceptions\SmsGatewayBadRequestException;
use App\Exceptions\SmsGatewayClientException;
use App\Exceptions\SmsGatewayConflictException;
use App\Exceptions\SmsGatewayNetworkException;
use App\Exceptions\SmsGatewayRateLimitException;
use App\Exceptions\SmsGatewayServerException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable; // Import base Throwable

class SmsGatewayService
{
    protected string $url;
    protected string $username;
    protected string $password;
    protected ?int $defaultSim;
    protected ?bool $deliveryReport;

    public function __construct()
    {
        $this->url = config('services.smsgateway.url');
        $this->username = config('services.smsgateway.username');
        $this->password = config('services.smsgateway.password');
        $this->defaultSim = config('services.smsgateway.default_sim') ?: null;
        $this->deliveryReport = config('services.smsgateway.delivery_report') === null ? null : filter_var(config('services.smsgateway.delivery_report'), FILTER_VALIDATE_BOOLEAN);

        if (empty($this->url) || empty($this->username) || empty($this->password)) {
            // Use a more specific exception for configuration issues if preferred
            throw new \InvalidArgumentException('SMS Gateway URL, Username, or Password not configured.');
        }
    }

    /**
     * Send an SMS message.
     *
     * @param string|array $phoneNumbers Recipient phone number(s). Use international format (e.g., +1...).
     * @param string $message The message text.
     * @param int|null $simNumber Specify SIM slot (1, 2, etc.), null for default. Overrides config.
     * @param bool|null $withDeliveryReport Request delivery report? Overrides config.
     * @param string|null $messageId Optional custom message ID.
     * @param int|null $priority Optional message priority (-128 to 127). >= 100 bypasses limits/delays.
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
    public function send(
        string|array $phoneNumbers,
        string $message,
        ?int $simNumber = null,
        ?bool $withDeliveryReport = null,
        ?string $messageId = null,
        ?int $priority = null
    ): Response {
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
             $payload['priority'] = $priority;
        }

        // Sanitize payload for logging (remove sensitive parts if necessary)
        $logPayload = $payload; // Adjust if message content is sensitive

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->timeout(15) // Set a reasonable timeout (in seconds)
                ->acceptJson()
                ->asJson()
                ->post($this->url, $payload);

            // Handle specific statuses
            return match ($response->status()) {
                202 => $this->handleSuccess($response, $messageId, $logPayload),
                400 => $this->handleBadRequest($response, $logPayload),
                401 => $this->handleUnauthorized($response, $logPayload),
                409 => $this->handleConflict($response, $logPayload),
                429 => $this->handleRateLimit($response, $logPayload),
                // Default cases for other errors
                default => $this->handleOtherError($response, $logPayload),
            };

        } catch (ConnectionException $e) {
            Log::error('SMS Gateway Connection Exception', [
                'message' => $e->getMessage(),
                'url' => $this->url,
            ]);
            throw new SmsGatewayNetworkException("Connection failed: " . $e->getMessage(), 0, null, $e);
        } catch (Throwable $e) {
            // Catch other potential exceptions from Http client or elsewhere
             Log::error('SMS Gateway Generic Exception', [
                 'message' => $e->getMessage(),
                 'trace' => $e->getTraceAsString() // Be cautious with trace in production logs
             ]);
            throw new \Exception("Failed to send SMS via gateway: " . $e->getMessage(), 0, $e);
        }
    }

    // --- Helper methods for handling responses ---

    protected function handleSuccess(Response $response, ?string $inputMessageId, array $logPayload): Response
    {
        Log::info('SMS Gateway Send Successful', [
            'status' => $response->status(),
            'input_message_id' => $inputMessageId, // ID sent in the request
            'gateway_message_id' => $response->json('id'), // ID returned by API
            'state' => $response->json('state'),
        ]);
        return $response;
    }

    protected function handleBadRequest(Response $response, array $logPayload): never
    {
        Log::warning('SMS Gateway Bad Request', [
            'status' => $response->status(),
            'url' => $this->url,
            'payload' => $logPayload,
            'response_body' => $response->body(),
        ]);
        $errorMessage = $response->json('message') ?? 'Bad request';
        throw new SmsGatewayBadRequestException("Bad request: {$errorMessage}", $response->status(), $response);
    }

    protected function handleUnauthorized(Response $response, array $logPayload): never
    {
         // Log only username, never the password!
        Log::error('SMS Gateway Authentication Failed', [
            'status' => $response->status(),
            'url' => $this->url,
            'username' => $this->username, // Log username for identification
            // 'payload' => $logPayload, // Avoid logging payload on auth failure
            'response_body' => $response->body(),
        ]);
        $errorMessage = $response->json('message') ?? 'Authentication failed';
        throw new SmsGatewayAuthenticationException("Authentication failed: {$errorMessage}", $response->status(), $response);
    }

     protected function handleConflict(Response $response, array $logPayload): never
     {
         Log::warning('SMS Gateway Conflict', [
             'status' => $response->status(),
             'url' => $this->url,
             'payload' => $logPayload, // Contains the potentially duplicate ID
             'response_body' => $response->body(),
         ]);
         $errorMessage = $response->json('message') ?? 'Conflict detected (e.g., duplicate message ID)';
         throw new SmsGatewayConflictException("Conflict: {$errorMessage}", $response->status(), $response);
     }

     protected function handleRateLimit(Response $response, array $logPayload): never
     {
         Log::warning('SMS Gateway Rate Limited', [
             'status' => $response->status(),
             'url' => $this->url,
             // 'payload' => $logPayload, // Payload might not be needed for rate limit logs
             'response_body' => $response->body(),
         ]);
         $errorMessage = $response->json('message') ?? 'Too many requests';
         throw new SmsGatewayRateLimitException("Rate limit exceeded: {$errorMessage}", $response->status(), $response);
     }

    protected function handleOtherError(Response $response, array $logPayload): never
    {
        $logLevel = $response->serverError() ? 'error' : 'warning';
        Log::log($logLevel, 'SMS Gateway Request Failed', [
            'status' => $response->status(),
            'url' => $this->url,
            'payload' => $logPayload,
            'response_body' => $response->body(),
        ]);

        $errorMessage = $response->json('message') ?? "Request failed with status {$response->status()}";

        if ($response->serverError()) {
            throw new SmsGatewayServerException("Gateway server error: {$errorMessage}", $response->status(), $response);
        } else {
            // Other 4xx errors
            throw new SmsGatewayClientException("Gateway client error: {$errorMessage}", $response->status(), $response);
        }
    }
}
```

**3. Update Controller (Optional Fine-grained Catching):**

Your controller can now potentially catch specific exceptions if you need different user feedback for different errors. However, often logging the details in the service and showing a generic error is sufficient.

```php
// app/Http/Controllers/SmsController.php

namespace App\Http\Controllers;

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
    // ... (constructor as before)

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
}
```

**Key Changes:**

*   **Custom Exceptions:** Provide clear, typed errors for different failure modes.
*   **`match` Statement:** Uses PHP 8.0's `match` for cleaner status code handling (a `switch` works too).
*   **Specific Handlers:** Dedicated private methods (`handleSuccess`, `handleBadRequest`, etc.) to process each response type.
*   **Improved Logging:** Logs now include the status code and context-specific information. Importantly, sensitive data like passwords are *not* logged in case of authentication failures.
*   **Controller Refinement:** The controller can now catch specific `SmsGateway...Exception` types if necessary, although often catching the base `SmsGatewayException` or just `\Exception` is enough, relying on the service's more detailed logging.
*   **Network Errors:** Explicitly catches `ConnectionException` and wraps it in `SmsGatewayNetworkException`.

Now, when an SMS fails to send, you'll get more specific exceptions and logs, making it much easier to debug whether it's a credential issue, a bad phone number, a server problem, or something else.