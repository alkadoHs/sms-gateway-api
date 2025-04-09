<?php

use App\Http\Controllers\SmsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('sms', [SmsController::class, 'index'])
    ->name('sms.index');

Route::post('send-sms', [SmsController::class, 'sendTestSms'])
    ->name('send.sms');

Route::get('/sms/status/{messageId}', [SmsController::class, 'getSmsStatus'])->name('sms.status');
