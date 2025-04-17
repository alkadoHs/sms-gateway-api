<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                   <p class="p-4 rounded bg-green-200 text-green-800 border border-green-700">
                       {{ session('token') }}
                   </p>
                
            <div class="max-w-4xl mx-auto">
            <h1 class="text-3xl font-bold underline text-center mt-10">
                SMS CLIENT
            </h1>

            <div class="mt-10">
                <form action="{{ route('sms.send') }}" method="post">
                    @csrf
                    <div class="grid p-2">
                        <label for="phone">Phone number</label>
                        <input type="text" name="phone" id="phone" class="border-2 border-gray-300 p-2 rounded-md" placeholder="Enter phone number" required>
                    </div>

                    <div class="grid p-2">
                        <label for="message">Message</label>
                        <textarea name="message" id="message" class="border-2 border-gray-300 p-2 rounded-md" placeholder="Enter message" required></textarea>
                    </div>
                    <div class="grid p-2">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Send
                        </button>
                    </div>
                </form>
            </div>

        </div>
            </div>
        </div>
    </div>
</x-app-layout>
