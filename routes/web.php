<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AskController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use Inertia\Inertia;

Route::get('/', function () {
  return Inertia::render('Welcome', [
    'canLogin' => Route::has('login'),
    'canRegister' => Route::has('register'),
    'laravelVersion' => Application::VERSION,
    'phpVersion' => PHP_VERSION,
  ]);
});

Route::middleware([
  'auth:sanctum',
  config('jetstream.auth_session'),
  'verified',
])->group(function () {
  Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
  })->name('dashboard');

  // Routes pour AskController
  Route::get('/ask', [AskController::class, 'index'])->name('ask.index');

  // Routes pour ConversationController
  Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
  Route::post('/conversations', [ConversationController::class, 'store'])->name('conversations.store');
  Route::put('/conversations/{id}/model', [ConversationController::class, 'updateModel'])->name('conversations.updateModel');

  // Routes pour MessageController
  Route::post('/conversations/{id}/messages', [MessageController::class, 'store'])->name('messages.store');
  Route::post('/conversations/{conversation}/stream', [MessageController::class, 'streamMessage'])->name('messages.stream');

  // Routes pour CustomInstructionController
  Route::get('/custom-instructions', [CustomInstructionController::class, 'index'])->name('custom-instructions.index');
  Route::post('/custom-instructions', [CustomInstructionController::class, 'store'])->name('custom-instructions.store');
  Route::put('/custom-instructions/{id}', [CustomInstructionController::class, 'update'])->name('custom-instructions.update');
});
