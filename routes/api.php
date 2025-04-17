<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\Api\SmsController as ApiSmsController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// Protect the API route with Sanctum middleware
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/sms/send', [ApiSmsController::class, 'send'])
        ->name('api.sms.send');
        
    Route::get('/sms/status/{messageId}', [ApiSmsController::class, 'getStatus'])
        ->name('api.sms.status');
});


// routes/api.php (Recommended)
Route::post('/webhooks/smsgateway/incoming-sms', [WebhookController::class, 'handleIncomingSms'])
    ->name('webhooks.smsgateway.incoming'); // Give it a name
