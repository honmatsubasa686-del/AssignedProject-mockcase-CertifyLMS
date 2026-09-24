<?php

declare(strict_types=1);

return [
    'enabled' => env('AI_CHAT_ENABLED', false),

    'daily_limit' => (int) env('AI_CHAT_DAILY_LIMIT', 50),

    'history_limit' => (int) env('AI_CHAT_HISTORY_LIMIT', 20),

    'auto_title_enabled' => env('AI_CHAT_AUTO_TITLE_ENABLED', true),

    'system_prompt' => env(
        'AI_CHAT_SYSTEM_PROMPT',
        'あなたは資格学習を支援するAIアシスタントです。'
        .'受講生の質問に対して、わかりやすく簡潔に回答してください。'
        .'不確かな内容は断定せず、AIの回答は参考情報として扱ってください。'
    ),

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash'),
    ],
];
