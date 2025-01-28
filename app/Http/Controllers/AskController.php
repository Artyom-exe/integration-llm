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

    // Ne récupérer que les conversations permanentes
    $conversations = Conversation::where('user_id', auth()->id())
      ->where('is_temporary', false)
      ->with('messages')
      ->orderBy('updated_at', 'desc')
      ->get();

    return Inertia::render('Ask/Index', [
      'conversations' => $conversations,
      'models' => $chatService->getModels(),
      'selectedModel' => auth()->user()->last_used_model
    ]);
  }
}
