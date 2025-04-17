<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // <-- Add this
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable; // <-- Add HasApiTokens here

    // ... (rest of your User model from the previous step, including fillable, hidden, casts, smsGatewayPassword accessor/mutator, hasSmsGatewayConfigured)
    // Make sure sms_gateway_url, sms_gateway_username, sms_gateway_password_encrypted are in $fillable

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        // Add the SMS gateway fields
        'sms_gateway_url',
        'sms_gateway_username',
        'sms_gateway_password_encrypted',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'sms_gateway_password_encrypted', // Hide the encrypted password
    ];

     /**
      * The attributes that should be cast.
      *
      * @var array<string, string>
      */
     protected $casts = [
         'email_verified_at' => 'datetime',
         'password' => 'hashed', // Use Laravel 10+'s built-in password caster
     ];


     /**
      * Interact with the user's SMS gateway password.
      */
     protected function smsGatewayPassword(): Attribute
     {
         return Attribute::make(
             get: function ($value, $attributes) {
                 if (isset($attributes['sms_gateway_password_encrypted'])) {
                     try {
                         return Crypt::decryptString($attributes['sms_gateway_password_encrypted']);
                     } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                         Log::error('Failed to decrypt SMS Gateway password for user: ' . $this->id);
                         return null;
                     }
                 }
                 return null;
             },
             set: function ($value) {
                 return [
                     'sms_gateway_password_encrypted' => $value ? Crypt::encryptString($value) : null
                 ];
             }
         );
     }

     /**
      * Helper to check if gateway settings are configured.
      */
     public function hasSmsGatewayConfigured(): bool
     {
         return !empty($this->sms_gateway_url) &&
                !empty($this->sms_gateway_username) &&
                !empty($this->sms_gateway_password_encrypted);
     }
}