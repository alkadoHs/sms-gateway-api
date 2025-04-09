<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebhookController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// routes/api.php (Recommended)
Route::post('/webhooks/smsgateway/incoming-sms', [WebhookController::class, 'handleIncomingSms'])
    ->name('webhooks.smsgateway.incoming'); // Give it a name
