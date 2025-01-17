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
    $user = auth()->user();
    $now = now()->locale('fr')->format('l d F Y H:i');

    return [
      'role' => 'system',
      'content' => <<<EOT
                Tu es un assistant de chat. La date et l'heure actuelle est le {$now}.
                Tu es actuellement utilisé par {$user->name}.
                EOT,
    ];
  }

  public function generateTitle(string $message): string
  {
    return $this->sendMessage(
      messages: [['role' => 'user', 'content' => "Génère un titre court de 4-5 mots pour la conversation suivante : $message"]],
      model: self::DEFAULT_MODEL
    );
  }
}
