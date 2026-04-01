<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Canais privados requerem autenticação. Canais públicos não.
| O wall usa canal público para simplificar acesso do telão.
|
*/

// ─── Wall (canal PÚBLICO — telão não requer autenticação) ──────────
// A autorização é implícita: qualquer browser com o wall_code consegue
// se inscrever. O wall_code é um secret curto (8 chars).
Broadcast::channel('wall.{wallCode}', function () {
    return true;
});

// ─── Canais privados (para admin/operador) ──────────────────────────
Broadcast::channel('event.{eventId}.wall', function ($user, int $eventId) {
    // TODO: Verificar se o usuário tem acesso ao evento
    return $user !== null;
});

Broadcast::channel('event.{eventId}.gallery', function ($user, int $eventId) {
    return $user !== null;
});

Broadcast::channel('event.{eventId}.moderation', function ($user, int $eventId) {
    return $user !== null;
});

Broadcast::channel('event.{eventId}.play', function ($user, int $eventId) {
    return $user !== null;
});
