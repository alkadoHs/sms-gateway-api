<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SmsController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserSettingsController;


Route::get('/', function () {
    return view('welcome');
});

Route::get('dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/settings/sms-gateway', [UserSettingsController::class, 'editSmsGateway'])->name('settings.sms.edit');
    Route::post('/settings/sms-gateway', [UserSettingsController::class, 'updateSmsGateway'])->name('settings.sms.update');
});

Route::get('sms', [SmsController::class, 'index'])
    ->name('sms.index');

// Route to queue an SMS for sending (POST)
Route::post('/sms/send', [SmsController::class, 'sendSms'])
    ->name('sms.send');

// Route to check the status of a sent SMS (GET)
Route::get('/sms/status/{messageId}', [SmsController::class, 'getSmsStatus'])
    ->name('sms.status');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

include __DIR__ .'/auth.php';

