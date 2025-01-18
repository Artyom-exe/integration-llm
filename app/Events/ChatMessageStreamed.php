<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ChatMessageStreamed implements ShouldBroadcastNow
{
    public function __construct(
        protected string $channel,
        protected string $content,
        protected bool $isComplete = false,
        protected bool $error = false
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel($this->channel),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.streamed';
    }

    public function broadcastWith(): array
    {
        return [
            'content'    => $this->content,
            'isComplete' => $this->isComplete,
            'error'      => $this->error,
        ];
    }
}
