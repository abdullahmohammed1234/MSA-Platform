<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiMentorService
{
    public function __construct(
        protected AiMentorMockService $mockService
    ) {}

    public function getMentors(): array
    {
        return config('ai_mentors.mentors', []);
    }

    public function getChips(): array
    {
        return config('ai_mentors.chips', []);
    }

    public function findMentor(string $mentorId): ?array
    {
        return collect($this->getMentors())->firstWhere('id', $mentorId);
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function chat(string $mentorId, string $message, array $history = []): array
    {
        $mentor = $this->findMentor($mentorId);
        if (!$mentor) {
            throw new RuntimeException('Unknown mentor persona.');
        }

        $provider = config('ai_mentors.provider', 'mock');

        if ($provider === 'gemini' && config('ai_mentors.gemini.api_key')) {
            try {
                $text = $this->generateWithGemini($mentor, $message, $history);

                return [
                    'text' => $text,
                    'model' => config('ai_mentors.gemini.model'),
                    'provider' => 'gemini',
                ];
            } catch (\Throwable) {
                // Fall back to mock when Gemini is unavailable
            }
        }

        return [
            'text' => $this->mockService->generate($mentorId, $message),
            'model' => 'local-simulation-engine',
            'provider' => 'mock',
        ];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    protected function generateWithGemini(array $mentor, string $message, array $history): string
    {
        $apiKey = config('ai_mentors.gemini.api_key');
        $model = config('ai_mentors.gemini.model');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $contents = [];
        foreach ($history as $entry) {
            $role = ($entry['role'] ?? '') === 'assistant' ? 'model' : 'user';
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $entry['content'] ?? '']],
            ];
        }
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $message]],
        ];

        $response = Http::timeout(60)
            ->withQueryParameters(['key' => $apiKey])
            ->post($url, [
                'systemInstruction' => [
                    'parts' => [['text' => $mentor['system_instruction']]],
                ],
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 2048,
                ],
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('Gemini API request failed.');
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');
        if (!is_string($text) || trim($text) === '') {
            throw new RuntimeException('Gemini returned an empty response.');
        }

        return trim($text);
    }
}
