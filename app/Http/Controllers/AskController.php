<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\ChatService;
use Inertia\Inertia;

class AskController extends Controller
{
  public function index()
  {
    // Nettoyer les conversations temporaires
    Conversation::where('user_id', auth()->id())
      ->where('is_temporary', true)
      ->delete();

    $chatService = new ChatService();

    return Inertia::render('Ask/Index', [
      'conversations' => Conversation::where('user_id', auth()->id())
        ->with('messages')
        ->orderBy('updated_at', 'desc')
        ->get(),
      'models' => $chatService->getModels(),
      'selectedModel' => auth()->user()->last_used_model
    ]);
  }
}
