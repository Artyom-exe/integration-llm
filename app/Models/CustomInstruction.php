<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CustomInstruction extends Model
{
  use HasFactory;

  protected $fillable = [
    'title',
    'content',
    'category',
    'user_id'  // Ajout du user_id dans les fillables
  ];

  protected $casts = [
    'created_at' => 'datetime',
    'updated_at' => 'datetime'
  ];

  // Optionnel: Ajouter une règle de validation personnalisée si nécessaire
  public static $rules = [
    'title' => 'required|string|max:255',
    'content' => 'required|string',
    'category' => 'required|string|max:100'
  ];

  /**
   * Get the user that owns the custom instruction.
   */
  public function user()
  {
    return $this->belongsTo(User::class);
  }
}
