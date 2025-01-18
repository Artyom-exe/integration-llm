<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

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
          ];
        })
        ->values()
        ->all();
    });
  }

  public function streamConversation(array $messages, ?string $model = null, float $temperature = 0.7)
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

      $messages = [$this->getChatSystemPrompt(), ...$messages];

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

  public function sendMessage(array $messages, string $model = null, float $temperature = 0.7): string
  {
    try {
      logger()->info('Envoi du message', [
        'model' => $model,
        'temperature' => $temperature,
      ]);

      $models = collect($this->getModels());
      if (!$model || !$models->contains('id', $model)) {
        $model = self::DEFAULT_MODEL;
        logger()->info('Modèle par défaut utilisé:', ['model' => $model]);
      }

      $messages = [$this->getChatSystemPrompt(), ...$messages];
      $response = $this->client->chat()->create([
        'model' => $model,
        'messages' => $messages,
        'temperature' => $temperature,
      ]);

      logger()->info('Réponse reçue:', ['response' => $response]);

      return $response->choices[0]->message->content;
    } catch (\Exception $e) {
      if ($e->getMessage() === 'Undefined array key "choices"') {
        throw new \Exception("Limite de messages atteinte");
      }

      logger()->error('Erreur dans sendMessage:', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);

      throw $e;
    }
  }

  private function createOpenAIClient(): \OpenAI\Client
  {
    return \OpenAI::factory()
      ->withApiKey($this->apiKey)
      ->withBaseUri($this->baseUrl)
      ->make();
  }

  private function getChatSystemPrompt(): array
  {
    $user = auth()->user()->name;
    $now = now()->locale('fr')->translatedFormat('l d F Y H:i');

    return [
      'role' => 'system',
      'content' => <<<EOT
            Tu as décidé de t'appeler Nexus et tu es un assistant de chat.
            Réponds dans la langue dans laquelle on te parle.
            La date et l'heure actuelles sont : {$now}.
            N'invente rien si cela ne t'a pas été demandé explicitement.

            Tu es actuellement utilisé par {$user}. Retiens son pseudo et appelle-le systématiquement par ce pseudo dans toutes tes interactions.

            Règles pour ton comportement :
            1. Réponds toujours de manière précise et concise sauf si on te demande plus de détails.
            2. Adapte tes explications au niveau de l'utilisateur si tu en disposes.
            3. Ne fournis jamais d'informations non sollicitées ou incertaines.

            EOT,
    ];
  }

  public function generateTitle(string $message): string
  {

    return $this->sendMessage(
      messages: [[
        'role' => 'user',
        'content' => "En tant qu'utilisateur, je te demande de générer un titre court et accrocheur de 4 mots maximum qui résume précisément la conversation suivante : $message. Le titre doit être uniquement composé de 1 à 4 mots clairs et précis, sans phrase complète, ni texte supplémentaire. Si le message est incohérent, incompréhensible, ou trop court pour être résumé, ta réponse doit uniquement et strictement être : 'Clarification request'. Aucun autre texte, phrase ou détail ne doit être inclus dans la réponse, même si cela semble approprié. Par default si le message est trop long ou trop complexe ou sans contenu, tu peux répondre 'Résumé de la conversation'."



      ]],
      model: self::DEFAULT_MODEL
    );
  }
}
