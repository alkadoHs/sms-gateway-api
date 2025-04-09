<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>SMS CLIENT</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>

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

    </body>
</html>