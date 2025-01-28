<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Conversation;

class ChatService
{
  private $baseUrl;
  private $apiKey;
  private $client;
  public const DEFAULT_MODEL = 'meta-llama/llama-3.2-11b-vision-instruct:free';

  public function __construct()
  {
    $this->baseUrl = config('services.openrouter.base_url', 'https://openrouter.ai/api/v1');
    $this->apiKey = config('services.openrouter.api_key');
    $this->client = $this->createOpenAIClient();
  }

  public function getModels(): array
  {
    return cache()->remember('openai.models', now()->addHour(), function () {
      $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $this->apiKey,
      ])->get($this->baseUrl . '/models');

      return collect($response->json()['data'])
        ->filter(function ($model) {
          return str_ends_with($model['id'], ':free');
        })
        ->sortBy('name')
        ->map(function ($model) {
          return [
            'id' => $model['id'],
            'name' => $model['name'],
            'context_length' => $model['context_length'],
            'max_completion_tokens' => $model['top_provider']['max_completion_tokens'],
            'pricing' => $model['pricing'],
            'supports_image' => 'text+image->text' === ($model['architecture']['modality'] ?? null)
          ];
        })
        ->values()
        ->all();
    });
  }

  public function streamConversation(array $messages, ?string $model = null, float $temperature = 0.7, ?Conversation $conversation = null)
  {
    try {
      logger()->info('Début streamConversation', [
        'model' => $model,
        'temperature' => $temperature,
      ]);

      $models = collect($this->getModels());
      if (!$model || !$models->contains('id', $model)) {
        $model = self::DEFAULT_MODEL;
        logger()->info('Modèle par défaut utilisé:', ['model' => $model]);
      }

      // Récupérer le système prompt en tenant compte de la conversation
      $systemPrompt = $this->getChatSystemPrompt($conversation);
      $messages = [$systemPrompt, ...$messages];

      // Méthode "createStreamed" qui renvoie un flux "StreamResponse"
      return $this->client->chat()->createStreamed([
        'model' => $model,
        'messages' => $messages,
        'temperature' => $temperature,
      ]);
    } catch (\Exception $e) {
      logger()->error('Erreur dans streamConversation:', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);
      throw $e;
    }
  }

  public function generateTitle(string $messages): mixed
  {
    return $this->streamConversation(
      messages: [[
        'role' => 'user',
        'content' => "En tant qu'utilisateur, je te demande de générer un titre court et accrocheur de 4 mots maximum qui résume précisément l'échange suivant :\n\n$messages\n\nLe titre doit être uniquement composé de 1 à 4 mots clairs et précis, sans phrase complète, ni texte supplémentaire. Si les messages sont incohérents, incompréhensibles, ou trop courts pour être résumés, ta réponse doit uniquement et strictement être : 'Clarification request'. Aucun autre texte, phrase ou détail ne doit être inclus dans la réponse, même si cela semble approprié. Par défaut si les messages sont trop longs ou trop complexes, tu peux répondre 'Résumé de la conversation'."
      ]],
      model: self::DEFAULT_MODEL
    );
  }

  private function createOpenAIClient(): \OpenAI\Client
  {
    return \OpenAI::factory()
      ->withApiKey($this->apiKey)
      ->withBaseUri($this->baseUrl)
      ->make();
  }

  private function getChatSystemPrompt(?Conversation $conversation = null): array
  {
    $user = auth()->user();
    $now = now()->locale('fr')->translatedFormat('l d F Y H:i');

    // Récupérer les instructions actives
    $instructionsQuery = $user->customInstructions()->where('is_active', true);

    // Si une conversation spécifique a une instruction personnalisée, l'utiliser
    if ($conversation && $conversation->custom_instruction_id) {
      $instructionsQuery->where('id', $conversation->custom_instruction_id);
    }

    $instructions = $instructionsQuery->orderBy('priority', 'desc')->get();

    // Les instructions sont déjà triées par priorité descendante (les plus prioritaires d'abord)
    $basePrompt = "Tu es Nexus, un assistant personnalisé.\n";
    $basePrompt .= "Date actuelle : {$now}\n";
    $basePrompt .= "Utilisateur : {$user->name}\n\n";

    // Instructions prioritaires d'abord, par type
    foreach (['general', 'tone', 'format', 'command'] as $type) {
      $typeInstructions = $instructions->where('type', $type)
        ->pluck('content')
        ->join("\n");

      if ($typeInstructions) {
        $basePrompt .= match ($type) {
          'general' => "Instructions générales:\n{$typeInstructions}\n\n",
          'tone' => "Ton à adopter:\n{$typeInstructions}\n\n",
          'format' => "Format des réponses:\n{$typeInstructions}\n\n",
          'command' => "Commandes disponibles:\n{$typeInstructions}\n\n",
        };
      }
    }

    return [
      'role' => 'system',
      'content' => $basePrompt
    ];
  }
}
