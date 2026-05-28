# TelegramBotPHP (Modern PHP 8+ Fork)

A lightweight, dependency-free PHP client for the Telegram Bot API.

This project is a **modernized PHP 8.1+ fork** of [https://github.com/Eleirbag89/TelegramBotPHP](https://github.com/Eleirbag89/TelegramBotPHP).

It keeps the original minimal structure while adding modern PHP features, type safety, and improved Telegram Bot API compatibility.

---

## ✨ Features

* PHP 8.1+ support (strict types, type hints)
* Full Telegram Bot API wrapper
* IDE-friendly method support + `__call()` fallback
* Zero dependencies (no Composer required)
* File upload support via `CURLFile` / `curlFileCreate()` (local paths are auto-converted)
* Optional error logging system
* Supports both Webhook and Long Polling
* Lightweight and minimal architecture

---

## 📦 Requirements

* PHP 8.1+
* cURL extension enabled
* Bot token from Telegram Bot API (via @BotFather)

---

## 🚀 Installation (No Composer)

Copy these files into your project:

```
Telegram.php
TelegramErrorLogger.php
```

Then include the main class:

```php
require_once __DIR__ . '/Telegram.php';

$telegram = new Telegram('YOUR_BOT_TOKEN');
```

---

## 🧾 Error Logging (Optional)

If enabled, failed API requests are logged into:

```
./logs/TelegramErrorLogger-YYYY-MM-DD.txt
```

Includes:

* Request payload
* API response
* Error details

---

## ⚡ Quick Start

### Send a message

```php
require_once __DIR__ . '/Telegram.php';

$telegram = new Telegram('YOUR_BOT_TOKEN');

$telegram->sendMessage([
    'chat_id' => 123456789,
    'text' => 'Hello from PHP 🚀',
]);
```

---

### Send a photo

```php
require_once __DIR__ . '/Telegram.php';

$telegram = new Telegram('YOUR_BOT_TOKEN');

// Option A: use the built-in helper
$photo = $telegram->curlFileCreate(__DIR__ . '/test.png', 'image/png');

// Option B: pass a local file path (auto-converted to CURLFile)
$telegram->sendPhoto([
    'chat_id' => 123456789,
    'photo' => __DIR__ . '/test.png',
]);
```

---

## 🌐 Webhook Mode (Base Bot Flow)

The **recommended base entry point for building bots** is:

```
examples/core/webhook.php
```

This file contains the **core routing logic** for handling all Telegram updates using Webhook mode.

It is considered the **main and recommended base structure** for building bots on top of this library.

---

### Set webhook

```
https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://your-domain.com/path/examples/core/webhook.php
```

---

### Update handling

Inside `webhook.php`:

* `Update()` → full payload from Telegram
* `getUpdateType()` → detects update type
* `UpdatePart($type)` → extracts relevant data (message, callback, inline, etc.)

---

## 🔁 Long Polling Mode

Run:

```
examples/core/polling.php
```

* Stores offset in `.offset`
* Prevents duplicate updates
* Useful for local development

---

## 🤖 Example Bots

The example bots included in this repository:

```
examples/bots/gamebot.php
examples/bots/cowsay.php
```

are **updated versions of the original example bots from the upstream repository**:
[https://github.com/Eleirbag89/TelegramBotPHP](https://github.com/Eleirbag89/TelegramBotPHP)

They have been modernized for PHP 8+ compatibility and improved API usage while keeping the same logic and structure.

---

## 🔐 Webhook Security (Recommended)

* Use `secret_token` in `setWebhook`
* Validate header: `X-Telegram-Bot-Api-Secret-Token`
* Protect endpoint (rate limiting / firewall)
* Always return HTTP 200 quickly

---

## 📌 Project Philosophy

A simple Telegram Bot API wrapper without framework overhead.

Focused on:

* Simplicity
* Minimal dependencies
* Easy customization
* Modern PHP compatibility

---

## 📂 Project Structure

```
TelegramBotPHP/
├── Telegram.php
├── TelegramErrorLogger.php
├── examples/
│   ├── core/
│   │   ├── webhook.php   <-- Base bot entry point (recommended)
│   │   └── polling.php
│   └── bots/
│       ├── gamebot.php
│       └── cowsay.php
└── logs/
```

---

## 🙏 Attribution

This project is based on:
[https://github.com/Eleirbag89/TelegramBotPHP](https://github.com/Eleirbag89/TelegramBotPHP) (MIT License)

Original work is extended and modernized for PHP 8+ and newer Telegram Bot API features.

---

## 📄 License

MIT License — see LICENSE.md.
