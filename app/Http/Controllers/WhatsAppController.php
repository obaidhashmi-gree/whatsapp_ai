<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\GeminiService;
use App\Services\WhatsAppService;
use App\Models\User;
use App\Models\Task;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppController extends Controller
{
    protected $geminiService;
    protected $whatsAppService;

    public function __construct(GeminiService $gemini, WhatsAppService $whatsApp)
    {
        $this->geminiService = $gemini;
        $this->whatsAppService = $whatsApp;
    }

    /**
     * Verify Webhook (GET)
     */
    public function verifyWebhook(Request $request)
    {
        $mode = $request->query('hub.mode');
        $token = $request->query('hub.verify_token');
        $challenge = $request->query('hub.challenge');

        if ($mode === 'subscribe' && $token === 'laravel-whatsapp-webhook') {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }
    // public function verifyWebhook(Request $request)
    // {
    //     return response()->json([
    //         'mode' => $request->query('hub.mode'),
    //         'token' => $request->query('hub.verify_token'),
    //         'challenge' => $request->query('hub.challenge'),
    //     ]);
    // }
    /**
     * Receive Message (POST)
     */
    public function receiveMessage(Request $request)
    {
        $payload = $request->all();
        // Log::info('Webhook Payload: ' . json_encode($payload));

        if (empty($payload['entry'][0]['changes'][0]['value']['messages'][0])) {
            return response('No message found', 200);
        }

        $messageData = $payload['entry'][0]['changes'][0]['value']['messages'][0];
        $phoneNumber = $messageData['from'];
        $messageText = $messageData['text']['body'] ?? '';

        if (empty($messageText)) {
            return response('Empty message', 200);
        }

        // 1. Find or Create User
        $user = User::firstOrCreate(
            ['phone' => $phoneNumber],
            ['name' => 'WhatsApp User', 'password' => Str::random(16)]
        );

        // 2. Analyze Intent with Gemini
        $aiResponse = $this->geminiService->analyzeMessage($messageText, $user);
        $intent = $aiResponse['intent'] ?? 'chat';
        $data = $aiResponse['data'] ?? [];
        $replyText = $aiResponse['response_text'] ?? 'I did not understand that.';

        // 3. Execute Logic based on Intent
        switch ($intent) {
            case 'assign_task':
                $replyText = $this->handleAssignTask($user, $data, $replyText);
                break;
            case 'view_tasks':
                $replyText = $this->handleViewTasks($user);
                break;
            case 'complete_task':
                $replyText = $this->handleCompleteTask($user, $data);
                break;
        }

        // 4. Send Reply
        $this->whatsAppService->sendMessage($phoneNumber, $replyText);

        return response('Processed', 200);
    }

    private function handleAssignTask($user, $data, $defaultReply)
    {
        if ($user->role !== 'manager') {
            return "⛔ Only managers can assign tasks. You are registered as an 'employee'.";
        }

        if (empty($data['assignee_name']) || empty($data['task'])) {
            return "Could you please specify who to assign the task to and what the task is? E.g., 'Assign John to fix the bug'";
        }

        // Simple name matching (In production, use ID or exact match)
        $assignee = User::where('name', 'like', "%{$data['assignee_name']}%")->first();

        if (!$assignee) {
            return "⚠️ User '{$data['assignee_name']}' not found in the system.";
        }

        Task::create([
            'task_id' => now()->timestamp,
            'assigned_by' => $user->id,
            'assigned_to' => $assignee->id,
            'task' => $data['task'],
            'deadline' => $data['deadline'] ?? null,
            'status' => 'Pending'
        ]);

        // Notify Assignee
        $this->whatsAppService->sendMessage($assignee->phone, "🆕 New Task Assigned by {$user->name}: \n{$data['task']}");

        return $defaultReply;
    }

    private function handleViewTasks($user)
    {
        if ($user->role === 'manager') {
            $tasks = Task::where('assigned_by', $user->id)
                ->where('status', 'Pending')
                ->latest()
                ->get();
            $title = "📋 Tasks You Assigned:";
        } else {
            $tasks = Task::where('assigned_to', $user->id)
                ->where('status', 'Pending')
                ->latest()
                ->get();
            $title = "📋 Your Pending Tasks:";
        }

        if ($tasks->isEmpty()) {
            return "✅ No pending tasks found.";
        }

        $response = "$title\n";
        foreach ($tasks as $task) {
            $response .= "- [ID: {$task->id}] {$task->task}\n";
        }

        return $response;
    }

    private function handleCompleteTask($user, $data)
    {
        $taskId = $data['task_id'] ?? null;
        
        if (!$taskId) {
            return "Please provide the Task ID. Example: 'Complete task 123'";
        }

        // Logic: Managers can complete any task they assigned. Employees can complete tasks assigned to them.
        $task = Task::where('id', $taskId)->first();

        if (!$task) {
            return "❌ Task ID {$taskId} not found.";
        }

        if ($user->role === 'manager' && $task->assigned_by !== $user->id) {
             return "⛔ You can only modify tasks you assigned.";
        }

        if ($user->role === 'employee' && $task->assigned_to !== $user->id) {
            return "⛔ You can only complete tasks assigned to you.";
        }

        $task->update(['status' => 'Completed']);

        // Notify Manager if employee completes it
        if ($user->role === 'employee') {
            $manager = User::find($task->assigned_by);
            if ($manager) {
                $this->whatsAppService->sendMessage($manager->phone, "✅ Task Completed by {$user->name}:\n{$task->task}");
            }
        }

        return "🎉 Task {$taskId} marked as Completed!";
    }
}
