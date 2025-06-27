<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Article;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('article.{id}', function ($user, $id) {
	// Allow access if the user has access to the article
	// Add your authorization logic here
	return true;
});

Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
	// Allow access if the user has access to the conversation
	// Add your authorization logic here
	return true;
});
