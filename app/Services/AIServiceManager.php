<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIServiceManager
{
    protected array $groqKeys;
    protected array $geminiKeys;

    public function __construct()
    {
        $this->groqKeys = config('kindo.groq_keys', []);
        $this->geminiKeys = config('kindo.gemini_keys', []);
    }

    protected const SYSTEM_PROMPT = <<<'PROMPT'
You are a licensed pediatric cognitive specialist, bilingual linguist, and behavioral therapist.
Analyze the provided child activity data (Urdu/English mistakes, sensory logs, and potty schedules).
Return ONLY a valid, raw JSON object matching this exact schema:
{
  "language_dominance": "urdu|english|balanced",
  "phonetic_blindspots": [{"issue": "string", "frequency": 0, "root_cause": "string"}],
  "potty_routine_summary": "string",
  "sensory_behavior_analysis": "string",
  "parent_tips": ["string", "string", "string"],
  "dietary_chaining_tips": ["string"],
  "custom_exercises": [{"title": "string", "instructions": "string", "target_skill": "string"}]
}
PROMPT;

    /**
     * Generates a pediatric diagnosis from combined learning/needs logs.
     * Tries the Groq pool first, then the Gemini pool. Returns null if every key fails.
     */
    public function generateDiagnosticReport(array $contextData): ?array
    {
        $cacheKey = 'ai_diag_' . md5(json_encode($contextData));
        if (($cached = Cache::get($cacheKey)) !== null) {
            return $cached;
        }

        $userPrompt = 'Child Data: ' . json_encode($contextData, JSON_UNESCAPED_UNICODE);

        foreach ($this->groqKeys as $idx => $key) {
            if ($this->isCoolingDown('groq', $idx)) {
                continue;
            }
            $json = $this->callGroq($idx, $key, $userPrompt);
            if ($json !== null) {
                $json['_provider'] = 'groq';
                Cache::put($cacheKey, $json, now()->addDays(3));
                return $json;
            }
        }

        foreach ($this->geminiKeys as $idx => $key) {
            if ($this->isCoolingDown('gemini', $idx)) {
                continue;
            }
            $json = $this->callGemini($idx, $key, $userPrompt);
            if ($json !== null) {
                $json['_provider'] = 'gemini';
                Cache::put($cacheKey, $json, now()->addDays(3));
                return $json;
            }
        }

        return null;
    }

    protected const IMPORT_PROMPT = <<<'PROMPT'
You help parents of young children turn a photo of a school syllabus, worksheet or homework page into a short practice lesson.
Read the page (Urdu, English or both). Return ONLY a raw JSON object:
{
  "title": "short lesson title",
  "tasks": [{"type": "trace_letter|spell_word|listen_repeat", "title": "string", "instruction": "string", "target": "the letter, word or phrase"}]
}
Rules: use trace_letter for single letters, spell_word for single words, listen_repeat for short phrases or sentences.
Copy the target exactly as written on the page. At most 12 tasks. Ignore names, phone numbers, addresses and anything that is not learning content.
If the page has no learning content, return {"title": "", "tasks": []}.
PROMPT;

    /**
     * Reads a photographed syllabus page and returns a rough lesson outline (title + tasks), or null if no provider could read it.
     * Same failover as the diagnostics: the Groq pool first, then Gemini. The photo is sent for this one request and never kept.
     */
    public function extractLessonFromImage(string $jpeg, string $language): ?array
    {
        $hint = $language === 'both' ? 'The page may mix Urdu and English.' : 'The lesson language is '.$language.'.';
        $b64 = base64_encode($jpeg);

        foreach ($this->groqKeys as $idx => $key) {
            if ($this->isCoolingDown('groq', $idx)) {
                continue;
            }
            try {
                $r = Http::withToken($key)->timeout(40)->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => config('kindo.groq_vision_model'),
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.1,
                    'messages' => [
                        ['role' => 'system', 'content' => self::IMPORT_PROMPT],
                        ['role' => 'user', 'content' => [
                            ['type' => 'text', 'text' => $hint],
                            ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,'.$b64]],
                        ]],
                    ],
                ]);
                if ($r->successful() && ($json = $this->decode($r->json('choices.0.message.content'))) !== null) {
                    return $json + ['_provider' => 'groq'];
                }
                if (! $r->successful()) {
                    $this->handleFailure('groq', $idx, $r->status(), 300);
                }
            } catch (\Throwable $e) {
                Log::warning("Groq account {$idx} unavailable for import: {$e->getMessage()}");
            }
        }

        foreach ($this->geminiKeys as $idx => $key) {
            if ($this->isCoolingDown('gemini', $idx)) {
                continue;
            }
            try {
                $model = config('kindo.gemini_model');
                $r = Http::withHeaders(['x-goog-api-key' => $key])->timeout(45)
                    ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                        'systemInstruction' => ['parts' => [['text' => self::IMPORT_PROMPT]]],
                        'contents' => [['role' => 'user', 'parts' => [['text' => $hint], ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => $b64]]]]],
                        'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.1],
                    ]);
                if ($r->successful() && ($json = $this->decode($r->json('candidates.0.content.parts.0.text'))) !== null) {
                    return $json + ['_provider' => 'gemini'];
                }
                if (! $r->successful()) {
                    $this->handleFailure('gemini', $idx, $r->status(), 600);
                }
            } catch (\Throwable $e) {
                Log::warning("Gemini account {$idx} unavailable for import: {$e->getMessage()}");
            }
        }

        return null;
    }

    protected function callGroq(int $idx, string $key, string $userPrompt): ?array
    {
        try {
            $response = Http::withToken($key)
                ->timeout(12)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => config('kindo.groq_model'),
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'temperature' => 0.2,
                ]);

            if ($response->successful()) {
                return $this->decode($response->json('choices.0.message.content'));
            }
            $this->handleFailure('groq', $idx, $response->status(), 300);
        } catch (\Throwable $e) {
            Log::warning("Groq account {$idx} unavailable: {$e->getMessage()}");
        }
        return null;
    }

    protected function callGemini(int $idx, string $key, string $userPrompt): ?array
    {
        try {
            $model = config('kindo.gemini_model');
            // Key goes in a header, not the URL, so it never lands in logs.
            $response = Http::withHeaders(['x-goog-api-key' => $key])
                ->timeout(15)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                    'systemInstruction' => ['parts' => [['text' => self::SYSTEM_PROMPT]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $userPrompt]]]],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'temperature' => 0.2,
                    ],
                ]);

            if ($response->successful()) {
                return $this->decode($response->json('candidates.0.content.parts.0.text'));
            }
            $this->handleFailure('gemini', $idx, $response->status(), 600);
        } catch (\Throwable $e) {
            Log::warning("Gemini account {$idx} unavailable: {$e->getMessage()}");
        }
        return null;
    }

    protected function decode(?string $raw): ?array
    {
        if ($raw === null) {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) && $decoded !== [] ? $decoded : null;
    }

    protected function handleFailure(string $provider, int $idx, int $status, int $cooldown): void
    {
        Log::warning("{$provider} account {$idx} returned HTTP {$status}");
        if ($status === 429) {
            $this->triggerCooldown($provider, $idx, $cooldown);
        }
    }

    protected function isCoolingDown(string $provider, int $index): bool
    {
        return Cache::has("cooldown_{$provider}_{$index}");
    }

    protected function triggerCooldown(string $provider, int $index, int $seconds): void
    {
        Cache::put("cooldown_{$provider}_{$index}", true, $seconds);
    }
}
