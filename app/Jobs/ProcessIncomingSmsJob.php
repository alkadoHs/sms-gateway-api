<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\IncomingSms; // Example: Create an Eloquent model

class ProcessIncomingSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $eventData;

    /**
     * Create a new job instance.
     *
     * @param array $eventData The 'payload' part of the webhook for sms:received
     */
    public function __construct(array $eventData)
    {
        $this->eventData = $eventData;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $sender = $this->eventData['phoneNumber'] ?? null;
        $message = $this->eventData['message'] ?? null;
        $receivedAt = $this->eventData['receivedAt'] ?? null; // Assuming it's an ISO 8601 string
        $simNumber = $this->eventData['simNumber'] ?? null; // Optional
        $messageId = $this->eventData['messageId'] ?? null; // Optional ID from gateway

        Log::info('Processing incoming SMS', [
            'sender' => $sender,
            'sim' => $simNumber,
            'received_at' => $receivedAt,
            'gateway_msg_id' => $messageId,
            // 'message' => $message // Uncomment if you need to log the message content
        ]);

        if (!$sender || !$message) {
            Log::error('ProcessIncomingSmsJob: Missing sender or message in event data.');
            return; // Or fail the job explicitly: $this->fail(...)
        }

        try {
            // --- YOUR BUSINESS LOGIC HERE ---
            // Example: Save to database
            IncomingSms::create([
                'sender' => $sender,
                'message' => $message,
                'received_at' => $receivedAt ? \Carbon\Carbon::parse($receivedAt) : now(),
                'sim_number' => $simNumber,
                'gateway_message_id' => $messageId,
                'raw_payload' => $this->eventData, // Store the full payload if needed
            ]);

            // Example: Send a notification, trigger another action, etc.
            // \App\Events\SmsReceived::dispatch($sender, $message);

            // --- END BUSINESS LOGIC ---

        } catch (\Exception $e) {
            Log::error('ProcessIncomingSmsJob: Failed to process SMS.', [
                'error' => $e->getMessage(),
                'sender' => $sender,
                'gateway_msg_id' => $messageId,
            ]);
            // Optionally re-throw or fail the job to trigger retries based on your queue setup
            // $this->fail($e);
        }
    }

    // /**
    //  * Handle a job failure.
    //  *
    //  * @param \Exception $exception
    //  */
    // public function failed(\Exception $exception): void
    // {
    //     Log::error('ProcessIncomingSmsJob: Job failed.', [
    //         'error' => $exception->getMessage(),
    //         'event_data' => $this->eventData,
    //     ]);
    //     // Optionally notify someone or take other actions on failure
    // }

    // /**
    //  * Optionally, you can define a retry method if you want to handle retries manually.
    //  *
    //  * @return int
    //  */
    // public function retryAfter(): int
    // {
    //     return 60; // Retry after 1 minute
    // }

    // public function failedAfter(): int
    // {
    //     return 3; // Number of attempts before failing permanently
    // }

    // /**
    //  * Optionally, you can define a delay method if you want to delay the job.
    //  *
    //  * @return int
    //  */
    // public function delay(): int
    // {
    //     return 5; // Delay for 5 seconds
    // }

}
