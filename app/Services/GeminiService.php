<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected $apiKey;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY');
        $this->baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';
    }

    public function analyzeMessage($message, $user)
    {
        $systemPrompt = "You are a WhatsApp Task Assistant for a Laravel AI system.
        Current User: {$user->name} (Role: {$user->role}, ID: {$user->id}).
        
        Analyze the user's message and return a JSON object ONLY. Do not include markdown formatting like ```json.
        
        Available Intents:
        1. assign_task: User wants to assign a task. ONLY Managers can assign tasks.
           Structure: { \"intent\": \"assign_task\", \"data\": { \"assignee_name\": \"John\", \"task\": \"Fix bug\", \"deadline\": \"2024-12-31 15:00\" }, \"response_text\": \"Task assigned...\" }
        2. view_tasks: User wants to see tasks.
           Structure: { \"intent\": \"view_tasks\", \"response_text\": \"Checking your tasks...\" }
        3. complete_task: User wants to mark a task as done.
           Structure: { \"intent\": \"complete_task\", \"data\": { \"task_id\": \"12345\" }, \"response_text\": \"Marking task as complete...\" }
        4. chat: General conversation or if user is unauthorized.
           Structure: { \"intent\": \"chat\", \"response_text\": \"Your reply here...\" }
        
        Rules:
        - If 'assign_task', try to extract assignee name, task description, and optional deadline.
        - If 'complete_task', extract the task ID/reference.
        - response_text should be a friendly WhatsApp message.
        - Output STRICT JSON only.
        ";

        try {
            $response = Http::post("{$this->baseUrl}?key={$this->apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $systemPrompt . "\n\nUser Message: " . $message]
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
                
                // Clean markdown if present
                $text = str_replace(['```json', '```'], '', $text);
                
                return json_decode($text, true);
            }

            Log::error('Gemini API Error: ' . $response->body());
            return ['intent' => 'chat', 'response_text' => 'Sorry, I am having trouble processing your request right now.'];

        } catch (\Exception $e) {
            Log::error('Gemini Service Exception: ' . $e->getMessage());
            return ['intent' => 'chat', 'response_text' => 'An error occurred while connecting to AI.'];
        }
    }
}
