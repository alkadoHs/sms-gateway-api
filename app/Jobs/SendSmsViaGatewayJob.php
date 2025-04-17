<?php

namespace App\Jobs;

use App\Exceptions\SmsGatewayAuthenticationException;
use App\Exceptions\SmsGatewayBadRequestException;
use App\Exceptions\SmsGatewayNetworkException;
use App\Exceptions\SmsGatewayServerException;
use App\Models\User; // Import User model
use App\Services\SmsGatewayService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
// use Illuminate\Contracts\Queue\ShouldBeUnique; // Uncomment if using uniqueness
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendSmsViaGatewayJob implements ShouldQueue //, ShouldBeUnique // Optional
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Public properties are automatically serialized
    public int $userId; // Store user ID
    public string|array $phoneNumbers;
    public string $message;
    public ?int $simNumber;
    public ?bool $withDeliveryReport;
    public ?string $messageId;
    public ?int $priority;

    // Optional: Job configuration
    public int $tries = 3; // Number of times to attempt the job
    public int $maxExceptions = 2; // Stop retrying after this many exceptions (Laravel default is usually unlimited unless specified)
    public int $backoff = 60; // Initial delay (seconds) before retrying: 60s, 120s, 180s...


    /**
     * Create a new job instance.
     *
     * @param int $userId ID of the user whose settings to use
     * @param string|array $phoneNumbers
     * @param string $message
     * @param int|null $simNumber
     * @param bool|null $withDeliveryReport
     * @param string|null $messageId
     * @param int|null $priority
     */
    public function __construct(
        int $userId,
        string|array $phoneNumbers,
        string $message,
        ?int $simNumber = null,
        ?bool $withDeliveryReport = null,
        ?string $messageId = null,
        ?int $priority = null
    ) {
        $this->userId = $userId; // Store user ID
        $this->phoneNumbers = $phoneNumbers;
        $this->message = $message;
        $this->simNumber = $simNumber;
        $this->withDeliveryReport = $withDeliveryReport;
        $this->messageId = $messageId;
        $this->priority = $priority;
    }

    /**
     * Optional: Define a unique ID for the job to prevent duplicates.
     * Requires implementing ShouldBeUnique.
     */
    // public function uniqueId(): string
    // {
    //     // Using messageId ensures a specific gateway message isn't queued multiple times
    //     // if the dispatch happens rapidly before the first job runs.
    //     return $this->messageId ?? ('sms_send_user_' . $this->userId . '_' . uniqid());
    // }

    /**
     * Optional: Define how long the lock should be held for uniqueness.
     */
    // public function uniqueFor(): int
    // {
    //     return 60 * 5; // 5 minutes lock
    // }

    /**
     * Execute the job.
     * Fetches user, retrieves credentials, and calls the direct send method.
     *
     * @param SmsGatewayService $smsGateway Injected by Laravel's service container
     */
    public function handle(SmsGatewayService $smsGateway): void
    {
        Log::info('SendSmsViaGatewayJob: Starting job', [
            'user_id' => $this->userId,
            'message_id' => $this->messageId,
            'attempt' => $this->attempts() // Log current attempt number
        ]);

        // Find the user
        $user = User::find($this->userId);
        if (!$user) {
            Log::error('SendSmsViaGatewayJob: User not found.', ['user_id' => $this->userId]);
            $this->fail(new \Exception("User {$this->userId} not found. Cannot send SMS.")); // Fail permanently
            return;
        }

        // Get credentials (handle case where they might be missing or decryption fails)
        $credentials = null;
        try {
            if (!$user->hasSmsGatewayConfigured()) {
                 Log::error('SendSmsViaGatewayJob: SMS Gateway not configured for user.', ['user_id' => $this->userId]);
                 $this->fail(new \Exception("SMS Gateway not configured for user {$this->userId}."));
                 return;
            }
            // Use the accessor/mutator for decryption - ensure User model is correct
             $decryptedPassword = $user->sms_gateway_password;
             if ($decryptedPassword === null || $user->sms_gateway_url === null || $user->sms_gateway_username === null) {
                  throw new \Exception('Failed to get/decrypt complete credentials from user model.');
             }
             $credentials = [
                 'url' => $user->sms_gateway_url,
                 'username' => $user->sms_gateway_username,
                 'password' => $decryptedPassword,
             ];
        } catch (Throwable $e) {
             Log::critical('SendSmsViaGatewayJob: Failed to get/decrypt credentials for user.', [
                'user_id' => $this->userId,
                'exception' => $e->getMessage()
             ]);
             $this->fail($e); // Fail permanently if credentials can't be retrieved/decrypted
             return;
        }

        try {
             // Call the *direct* sending method with the fetched credentials
             $response = $smsGateway->sendDirect(
                 $credentials['url'],
                 $credentials['username'],
                 $credentials['password'],
                 $this->phoneNumbers,
                 $this->message,
                 $this->simNumber,
                 $this->withDeliveryReport,
                 $this->messageId, // Pass the specific message ID
                 $this->priority
             );

            // Logging is handled within sendDirect's success handler
            Log::info('SendSmsViaGatewayJob: API call successful.', [
                 'user_id' => $this->userId,
                 'message_id' => $this->messageId,
                 'gateway_message_id' => $response->json('id')
             ]);

            // Job finished successfully, no need to do anything else.

        } catch (Throwable $e) {
            // Exceptions are logged within the service's specific handle methods
            Log::error('SendSmsViaGatewayJob: Failed during API call execution', [
                'user_id' => $this->userId,
                'message_id' => $this->messageId,
                'attempt' => $this->attempts(),
                'exception_type' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);

            // Decide if the job should be released back to the queue for retry
            if ($this->attempts() < $this->tries && $this->shouldRetry($e)) {
                // release() increases the attempt count and respects backoff
                $delay = $this->backoff * $this->attempts(); // Simple exponential backoff
                 Log::warning("SendSmsViaGatewayJob: Releasing job back to queue.", [
                    'user_id' => $this->userId,
                    'message_id' => $this->messageId,
                    'delay_seconds' => $delay
                ]);
                $this->release($delay);
            } else {
                 // Mark as failed permanently if retries exhausted or error is non-retryable
                 Log::error("SendSmsViaGatewayJob: Failing job permanently.", [
                    'user_id' => $this->userId,
                    'message_id' => $this->messageId,
                    'attempts' => $this->attempts(),
                 ]);
                $this->fail($e);
            }
        }
    }

    /**
     * Determine if the job should be retried based on the exception.
     * Customize this based on the specific exceptions thrown by your service.
     */
    protected function shouldRetry(Throwable $e): bool
    {
        // Example: Retry network issues or temporary server errors (5xx)
        if ($e instanceof SmsGatewayNetworkException || $e instanceof SmsGatewayServerException) {
            return true;
        }
        // Example: Don't retry authentication (401) or bad request (400, 409) errors
        if ($e instanceof SmsGatewayAuthenticationException ||
            $e instanceof SmsGatewayBadRequestException ||
            $e instanceof SmsGatewayConflictException) {
            return false;
        }

        // Example: Potentially retry rate limit errors after a delay (handled by release backoff)
        if ($e instanceof SmsGatewayRateLimitException) {
             return true; // relies on backoff delay
        }

        // Default: Maybe don't retry other client errors or generic exceptions?
        return false;
    }

    /**
     * Handle a job failure after all retries are exhausted or fail() is called.
     */
    public function failed(Throwable $exception): void
    {
        Log::critical('SendSmsViaGatewayJob: PERMANENTLY FAILED', [
            'user_id' => $this->userId,
            'message_id' => $this->messageId,
            'exception_type' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            // 'trace' => $exception->getTraceAsString() // Optional: include trace for critical failures
        ]);
        // Add logic here: notify admin, mark message as failed in your DB, etc.
        // Example: Find your internal message record using $this->messageId and update its status
        // YourInternalMessageModel::where('tracking_id', $this->messageId)->update(['status' => 'failed']);
    }
}