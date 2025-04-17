{{-- resources/views/settings/sms_gateway.blade.php --}}
<x-app-layout> {{-- Assuming you have a layout --}}
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('SMS Gateway Settings') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">

                    @if (session('success'))
                        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                            <span class="block sm:inline">{{ session('success') }}</span>
                        </div>
                    @endif

                     @if ($errors->any())
                        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif


                    <form method="POST" action="{{ route('settings.sms.update') }}">
                        @csrf

                        <p class="text-sm text-gray-600 mb-4">
                            Configure your connection to the SMS Gateway (either the cloud service or your local device). Leave all fields blank to disable.
                        </p>

                        <!-- SMS Gateway URL -->
                        <div class="mb-4">
                            <x-input-label for="sms_gateway_url" :value="__('Gateway Base URL')" />
                            <x-text-input id="sms_gateway_url" class="block mt-1 w-full" type="url" name="sms_gateway_url" :value="old('sms_gateway_url', $user->sms_gateway_url)" placeholder="e.g., https://api.sms-gate.app/3rdparty/v1 or http://192.168.1.X:8080" />
                            <x-input-error :messages="$errors->get('sms_gateway_url')" class="mt-2" />
                        </div>

                        <!-- SMS Gateway Username -->
                        <div class="mb-4">
                            <x-input-label for="sms_gateway_username" :value="__('Gateway Username')" />
                            <x-text-input id="sms_gateway_username" class="block mt-1 w-full" type="text" name="sms_gateway_username" :value="old('sms_gateway_username', $user->sms_gateway_username)" />
                             <x-input-error :messages="$errors->get('sms_gateway_username')" class="mt-2" />
                        </div>

                        <!-- SMS Gateway Password -->
                        <div class="mb-4">
                            <x-input-label for="sms_gateway_password" :value="__('Gateway Password')" />
                             <x-text-input id="sms_gateway_password" class="block mt-1 w-full" type="password" name="sms_gateway_password" placeholder="Enter new password or leave blank to keep current" />
                             <p class="mt-1 text-xs text-gray-500">Leave blank to keep the existing password (if set).</p>
                             <x-input-error :messages="$errors->get('sms_gateway_password')" class="mt-2" />
                        </div>


                        <div class="flex items-center justify-end mt-4">
                            <x-primary-button>
                                {{ __('Save Settings') }}
                            </x-primary-button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>