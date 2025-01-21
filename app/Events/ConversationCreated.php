<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ConversationCreated implements ShouldBroadcastNow
{
  public function __construct(
    protected Conversation $conversation
  ) {}

  public function broadcastOn(): array
  {
    return [
      new PrivateChannel('conversations'),
    ];
  }

  public function broadcastAs(): string
  {
    return 'conversation.created';
  }

  public function broadcastWith(): array
  {
    return [
      'conversation' => $this->conversation
    ];
  }
}
