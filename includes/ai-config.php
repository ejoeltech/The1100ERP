<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }
/**
 * AI Provider Configuration - Multi-Provider Support
 * Supports Groq, OpenRouter, Mistral, Together, DeepSeek, Gemini, Custom
 */

if (!function_exists('getSetting')) {
    require_once __DIR__ . '/../config.php';
}

// Provider registry - free & cheap providers prioritizing free tier
function getAiProviders() {
    return [
        'groq' => [
            'name' => 'Groq (Free & Fast)',
            'label' => 'Groq',
            'base_url' => 'https://api.groq.com/openai/v1/chat/completions',
            'docs' => 'https://console.groq.com/keys',
            'free_note' => 'Free 14k req/day - updated Apr 2026',
            'models' => [
                'groq/compound-mini' => 'Groq Compound Mini (Free, Recommended)',
                'groq/compound' => 'Groq Compound',
                'openai/gpt-oss-20b' => 'GPT-OSS 20B',
                'openai/gpt-oss-120b' => 'GPT-OSS 120B',
                'qwen/qwen3.8-27b' => 'Qwen 3 27B',
                'allam-2-7b' => 'ALLaM 2 7B',
                'meta-llama/llama-prompt-guard-2-86m' => 'Prompt Guard 86M (guard)',
            ],
            'default_model' => 'groq/compound-mini',
        ],
        'openrouter' => [
            'name' => 'OpenRouter (Free Models)',
            'label' => 'OpenRouter',
            'base_url' => 'https://openrouter.ai/api/v1/chat/completions',
            'docs' => 'https://openrouter.ai/keys',
            'free_note' => 'Free: llama/gemma/mistral :free suffix',
            'models' => [
                'meta-llama/llama-3.1-8b-instruct:free' => 'Llama 3.1 8B :free',
                'google/gemma-2-9b-it:free' => 'Gemma 2 9B :free',
                'mistralai/mistral-7b-instruct:free' => 'Mistral 7B :free',
                'qwen/qwen-2-7b-instruct:free' => 'Qwen 2 7B :free',
            ],
            'default_model' => 'meta-llama/llama-3.1-8b-instruct:free',
        ],
        'mistral' => [
            'name' => 'Mistral AI (Free Tier)',
            'label' => 'Mistral',
            'base_url' => 'https://api.mistral.ai/v1/chat/completions',
            'docs' => 'https://console.mistral.ai/api-keys',
            'free_note' => 'Free $ trial + cheap',
            'models' => [
                'mistral-small-latest' => 'Mistral Small (Free)',
                'mistral-tiny' => 'Mistral Tiny',
                'open-mistral-7b' => 'Open Mistral 7B',
            ],
            'default_model' => 'mistral-small-latest',
        ],
        'together' => [
            'name' => 'Together AI (Free)',
            'label' => 'Together',
            'base_url' => 'https://api.together.xyz/v1/chat/completions',
            'docs' => 'https://api.together.ai/settings/api-keys',
            'free_note' => 'Free $25 credit',
            'models' => [
                'meta-llama/Meta-Llama-3.1-8B-Instruct-Turbo' => 'Llama 3.1 8B Turbo',
                'mistralai/Mixtral-8x7B-Instruct-v0.1' => 'Mixtral 8x7B',
            ],
            'default_model' => 'meta-llama/Meta-Llama-3.1-8B-Instruct-Turbo',
        ],
        'gemini' => [
            'name' => 'Google Gemini (Free)',
            'label' => 'Gemini',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent',
            'docs' => 'https://aistudio.google.com/app/apikey',
            'free_note' => 'Free 60 req/min',
            'models' => [
                'gemini-1.5-flash' => 'Gemini 1.5 Flash (Free, Recommended)',
                'gemini-1.5-pro' => 'Gemini 1.5 Pro',
                'gemini-2.0-flash-exp' => 'Gemini 2.0 Flash Exp',
            ],
            'default_model' => 'gemini-1.5-flash',
            'is_gemini' => true,
        ],
        'deepseek' => [
            'name' => 'DeepSeek (Cheap)',
            'label' => 'DeepSeek',
            'base_url' => 'https://api.deepseek.com/chat/completions',
            'docs' => 'https://platform.deepseek.com/api_keys',
            'free_note' => '$1.40/M tokens',
            'models' => [
                'deepseek-chat' => 'DeepSeek Chat V3',
                'deepseek-reasoner' => 'DeepSeek Reasoner',
            ],
            'default_model' => 'deepseek-chat',
        ],
        'custom' => [
            'name' => 'Custom (OpenAI-Compatible)',
            'label' => 'Custom',
            'base_url' => '',
            'docs' => '',
            'free_note' => 'Any OpenAI API',
            'models' => [],
            'default_model' => 'gpt-3.5-turbo',
        ],
    ];
}

