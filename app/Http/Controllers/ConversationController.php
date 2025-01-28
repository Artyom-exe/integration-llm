<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\ChatService;
use Illuminate\Http\Request;
use App\Events\ConversationCreated;

class ConversationController extends Controller
{
  // Crée une nouvelle conversation
  public function store(Request $request)
  {
    $request->validate([
      'model' => 'required|string',
    ]);

    $conversation = Conversation::create([
      'user_id' => auth()->id(),
      'model' => $request->model,
      'title' => 'Nouvelle conversation', // Titre temporaire
    ]);

    // Suppression du broadcast ici car il sera fait après la génération du titre
    return response()->json(['conversation' => $conversation], 201);
  }

  // Met à jour le modèle d'une conversation
  public function updateModel(Request $request, $id)
  {
    $request->validate([
      'model' => 'required|string',
    ]);

    $conversation = Conversation::where('id', $id)
      ->where('user_id', auth()->id())
      ->firstOrFail();

    $conversation->update(['model' => $request->model]);

    // Recharger la conversation avec ses messages
    $conversation = $conversation->fresh(['messages']);

    return response()->json(['conversation' => $conversation], 200);
  }

  // Supprime une conversation
  public function destroy($id)
  {
    $conversation = Conversation::where('id', $id)
      ->where('user_id', auth()->id())
      ->firstOrFail();

    $conversation->delete();

    return response()->json(['message' => 'Conversation supprimée.'], 200);
  }

  // Charger les conversations
  public function index()
  {
    $conversations = Conversation::where('user_id', auth()->id())
      ->with('messages')
      ->orderBy('updated_at', 'desc')
      ->get();

    return response()->json(['conversations' => $conversations], 200);
  }

  // Ajouter cette nouvelle méthode
  public function show($id)
  {
    $conversation = Conversation::where('id', $id)
      ->where('user_id', auth()->id())
      ->with('messages')
      ->firstOrFail();

    return response()->json(['conversation' => $conversation]);
  }
}
