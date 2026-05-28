<?php

declare(strict_types=1);

if (file_exists(__DIR__ . '/TelegramErrorLogger.php')) {
    require_once __DIR__ . '/TelegramErrorLogger.php';
}

/**
 * MahdyarEn/TelegramBotPHP-Modern (modernized fork).
 *
 * Originally based on the upstream project `Eleirbag89/TelegramBotPHP` (MIT License).
 * Updated/modernized for PHP 8.1+ by Mahdyar Entezami -> MahdyarEn/TelegramBotPHP-Modern.
 */

/**
 * Optional modern helper for update types.
 * (Kept separate from legacy string constants for backward compatibility.)
 */
if (!enum_exists('TelegramUpdateType', false)) {
    enum TelegramUpdateType: string
    {
        // Update object fields (https://core.telegram.org/bots/api#update)
        case MESSAGE = 'message';
        case EDITED_MESSAGE = 'edited_message';
        case CHANNEL_POST = 'channel_post';
        case EDITED_CHANNEL_POST = 'edited_channel_post';

        case BUSINESS_CONNECTION = 'business_connection';
        case BUSINESS_MESSAGE = 'business_message';
        case EDITED_BUSINESS_MESSAGE = 'edited_business_message';
        case DELETED_BUSINESS_MESSAGES = 'deleted_business_messages';

        case GUEST_MESSAGE = 'guest_message';

        case MESSAGE_REACTION = 'message_reaction';
        case MESSAGE_REACTION_COUNT = 'message_reaction_count';

        case INLINE_QUERY = 'inline_query';
        case CHOSEN_INLINE_RESULT = 'chosen_inline_result';
        case CALLBACK_QUERY = 'callback_query';

        case SHIPPING_QUERY = 'shipping_query';
        case PRE_CHECKOUT_QUERY = 'pre_checkout_query';
        case PURCHASED_PAID_MEDIA = 'purchased_paid_media';

        case POLL = 'poll';
        case POLL_ANSWER = 'poll_answer';

        case MY_CHAT_MEMBER = 'my_chat_member';
        case CHAT_MEMBER = 'chat_member';
        case CHAT_JOIN_REQUEST = 'chat_join_request';

        case CHAT_BOOST = 'chat_boost';
        case REMOVED_CHAT_BOOST = 'removed_chat_boost';

        case MANAGED_BOT = 'managed_bot';

        // These are NOT Update object fields; they describe message contents.
        case REPLY = 'reply';
        case PHOTO = 'photo';
        case VIDEO = 'video';
        case AUDIO = 'audio';
        case VOICE = 'voice';
        case ANIMATION = 'animation';
        case STICKER = 'sticker';
        case DOCUMENT = 'document';
        case LOCATION = 'location';
        case CONTACT = 'contact';
        case NEW_CHAT_MEMBER = 'new_chat_member';
        case LEFT_CHAT_MEMBER = 'left_chat_member';
    }
}

/**
 * Modern Telegram Bot API client (PHP 8+).
 *
 * This client supports ALL Bot API methods via:
 * - request($method, $params)
 * - dynamic calls: $bot->sendMessage([...]) (handled by __call)
 */
class Telegram
{
    public const API_BASE = 'https://api.telegram.org';
    public const FILE_BASE = 'https://api.telegram.org/file';

    private array $lastUpdate = [];
    private array $lastUpdatesResponse = [];

    public function __construct(
        private readonly string $botToken,
        private readonly bool $logErrors = true,
        private readonly array $proxy = [],
        private readonly bool $throwOnError = true,
        private readonly int $connectTimeoutSeconds = 10,
        private readonly int $timeoutSeconds = 60,
    ) {}

    /**
     * Helper methods for building keyboards (upstream-style).
     */

