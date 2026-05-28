<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Telegram.php';

$bot_token = 'bot_token';
$telegram = new Telegram($bot_token);

// Store offset in a file so we don't re-process updates across restarts
$offsetFile = __DIR__ . '/.offset';
$offset = 0;
if (file_exists($offsetFile)) {
    $raw = trim((string) file_get_contents($offsetFile));
    if ($raw !== '' && ctype_digit($raw)) {
        $offset = (int) $raw;
    }
}

while (true) {
    $resp = $telegram->request('getUpdates', [
        'offset' => $offset,
        'limit' => 50,
        'timeout' => 25,
        // You can limit update types:
        // 'allowed_updates' => ['message', 'callback_query', 'message_reaction'],
    ]);

    $result = $resp['result'] ?? [];
    if (!is_array($result) || $result === []) {
        continue;
    }

    foreach ($result as $update) {
        if (!is_array($update)) {
            continue;
        }

        $updateId = $update['update_id'] ?? null;
        if (is_int($updateId)) {
            $offset = $updateId + 1;
            file_put_contents($offsetFile, (string) $offset);
        }

        // Set current update and route
        $telegram->setCurrentUpdate($update);
        $type = $telegram->getUpdateType();

        if ($type === TelegramUpdateType::MESSAGE) {
            $message = $telegram->UpdatePart($type) ?? [];
            $chat_id = $message['chat']['id'] ?? null;
            $text = $message['text'] ?? null;

            if ($chat_id !== null && $text === '/start') {
                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => 'Polling router is running.']);
            }
        }

        // You can handle other update types just like in webhook router:
        // $payload = $telegram->UpdatePart($type);
    }
}
