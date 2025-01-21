<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\ChatService;
use Illuminate\Http\Request;
use App\Events\ChatMessageStreamed;
use App\Models\CustomInstruction;
use App\Events\ConversationCreated;

class MessageController extends Controller
{

  public function streamMessage(Conversation $conversation, Request $request)
  {
    $request->validate([
      'message' => 'required|string',
      'model'   => 'nullable|string',
      'custom_instruction_id' => 'nullable|exists:custom_instructions,id'
    ]);

    try {
      // Sauvegarder le message et vérifier si on doit générer un titre
      $shouldGenerateTitle = $conversation->title === 'Nouvelle conversation';

      // 1. Sauvegarder le message de l'utilisateur
      $conversation->messages()->create([
        'content' => $request->input('message'),
        'role'    => 'user',
      ]);

      // 2. Nom du canal
      $channelName = "chat.{$conversation->id}";

      // 3. Récupérer historique et ajouter les instructions personnalisées si spécifiées
      $messages = $conversation->messages()
        ->orderBy('created_at', 'asc')
        ->get()
        ->map(fn($msg) => [
          'role'    => $msg->role,
          'content' => $msg->content,
        ])
        ->toArray();

      if ($request->has('custom_instruction_id')) {
        $customInstruction = CustomInstruction::findOrFail($request->custom_instruction_id);
        array_unshift($messages, [
          'role' => 'system',
          'content' => $customInstruction->content
        ]);
      }

      // 4. Obtenir un flux depuis le ChatService
      $chatService = new ChatService();
      $stream = $chatService->streamConversation(
        messages: $messages,
        model: $conversation->model ?? $request->user()->last_used_model ?? ChatService::DEFAULT_MODEL,
      );

      // 5. Créer le message "assistant" dans la BD (vide pour l'instant)
      $assistantMessage = $conversation->messages()->create([
        'content' => '',
        'role'    => 'assistant',
      ]);

      // 6. Variables pour accumuler la réponse
      $fullResponse = '';
      $buffer = '';
      $lastBroadcastTime = microtime(true) * 1000; // ms

      // 7. Itération sur le flux
      foreach ($stream as $response) {
        $chunk = $response->choices[0]->delta->content ?? '';

        if ($chunk) {
          $fullResponse .= $chunk;
          $buffer .= $chunk;

          // Broadcast seulement toutes les ~100ms
          $currentTime = microtime(true) * 1000;
          if ($currentTime - $lastBroadcastTime >= 100) {
            broadcast(new ChatMessageStreamed(
              channel: $channelName,
              content: $buffer,
              isComplete: false
            ));

            $buffer = '';
            $lastBroadcastTime = $currentTime;
          }
        }
      }

      // 8. Diffuser le buffer restant
      if (!empty($buffer)) {
        broadcast(new ChatMessageStreamed(
          channel: $channelName,
          content: $buffer,
          isComplete: false
        ));
      }

      // 9. Mettre à jour la BD avec le texte complet
      $assistantMessage->update([
        'content' => $fullResponse
      ]);

      // Vérifier si on doit générer/régénérer le titre
      $shouldGenerateTitle = $conversation->title === 'Nouvelle conversation' ||
        ($conversation->messages()->count() % 7 === 0); // Tous les 7 messages

      // Générer et mettre à jour le titre si nécessaire
      if ($shouldGenerateTitle) {
        // Pour la régénération, utiliser les derniers messages comme contexte
        $contextMessages = $conversation->messages()
          ->orderBy('created_at', 'desc')
          ->take(7)
          ->get()
          ->map(fn($msg) => $msg->content)
          ->join("\n");

        $titleStream = $chatService->generateTitle($contextMessages);
        $titleContent = '';

        foreach ($titleStream as $response) {
          $chunk = $response->choices[0]->delta->content ?? '';
          if ($chunk) {
            $titleContent .= $chunk;
            broadcast(new ChatMessageStreamed(
              channel: $channelName,
              content: $titleContent,
              isComplete: false,
              isTitle: true
            ));
          }
        }

        $conversation->update(['title' => $titleContent]);

        // Broadcaster la mise à jour de la conversation
        broadcast(new ConversationCreated($conversation->fresh()));

        broadcast(new ChatMessageStreamed(
          channel: $channelName,
          content: $titleContent,
          isComplete: true,
          isTitle: true
        ));
      }

      // 10. Émettre un dernier événement pour signaler la complétion
      broadcast(new ChatMessageStreamed(
        channel: $channelName,
        content: $fullResponse,
        isComplete: true
      ));

      return response()->json([
        "ok" => true,
        "conversation" => $conversation->fresh()
      ]);
    } catch (\Exception $e) {
      // Diffuser l’erreur
      if (isset($conversation)) {
        broadcast(new ChatMessageStreamed(
          channel: "chat.{$conversation->id}",
          content: "Erreur: " . $e->getMessage(),
          isComplete: true,
          error: true
        ));
      }

      return response()->json(['error' => $e->getMessage()], 500);
    }
  }

  public function store(Request $request, $conversationId)
  {
    $conversation = Conversation::findOrFail($conversationId);
    return $this->streamMessage($conversation, new Request([
      'message' => $request->message,
      'model' => $conversation->model
    ]));
  }
}
