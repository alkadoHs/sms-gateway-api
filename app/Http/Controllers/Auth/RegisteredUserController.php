<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            // Initialize SMS gateway fields as null
            'sms_gateway_url' => null,
            'sms_gateway_username' => null,
            'sms_gateway_password_encrypted' => null,
        ]);

        event(new Registered($user));

        Auth::login($user);

        // --- Generate API Token ---
        // Create a token named 'api-token'. You can use different names for different token purposes.
        // IMPORTANT: $plainTextToken is shown ONLY ONCE. The user MUST save it.
        $plainTextToken = $user->createToken('api-token')->plainTextToken;

        return redirect(route('dashboard'))->with('token', $plainTextToken);
    }
}
