<?php

return [
    'modes' => [
        'platform_help',
        'skills_market_analysis',
        'opportunity_matching',
    ],

    'max_message_length' => (int) env('CHATBOT_MAX_MESSAGE_LENGTH', 2000),
    'conversations_per_page' => (int) env('CHATBOT_CONVERSATIONS_PER_PAGE', 20),
    'messages_per_page' => (int) env('CHATBOT_MESSAGES_PER_PAGE', 30),

    'knowledge_matching' => [
        'minimum_score' => (float) env('CHATBOT_KNOWLEDGE_MIN_SCORE', 0.62),
        'ambiguity_margin' => (float) env('CHATBOT_KNOWLEDGE_AMBIGUITY_MARGIN', 0.08),

        'ai_fallback_enabled' => filter_var(
            env('CHATBOT_KNOWLEDGE_AI_FALLBACK_ENABLED', true),
            FILTER_VALIDATE_BOOL,
        ),
    ],

    'intent_classification' => [
        'ai_fallback_enabled' => filter_var(
            env('CHATBOT_INTENT_AI_FALLBACK_ENABLED', true),
            FILTER_VALIDATE_BOOL,
        ),

        'task_type' => env('CHATBOT_INTENT_AI_TASK_TYPE', 'default'),
    ],

    'skills_market_analysis' => [
        'market_skill_limit' => (int) env('CHATBOT_MARKET_SKILL_LIMIT', 10),
    ],

    'response_formatter' => [
        'enabled' => filter_var(
            env('CHATBOT_RESPONSE_FORMATTER_ENABLED', false),
            FILTER_VALIDATE_BOOL,
        ),

        'modes' => [
            'skills_market_analysis',
            'opportunity_matching',
        ],

        'task_type' => env('CHATBOT_RESPONSE_FORMATTER_TASK_TYPE', 'default'),
        'max_output_length' => (int) env(
            'CHATBOT_RESPONSE_FORMATTER_MAX_LENGTH',
            2500,
        ),
    ],

    'opportunity_matching' => [
        'result_limit' => (int) env('CHATBOT_OPPORTUNITY_RESULT_LIMIT', 3),
    ],
];