    /**
     * Build a ReplyKeyboardMarkup.
     *
     * @see https://core.telegram.org/bots/api#replykeyboardmarkup
     */
    public function buildKeyBoard(
        array $options,
        bool $onetime = false,
        bool $resize = false,
        bool $selective = true
    ): string {
        $replyMarkup = [
            'keyboard' => $options,
            'one_time_keyboard' => $onetime,
            'resize_keyboard' => $resize,
            'selective' => $selective,
        ];

        return json_encode($replyMarkup, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    /**
     * Build an InlineKeyboardMarkup.
     *
     * @see https://core.telegram.org/bots/api#inlinekeyboardmarkup
     */
    public function buildInlineKeyBoard(array $options): string
    {
        $replyMarkup = [
            'inline_keyboard' => $options,
        ];

        return json_encode($replyMarkup, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    /**
     * Build an InlineKeyboardButton.
     *
     * Note: Telegram requires exactly one of the optional action fields.
     *
     * @see https://core.telegram.org/bots/api#inlinekeyboardbutton
     */
    public function buildInlineKeyboardButton(
        string $text,
        string $url = '',
        string $callback_data = '',
        ?string $switch_inline_query = null,
        ?string $switch_inline_query_current_chat = null,
        array $callback_game = [],
        ?bool $pay = null
    ): array {
        $replyMarkup = ['text' => $text];

        if ($url !== '') {
            $replyMarkup['url'] = $url;
        } elseif ($callback_data !== '') {
            $replyMarkup['callback_data'] = $callback_data;
        } elseif ($switch_inline_query !== null) {
            $replyMarkup['switch_inline_query'] = $switch_inline_query;
        } elseif ($switch_inline_query_current_chat !== null) {
            $replyMarkup['switch_inline_query_current_chat'] = $switch_inline_query_current_chat;
        } elseif ($callback_game !== []) {
            $replyMarkup['callback_game'] = $callback_game;
        } elseif ($pay !== null) {
            $replyMarkup['pay'] = $pay;
        }

        return $replyMarkup;
    }

    /**
     * Build a KeyboardButton.
     *
     * @see https://core.telegram.org/bots/api#keyboardbutton
     */
    public function buildKeyboardButton(
        string $text,
        bool $request_contact = false,
        bool $request_location = false
    ): array {
        return [
            'text' => $text,
            'request_contact' => $request_contact,
            'request_location' => $request_location,
        ];
    }

    /**
     * Build a ReplyKeyboardRemove.
     *
     * @see https://core.telegram.org/bots/api#replykeyboardremove
     */
    public function buildKeyBoardHide(bool $selective = true): string
    {
        $replyMarkup = [
            'remove_keyboard' => true,
            'selective' => $selective,
        ];

        return json_encode($replyMarkup, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    /**
     * Build a ForceReply.
     *
     * @see https://core.telegram.org/bots/api#forcereply
     */
    public function buildForceReply(bool $selective = true): string
    {
        $replyMarkup = [
            'force_reply' => true,
            'selective' => $selective,
        ];

        return json_encode($replyMarkup, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    /**
     * Create a CURLFile for multipart uploads (wrapper around curl_file_create()).
     *
     * @see https://www.php.net/manual/en/function.curl-file-create.php
     */
    public function curlFileCreate(
        string $filename,
        ?string $mimeType = null,
        ?string $postedFilename = null
    ): \CURLFile {
        if ($mimeType !== null && $postedFilename !== null) {
            return curl_file_create($filename, $mimeType, $postedFilename);
        }
        if ($mimeType !== null) {
            return curl_file_create($filename, $mimeType);
        }

        return curl_file_create($filename);
    }

    /**
     * Call any Telegram Bot API method.
     *
     * @return array Decoded JSON response as associative array.
     */
    public function request(string $method, array $params = []): array
    {
        $url = self::API_BASE . '/bot' . $this->botToken . '/' . $method;

        $params = $this->normalizeUploads($params);
        $isMultipart = $this->hasUpload($params);
        $raw = $this->sendCurl($url, $params, $isMultipart);

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $decoded = [
                'ok' => false,
                'description' => 'Invalid JSON response from Telegram',
                'raw' => $raw,
            ];
        }

        if ($this->logErrors) {
            TelegramErrorLogger::log($decoded, ['method' => $method, 'params' => $this->redact($params)]);
        }

        if ($this->throwOnError && (($decoded['ok'] ?? false) !== true)) {
            throw new TelegramApiException($method, $this->redact($params), $decoded);
        }

        return $decoded;
    }

    /**
     * Dynamic method support: $bot->sendMessage([...]) maps to request('sendMessage', [...])
     */
    public function __call(string $name, array $arguments): array
    {
        $params = $arguments[0] ?? [];
        if (!is_array($params)) {
            $params = ['value' => $params];
        }

        return $this->request($name, $params);
    }

    // --- Telegram Bot API methods (explicit wrappers for IDE autocomplete) ---

    /**
     * @see https://core.telegram.org/bots/api#setwebhook
     *
     * @param array{
     *   url:string,
     *   certificate?:\CURLFile|string,
     *   ip_address?:string,
     *   max_connections?:int,
     *   allowed_updates?:array,
     *   drop_pending_updates?:bool,
     *   secret_token?:string
     * } $params
     */
    public function setWebhook(array $params = []): array
    {
        return $this->request('setWebhook', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#deletewebhook
     */
    public function deleteWebhook(array $params = []): array
    {
        return $this->request('deleteWebhook', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getwebhookinfo
     */
    public function getWebhookInfo(array $params = []): array
    {
        return $this->request('getWebhookInfo', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getme
     */
    public function getMe(array $params = []): array
    {
        return $this->request('getMe', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#logout
     */
    public function logOut(array $params = []): array
    {
        return $this->request('logOut', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#close
     */
    public function close(array $params = []): array
    {
        return $this->request('close', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#sendmessage
     *
     * @param array{
     *   chat_id:int|string,
     *   text:string,
     *   message_thread_id?:int,
     *   parse_mode?:string,
     *   entities?:array,
     *   link_preview_options?:array,
     *   disable_notification?:bool,
     *   protect_content?:bool,
     *   message_effect_id?:string,
     *   reply_parameters?:array,
     *   reply_markup?:array|string
     * } $params
     */
    public function sendMessage(array $params = []): array
    {
        return $this->request('sendMessage', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#forwardmessage
     */
    public function forwardMessage(array $params = []): array
    {
        return $this->request('forwardMessage', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#forwardmessages
     */
    public function forwardMessages(array $params = []): array
    {
        return $this->request('forwardMessages', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#copymessage
     */
    public function copyMessage(array $params = []): array
    {
        return $this->request('copyMessage', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#copymessages
     */
    public function copyMessages(array $params = []): array
    {
        return $this->request('copyMessages', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#sendphoto
     *
     * @param array{
     *   chat_id:int|string,
     *   photo:\CURLFile|string,
     *   caption?:string,
     *   parse_mode?:string,
     *   caption_entities?:array,
     *   has_spoiler?:bool,
     *   disable_notification?:bool,
     *   protect_content?:bool,
     *   message_effect_id?:string,
     *   reply_parameters?:array,
     *   reply_markup?:array|string
     * } $params
     */
    public function sendPhoto(array $params = []): array
    {
        return $this->request('sendPhoto', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#sendlivephoto
     */
    public function sendLivePhoto(array $params = []): array
    {
        return $this->request('sendLivePhoto', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#sendaudio
     *
     * @param array{
     *   chat_id:int|string,
     *   audio:\CURLFile|string,
     *   caption?:string,
     *   parse_mode?:string,
     *   caption_entities?:array,
     *   duration?:int,
     *   performer?:string,
     *   title?:string,
     *   thumbnail?:\CURLFile|string,
     *   disable_notification?:bool,
     *   protect_content?:bool,
     *   message_effect_id?:string,
     *   reply_parameters?:array,
     *   reply_markup?:array|string
     * } $params
     */
    public function sendAudio(array $params = []): array
    {
        return $this->request('sendAudio', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#senddocument
     *
     * @param array{
     *   chat_id:int|string,
     *   document:\CURLFile|string,
     *   thumbnail?:\CURLFile|string,
     *   caption?:string,
     *   parse_mode?:string,
     *   caption_entities?:array,
     *   disable_content_type_detection?:bool,
     *   disable_notification?:bool,
     *   protect_content?:bool,
     *   message_effect_id?:string,
     *   reply_parameters?:array,
     *   reply_markup?:array|string
     * } $params
     */
    public function sendDocument(array $params = []): array
    {
        return $this->request('sendDocument', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#sendvideo
     *
     * @param array{
     *   chat_id:int|string,
     *   video:\CURLFile|string,
     *   duration?:int,
     *   width?:int,
     *   height?:int,
     *   thumbnail?:\CURLFile|string,
     *   caption?:string,
     *   parse_mode?:string,
     *   caption_entities?:array,
     *   has_spoiler?:bool,
     *   supports_streaming?:bool,
     *   disable_notification?:bool,
     *   protect_content?:bool,
     *   message_effect_id?:string,
     *   reply_parameters?:array,
     *   reply_markup?:array|string
     * } $params
     */
    public function sendVideo(array $params = []): array
    {
        return $this->request('sendVideo', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#sendanimation
     *
     * @param array{
     *   chat_id:int|string,
     *   animation:\CURLFile|string,
     *   duration?:int,
     *   width?:int,
     *   height?:int,
     *   thumbnail?:\CURLFile|string,
     *   caption?:string,
     *   parse_mode?:string,
     *   caption_entities?:array,
     *   has_spoiler?:bool,
     *   disable_notification?:bool,
     *   protect_content?:bool,
     *   message_effect_id?:string,
     *   reply_parameters?:array,
     *   reply_markup?:array|string
     * } $params
     */
    public function sendAnimation(array $params = []): array
    {
        return $this->request('sendAnimation', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#sendvoice
     *
     * @param array{
     *   chat_id:int|string,
     *   voice:\CURLFile|string,
     *   caption?:string,
     *   parse_mode?:string,
     *   caption_entities?:array,
     *   duration?:int,
     *   disable_notification?:bool,
     *   protect_content?:bool,
     *   message_effect_id?:string,
     *   reply_parameters?:array,
     *   reply_markup?:array|string
     * } $params
     */
    public function sendVoice(array $params = []): array
    {
        return $this->request('sendVoice', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#sendvideonote
     *
     * @param array{
     *   chat_id:int|string,
     *   video_note:\CURLFile|string,
     *   duration?:int,
     *   length?:int,
     *   thumbnail?:\CURLFile|string,
     *   disable_notification?:bool,
     *   protect_content?:bool,
     *   message_effect_id?:string,
     *   reply_parameters?:array,
     *   reply_markup?:array|string
     * } $params
     */
    public function sendVideoNote(array $params = []): array
    {
        return $this->request('sendVideoNote', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#sendpaidmedia
     */
    public function sendPaidMedia(array $params = []): array
    {
        return $this->request('sendPaidMedia', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#sendmediagroup
     *
     * @param array{
     *   chat_id:int|string,
     *   message_thread_id?:int,
     *   media:array,
     *   disable_notification?:bool,
     *   protect_content?:bool,
     *   message_effect_id?:string,
     *   reply_parameters?:array
     * } $params
     */
    public function sendMediaGroup(array $params = []): array
    {
        return $this->request('sendMediaGroup', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#sendlocation
     *
     * @param array{
     *   chat_id:int|string,
     *   latitude:float,
     *   longitude:float,
     *   message_thread_id?:int,
     *   horizontal_accuracy?:float,
     *   live_period?:int,
     *   heading?:int,
     *   proximity_alert_radius?:int,
     *   disable_notification?:bool,
     *   protect_content?:bool,
     *   message_effect_id?:string,
     *   reply_parameters?:array,
     *   reply_markup?:array|string
     * } $params
     */
    public function sendLocation(array $params = []): array
    {
        return $this->request('sendLocation', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#sendvenue
     */
    public function sendVenue(array $params = []): array
    {
        return $this->request('sendVenue', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#sendcontact
     */
    public function sendContact(array $params = []): array
    {
        return $this->request('sendContact', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#sendpoll
     *
     * @param array{
     *   chat_id:int|string,
     *   question:string,
     *   options:array,
     *   message_thread_id?:int,
     *   is_anonymous?:bool,
     *   type?:string,
     *   allows_multiple_answers?:bool,
     *   correct_option_id?:int,
     *   explanation?:string,
     *   explanation_parse_mode?:string,
     *   explanation_entities?:array,
     *   open_period?:int,
     *   close_date?:int,
     *   is_closed?:bool,
     *   disable_notification?:bool,
     *   protect_content?:bool,
     *   message_effect_id?:string,
     *   reply_parameters?:array,
     *   reply_markup?:array|string
     * } $params
     */
    public function sendPoll(array $params = []): array
    {
        return $this->request('sendPoll', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#sendchecklist
     */
    public function sendChecklist(array $params = []): array
    {
        return $this->request('sendChecklist', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#senddice
     */
    public function sendDice(array $params = []): array
    {
        return $this->request('sendDice', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#sendmessagedraft
     */
    public function sendMessageDraft(array $params = []): array
    {
        return $this->request('sendMessageDraft', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#sendchataction
     */
    public function sendChatAction(array $params = []): array
    {
        return $this->request('sendChatAction', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setmessagereaction
     */
    public function setMessageReaction(array $params = []): array
    {
        return $this->request('setMessageReaction', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getuserprofilephotos
     */
    public function getUserProfilePhotos(array $params = []): array
    {
        return $this->request('getUserProfilePhotos', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getuserprofileaudios
     */
    public function getUserProfileAudios(array $params = []): array
    {
        return $this->request('getUserProfileAudios', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setuseremojistatus
     */
    public function setUserEmojiStatus(array $params = []): array
    {
        return $this->request('setUserEmojiStatus', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getfile
     *
     * @param array{file_id:string} $params
     */
    public function getFile(array $params = []): array
    {
        return $this->request('getFile', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#banchatmember
     *
     * @param array{
     *   chat_id:int|string,
     *   user_id:int,
     *   until_date?:int,
     *   revoke_messages?:bool
     * } $params
     */
    public function banChatMember(array $params = []): array
    {
        return $this->request('banChatMember', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#unbanchatmember
     *
     * @param array{
     *   chat_id:int|string,
     *   user_id:int,
     *   only_if_banned?:bool
     * } $params
     */
    public function unbanChatMember(array $params = []): array
    {
        return $this->request('unbanChatMember', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#restrictchatmember
     *
     * @param array{
     *   chat_id:int|string,
     *   user_id:int,
     *   permissions:array,
     *   use_independent_chat_permissions?:bool,
     *   until_date?:int
     * } $params
     */
    public function restrictChatMember(array $params = []): array
    {
        return $this->request('restrictChatMember', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#promotechatmember
     *
     * @param array{
     *   chat_id:int|string,
     *   user_id:int,
     *   is_anonymous?:bool,
     *   can_manage_chat?:bool,
     *   can_delete_messages?:bool,
     *   can_manage_video_chats?:bool,
     *   can_restrict_members?:bool,
     *   can_promote_members?:bool,
     *   can_change_info?:bool,
     *   can_invite_users?:bool,
     *   can_post_stories?:bool,
     *   can_edit_stories?:bool,
     *   can_delete_stories?:bool,
     *   can_post_messages?:bool,
     *   can_edit_messages?:bool,
     *   can_pin_messages?:bool,
     *   can_manage_topics?:bool
     * } $params
     */
    public function promoteChatMember(array $params = []): array
    {
        return $this->request('promoteChatMember', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setchatadministratorcustomtitle
     */
    public function setChatAdministratorCustomTitle(array $params = []): array
    {
        return $this->request('setChatAdministratorCustomTitle', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setchatmembertag
     */
    public function setChatMemberTag(array $params = []): array
    {
        return $this->request('setChatMemberTag', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#banchatsenderchat
     */
    public function banChatSenderChat(array $params = []): array
    {
        return $this->request('banChatSenderChat', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#unbanchatsenderchat
     */
    public function unbanChatSenderChat(array $params = []): array
    {
        return $this->request('unbanChatSenderChat', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setchatpermissions
     *
     * @param array{
     *   chat_id:int|string,
     *   permissions:array,
     *   use_independent_chat_permissions?:bool
     * } $params
     */
    public function setChatPermissions(array $params = []): array
    {
        return $this->request('setChatPermissions', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#exportchatinvitelink
     */
    public function exportChatInviteLink(array $params = []): array
    {
        return $this->request('exportChatInviteLink', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#createchatinvitelink
     */
    public function createChatInviteLink(array $params = []): array
    {
        return $this->request('createChatInviteLink', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#editchatinvitelink
     */
    public function editChatInviteLink(array $params = []): array
    {
        return $this->request('editChatInviteLink', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#createchatsubscriptioninvitelink
     */
    public function createChatSubscriptionInviteLink(array $params = []): array
    {
        return $this->request('createChatSubscriptionInviteLink', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#editchatsubscriptioninvitelink
     */
    public function editChatSubscriptionInviteLink(array $params = []): array
    {
        return $this->request('editChatSubscriptionInviteLink', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#revokechatinvitelink
     */
    public function revokeChatInviteLink(array $params = []): array
    {
        return $this->request('revokeChatInviteLink', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#approvechatjoinrequest
     */
    public function approveChatJoinRequest(array $params = []): array
    {
        return $this->request('approveChatJoinRequest', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#declinechatjoinrequest
     */
    public function declineChatJoinRequest(array $params = []): array
    {
        return $this->request('declineChatJoinRequest', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setchatphoto
     */
    public function setChatPhoto(array $params = []): array
    {
        return $this->request('setChatPhoto', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#deletechatphoto
     */
    public function deleteChatPhoto(array $params = []): array
    {
        return $this->request('deleteChatPhoto', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setchattitle
     */
    public function setChatTitle(array $params = []): array
    {
        return $this->request('setChatTitle', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setchatdescription
     */
    public function setChatDescription(array $params = []): array
    {
        return $this->request('setChatDescription', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#pinchatmessage
     */
    public function pinChatMessage(array $params = []): array
    {
        return $this->request('pinChatMessage', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#unpinchatmessage
     */
    public function unpinChatMessage(array $params = []): array
    {
        return $this->request('unpinChatMessage', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#unpinallchatmessages
     */
    public function unpinAllChatMessages(array $params = []): array
    {
        return $this->request('unpinAllChatMessages', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#leavechat
     */
    public function leaveChat(array $params = []): array
    {
        return $this->request('leaveChat', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getchat
     *
     * @param array{chat_id:int|string} $params
     */
    public function getChat(array $params = []): array
    {
        return $this->request('getChat', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getchatadministrators
     */
    public function getChatAdministrators(array $params = []): array
    {
        return $this->request('getChatAdministrators', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getchatmembercount
     */
    public function getChatMemberCount(array $params = []): array
    {
        return $this->request('getChatMemberCount', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getchatmember
     *
     * @param array{chat_id:int|string, user_id:int} $params
     */
    public function getChatMember(array $params = []): array
    {
        return $this->request('getChatMember', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getuserpersonalchatmessages
     */
    public function getUserPersonalChatMessages(array $params = []): array
    {
        return $this->request('getUserPersonalChatMessages', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setchatstickerset
     */
    public function setChatStickerSet(array $params = []): array
    {
        return $this->request('setChatStickerSet', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#deletechatstickerset
     */
    public function deleteChatStickerSet(array $params = []): array
    {
        return $this->request('deleteChatStickerSet', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getforumtopiciconstickers
     */
    public function getForumTopicIconStickers(array $params = []): array
    {
        return $this->request('getForumTopicIconStickers', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#createforumtopic
     */
    public function createForumTopic(array $params = []): array
    {
        return $this->request('createForumTopic', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#editforumtopic
     */
    public function editForumTopic(array $params = []): array
    {
        return $this->request('editForumTopic', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#closeforumtopic
     */
    public function closeForumTopic(array $params = []): array
    {
        return $this->request('closeForumTopic', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#reopenforumtopic
     */
    public function reopenForumTopic(array $params = []): array
    {
        return $this->request('reopenForumTopic', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#deleteforumtopic
     */
    public function deleteForumTopic(array $params = []): array
    {
        return $this->request('deleteForumTopic', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#unpinallforumtopicmessages
     */
    public function unpinAllForumTopicMessages(array $params = []): array
    {
        return $this->request('unpinAllForumTopicMessages', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#editgeneralforumtopic
     */
    public function editGeneralForumTopic(array $params = []): array
    {
        return $this->request('editGeneralForumTopic', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#closegeneralforumtopic
     */
    public function closeGeneralForumTopic(array $params = []): array
    {
        return $this->request('closeGeneralForumTopic', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#reopengeneralforumtopic
     */
    public function reopenGeneralForumTopic(array $params = []): array
    {
        return $this->request('reopenGeneralForumTopic', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#hidegeneralforumtopic
     */
    public function hideGeneralForumTopic(array $params = []): array
    {
        return $this->request('hideGeneralForumTopic', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#unhidegeneralforumtopic
     */
    public function unhideGeneralForumTopic(array $params = []): array
    {
        return $this->request('unhideGeneralForumTopic', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#unpinallgeneralforumtopicmessages
     */
    public function unpinAllGeneralForumTopicMessages(array $params = []): array
    {
        return $this->request('unpinAllGeneralForumTopicMessages', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#answercallbackquery
     *
     * @param array{
     *   callback_query_id:string,
     *   text?:string,
     *   show_alert?:bool,
     *   url?:string,
     *   cache_time?:int
     * } $params
     */
    public function answerCallbackQuery(array $params = []): array
    {
        return $this->request('answerCallbackQuery', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#answerguestquery
     */
    public function answerGuestQuery(array $params = []): array
    {
        return $this->request('answerGuestQuery', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getuserchatboosts
     */
    public function getUserChatBoosts(array $params = []): array
    {
        return $this->request('getUserChatBoosts', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getbusinessconnection
     */
    public function getBusinessConnection(array $params = []): array
    {
        return $this->request('getBusinessConnection', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getmanagedbottoken
     */
    public function getManagedBotToken(array $params = []): array
    {
        return $this->request('getManagedBotToken', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#replacemanagedbottoken
     */
    public function replaceManagedBotToken(array $params = []): array
    {
        return $this->request('replaceManagedBotToken', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getmanagedbotaccesssettings
     */
    public function getManagedBotAccessSettings(array $params = []): array
    {
        return $this->request('getManagedBotAccessSettings', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setmanagedbotaccesssettings
     */
    public function setManagedBotAccessSettings(array $params = []): array
    {
        return $this->request('setManagedBotAccessSettings', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setmycommands
     *
     * @param array{
     *   commands:array,
     *   scope?:array,
     *   language_code?:string
     * } $params
     */
    public function setMyCommands(array $params = []): array
    {
        return $this->request('setMyCommands', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#deletemycommands
     */
    public function deleteMyCommands(array $params = []): array
    {
        return $this->request('deleteMyCommands', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getmycommands
     */
    public function getMyCommands(array $params = []): array
    {
        return $this->request('getMyCommands', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setmyname
     */
    public function setMyName(array $params = []): array
    {
        return $this->request('setMyName', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getmyname
     */
    public function getMyName(array $params = []): array
    {
        return $this->request('getMyName', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setmydescription
     */
    public function setMyDescription(array $params = []): array
    {
        return $this->request('setMyDescription', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getmydescription
     */
    public function getMyDescription(array $params = []): array
    {
        return $this->request('getMyDescription', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setmyshortdescription
     */
    public function setMyShortDescription(array $params = []): array
    {
        return $this->request('setMyShortDescription', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getmyshortdescription
     */
    public function getMyShortDescription(array $params = []): array
    {
        return $this->request('getMyShortDescription', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setmyprofilephoto
     */
    public function setMyProfilePhoto(array $params = []): array
    {
        return $this->request('setMyProfilePhoto', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#removemyprofilephoto
     */
    public function removeMyProfilePhoto(array $params = []): array
    {
        return $this->request('removeMyProfilePhoto', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setchatmenubutton
     */
    public function setChatMenuButton(array $params = []): array
    {
        return $this->request('setChatMenuButton', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getchatmenubutton
     */
    public function getChatMenuButton(array $params = []): array
    {
        return $this->request('getChatMenuButton', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setmydefaultadministratorrights
     */
    public function setMyDefaultAdministratorRights(array $params = []): array
    {
        return $this->request('setMyDefaultAdministratorRights', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getmydefaultadministratorrights
     */
    public function getMyDefaultAdministratorRights(array $params = []): array
    {
        return $this->request('getMyDefaultAdministratorRights', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getavailablegifts
     */
    public function getAvailableGifts(array $params = []): array
    {
        return $this->request('getAvailableGifts', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#sendgift
     */
    public function sendGift(array $params = []): array
    {
        return $this->request('sendGift', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#giftpremiumsubscription
     */
    public function giftPremiumSubscription(array $params = []): array
    {
        return $this->request('giftPremiumSubscription', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#verifyuser
     */
    public function verifyUser(array $params = []): array
    {
        return $this->request('verifyUser', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#verifychat
     */
    public function verifyChat(array $params = []): array
    {
        return $this->request('verifyChat', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#removeuserverification
     */
    public function removeUserVerification(array $params = []): array
    {
        return $this->request('removeUserVerification', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#removechatverification
     */
    public function removeChatVerification(array $params = []): array
    {
        return $this->request('removeChatVerification', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#readbusinessmessage
     */
    public function readBusinessMessage(array $params = []): array
    {
        return $this->request('readBusinessMessage', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#deletebusinessmessages
     */
    public function deleteBusinessMessages(array $params = []): array
    {
        return $this->request('deleteBusinessMessages', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setbusinessaccountname
     */
    public function setBusinessAccountName(array $params = []): array
    {
        return $this->request('setBusinessAccountName', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setbusinessaccountusername
     */
    public function setBusinessAccountUsername(array $params = []): array
    {
        return $this->request('setBusinessAccountUsername', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setbusinessaccountbio
     */
    public function setBusinessAccountBio(array $params = []): array
    {
        return $this->request('setBusinessAccountBio', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setbusinessaccountprofilephoto
     */
    public function setBusinessAccountProfilePhoto(array $params = []): array
    {
        return $this->request('setBusinessAccountProfilePhoto', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#removebusinessaccountprofilephoto
     */
    public function removeBusinessAccountProfilePhoto(array $params = []): array
    {
        return $this->request('removeBusinessAccountProfilePhoto', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setbusinessaccountgiftsettings
     */
    public function setBusinessAccountGiftSettings(array $params = []): array
    {
        return $this->request('setBusinessAccountGiftSettings', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getbusinessaccountstarbalance
     */
    public function getBusinessAccountStarBalance(array $params = []): array
    {
        return $this->request('getBusinessAccountStarBalance', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#transferbusinessaccountstars
     */
    public function transferBusinessAccountStars(array $params = []): array
    {
        return $this->request('transferBusinessAccountStars', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getbusinessaccountgifts
     */
    public function getBusinessAccountGifts(array $params = []): array
    {
        return $this->request('getBusinessAccountGifts', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getusergifts
     */
    public function getUserGifts(array $params = []): array
    {
        return $this->request('getUserGifts', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getchatgifts
     */
    public function getChatGifts(array $params = []): array
    {
        return $this->request('getChatGifts', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#convertgifttostars
     */
    public function convertGiftToStars(array $params = []): array
    {
        return $this->request('convertGiftToStars', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#upgradegift
     */
    public function upgradeGift(array $params = []): array
    {
        return $this->request('upgradeGift', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#transfergift
     */
    public function transferGift(array $params = []): array
    {
        return $this->request('transferGift', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#poststory
     */
    public function postStory(array $params = []): array
    {
        return $this->request('postStory', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#repoststory
     */
    public function repostStory(array $params = []): array
    {
        return $this->request('repostStory', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#editstory
     */
    public function editStory(array $params = []): array
    {
        return $this->request('editStory', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#deletestory
     */
    public function deleteStory(array $params = []): array
    {
        return $this->request('deleteStory', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#answerwebappquery
     */
    public function answerWebAppQuery(array $params = []): array
    {
        return $this->request('answerWebAppQuery', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#savepreparedinlinemessage
     */
    public function savePreparedInlineMessage(array $params = []): array
    {
        return $this->request('savePreparedInlineMessage', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#savepreparedkeyboardbutton
     */
    public function savePreparedKeyboardButton(array $params = []): array
    {
        return $this->request('savePreparedKeyboardButton', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#editmessagetext
     *
     * @param array{
     *   business_connection_id?:string,
     *   chat_id?:int|string,
     *   message_id?:int,
     *   inline_message_id?:string,
     *   text:string,
     *   parse_mode?:string,
     *   entities?:array,
     *   link_preview_options?:array,
     *   reply_markup?:array|string
     * } $params
     */
    public function editMessageText(array $params = []): array
    {
        return $this->request('editMessageText', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#editmessagecaption
     *
     * @param array{
     *   business_connection_id?:string,
     *   chat_id?:int|string,
     *   message_id?:int,
     *   inline_message_id?:string,
     *   caption?:string,
     *   parse_mode?:string,
     *   caption_entities?:array,
     *   show_caption_above_media?:bool,
     *   reply_markup?:array|string
     * } $params
     */
    public function editMessageCaption(array $params = []): array
    {
        return $this->request('editMessageCaption', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#editmessagemedia
     *
     * @param array{
     *   business_connection_id?:string,
     *   chat_id?:int|string,
     *   message_id?:int,
     *   inline_message_id?:string,
     *   media:array,
     *   reply_markup?:array|string
     * } $params
     */
    public function editMessageMedia(array $params = []): array
    {
        return $this->request('editMessageMedia', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#editmessagelivelocation
     */
    public function editMessageLiveLocation(array $params = []): array
    {
        return $this->request('editMessageLiveLocation', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#stopmessagelivelocation
     */
    public function stopMessageLiveLocation(array $params = []): array
    {
        return $this->request('stopMessageLiveLocation', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#editmessagechecklist
     */
    public function editMessageChecklist(array $params = []): array
    {
        return $this->request('editMessageChecklist', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#editmessagereplymarkup
     *
     * @param array{
     *   business_connection_id?:string,
     *   chat_id?:int|string,
     *   message_id?:int,
     *   inline_message_id?:string,
     *   reply_markup?:array|string
     * } $params
     */
    public function editMessageReplyMarkup(array $params = []): array
    {
        return $this->request('editMessageReplyMarkup', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#stoppoll
     */
    public function stopPoll(array $params = []): array
    {
        return $this->request('stopPoll', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#approvesuggestedpost
     */
    public function approveSuggestedPost(array $params = []): array
    {
        return $this->request('approveSuggestedPost', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#declinesuggestedpost
     */
    public function declineSuggestedPost(array $params = []): array
    {
        return $this->request('declineSuggestedPost', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#deletemessage
     *
     * @param array{
     *   chat_id:int|string,
     *   message_id:int
     * } $params
     */
    public function deleteMessage(array $params = []): array
    {
        return $this->request('deleteMessage', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#deletemessages
     */
    public function deleteMessages(array $params = []): array
    {
        return $this->request('deleteMessages', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#deletemessagereaction
     */
    public function deleteMessageReaction(array $params = []): array
    {
        return $this->request('deleteMessageReaction', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#deleteallmessagereactions
     */
    public function deleteAllMessageReactions(array $params = []): array
    {
        return $this->request('deleteAllMessageReactions', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#sendsticker
     *
     * @param array{
     *   chat_id:int|string,
     *   sticker:\CURLFile|string,
     *   message_thread_id?:int,
     *   emoji?:string,
     *   disable_notification?:bool,
     *   protect_content?:bool,
     *   message_effect_id?:string,
     *   reply_parameters?:array,
     *   reply_markup?:array|string
     * } $params
     */
    public function sendSticker(array $params = []): array
    {
        return $this->request('sendSticker', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getstickerset
     */
    public function getStickerSet(array $params = []): array
    {
        return $this->request('getStickerSet', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getcustomemojistickers
     */
    public function getCustomEmojiStickers(array $params = []): array
    {
        return $this->request('getCustomEmojiStickers', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#uploadstickerfile
     */
    public function uploadStickerFile(array $params = []): array
    {
        return $this->request('uploadStickerFile', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#createnewstickerset
     */
    public function createNewStickerSet(array $params = []): array
    {
        return $this->request('createNewStickerSet', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#addstickertoset
     */
    public function addStickerToSet(array $params = []): array
    {
        return $this->request('addStickerToSet', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setstickerpositioninset
     */
    public function setStickerPositionInSet(array $params = []): array
    {
        return $this->request('setStickerPositionInSet', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#deletestickerfromset
     */
    public function deleteStickerFromSet(array $params = []): array
    {
        return $this->request('deleteStickerFromSet', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#replacestickerinset
     */
    public function replaceStickerInSet(array $params = []): array
    {
        return $this->request('replaceStickerInSet', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setstickeremojilist
     */
    public function setStickerEmojiList(array $params = []): array
    {
        return $this->request('setStickerEmojiList', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setstickerkeywords
     */
    public function setStickerKeywords(array $params = []): array
    {
        return $this->request('setStickerKeywords', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setstickermaskposition
     */
    public function setStickerMaskPosition(array $params = []): array
    {
        return $this->request('setStickerMaskPosition', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setstickersettitle
     */
    public function setStickerSetTitle(array $params = []): array
    {
        return $this->request('setStickerSetTitle', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setstickersetthumbnail
     */
    public function setStickerSetThumbnail(array $params = []): array
    {
        return $this->request('setStickerSetThumbnail', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setcustomemojistickersetthumbnail
     */
    public function setCustomEmojiStickerSetThumbnail(array $params = []): array
    {
        return $this->request('setCustomEmojiStickerSetThumbnail', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#deletestickerset
     */
    public function deleteStickerSet(array $params = []): array
    {
        return $this->request('deleteStickerSet', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#answerinlinequery
     *
     * @param array{
     *   inline_query_id:string,
     *   results:array,
     *   cache_time?:int,
     *   is_personal?:bool,
     *   next_offset?:string,
     *   button?:array
     * } $params
     */
    public function answerInlineQuery(array $params = []): array
    {
        return $this->request('answerInlineQuery', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#sendinvoice
     *
     * @param array{
     *   chat_id:int|string,
     *   title:string,
     *   description:string,
     *   payload:string,
     *   provider_token:string,
     *   currency:string,
     *   prices:array,
     *   message_thread_id?:int,
     *   max_tip_amount?:int,
     *   suggested_tip_amounts?:array,
     *   start_parameter?:string,
     *   provider_data?:string,
     *   photo_url?:string,
     *   photo_size?:int,
     *   photo_width?:int,
     *   photo_height?:int,
     *   need_name?:bool,
     *   need_phone_number?:bool,
     *   need_email?:bool,
     *   need_shipping_address?:bool,
     *   send_phone_number_to_provider?:bool,
     *   send_email_to_provider?:bool,
     *   is_flexible?:bool,
     *   disable_notification?:bool,
     *   protect_content?:bool,
     *   message_effect_id?:string,
     *   reply_parameters?:array,
     *   reply_markup?:array|string
     * } $params
     */
    public function sendInvoice(array $params = []): array
    {
        return $this->request('sendInvoice', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#createinvoicelink
     */
    public function createInvoiceLink(array $params = []): array
    {
        return $this->request('createInvoiceLink', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#answershippingquery
     */
    public function answerShippingQuery(array $params = []): array
    {
        return $this->request('answerShippingQuery', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#answerprecheckoutquery
     */
    public function answerPreCheckoutQuery(array $params = []): array
    {
        return $this->request('answerPreCheckoutQuery', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getmystarbalance
     */
    public function getMyStarBalance(array $params = []): array
    {
        return $this->request('getMyStarBalance', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getstartransactions
     */
    public function getStarTransactions(array $params = []): array
    {
        return $this->request('getStarTransactions', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#refundstarpayment
     */
    public function refundStarPayment(array $params = []): array
    {
        return $this->request('refundStarPayment', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#edituserstarsubscription
     */
    public function editUserStarSubscription(array $params = []): array
    {
        return $this->request('editUserStarSubscription', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setpassportdataerrors
     */
    public function setPassportDataErrors(array $params = []): array
    {
        return $this->request('setPassportDataErrors', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#sendgame
     */
    public function sendGame(array $params = []): array
    {
        return $this->request('sendGame', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#setgamescore
     */
    public function setGameScore(array $params = []): array
    {
        return $this->request('setGameScore', $params);
    }

    /**
     * @see https://core.telegram.org/bots/api#getgamehighscores
     */
    public function getGameHighScores(array $params = []): array
    {
        return $this->request('getGameHighScores', $params);
    }

    /**
     * Get webhook update payload from php://input (associative array).
     */
    public function getWebhookUpdate(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Legacy alias (TelegramBotPHP historically used getData()).
     * If no current update is set, it will read from webhook payload.
     */
    public function getData(): array
    {
        if ($this->lastUpdate === []) {
            $this->lastUpdate = $this->getWebhookUpdate();
        }
        return $this->lastUpdate;
    }

    /**
     * Legacy: respond HTTP 200 to Telegram.
     */
    public function respondSuccess(): string
    {
        http_response_code(200);
        return json_encode(['status' => 'success'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{"status":"success"}';
    }

    /**
     * Store a specific update as "current" (useful for polling flows).
     */
    public function setCurrentUpdate(array $update): void
    {
        $this->lastUpdate = $update;
    }

    public function getCurrentUpdate(): array
    {
        return $this->lastUpdate;
    }

    /**
     * Returns the full Update payload currently being processed.
     * This is the full JSON object you receive via webhook or getUpdates().
     *
     * @see https://core.telegram.org/bots/api#update
     */
    public function Update(): array
    {
        return $this->getData();
    }

    /**
     * Return only the sub-payload for a specific update type, if present.
     *
     * For official update fields (e.g. message_reaction) it returns that field.
     * For legacy derived message types (photo, reply, ...) it returns the relevant message sub-field.
     */
    public function UpdatePart(?TelegramUpdateType $type = null): ?array
    {
        $update = $this->getData();
        $type = $type ?? $this->getUpdateType();
        if ($type === null) {
            return null;
        }

        // Official Update fields
        return match ($type) {
            TelegramUpdateType::MESSAGE => $update['message'] ?? null,
            TelegramUpdateType::EDITED_MESSAGE => $update['edited_message'] ?? null,
            TelegramUpdateType::CHANNEL_POST => $update['channel_post'] ?? null,
            TelegramUpdateType::EDITED_CHANNEL_POST => $update['edited_channel_post'] ?? null,

            TelegramUpdateType::BUSINESS_CONNECTION => $update['business_connection'] ?? null,
            TelegramUpdateType::BUSINESS_MESSAGE => $update['business_message'] ?? null,
            TelegramUpdateType::EDITED_BUSINESS_MESSAGE => $update['edited_business_message'] ?? null,
            TelegramUpdateType::DELETED_BUSINESS_MESSAGES => $update['deleted_business_messages'] ?? null,
            TelegramUpdateType::GUEST_MESSAGE => $update['guest_message'] ?? null,

            TelegramUpdateType::MESSAGE_REACTION => $update['message_reaction'] ?? null,
            TelegramUpdateType::MESSAGE_REACTION_COUNT => $update['message_reaction_count'] ?? null,

            TelegramUpdateType::INLINE_QUERY => $update['inline_query'] ?? null,
            TelegramUpdateType::CHOSEN_INLINE_RESULT => $update['chosen_inline_result'] ?? null,
            TelegramUpdateType::CALLBACK_QUERY => $update['callback_query'] ?? null,

            TelegramUpdateType::SHIPPING_QUERY => $update['shipping_query'] ?? null,
            TelegramUpdateType::PRE_CHECKOUT_QUERY => $update['pre_checkout_query'] ?? null,
            TelegramUpdateType::PURCHASED_PAID_MEDIA => $update['purchased_paid_media'] ?? null,

            TelegramUpdateType::POLL => $update['poll'] ?? null,
            TelegramUpdateType::POLL_ANSWER => $update['poll_answer'] ?? null,

            TelegramUpdateType::MY_CHAT_MEMBER => $update['my_chat_member'] ?? null,
            TelegramUpdateType::CHAT_MEMBER => $update['chat_member'] ?? null,
            TelegramUpdateType::CHAT_JOIN_REQUEST => $update['chat_join_request'] ?? null,
            TelegramUpdateType::CHAT_BOOST => $update['chat_boost'] ?? null,
            TelegramUpdateType::REMOVED_CHAT_BOOST => $update['removed_chat_boost'] ?? null,
            TelegramUpdateType::MANAGED_BOT => $update['managed_bot'] ?? null,

            // Legacy derived message types (message.*)
            TelegramUpdateType::REPLY => $update['message']['reply_to_message'] ?? null,
            TelegramUpdateType::PHOTO => $update['message']['photo'] ?? null,
            TelegramUpdateType::VIDEO => $update['message']['video'] ?? null,
            TelegramUpdateType::AUDIO => $update['message']['audio'] ?? null,
            TelegramUpdateType::VOICE => $update['message']['voice'] ?? null,
            TelegramUpdateType::ANIMATION => $update['message']['animation'] ?? null,
            TelegramUpdateType::STICKER => $update['message']['sticker'] ?? null,
            TelegramUpdateType::DOCUMENT => $update['message']['document'] ?? null,
            TelegramUpdateType::LOCATION => $update['message']['location'] ?? null,
            TelegramUpdateType::CONTACT => $update['message']['contact'] ?? null,
            TelegramUpdateType::NEW_CHAT_MEMBER => $update['message']['new_chat_member'] ?? null,
            TelegramUpdateType::LEFT_CHAT_MEMBER => $update['message']['left_chat_member'] ?? null,
        };
    }

    // Convenience accessors (return the raw JSON sub-object)
    public function Message(): ?array
    {
        return $this->getData()['message'] ?? null;
    }
    public function EditedMessage(): ?array
    {
        return $this->getData()['edited_message'] ?? null;
    }
    public function ChannelPost(): ?array
    {
        return $this->getData()['channel_post'] ?? null;
    }
    public function EditedChannelPost(): ?array
    {
        return $this->getData()['edited_channel_post'] ?? null;
    }
    public function BusinessConnection(): ?array
    {
        return $this->getData()['business_connection'] ?? null;
    }
    public function BusinessMessage(): ?array
    {
        return $this->getData()['business_message'] ?? null;
    }
    public function EditedBusinessMessage(): ?array
    {
        return $this->getData()['edited_business_message'] ?? null;
    }
    public function DeletedBusinessMessages(): ?array
    {
        return $this->getData()['deleted_business_messages'] ?? null;
    }
    public function GuestMessage(): ?array
    {
        return $this->getData()['guest_message'] ?? null;
    }
    public function MessageReaction(): ?array
    {
        return $this->getData()['message_reaction'] ?? null;
    }
    public function MessageReactionCount(): ?array
    {
        return $this->getData()['message_reaction_count'] ?? null;
    }
    public function InlineQuery(): ?array
    {
        return $this->getData()['inline_query'] ?? null;
    }
    public function ChosenInlineResult(): ?array
    {
        return $this->getData()['chosen_inline_result'] ?? null;
    }
    public function CallbackQuery(): ?array
    {
        return $this->getData()['callback_query'] ?? null;
    }

    /** @deprecated Use CallbackQuery() */
    public function Callback_Query(): ?array
    {
        return $this->CallbackQuery();
    }

    public function Callback_ID(): ?string
    {
        $cq = $this->CallbackQuery();

        return is_array($cq) ? ($cq['id'] ?? null) : null;
    }

    public function Callback_Data(): ?string
    {
        $cq = $this->CallbackQuery();

        return is_array($cq) ? ($cq['data'] ?? null) : null;
    }

    public function Callback_ChatID(): int|string|null
    {
        $cq = $this->CallbackQuery();
        if (!is_array($cq)) {
            return null;
        }

        return $cq['message']['chat']['id'] ?? null;
    }

    public function messageFromGroup(): bool
    {
        $chat = $this->Chat();
        $type = $chat['type'] ?? null;

        return $type === 'group' || $type === 'supergroup';
    }
    public function ShippingQuery(): ?array
    {
        return $this->getData()['shipping_query'] ?? null;
    }
    public function PreCheckoutQuery(): ?array
    {
        return $this->getData()['pre_checkout_query'] ?? null;
    }
    public function PurchasedPaidMedia(): ?array
    {
        return $this->getData()['purchased_paid_media'] ?? null;
    }
    public function Poll(): ?array
    {
        return $this->getData()['poll'] ?? null;
    }
    public function PollAnswer(): ?array
    {
        return $this->getData()['poll_answer'] ?? null;
    }
    public function MyChatMember(): ?array
    {
        return $this->getData()['my_chat_member'] ?? null;
    }
    public function ChatMember(): ?array
    {
        return $this->getData()['chat_member'] ?? null;
    }
    public function ChatJoinRequest(): ?array
    {
        return $this->getData()['chat_join_request'] ?? null;
    }
    public function ChatBoost(): ?array
    {
        return $this->getData()['chat_boost'] ?? null;
    }
    public function RemovedChatBoost(): ?array
    {
        return $this->getData()['removed_chat_boost'] ?? null;
    }
    public function ManagedBot(): ?array
    {
        return $this->getData()['managed_bot'] ?? null;
    }

    /**
     * Legacy: setData()
     */
    public function setData(array $data): void
    {
        $this->setCurrentUpdate($data);
    }

    /**
     * Convenience helper for long polling.
     *
     * @param int $offset Identifier of the first update to be returned
     * @param int $limit Limits the number of updates (1-100)
     * @param int $timeout Timeout in seconds for long polling
     * @param bool $confirm If true, confirms updates by requesting offset=last_update_id+1
     */
    public function getUpdates(int $offset = 0, int $limit = 100, int $timeout = 0, bool $confirm = true): array
    {
        $resp = $this->request('getUpdates', [
            'offset' => $offset,
            'limit' => $limit,
            'timeout' => $timeout,
        ]);
        $this->lastUpdatesResponse = $resp;

        if ($confirm) {
            $results = $resp['result'] ?? null;
            if (is_array($results) && count($results) > 0) {
                $last = $results[count($results) - 1];
                $lastUpdateId = is_array($last) ? ($last['update_id'] ?? null) : null;
                if (is_int($lastUpdateId)) {
                    $this->request('getUpdates', ['offset' => $lastUpdateId + 1, 'limit' => 1, 'timeout' => $timeout]);
                }
            }
        }

        return $resp;
    }

    public function updateCount(): int
    {
        $results = $this->lastUpdatesResponse['result'] ?? [];
        return is_array($results) ? count($results) : 0;
    }

    public function serveUpdate(int $index): void
    {
        $results = $this->lastUpdatesResponse['result'] ?? [];
        if (!is_array($results) || !array_key_exists($index, $results) || !is_array($results[$index])) {
            $this->lastUpdate = [];
            return;
        }
        $this->lastUpdate = $results[$index];
    }

    /**
     * Legacy helper: attempt to detect update type.
     */
    public function getUpdateType(): ?TelegramUpdateType
    {
        $update = $this->getData();

        // Official Update fields (newer ones first so they win)
        if (isset($update['edited_channel_post'])) {
            return TelegramUpdateType::EDITED_CHANNEL_POST;
        }
        if (isset($update['business_connection'])) {
            return TelegramUpdateType::BUSINESS_CONNECTION;
        }
        if (isset($update['business_message'])) {
            return TelegramUpdateType::BUSINESS_MESSAGE;
        }
        if (isset($update['edited_business_message'])) {
            return TelegramUpdateType::EDITED_BUSINESS_MESSAGE;
        }
        if (isset($update['deleted_business_messages'])) {
            return TelegramUpdateType::DELETED_BUSINESS_MESSAGES;
        }
        if (isset($update['guest_message'])) {
            return TelegramUpdateType::GUEST_MESSAGE;
        }
        if (isset($update['message_reaction'])) {
            return TelegramUpdateType::MESSAGE_REACTION;
        }
        if (isset($update['message_reaction_count'])) {
            return TelegramUpdateType::MESSAGE_REACTION_COUNT;
        }
        if (isset($update['chosen_inline_result'])) {
            return TelegramUpdateType::CHOSEN_INLINE_RESULT;
        }
        if (isset($update['shipping_query'])) {
            return TelegramUpdateType::SHIPPING_QUERY;
        }
        if (isset($update['pre_checkout_query'])) {
            return TelegramUpdateType::PRE_CHECKOUT_QUERY;
        }
        if (isset($update['purchased_paid_media'])) {
            return TelegramUpdateType::PURCHASED_PAID_MEDIA;
        }
        if (isset($update['poll'])) {
            return TelegramUpdateType::POLL;
        }
        if (isset($update['poll_answer'])) {
            return TelegramUpdateType::POLL_ANSWER;
        }
        if (isset($update['chat_member'])) {
            return TelegramUpdateType::CHAT_MEMBER;
        }
        if (isset($update['chat_join_request'])) {
            return TelegramUpdateType::CHAT_JOIN_REQUEST;
        }
        if (isset($update['chat_boost'])) {
            return TelegramUpdateType::CHAT_BOOST;
        }
        if (isset($update['removed_chat_boost'])) {
            return TelegramUpdateType::REMOVED_CHAT_BOOST;
        }
        if (isset($update['managed_bot'])) {
            return TelegramUpdateType::MANAGED_BOT;
        }

        if (isset($update['inline_query'])) {
            return TelegramUpdateType::INLINE_QUERY;
        }
        if (isset($update['callback_query'])) {
            return TelegramUpdateType::CALLBACK_QUERY;
        }
        if (isset($update['edited_message'])) {
            return TelegramUpdateType::EDITED_MESSAGE;
        }
        if (isset($update['channel_post'])) {
            return TelegramUpdateType::CHANNEL_POST;
        }
        if (isset($update['my_chat_member'])) {
            return TelegramUpdateType::MY_CHAT_MEMBER;
        }
        if (isset($update['message']['reply_to_message'])) {
            return TelegramUpdateType::REPLY;
        }
        if (isset($update['message']['text'])) {
            return TelegramUpdateType::MESSAGE;
        }
        if (isset($update['message']['photo'])) {
            return TelegramUpdateType::PHOTO;
        }
        if (isset($update['message']['video'])) {
            return TelegramUpdateType::VIDEO;
        }
        if (isset($update['message']['audio'])) {
            return TelegramUpdateType::AUDIO;
        }
        if (isset($update['message']['voice'])) {
            return TelegramUpdateType::VOICE;
        }
        if (isset($update['message']['contact'])) {
            return TelegramUpdateType::CONTACT;
        }
        if (isset($update['message']['location'])) {
            return TelegramUpdateType::LOCATION;
        }
        if (isset($update['message']['animation'])) {
            return TelegramUpdateType::ANIMATION;
        }
        if (isset($update['message']['sticker'])) {
            return TelegramUpdateType::STICKER;
        }
        if (isset($update['message']['document'])) {
            return TelegramUpdateType::DOCUMENT;
        }
        if (isset($update['message']['new_chat_member'])) {
            return TelegramUpdateType::NEW_CHAT_MEMBER;
        }
        if (isset($update['message']['left_chat_member'])) {
            return TelegramUpdateType::LEFT_CHAT_MEMBER;
        }

        return null;
    }


    public function Text(): ?string
    {
        $data = $this->getData();
        $type = $this->getUpdateType();
        if ($type === TelegramUpdateType::CALLBACK_QUERY) {
            return $data['callback_query']['data'] ?? null;
        }
        if ($type === TelegramUpdateType::CHANNEL_POST) {
            return $data['channel_post']['text'] ?? null;
        }
        if ($type === TelegramUpdateType::EDITED_MESSAGE) {
            return $data['edited_message']['text'] ?? null;
        }
        return $data['message']['text'] ?? null;
    }

    public function Caption(): ?string
    {
        $data = $this->getData();
        $type = $this->getUpdateType();
        if ($type === TelegramUpdateType::CHANNEL_POST) {
            return $data['channel_post']['caption'] ?? null;
        }
        return $data['message']['caption'] ?? null;
    }

    public function Chat(): array
    {
        $data = $this->getData();
        $type = $this->getUpdateType();
        if ($type === TelegramUpdateType::CALLBACK_QUERY) {
            return $data['callback_query']['message']['chat'] ?? [];
        }
        if ($type === TelegramUpdateType::CHANNEL_POST) {
            return $data['channel_post']['chat'] ?? [];
        }
        if ($type === TelegramUpdateType::EDITED_MESSAGE) {
            return $data['edited_message']['chat'] ?? [];
        }
        if ($type === TelegramUpdateType::INLINE_QUERY) {
            return $data['inline_query']['from'] ?? [];
        }
        if ($type === TelegramUpdateType::MY_CHAT_MEMBER) {
            return $data['my_chat_member']['chat'] ?? [];
        }
        return $data['message']['chat'] ?? [];
    }

    public function ChatID(): int|string|null
    {
        $chat = $this->Chat();
        return $chat['id'] ?? null;
    }

    public function MessageID(): ?int
    {
        $data = $this->getData();
        $type = $this->getUpdateType();
        if ($type === TelegramUpdateType::CALLBACK_QUERY) {
            return $data['callback_query']['message']['message_id'] ?? null;
        }
        if ($type === TelegramUpdateType::CHANNEL_POST) {
            return $data['channel_post']['message_id'] ?? null;
        }
        if ($type === TelegramUpdateType::EDITED_MESSAGE) {
            return $data['edited_message']['message_id'] ?? null;
        }
        return $data['message']['message_id'] ?? null;
    }

    public function UserID(): int|string|null
    {
        $data = $this->getData();
        $type = $this->getUpdateType();
        if ($type === TelegramUpdateType::CALLBACK_QUERY) {
            return $data['callback_query']['from']['id'] ?? null;
        }
        if ($type === TelegramUpdateType::CHANNEL_POST) {
            return $data['channel_post']['from']['id'] ?? null;
        }
        if ($type === TelegramUpdateType::EDITED_MESSAGE) {
            return $data['edited_message']['from']['id'] ?? null;
        }
        if ($type === TelegramUpdateType::INLINE_QUERY) {
            return $data['inline_query']['from']['id'] ?? null;
        }
        return $data['message']['from']['id'] ?? null;
    }

    public function FirstName(): ?string
    {
        $data = $this->getData();
        $type = $this->getUpdateType();
        if ($type === TelegramUpdateType::CALLBACK_QUERY) {
            return $data['callback_query']['from']['first_name'] ?? null;
        }
        if ($type === TelegramUpdateType::CHANNEL_POST) {
            return $data['channel_post']['from']['first_name'] ?? null;
        }
        if ($type === TelegramUpdateType::EDITED_MESSAGE) {
            return $data['edited_message']['from']['first_name'] ?? null;
        }
        return $data['message']['from']['first_name'] ?? null;
    }

    public function LastName(): ?string
    {
        $data = $this->getData();
        $type = $this->getUpdateType();
        if ($type === TelegramUpdateType::CALLBACK_QUERY) {
            return $data['callback_query']['from']['last_name'] ?? null;
        }
        if ($type === TelegramUpdateType::CHANNEL_POST) {
            return $data['channel_post']['from']['last_name'] ?? null;
        }
        if ($type === TelegramUpdateType::EDITED_MESSAGE) {
            return $data['edited_message']['from']['last_name'] ?? null;
        }
        return $data['message']['from']['last_name'] ?? null;
    }

    public function Username(): ?string
    {
        $data = $this->getData();
        $type = $this->getUpdateType();
        if ($type === TelegramUpdateType::CALLBACK_QUERY) {
            return $data['callback_query']['from']['username'] ?? null;
        }
        if ($type === TelegramUpdateType::CHANNEL_POST) {
            return $data['channel_post']['from']['username'] ?? null;
        }
        if ($type === TelegramUpdateType::EDITED_MESSAGE) {
            return $data['edited_message']['from']['username'] ?? null;
        }
        return $data['message']['from']['username'] ?? null;
    }

    /**
     * Download a file from Telegram file API using file_path from getFile().
     */
    public function downloadFile(string $telegramFilePath, string $localFilePath): void
    {
        $fileUrl = self::FILE_BASE . '/bot' . $this->botToken . '/' . ltrim($telegramFilePath, '/');
        $in = fopen($fileUrl, 'rb');
        if (!$in) {
            throw new \RuntimeException('Failed to open Telegram file stream');
        }
        $out = fopen($localFilePath, 'wb');
        if (!$out) {
            fclose($in);
            throw new \RuntimeException('Failed to open local file for writing');
        }

        while (!feof($in)) {
            $chunk = fread($in, 8192);
            if ($chunk === false) {
                break;
            }
            fwrite($out, $chunk);
        }

        fclose($in);
        fclose($out);
    }

    private function sendCurl(string $url, array $params, bool $multipart): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeoutSeconds);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeoutSeconds);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        // Proxy support
        if (!empty($this->proxy)) {
            if (array_key_exists('type', $this->proxy)) {
                curl_setopt($ch, CURLOPT_PROXYTYPE, $this->proxy['type']);
            }
            if (array_key_exists('auth', $this->proxy)) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->proxy['auth']);
            }
            if (array_key_exists('url', $this->proxy)) {
                curl_setopt($ch, CURLOPT_PROXY, $this->proxy['url']);
            }
            if (array_key_exists('port', $this->proxy)) {
                curl_setopt($ch, CURLOPT_PROXYPORT, $this->proxy['port']);
            }
        }

        curl_setopt($ch, CURLOPT_POST, 1);

        if ($multipart) {
            // For uploads, pass array with CURLFile(s). curl will set multipart boundary.
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        } else {
            $body = json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body === false ? '{}' : $body);
        }

        $result = curl_exec($ch);
        if ($result === false) {
            $result = json_encode([
                'ok' => false,
                'curl_error_code' => curl_errno($ch),
                'curl_error' => curl_error($ch),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        curl_close($ch);

        return (string)$result;
    }

    private function hasUpload(mixed $value): bool
    {
        if ($value instanceof \CURLFile) {
            return true;
        }
        if (is_array($value)) {
            foreach ($value as $v) {
                if ($this->hasUpload($v)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Convert local file paths into CURLFile objects for multipart uploads.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function normalizeUploads(array $params): array
    {
        $uploadKeys = [
            'photo',
            'audio',
            'document',
            'video',
            'animation',
            'voice',
            'video_note',
            'sticker',
            'thumbnail',
            'certificate',
        ];

        foreach ($params as $key => $value) {
            if (is_string($value) && is_file($value) && in_array((string) $key, $uploadKeys, true)) {
                $params[$key] = $this->curlFileCreate($value);
                continue;
            }

            if ($key === 'media' && is_array($value)) {
                foreach ($value as $i => $item) {
                    if (is_array($item)) {
                        $params['media'][$i] = $this->normalizeUploads($item);
                    }
                }
                continue;
            }

            if (is_array($value)) {
                $params[$key] = $this->normalizeUploads($value);
            }
        }

        return $params;
    }

    private function redact(array $params): array
    {
        // Keep it conservative: avoid dumping file paths/binaries to logs.
        $redacted = [];
        foreach ($params as $k => $v) {
            if ($v instanceof CURLFile) {
                $redacted[$k] = '[file]';
                continue;
            }
            if (is_array($v)) {
                $redacted[$k] = $this->redact($v);
                continue;
            }
            $redacted[$k] = $v;
        }
        return $redacted;
    }
}
