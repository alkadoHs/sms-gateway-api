<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>SMS Gateway for Android - Send SMS via API</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter+Tight:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">


    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
         body {
            font-family: "Inter Tight", sans-serif;
            font-optical-sizing: auto;
            font-weight: <weight>;
            font-style: normal;
        }
    </style>

    {{-- Favicon Placeholder --}}
    {{-- <link rel="icon" type="image/png" href="/path/to/favicon.png"> --}}

</head>
<body class="antialiased font-sans bg-gray-100 text-gray-800">

    {{-- Navigation (Optional Simple Version) --}}
    <nav class="bg-white shadow-md sticky top-0 z-50">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    {{-- Placeholder for Logo --}}
                    <svg class="h-8 w-auto text-cyan-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                    <span class="ml-3 text-xl font-bold text-gray-800">SMS Gateway</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="#" title="comming soon"  class="text-gray-600 hover:text-cyan-600 transition duration-150 ease-in-out">GitHub</a>
                    <a href="#" title="comming soon" class="text-gray-600 hover:text-cyan-600 transition duration-150 ease-in-out">Docs</a>
                    {{-- Add Download Button Here If You Have a Direct Link --}}
                    {{-- <a href="#download" class="px-4 py-2 bg-cyan-600 text-white rounded-md hover:bg-cyan-700 transition duration-150 ease-in-out">Download</a> --}}
                </div>
            </div>
        </div>
    </nav>

    {{-- Hero Section --}}
    <header class="bg-gradient-to-br from-gray-900 to-gray-800 text-white py-20 md:py-32">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl md:text-6xl font-bold mb-4 leading-tight">
                Turn Your Android Phone into an <span class="text-cyan-400">SMS Gateway</span>
            </h1>
            <p class="text-lg md:text-xl text-gray-300 mb-8 max-w-3xl mx-auto">
                Send and receive SMS messages programmatically using a simple API, leveraging the power of your existing Android device and SIM plan.
            </p>
            <div class="space-x-4">
                <a href="/apk"  class="inline-block bg-cyan-600 hover:bg-cyan-700 text-white font-semibold py-3 px-8 rounded-lg shadow-md transition duration-150 ease-in-out">
                    Download Latest APK
                </a>
                <a href="tel:+255764940382"  class="inline-block bg-gray-700 hover:bg-gray-600 text-white font-semibold py-3 px-8 rounded-lg shadow-md transition duration-150 ease-in-out">
                    Call me
                </a>
            </div>
        </div>
    </header>

    {{-- Introduction / What it is --}}
    <section class="py-16 bg-white">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 text-center">
             <h2 class="text-3xl font-bold text-gray-900 mb-6">How It Works</h2>
             <p class="text-lg text-gray-600 max-w-2xl mx-auto mb-10">
                This lightweight Android application listens for API requests (either locally on your device or via our cloud/your private server) and uses your phone's native capabilities to send or receive SMS messages. It's perfect for integrating SMS functionality into your projects.
             </p>
             {{-- Placeholder for a simple diagram or screenshot --}}
             {{-- <img src="/path/to/diagram.png" alt="SMS Gateway Flow Diagram" class="max-w-lg mx-auto rounded-lg shadow-lg"> --}}
        </div>
    </section>

    {{-- Features Section --}}
    <section class="py-16 bg-gray-50">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-center text-gray-900 mb-12">Powerful Features</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                {{-- Feature 1 --}}
                <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-200">
                    {{-- Icon Placeholder --}}
                    <div class="flex items-center justify-center h-12 w-12 rounded-full bg-cyan-100 text-cyan-600 mb-4">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" /></svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">API Controlled SMS</h3>
                    <p class="text-gray-600">Send and receive SMS messages programmatically via a simple HTTP API. Integrate easily with any language.</p>
                </div>
                {{-- Feature 2 --}}
                <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-200">
                     <div class="flex items-center justify-center h-12 w-12 rounded-full bg-cyan-100 text-cyan-600 mb-4">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Local & Cloud Modes</h3>
                    <p class="text-gray-600">Run a server directly on your device for local network access, or connect to the cloud/your private server for global reach.</p>
                </div>
                {{-- Feature 3 --}}
                <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-200">
                    <div class="flex items-center justify-center h-12 w-12 rounded-full bg-cyan-100 text-cyan-600 mb-4">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" /></svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Real-time Webhooks</h3>
                    <p class="text-gray-600">Get instant notifications for incoming SMS, delivery status updates, and more via configurable webhooks.</p>
                </div>
                {{-- Feature 4 --}}
                <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-200">
                    <div class="flex items-center justify-center h-12 w-12 rounded-full bg-cyan-100 text-cyan-600 mb-4">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">End-to-End Encryption</h3>
                    <p class="text-gray-600">Optionally encrypt message content and phone numbers between your application and the device for enhanced privacy.</p>
                </div>
                 {{-- Feature 5 --}}
                <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-200">
                     <div class="flex items-center justify-center h-12 w-12 rounded-full bg-cyan-100 text-cyan-600 mb-4">
                        {{-- Sim Card Icon --}}
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Multi-SIM & Device</h3>
                    <p class="text-gray-600">Supports devices with multiple SIM cards and allows connecting multiple devices to a single cloud account.</p>
                </div>
                 {{-- Feature 6 --}}
                <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-200">
                     <div class="flex items-center justify-center h-12 w-12 rounded-full bg-cyan-100 text-cyan-600 mb-4">
                         {{-- Status Icon --}}
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Status Tracking</h3>
                    <p class="text-gray-600">Check the delivery status (Pending, Sent, Delivered, Failed) of your messages via the API or webhooks.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Use Cases Section --}}
     <section class="py-16 bg-white">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
             <h2 class="text-3xl font-bold text-center text-gray-900 mb-12">Ideal For...</h2>
             <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 text-center">
                <div>
                    <div class="flex items-center justify-center h-16 w-16 rounded-full bg-cyan-100 text-cyan-600 mx-auto mb-4">
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                    </div>
                    <h4 class="text-lg font-semibold mb-2">2FA & Verification</h4>
                    <p class="text-gray-600">Secure logins and actions with SMS codes.</p>
                </div>
                <div>
                     <div class="flex items-center justify-center h-16 w-16 rounded-full bg-cyan-100 text-cyan-600 mx-auto mb-4">
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    </div>
                    <h4 class="text-lg font-semibold mb-2">Notifications</h4>
                    <p class="text-gray-600">Send alerts, order confirmations, and updates.</p>
                </div>
                 <div>
                     <div class="flex items-center justify-center h-16 w-16 rounded-full bg-cyan-100 text-cyan-600 mx-auto mb-4">
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    </div>
                    <h4 class="text-lg font-semibold mb-2">Reminders</h4>
                    <p class="text-gray-600">Remind users about appointments or events.</p>
                </div>
                 <div>
                     <div class="flex items-center justify-center h-16 w-16 rounded-full bg-cyan-100 text-cyan-600 mx-auto mb-4">
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z" /></svg>
                    </div>
                    <h4 class="text-lg font-semibold mb-2">User Feedback</h4>
                    <p class="text-gray-600">Collect feedback or run polls via SMS.</p>
                </div>
             </div>
        </div>
     </section>

    {{-- Getting Started Section --}}
    <section class="py-16 bg-gray-50">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-center text-gray-900 mb-12">Get Started Quickly</h2>
            <div class="flex flex-col md:flex-row justify-center items-center md:items-start gap-8 md:gap-12">
                {{-- Step 1 --}}
                <div class="text-center max-w-xs">
                    <div class="flex items-center justify-center h-16 w-16 rounded-full bg-cyan-600 text-white mx-auto mb-4 text-2xl font-bold">1</div>
                    <h3 class="text-xl font-semibold mb-2">Install the App</h3>
                    <p class="text-gray-600">Download the latest APK from GitHub Releases and install it on your Android device (Android 5.0+ required).</p>
                </div>
                 {{-- Step 2 --}}
                 <div class="text-center max-w-xs">
                    <div class="flex items-center justify-center h-16 w-16 rounded-full bg-cyan-600 text-white mx-auto mb-4 text-2xl font-bold">2</div>
                    <h3 class="text-xl font-semibold mb-2">Choose Your Mode</h3>
                    <p class="text-gray-600">Enable Local Server for local network use or Cloud Server (public or private) for wider accessibility.</p>
                </div>
                 {{-- Step 3 --}}
                 <div class="text-center max-w-xs">
                    <div class="flex items-center justify-center h-16 w-16 rounded-full bg-cyan-600 text-white mx-auto mb-4 text-2xl font-bold">3</div>
                    <h3 class="text-xl font-semibold mb-2">Integrate & Send</h3>
                    <p class="text-gray-600">Use the provided credentials and API endpoint with simple HTTP requests (like cURL or our <a href="https://docs.sms-gate.app/integration/cli/" class="text-cyan-600 hover:underline">CLI tool</a>) to send messages.</p>
                </div>
            </div>
        </div>
    </section>

     {{-- Call to Action / Docs --}}
    <section class="py-16 bg-cyan-700 text-white">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl font-bold mb-6">Ready to Integrate?</h2>
            <p class="text-lg text-cyan-100 mb-8 max-w-2xl mx-auto">
                Explore our comprehensive documentation for detailed API specifications, setup guides, encryption details, and more.
            </p>
            <a href="/docs"  class="inline-block bg-white hover:bg-gray-100 text-cyan-700 font-semibold py-3 px-8 rounded-lg shadow-md transition duration-150 ease-in-out">
                Read the Docs
            </a>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="py-8 bg-gray-800 text-gray-400 text-center">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <p>© {{ date('Y') }} . All rights reserved.</p>
            <p class="mt-2">Distributed under the <on href="https://kadolab.com"  class="text-cyan-500 hover:underline">Kadolab Technologies</on>.</p>
            <ny class="mt-2">Kadolab is a tech company which creates softwares.</ny>
        </div>
    </footer>

</body>
</html>