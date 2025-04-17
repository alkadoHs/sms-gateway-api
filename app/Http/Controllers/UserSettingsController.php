<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt; // Need Crypt for encryption
use Illuminate\Validation\Rule; // For validation rules

class UserSettingsController extends Controller
{
    public function editSmsGateway()
    {
        $user = Auth::user();
        return view('settings.sms_gateway', compact('user'));
    }

    public function updateSmsGateway(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'sms_gateway_url' => ['nullable', 'url', 'max:255', Rule::requiredIf(fn () => $request->filled('sms_gateway_username') || $request->filled('sms_gateway_password'))],
            'sms_gateway_username' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $request->filled('sms_gateway_url') || $request->filled('sms_gateway_password'))],
            'sms_gateway_password' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $request->filled('sms_gateway_url') || $request->filled('sms_gateway_username'))],
        ]);

        // Only update if all required fields are provided (or all are cleared)
        if ( !empty($validated['sms_gateway_url']) &&
             !empty($validated['sms_gateway_username']) &&
             $request->filled('sms_gateway_password') // Check original request for password presence
           )
        {
            $user->sms_gateway_url = $validated['sms_gateway_url'];
            $user->sms_gateway_username = $validated['sms_gateway_username'];
            // Use the mutator defined in the User model to automatically encrypt
            $user->sms_gateway_password = $request->input('sms_gateway_password');
        } else if (empty($validated['sms_gateway_url']) && empty($validated['sms_gateway_username']) && !$request->filled('sms_gateway_password')) {
            // Allow clearing all fields
             $user->sms_gateway_url = null;
             $user->sms_gateway_username = null;
             $user->sms_gateway_password = null; // Mutator handles setting encrypted field to null
        } else {
             // If trying to set partially, return an error
             return back()->withErrors(['config' => 'Please provide all SMS Gateway fields (URL, Username, Password) or leave them all blank.'])->withInput();
        }


        $user->save();

        return redirect()->route('settings.sms.edit')->with('success', 'SMS Gateway settings updated successfully!');
    }
}