<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chatbot Feature Flag
    |--------------------------------------------------------------------------
    |
    | Master switch to enable/disable chatbot functionality.
    | Set to false to completely disable chatbot without removing code.
    |
    */

    'enabled' => env('CHATBOT_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | OpenAI Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for OpenAI API integration.
    | Used for natural language understanding and response generation.
    |
    */

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION', null),
        'model' => env('OPENAI_MODEL', 'gpt-4-turbo-preview'),
        'max_tokens' => env('OPENAI_MAX_TOKENS', 500),
        'temperature' => env('OPENAI_TEMPERATURE', 0.7),
        'timeout' => env('OPENAI_TIMEOUT', 30),

        // Cost management
        'max_cost_per_salon_monthly' => env('OPENAI_MAX_COST_PER_SALON', 50.00),
        'alert_threshold_percentage' => env('OPENAI_ALERT_THRESHOLD', 80),
    ],

    /*
    |--------------------------------------------------------------------------
    | Meta (Facebook/Instagram) Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Meta Graph API integration.
    | Required for receiving and sending messages.
    |
    */

    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN'),
        'graph_api_version' => env('META_GRAPH_VERSION', 'v18.0'),
        'graph_api_url' => env('META_GRAPH_URL', 'https://graph.facebook.com'),

        // OAuth
        'oauth_redirect_uri' => env('META_OAUTH_REDIRECT_URI', null),

        // Required scopes
        'required_scopes' => [
            'pages_show_list',
            'pages_messaging',
            'pages_manage_metadata',
            'instagram_basic',
            'instagram_manage_messages',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversation Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for conversation management and state machine.
    |
    */

    'conversation' => [
        // How many recent messages to include in AI context
        'max_context_messages' => env('CHATBOT_MAX_CONTEXT_MESSAGES', 10),

        // Hours of inactivity before marking conversation as abandoned
        'abandon_after_hours' => env('CHATBOT_ABANDON_AFTER_HOURS', 24),

        // Maximum retry attempts for failed operations
        'max_retry_attempts' => env('CHATBOT_MAX_RETRY_ATTEMPTS', 3),

        // Confidence threshold for human escalation
        'low_confidence_threshold' => env('CHATBOT_LOW_CONFIDENCE_THRESHOLD', 0.5),

        // Auto-escalate to human after X failed attempts
        'auto_escalate_after_failures' => env('CHATBOT_AUTO_ESCALATE_FAILURES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limits for various operations to prevent abuse and manage costs.
    |
    */

    'rate_limits' => [
        // Messages per minute per salon
        'messages_per_minute' => env('CHATBOT_MESSAGES_PER_MINUTE', 60),

        // AI API calls per minute per salon
        'ai_calls_per_minute' => env('CHATBOT_AI_CALLS_PER_MINUTE', 30),

        // Maximum concurrent conversations per salon
        'max_concurrent_conversations' => env('CHATBOT_MAX_CONCURRENT_CONVERSATIONS', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Templates
    |--------------------------------------------------------------------------
    |
    | Fallback templates when AI is unavailable or for common scenarios.
    |
    */

    'templates' => [
        'greeting' => 'Zdravo! Dobrodošli u naš salon. Kako vam mogu pomoći?',
        'error' => 'Izvinite, trenutno imam tehničkih poteškoća. Molim vas kontaktirajte nas telefonom.',
        'outside_hours' => 'Hvala na poruci! Trenutno smo van radnog vremena. Odgovorićemo vam čim prije.',
        'human_takeover' => 'Povezujem vas sa našim timom. Molim vas sačekajte trenutak.',
        'booking_success' => 'Vaša rezervacija je uspješno kreirana! Poslali smo vam potvrdu.',
        'booking_failed' => 'Nažalost, nije moguće kreirati rezervaciju. Molim vas kontaktirajte nas direktno.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tone of Voice
    |--------------------------------------------------------------------------
    |
    | Settings for AI response generation tone and style.
    |
    */

    'tone' => [
        'style' => env('CHATBOT_TONE_STYLE', 'friendly_professional'),
        'language' => env('CHATBOT_LANGUAGE', 'sr_ijekavica'), // sr_ijekavica, sr_ekavica
        'formality' => env('CHATBOT_FORMALITY', 'informal'), // formal, informal
        'use_emojis' => env('CHATBOT_USE_EMOJIS', false),
        'max_response_length' => env('CHATBOT_MAX_RESPONSE_LENGTH', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for analytics aggregation and reporting.
    |
    */

    'analytics' => [
        // Automatically aggregate analytics daily
        'auto_aggregate' => env('CHATBOT_AUTO_AGGREGATE', true),

        // Time to run daily aggregation (24-hour format)
        'aggregation_time' => env('CHATBOT_AGGREGATION_TIME', '01:00'),

        // Days to keep detailed message logs
        'message_retention_days' => env('CHATBOT_MESSAGE_RETENTION_DAYS', 90),

        // Days to keep analytics data
        'analytics_retention_days' => env('CHATBOT_ANALYTICS_RETENTION_DAYS', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring & Alerts
    |--------------------------------------------------------------------------
    |
    | Configuration for monitoring and alerting.
    |
    */

    'monitoring' => [
        // Enable monitoring
        'enabled' => env('CHATBOT_MONITORING_ENABLED', true),

        // Alert email addresses
        'alert_emails' => explode(',', env('CHATBOT_ALERT_EMAILS', '')),

        // Alert thresholds
        'error_rate_threshold' => env('CHATBOT_ERROR_RATE_THRESHOLD', 0.1), // 10%
        'response_time_threshold' => env('CHATBOT_RESPONSE_TIME_THRESHOLD', 5000), // 5 seconds
        'low_confidence_threshold' => env('CHATBOT_LOW_CONFIDENCE_ALERT_THRESHOLD', 0.3), // 30% of messages
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Granular feature flags for gradual rollout.
    |
    */

    'features' => [
        'auto_booking' => env('CHATBOT_FEATURE_AUTO_BOOKING', false),
        'rich_media' => env('CHATBOT_FEATURE_RICH_MEDIA', false),
        'quick_replies' => env('CHATBOT_FEATURE_QUICK_REPLIES', false),
        'proactive_messages' => env('CHATBOT_FEATURE_PROACTIVE_MESSAGES', false),
        'sentiment_analysis' => env('CHATBOT_FEATURE_SENTIMENT_ANALYSIS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | Enable verbose logging for debugging.
    |
    */

    'debug' => env('CHATBOT_DEBUG', false),

];
