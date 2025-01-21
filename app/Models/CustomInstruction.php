<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomInstruction extends Model
{
  use HasFactory;

  protected $fillable = [
    'user_id',
    'title',
    'content',
    'category',
    'type',
    'settings',
    'is_active',
    'priority'
  ];

  protected $casts = [
    'settings' => 'array',
    'is_active' => 'boolean',
    'priority' => 'integer'
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }
}
