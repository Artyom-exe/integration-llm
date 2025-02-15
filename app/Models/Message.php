<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
  protected $fillable = [
    'conversation_id',
    'role',
    'content',
  ];

  protected $appends = ['display_content'];

  public function getDisplayContentAttribute()
  {
    if ($this->role === 'assistant') {
      return $this->content;
    }

    if (is_string($this->content) && str_starts_with($this->content, '[')) {
      try {
        $decoded = json_decode($this->content, true);
        if (isset($decoded[0]['text'])) {
          return $decoded[0]['text'];
        }
      } catch (\Exception $e) {
      }
    }
    return $this->content;
  }

  public function conversation(): BelongsTo
  {
    return $this->belongsTo(Conversation::class);
  }
}
