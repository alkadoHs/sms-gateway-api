<?php

use App\Http\Controllers\SmsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('sms', [SmsController::class, 'index'])
    ->name('sms.index');

// Route to queue an SMS for sending (POST)
Route::post('/sms/send', [SmsController::class, 'sendSms'])
    ->name('sms.send');

// Route to check the status of a sent SMS (GET)
Route::get('/sms/status/{messageId}', [SmsController::class, 'getSmsStatus'])
    ->name('sms.status');

