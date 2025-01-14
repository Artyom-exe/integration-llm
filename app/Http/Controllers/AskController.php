<?php

namespace App\Http\Controllers;

use App\Services\ChatService;
use App\Models\Conversation;
use Inertia\Inertia;

class AskController extends Controller
{
  public function index()
  {
    $models = (new ChatService())->getModels();
    $selectedModel = ChatService::DEFAULT_MODEL;

    $conversations = Conversation::where('user_id', auth()->id())
      ->with(['messages' => function ($query) {
        $query->orderBy('created_at', 'asc');
      }])
      ->orderBy('updated_at', 'desc')
      ->get();

    return Inertia::render('Ask/Index', [
      'models' => $models,
      'selectedModel' => $selectedModel,
      'conversations' => $conversations,
    ]);
  }
}
