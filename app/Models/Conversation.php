<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
  protected $fillable = [
    'user_id',
    'title',
    'model',
    'custom_instruction_id',
    'is_temporary'
  ];

  protected $casts = [
    'is_temporary' => 'boolean',
  ];

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  public function messages(): HasMany
  {
    return $this->hasMany(Message::class);
  }

  public function customInstruction()
  {
    return $this->belongsTo(CustomInstruction::class);
  }
}
