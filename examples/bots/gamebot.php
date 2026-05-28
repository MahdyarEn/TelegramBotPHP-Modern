<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Telegram.php';

$bot_token = 'bot_token';
$telegram = new Telegram($bot_token);
$text = $telegram->Text();
$chat_id = $telegram->ChatID();
$data = $telegram->getData();
$callback_query = $telegram->CallbackQuery();

if (isset($_GET['user_id']) && isset($_GET['inline']) && isset($_GET['score'])) {
    $content = [
        'user_id' => $_GET['user_id'],
        'inline_message_id' => $_GET['inline'],
        'score' => (int) $_GET['score'],
        'force' => false,
    ];
    $reply = $telegram->setGameScore($content);
    echo json_encode($reply, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return;
}
if (!empty($data['inline_query'])) {
    $query = $data['inline_query']['query'];

    if (is_string($query) && strpos($query, 'gamename') !== false) {
        $results = json_encode([['type' => 'game', 'id' => '1', 'game_short_name' => 'game_short']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $content = ['inline_query_id' => $data['inline_query']['id'], 'results' => $results];
        $telegram->answerInlineQuery($content);
    }
}

if (!empty($callback_query)) {
    $user_id = $data['callback_query']['from']['id'];
    $inline_id = $data['callback_query']['inline_message_id'];

    $content = [
        'callback_query_id' => $data['callback_query']['id'] ?? null,
        'url' => 'http://domain.com/gamefolder/?user_id=' . $user_id . '&inline=' . $inline_id,
    ];
    if (!empty($content['callback_query_id'])) {
        $telegram->answerCallbackQuery($content);
    }
}

if ($text == '/start') {
    $content = ['chat_id' => $chat_id, 'text' => 'Welcome to Test GameBot !'];
    $telegram->sendMessage($content);
}
