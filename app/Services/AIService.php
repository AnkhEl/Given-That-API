<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    protected string $provider;
    protected string $apiKey;
    protected string $model;
    protected string $baseUrl;

    public function __construct()
    {
        $this->provider = config('services.ai.provider', 'gpt4all'); // 'anthropic', 'openai', or 'gpt4all'
        $this->apiKey = config('services.ai.api_key');

        if ($this->provider === 'anthropic') {
            $this->model = config('services.ai.anthropic_model', 'claude-sonnet-4-20250514');
            $this->baseUrl = 'https://api.anthropic.com/v1';
        } elseif ($this->provider === 'openai') {
            $this->model = config('services.ai.openai_model', 'gpt-4-turbo-preview');
            $this->baseUrl = 'https://api.openai.com/v1';
        } else {
            // GPT4All - Local
            $this->model = config('services.ai.gpt4all_model', 'mistral-7b-instruct-v0.2.Q4_0.gguf');
            $this->baseUrl = config('services.ai.gpt4all_url', 'http://localhost:4891');
            $this->apiKey = 'not-needed'; // GPT4All doesn't require API key
        }
    }

    /**
     * Check if AI is configured and available.
     */
    public function isAvailable(): bool
    {
        if ($this->provider === 'gpt4all') {
            // Check if GPT4All server is running
            try {
                $response = Http::timeout(5)->get("{$this->baseUrl}/v1/models");
                return $response->successful();
            } catch (\Exception $e) {
                Log::warning('GPT4All server not available', ['error' => $e->getMessage()]);
                return false;
            }
        }

        return !empty($this->apiKey) && $this->apiKey !== 'not-needed';
    }

    /**
     * Extract skills from text content.
     */
    public function extractSkills(string $content): array
    {
        if (!$this->isAvailable()) {
            Log::warning('AI service not configured or unavailable');
            return [
                'source_skills' => [],
                'target_skills' => [],
                'experience_level' => 'intermediate',
                'confidence' => 'low',
            ];
        }

        $prompt = $this->buildSkillExtractionPrompt($content);

        try {
            $response = $this->callAI($prompt);
            return $this->parseSkillResponse($response);
        } catch (\Exception $e) {
            Log::error('AI skill extraction failed', [
                'provider' => $this->provider,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate course outline from skills.
     */
    public function generateCourseOutline(array $context): array
    {
        if (!$this->isAvailable()) {
            Log::warning('AI service not configured or unavailable');
            return $this->getFallbackCourseOutline($context);
        }

        $prompt = $this->buildCourseGenerationPrompt($context);

        try {
            $response = $this->callAI($prompt);
            return $this->parseCourseResponse($response);
        } catch (\Exception $e) {
            Log::error('AI course generation failed', [
                'provider' => $this->provider,
                'error' => $e->getMessage(),
            ]);
            // Return fallback instead of failing
            return $this->getFallbackCourseOutline($context);
        }
    }

    /**
     * Call AI API.
     */
    protected function callAI(string $prompt, int $maxTokens = 4096): string
    {
        return match($this->provider) {
            'anthropic' => $this->callAnthropic($prompt, $maxTokens),
            'openai' => $this->callOpenAI($prompt, $maxTokens),
            'gpt4all' => $this->callGPT4All($prompt, $maxTokens),
            default => throw new \Exception("Unknown AI provider: {$this->provider}"),
        };
    }

    /**
     * Call Anthropic Claude API.
     */
    protected function callAnthropic(string $prompt, int $maxTokens): string
    {
        $response = Http::timeout(60)
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
            ->post("{$this->baseUrl}/messages", [
                'model' => $this->model,
                'max_tokens' => $maxTokens,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new \Exception('Anthropic API request failed: ' . $response->body());
        }

        $data = $response->json();
        return $data['content'][0]['text'] ?? '';
    }

    /**
     * Call OpenAI API.
     */
    protected function callOpenAI(string $prompt, int $maxTokens): string
    {
        $response = Http::timeout(60)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'max_tokens' => $maxTokens,
            ]);

        if ($response->failed()) {
            throw new \Exception('OpenAI API request failed: ' . $response->body());
        }

        $data = $response->json();
        return $data['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Call GPT4All Local API.
     */
    protected function callGPT4All(string $prompt, int $maxTokens): string
    {
        try {
            $response = Http::timeout(120) // GPT4All can be slower
            ->post("{$this->baseUrl}/v1/chat/completions", [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'max_tokens' => $maxTokens,
                'temperature' => 0.7,
            ]);

            if ($response->failed()) {
                throw new \Exception('GPT4All API request failed: ' . $response->body());
            }

            $data = $response->json();
            return $data['choices'][0]['message']['content'] ?? '';

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new \Exception('Cannot connect to GPT4All. Make sure GPT4All app is running with API enabled on port 4891.');
        }
    }

    /**
     * Build skill extraction prompt.
     */
    protected function buildSkillExtractionPrompt(string $content): string
    {
        return <<<PROMPT
You are an expert at analyzing training requirements and extracting technical skills.

Analyze the following content and extract:
1. SOURCE SKILLS: Technologies/frameworks the team currently knows
2. TARGET SKILLS: Technologies/frameworks they want to learn
3. EXPERIENCE LEVEL: Their current experience level (beginner/intermediate/advanced)
4. TEAM SIZE: Number of people (if mentioned)

Content:
---
{$content}
---

IMPORTANT: Respond ONLY with a valid JSON object. No markdown, no explanations, just JSON:

{
  "source_skills": ["skill1", "skill2"],
  "target_skills": ["skill1", "skill2"],
  "experience_level": "intermediate",
  "team_size": 5,
  "confidence": "high"
}

JSON Response:
PROMPT;
    }

    /**
     * Build course generation prompt.
     */
    protected function buildCourseGenerationPrompt(array $context): string
    {
        $sourceSkills = implode(', ', $context['source_skills'] ?? []);
        $targetSkills = implode(', ', $context['target_skills'] ?? []);
        $experienceLevel = $context['experience_level'] ?? 'intermediate';
        $timelineWeeks = $context['timeline_weeks'] ?? 12;
        $specialRequirements = $context['special_requirements'] ?? '';

        return <<<PROMPT
You are an expert curriculum designer for technical training programs.

Create a detailed course outline for transitioning from {$sourceSkills} to {$targetSkills}.

Context:
- Current Skills: {$sourceSkills}
- Target Skills: {$targetSkills}
- Experience Level: {$experienceLevel}
- Timeline: {$timelineWeeks} weeks
- Special Requirements: {$specialRequirements}

Create a curriculum with 8-12 modules that:
1. Leverages their existing knowledge of {$sourceSkills}
2. Draws explicit comparisons between known and new concepts
3. Progressively builds from familiar to new
4. Includes practical exercises

IMPORTANT: Respond ONLY with valid JSON. No markdown, no explanations:

{
  "course": {
    "title": "Transitioning from X to Y",
    "description": "Learn Y by leveraging your X knowledge",
    "difficulty_level": "intermediate",
    "estimated_hours": 80,
    "modules": [
      {
        "title": "Module Title",
        "order_index": 1,
        "description": "What you'll learn",
        "comparison_points": [
          {
            "source_concept": "Concept in X",
            "target_concept": "Concept in Y",
            "explanation": "How they relate"
          }
        ],
        "key_topics": ["topic1", "topic2"],
        "estimated_minutes": 120
      }
    ]
  }
}

JSON Response:
PROMPT;
    }

    /**
     * Parse AI skill extraction response.
     */
    protected function parseSkillResponse(string $response): array
    {
        // Remove Markdown code blocks if present
        $response = preg_replace('/```json\n?/', '', $response);
        $response = preg_replace('/\n?```/', '', $response);
        $response = trim($response);

        // Clean any leading text before JSON
        $response = $this->cleanAIJsonResponse($response);

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {

            Log::warning('Initial JSON parse failed, attempting to normalize quotes');
            $data = json_decode($this->normalizeSingleQuotes($response), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to parse AI response as JSON', [
                    'response' => $response,
                    'error' => json_last_error_msg(),
                ]);
                throw new \Exception('Failed to parse AI response as JSON: ' . json_last_error_msg());
            }
        }

        return [
            'source_skills' => $data['source_skills'] ?? [],
            'target_skills' => $data['target_skills'] ?? [],
            'experience_level' => $data['experience_level'] ?? 'intermediate',
            'team_size' => $data['team_size'] ?? null,
            'confidence' => $data['confidence'] ?? 'medium',
        ];
    }

    /**
     * Parse AI course generation response.
     */
    protected function parseCourseResponse(string $response): array
    {
        // Remove Markdown code blocks if present
        $response = preg_replace('/```json\n?/', '', $response);
        $response = preg_replace('/\n?```/', '', $response);
        $response = trim($response);

        // Clean any leading text before JSON
        $response = $this->cleanAIJsonResponse($response);

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Initial JSON parse failed, attempting to normalize quotes', [
                'error' => json_last_error_msg(),
            ]);

            $normalizedResponse = $this->normalizeSingleQuotes($response);
            $data = json_decode($normalizedResponse, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to parse AI course response as JSON', [
                    'response' => substr($response, 0, 500),
                    'error' => json_last_error_msg(),
                ]);
                throw new \Exception('Failed to parse AI course response as JSON: ' . json_last_error_msg());
            }
        }

        return $data['course'] ?? $data;
    }

    /**
     * Get fallback course outline when AI is unavailable.
     */
    protected function getFallbackCourseOutline(array $context): array
    {
        $sourceSkill = $context['source_skills'][0] ?? 'Current Technology';
        $targetSkill = $context['target_skills'][0] ?? 'New Technology';

        return [
            'title' => "Transitioning from {$sourceSkill} to {$targetSkill}",
            'description' => "Learn {$targetSkill} by leveraging your {$sourceSkill} knowledge",
            'difficulty_level' => $context['experience_level'] ?? 'intermediate',
            'estimated_hours' => 80,
            'modules' => [
                [
                    'title' => 'Getting Started',
                    'order_index' => 1,
                    'description' => 'Introduction and setup',
                    'comparison_points' => [],
                    'key_topics' => ['Installation', 'Basic concepts'],
                    'estimated_minutes' => 120,
                ],
                [
                    'title' => 'Core Concepts',
                    'order_index' => 2,
                    'description' => 'Understanding the fundamentals',
                    'comparison_points' => [],
                    'key_topics' => ['Architecture', 'Key features'],
                    'estimated_minutes' => 180,
                ],
                [
                    'title' => 'Advanced Topics',
                    'order_index' => 3,
                    'description' => 'Deep dive into advanced features',
                    'comparison_points' => [],
                    'key_topics' => ['Best practices', 'Optimization'],
                    'estimated_minutes' => 240,
                ],
            ],
        ];
    }

    /**
     * Clean AI JSON response by removing any text before the first JSON object or array and any
     * text after the last JSON object.
     *
     * @param string $response
     * @return string
     */
    public function cleanAIJsonResponse(string $response): string
    {
        // Find first curly brace or square bracket
        $firstBracePos = min(
            $this->strposOrLength($response, '{'),
            $this->strposOrLength($response, '[')
        );

        // Strip any text before the first brace/bracket
        if ($firstBracePos !== false && $firstBracePos > 0) {
            $response = substr($response, $firstBracePos);
        }

        // Remove any text after the last closing } or ]
        $lastCurly = strrpos($response, '}');
        $lastSquare = strrpos($response, ']');

        $lastPos = max($lastCurly, $lastSquare);
        if ($lastPos !== false) {
            $response = substr($response, 0, $lastPos + 1);
        }

        return $response;
    }


    /**
     * Helper: return strpos or a large number if not found (for min comparison)
     */
    protected function strposOrLength(string $haystack, string $needle): int
    {
        $pos = strpos($haystack, $needle);
        return $pos === false ? strlen($haystack) : $pos;
    }


    /**
     * Normalize single quotes to double quotes in JSON-like strings
     */
    protected function normalizeSingleQuotes(string $json): string
    {
        // Replace single quotes with double quotes, but only in specific contexts
        // Pattern: 'key': or 'value' (property names and string values)

        // Fix property names: 'order_index': -> "order_index":
        $json = preg_replace("/'([^']+)'(\s*:)/", '"$1"$2', $json);

        // Fix string values: : 'value' -> : "value"
        $json = preg_replace("/(:\s*)'([^']*?)'/", '$1"$2"', $json);

        return $json;
    }
}
