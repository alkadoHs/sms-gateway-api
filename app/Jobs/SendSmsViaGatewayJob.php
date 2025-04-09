<?php

namespace App\Jobs;

use App\Services\SmsGatewayService; // Import the service
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable; // Use base Throwable

// Use ShouldBeUnique if you want to prevent duplicate jobs for the same message ID within a certain timeframe
// use Illuminate\Contracts\Queue\ShouldBeUnique;

class SendSmsViaGatewayJob implements ShouldQueue //, ShouldBeUnique // Optional
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Public properties are automatically serialized
    public string|array $phoneNumbers;
    public string $message;
    public ?int $simNumber;
    public ?bool $withDeliveryReport;
    public ?string $messageId; // Use the ID passed to the job
    public ?int $priority;

    // Optional: Job configuration
    public int $tries = 3; // Number of times to attempt the job
    public int $maxExceptions = 2; // Stop retrying after this many exceptions
    public int $backoff = 60; // Delay (seconds) before retrying: 60s, 120s, 180s...


    /**
     * Create a new job instance.
     *
     * @param string|array $phoneNumbers
     * @param string $message
     * @param int|null $simNumber
     * @param bool|null $withDeliveryReport
     * @param string|null $messageId // Pass the intended message ID
     * @param int|null $priority
     */
    public function __construct(
        string|array $phoneNumbers,
        string $message,
        ?int $simNumber = null,
        ?bool $withDeliveryReport = null,
        ?string $messageId = null,
        ?int $priority = null
    ) {
        $this->phoneNumbers = $phoneNumbers;
        $this->message = $message;
        $this->simNumber = $simNumber;
        $this->withDeliveryReport = $withDeliveryReport;
        $this->messageId = $messageId; // Store the ID
        $this->priority = $priority;
    }

    /**
     * Optional: Define a unique ID for the job to prevent duplicates.
     * Requires implementing ShouldBeUnique.
     */
    // public function uniqueId(): string
    // {
    //     // Be careful making this too broad. Using messageId might be good if you
    //     // want to ensure a specific message ID is only queued once.
    //     return $this->messageId ?? uniqid('sms_send_', true);
    // }

    /**
     * Optional: Define how long the lock should be held for uniqueness.
     */
    // public function uniqueFor(): int
    // {
    //     return 60 * 5; // 5 minutes
    // }

    /**
     * Execute the job.
     *
     * Inject the service directly into the handle method.
     */
    public function handle(SmsGatewayService $smsGateway): void
    {
        Log::info('SendSmsViaGatewayJob: Starting job', ['message_id' => $this->messageId]);

        try {
            // Call the *actual* sending logic (which is now encapsulated here or
            // potentially in a separate private method within the service if preferred)
            // For simplicity, we assume the service's send method now performs the direct HTTP call
            // Let's create a new method in the service for the direct call

            // Note: We pass $this->messageId to the *direct* send method now
            $response = $smsGateway->sendDirect(
                $this->phoneNumbers,
                $this->message,
                $this->simNumber,
                $this->withDeliveryReport,
                $this->messageId, // Pass the ID for the API call
                $this->priority
            );

            // Log success based on the API response inside the job
            // The service's handleSendSuccess method already logs.
             Log::info('SendSmsViaGatewayJob: Successfully sent SMS via gateway', [
                 'job_message_id' => $this->messageId, // ID passed to job
                 'gateway_message_id' => $response->json('id'), // ID returned by API
                 'status' => $response->status()
             ]);

        } catch (Throwable $e) {
            // Exceptions are already logged within the service's handle methods
            Log::error('SendSmsViaGatewayJob: Failed', [
                'job_message_id' => $this->messageId,
                'exception_type' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);

            // Decide if the job should be released back to the queue for retry
            // based on the type of exception (e.g., retry network errors, fail auth errors)
            if ($this->shouldRetry($e)) {
                // release() increases the attempt count and respects backoff
                $this->release($this->backoff * $this->attempts()); // Exponential backoff example
            } else {
                // fail() marks the job as failed permanently
                $this->fail($e);
            }
        }
    }

    /**
     * Determine if the job should be retried based on the exception.
     */
    protected function shouldRetry(Throwable $e): bool
    {
        // Example: Retry network issues or temporary server errors
        if ($e instanceof \App\Exceptions\SmsGatewayNetworkException || $e instanceof \App\Exceptions\SmsGatewayServerException) {
            return true;
        }
        // Example: Don't retry authentication or bad request errors
        if ($e instanceof \App\Exceptions\SmsGatewayAuthenticationException || $e instanceof \App\Exceptions\SmsGatewayBadRequestException) {
            return false;
        }

        // Default: Maybe retry other generic exceptions a few times? Adjust as needed.
        return true;
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        // This method is called when the job has failed permanently (retries exhausted or fail() called)
        Log::critical('SendSmsViaGatewayJob: PERMANENTLY FAILED', [
            'job_message_id' => $this->messageId,
            'exception_type' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            // 'trace' => $exception->getTraceAsString() // Optional: include trace for critical failures
        ]);
        // Send notification to admin, etc.
    }
}