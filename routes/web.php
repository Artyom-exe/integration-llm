<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AskController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\CustomInstructionController;
use Inertia\Inertia;

// Routes publiques
Route::get('/', function () {
  return Inertia::render('Welcome', [
    'canLogin' => Route::has('login'),
    'canRegister' => Route::has('register'),
    'laravelVersion' => Application::VERSION,
    'phpVersion' => PHP_VERSION,
  ]);
});

// Routes protégées
Route::middleware([
  'auth:sanctum',
  config('jetstream.auth_session'),
  'verified',
])->group(function () {
  // Dashboard
  Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
  })->name('dashboard');

  // Routes pour l'interface de chat
  Route::get('/ask', [AskController::class, 'index'])->name('ask.index');

  // Routes pour les conversations
  Route::controller(ConversationController::class)->group(function () {
    Route::get('/conversations', 'index')->name('conversations.index');
    Route::post('/conversations', 'store')->name('conversations.store');
    Route::put('/conversations/{id}/model', 'updateModel')->name('conversations.updateModel');
    Route::put('/conversations/{id}/instruction', 'updateInstruction')->name('conversations.update-instruction');
    Route::delete('/conversations/{id}', 'destroy')->name('conversations.destroy');
  });

  // Routes pour les messages
  Route::controller(MessageController::class)->group(function () {
    Route::post('/conversations/{id}/messages', 'store')->name('messages.store');
    Route::post('/conversations/{conversation}/stream', 'streamMessage')->name('messages.stream');
  });

  // Routes pour les instructions personnalisées
  Route::controller(CustomInstructionController::class)->group(function () {
    Route::get('/custom-instructions', 'index')->name('custom-instructions.index');
    Route::post('/custom-instructions', 'store')->name('custom-instructions.store');
    Route::put('/custom-instructions/{id}', 'update')->name('custom-instructions.update');
    Route::delete('/custom-instructions/{id}', 'destroy')->name('custom-instructions.destroy');
  });
});
