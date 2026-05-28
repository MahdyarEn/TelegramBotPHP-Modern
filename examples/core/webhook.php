<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Telegram.php';

$bot_token = 'bot_token';
$telegram = new Telegram($bot_token);

// 2) Get full update payload (webhook JSON)
$update = $telegram->Update();
$type = $telegram->getUpdateType();

// Always respond 200 fast (Telegram retries on non-2xx)
// You can still continue executing; but keeping it fast is recommended.
// echo is optional; the important part is status code.
// echo $telegram->respondSuccess();

if ($type === null) {
    // Unknown/unsupported update payload
    $telegram->respondSuccess();
    exit;
}

// 3) Route by update type and use UpdatePart() to get the relevant JSON part
switch ($type) {
    case TelegramUpdateType::MESSAGE: {
            $message = $telegram->UpdatePart($type) ?? [];
            $chat_id = $message['chat']['id'] ?? null;
            $text = $message['text'] ?? null;

            if ($chat_id !== null && $text === '/start') {
                $keyboard = $telegram->buildKeyBoard([['Help', 'Ping']], $onetime = false, $resize = true);
                $telegram->sendMessage([
                    'chat_id' => $chat_id,
                    'text' => 'Hi. Send: Help / Ping / checklist / poll',
                    'reply_markup' => $keyboard,
                ]);
            }

            if ($chat_id !== null && ($text === 'Ping' || $text === '/ping')) {
                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => 'pong']);
            }

            if ($chat_id !== null && isset($message['checklist'])) {
                $title = $message['checklist']['title'] ?? '(no title)';
                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "Checklist received: {$title}"]);
            }

            if ($chat_id !== null && isset($message['poll'])) {
                $question = $message['poll']['question'] ?? '(no question)';
                $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "Poll received: {$question}"]);
            }

            break;
        }

    case TelegramUpdateType::CALLBACK_QUERY: {
            $cq = $telegram->CallbackQuery() ?? [];
            $id = $cq['id'] ?? null;
            if ($id) {
                $telegram->answerCallbackQuery([
                    'callback_query_id' => $id,
                    'text' => 'Callback received',
                    'show_alert' => false,
                ]);
            }
            break;
        }

    case TelegramUpdateType::INLINE_QUERY: {
            $iq = $telegram->InlineQuery() ?? [];
            $inline_query_id = $iq['id'] ?? null;
            if ($inline_query_id) {
                // Minimal inline response
                $telegram->answerInlineQuery([
                    'inline_query_id' => $inline_query_id,
                    'results' => json_encode([
                        [
                            'type' => 'article',
                            'id' => '1',
                            'title' => 'Hello',
                            'input_message_content' => [
                                'message_text' => 'Hello from inline query',
                            ],
                        ],
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'cache_time' => 1,
                    'is_personal' => true,
                ]);
            }
            break;
        }

    case TelegramUpdateType::MESSAGE_REACTION:
    case TelegramUpdateType::MESSAGE_REACTION_COUNT: {
            $reaction = $telegram->UpdatePart($type);
            // Do something with $reaction (log, analytics, moderation, ...)
            break;
        }

    default: {
            // For all other update types, just access:
            // $telegram->UpdatePart($type)
            break;
        }
}

$telegram->respondSuccess();
