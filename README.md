Okay, let's break down the contents of the `kadolab-sms` files.

This project is the **Android application** component of the SMS Gateway system. It's the app that runs on an Android phone and does the actual work of sending and receiving SMS messages, interacting with the backend server or running its own local server.

Here's a breakdown based on the provided files:

1.  **Project Name:** `kadolab-sms` (the Android app itself).
2.  **Purpose:** To turn an Android device into an SMS gateway, allowing SMS messages to be sent and received programmatically via an API. It acts as the bridge between an API (either local on the device or a remote cloud server) and the device's native SMS capabilities.
3.  **Core Functionality:**
    *   **SMS Sending/Receiving:** Uses the Android SDK's `SmsManager` to send SMS messages requested via the API and receives incoming SMS messages using `BroadcastReceiver`.
    *   **API Interaction:**
        *   **Local Server Mode:** Runs an embedded HTTP server (using Ktor) directly on the device, allowing control via the local network.
        *   **Cloud Server Mode:** Connects to a remote backend server API (like the Go server previously analyzed) to register the device, fetch pending messages, update message statuses, and manage webhooks.
    *   **Message Management:**
        *   Queues outgoing messages locally using a Room database.
        *   Tracks the state of messages (Pending, Processed, Sent, Delivered, Failed).
        *   Handles multipart messages.
        *   Optionally requests delivery reports.
    *   **Webhooks:** Can send outgoing HTTP POST requests to user-configured URLs upon specific events (e.g., `sms:received`, `sms:sent`, `sms:delivered`, `sms:failed`, `system:ping`). Webhook configuration can be managed via the API. Includes retry logic and optional payload signing (HMAC-SHA256).
    *   **Encryption:** Supports decrypting message content and phone numbers received from the API if they were encrypted using a shared passphrase (configured in settings).
    *   **Push Notifications (Cloud Mode):** Uses Firebase Cloud Messaging (FCM) to receive notifications from the backend server, prompting the app to fetch new messages or perform other actions (like updating webhooks or exporting inbox).
    *   **Device Registration (Cloud Mode):** Handles registering the device with the cloud server, obtaining credentials and authentication tokens. Supports anonymous registration, registration with existing user credentials, or registration using a one-time code.
    *   **Persistence:** Uses Android Room database (`AppDatabase.kt`) to store message queues, message states, webhook configurations, and logs. Uses SharedPreferences for application settings.
    *   **User Interface:** Provides a basic Android UI (using Fragments, ViewPager2, RecyclerView, ViewModel, PreferenceFragmentCompat) for:
        *   Displaying connection status (local/cloud).
        *   Showing local/cloud server addresses and credentials.
        *   Toggling servers on/off.
        *   Viewing recent messages and their statuses.
        *   Viewing application logs.
        *   Configuring settings (server URLs, credentials, encryption passphrase, message limits, intervals, webhook settings, etc.).
    *   **Background Processing:** Uses Android WorkManager for reliable background task execution (sending messages, sending webhooks, pulling messages from the cloud server, truncating logs). Uses Foreground Services for critical ongoing tasks (like the local web server).
    *   **Permissions:** Requires various Android permissions (SMS, Phone State, Internet, etc.) to function correctly.
    *   **Health Monitoring:** Includes internal health checks (battery, connection, message stats) exposed via the local server's `/health` endpoint.
    *   **Autostart:** Can be configured to start automatically on device boot (`BootReceiver.kt`).
    *   **Multi-SIM Support:** Detects and allows specifying which SIM card to use for sending messages.

4.  **Technology Stack:**
    *   **Language:** Kotlin
    *   **Platform:** Android (minSdk 21 - Lollipop 5.0)
    *   **UI:** AndroidX libraries (AppCompat, Material, ConstraintLayout, ViewPager2, RecyclerView, Preference), ViewBinding.
    *   **Networking:** Ktor (Client for cloud mode, Server for local mode), OkHttp (underlying engine for Ktor client).
    *   **Database:** Android Room.
    *   **Dependency Injection:** Koin.
    *   **Concurrency:** Kotlin Coroutines.
    *   **Background Tasks:** Android WorkManager.
    *   **Push Notifications:** Firebase Cloud Messaging.
    *   **Build System:** Gradle.
    *   **Other Libraries:** libphonenumber (for phone number validation/formatting), Jnanoid (for ID generation).

5.  **Project Structure:**
    *   Standard Android project structure (`app/src/main`, `app/src/test`, `app/src/androidTest`, `app/res`).
    *   Code organized into modules (`modules/gateway`, `modules/localserver`, `modules/messages`, `modules/webhooks`, etc.) often containing specific services, settings, workers, events, and sometimes UI components.
    *   `data/` package contains Room database definitions (DAO, Entities, Migrations).
    *   `ui/` package contains Fragments, Adapters, Dialogs.
    *   `receivers/` and `services/` handle Android system interactions.
    *   `schemas/` contains historical Room database schema JSON files for migration validation.
    *   `docs/api/` contains Swagger UI files for the *backend* API documentation (likely for user reference).

6.  **License:** Apache License 2.0.

In summary, these files constitute the Android client application for the SMS Gateway system. It's responsible for interfacing with the device's SMS hardware, managing message queues locally, running an optional local API server, and communicating with a remote cloud server for extended functionality and multi-device support.