<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Private channel for AI responses — isolated per user, no message mixing
Broadcast::channel('ai-response.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
