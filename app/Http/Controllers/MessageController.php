<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\ChatService;
use Illuminate\Http\Request;

class MessageController extends Controller
{
  public function store(Request $request, $conversationId)
  {
    $request->validate([
      'message' => 'required|string',
    ]);

    $conversation = Conversation::findOrFail($conversationId);

    $userMessage = Message::create([
      'conversation_id' => $conversation->id,
      'role' => 'user',
      'content' => $request->message,
    ]);

    $responseContent = (new ChatService())->sendMessage(
      messages: $conversation->messages->map(function ($msg) {
        return [
          'role' => $msg->role,
          'content' => $msg->content,
        ];
      })->toArray(),
      model: $conversation->model
    );

    $assistantMessage = Message::create([
      'conversation_id' => $conversation->id,
      'role' => 'assistant',
      'content' => $responseContent,
    ]);

    if ($conversation->title === 'Nouvelle conversation' || $conversation->title === 'Clarification request') {
      $chatService = new ChatService();
      $title = $chatService->generateTitle($request->message);
      $conversation->update(['title' => $title]);
    }

    return response()->json([
      'message' => $assistantMessage,
      'conversation' => $conversation->load('messages'),
    ], 201);
  }
}
