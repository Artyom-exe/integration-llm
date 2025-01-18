<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Conversation;
use App\Models\User;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
  return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.{conversation}', function (User $user, Conversation $conversation) {
  return $conversation && $conversation->user_id === $user->id;
});