function getAiProviderConfig($providerId = null) {
    if ($providerId === null) {
        $providerId = getSetting('ai_provider', 'groq');
    }
    $providers = getAiProviders();
    if (!isset($providers[$providerId])) $providerId = 'groq';
    $provider = $providers[$providerId];
    $provider['id'] = $providerId;

    // Resolve credentials: prefer new ai_* keys, fallback to legacy groq_api_key
    $apiKey = getSetting('ai_api_key', '');
    if (empty($apiKey)) $apiKey = getSetting('groq_api_key', '');
    // Provider-specific override for custom use
    if ($providerId === 'custom') {
        $apiKey = getSetting('ai_custom_api_key', $apiKey);
    }
    // Allow env override
    $envKey = getenv('GROQ_API_KEY');
    if (!empty($envKey) && $providerId === 'groq' && empty($apiKey)) $apiKey = $envKey;

    $model = getSetting('ai_model', '');
    $deprecated = ['llama-3.1-8b-instant','llama-3.3-70b-versatile','mixtral-8x7b-32768','gemma2-9b-it'];
    if (empty($model) || in_array($model, $deprecated, true)) $model = $provider['default_model'];

    $baseUrl = getSetting('ai_base_url', '');
    if (empty($baseUrl)) $baseUrl = $provider['base_url'];
    // Custom provider must have user-supplied base_url
    if ($providerId === 'custom' && !empty(getSetting('ai_custom_base_url', ''))) {
        $baseUrl = getSetting('ai_custom_base_url', '');
        $model = getSetting('ai_custom_model', $model);
    }

    return [
        'provider' => $providerId,
        'provider_name' => $provider['name'],
        'api_key' => $apiKey,
        'model' => $model,
        'base_url' => $baseUrl,
        'provider_config' => $provider,
    ];
}

/**
 * Generic AI call - OpenAI compatible + Gemini special case
 */
function callAiAPI($prompt, $systemPrompt = '', $options = [], $override = []) {
    $cfg = getAiProviderConfig($override['provider'] ?? null);
    $apiKey = $override['api_key'] ?? $cfg['api_key'];
    $model = $override['model'] ?? $cfg['model'];
    $baseUrl = $override['base_url'] ?? $cfg['base_url'];

    if (empty($apiKey)) {
        throw new Exception('AI API key not configured. Set it in Settings > API Integrations.');
    }
    if (empty($baseUrl)) {
        throw new Exception('AI Base URL not configured for provider: ' . $cfg['provider']);
    }

    // Gemini has different API shape
    if ($cfg['provider'] === 'gemini' || ($cfg['provider_config']['is_gemini'] ?? false)) {
        return callGeminiAPI($prompt, $systemPrompt, $apiKey, $model, $baseUrl, $options);
    }

    $messages = [];
    if (!empty($systemPrompt)) $messages[] = ['role' => 'system', 'content' => $systemPrompt];
    $messages[] = ['role' => 'user', 'content' => $prompt];

    $data = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => $options['temperature'] ?? 0.7,
        'max_tokens' => $options['max_tokens'] ?? 2048,
        'top_p' => $options['top_p'] ?? 1,
    ];
    if (isset($options['response_format'])) $data['response_format'] = $options['response_format'];

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ];
    // OpenRouter requires extra headers
    if ($cfg['provider'] === 'openrouter') {
        $headers[] = 'HTTP-Referer: ' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $headers[] = 'X-Title: 1100ERP';
    }

    $ch = curl_init($baseUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, $options['timeout'] ?? 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) throw new Exception("API connection error: {$curlError}");
    if ($httpCode !== 200) {
        $err = json_decode($response, true);
        $msg = $err['error']['message'] ?? $err['error']['msg'] ?? $err['message'] ?? "HTTP {$httpCode}";
        // Common: model not found -> suggest fallback
        if (stripos($msg, 'model') !== false && stripos($msg, 'does not exist') !== false) {
            $msg .= " | Tip: Try model '{$cfg['provider_config']['default_model']}' for {$cfg['provider']}.";
        }
        throw new Exception("{$cfg['provider_name']} error: {$msg}");
    }
    $result = json_decode($response, true);
    if (!isset($result['choices'][0]['message']['content'])) {
        // Try Together/Mistral alternate path
        if (isset($result['choices'][0]['text'])) return trim($result['choices'][0]['text']);
        throw new Exception('Invalid API response format');
    }
    return trim($result['choices'][0]['message']['content']);
}

function callGeminiAPI($prompt, $systemPrompt, $apiKey, $model, $baseUrl, $options) {
    $fullPrompt = $systemPrompt ? $systemPrompt . "\n\nUser: " . $prompt : $prompt;
    // Replace {model} placeholder
    $url = str_replace('{model}', $model, $baseUrl);
    // Gemini expects ?key=API_KEY
    $url .= (strpos($url, '?') === false ? '?' : '&') . 'key=' . $apiKey;

    $data = [
        'contents' => [['parts' => [['text' => $fullPrompt]]]],
        'generationConfig' => [
            'temperature' => $options['temperature'] ?? 0.7,
            'maxOutputTokens' => $options['max_tokens'] ?? 2048,
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, $options['timeout'] ?? 30);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) throw new Exception("Gemini connection error: {$curlError}");
    if ($httpCode !== 200) {
        $err = json_decode($response, true);
        $msg = $err['error']['message'] ?? "HTTP {$httpCode}";
        throw new Exception("Gemini error: {$msg}");
    }
    $result = json_decode($response, true);
    $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if ($text === null) throw new Exception('Invalid Gemini response format');
    return trim($text);
}
