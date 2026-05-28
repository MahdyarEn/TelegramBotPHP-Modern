<?php

declare(strict_types=1);

/**
 * Telegram API Exception.
 *
 * Kept in this file to preserve the upstream two-file style:
 * - Telegram.php
 * - TelegramErrorLogger.php
 */
if (!class_exists('TelegramApiException', false)) {
    class TelegramApiException extends \RuntimeException
    {
        public function __construct(
            public readonly string $method,
            public readonly array $payload,
            public readonly array $response,
            ?string $message = null,
            int $code = 0,
            ?\Throwable $previous = null
        ) {
            $description = $response['description'] ?? null;
            $effectiveMessage = $message
                ?? ($description ? "Telegram API error: {$description}" : 'Telegram API error');

            parent::__construct($effectiveMessage, $code, $previous);
        }
    }
}

/**
 * Telegram Error Logger Class.
 *
 * @author shakibonline <shakiba_9@yahoo.com>
 */
class TelegramErrorLogger
{
    private static ?self $self = null;

    /// Log request and response parameters from/to Telegram API

    /**
     * Prints the list of parameters from/to Telegram's API endpoint
     * \param $result the Telegram's response as array
     * \param $content the request parameters as array.
     */
    public static function log(array $result, array $content, bool $use_rt = true): void
    {
        try {
            if (($result['ok'] ?? true) === false) {
                self::$self = self::$self ?? new self();
                $e = new \Exception();
                $error = PHP_EOL;
                $error .= '==========[Response]==========';
                $error .= "\n";
                foreach ($result as $key => $value) {
                    if (is_bool($value)) {
                        $error .= $key . ":\t\t\t" . ($value ? 'True' : 'False') . "\n";
                        continue;
                    }
                    if (is_scalar($value) || $value === null) {
                        $error .= $key . ":\t\t" . (string) $value . "\n";
                        continue;
                    }
                    $error .= $key . ":\t\t" . json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
                }
                $array = '=========[Sent Data]==========';
                $array .= "\n";
                if ($use_rt == true) {
                    foreach ($content as $item) {
                        $array .= self::$self->rt($item).PHP_EOL.PHP_EOL;
                    }
                } else {
                    foreach ($content as $key => $value) {
                        $array .= $key.":\t\t".$value."\n";
                    }
                }
                $backtrace = '============[Trace]===========';
                $backtrace .= "\n";
                $backtrace .= $e->getTraceAsString();
                self::$self->_log_to_file($error.$array.$backtrace);
            }
        } catch (\Throwable) {
            // Best-effort logging only.
        }
    }

    /// Write a string in the log file adding the current server time

    /**
     * Write a string in the log file TelegramErrorLogger.txt adding the current server time
     * \param $error_text the text to append in the log.
     */
    private function _log_to_file(string $error_text): void
    {
        try {
            $dir_name = 'logs';
            if (!is_dir($dir_name)) {
                mkdir($dir_name, 0777, true);
            }
            $fileName = $dir_name.'/'.__CLASS__.'-'.date('Y-m-d').'.txt';
            $myFile = fopen($fileName, 'a+');
            if (!$myFile) {
                return;
            }
            $date = '============[Date]============';
            $date .= "\n";
            $date .= '[ '.date('Y-m-d H:i:s  e').' ] ';
            fwrite($myFile, $date.$error_text."\n\n");
            fclose($myFile);
        } catch (\Throwable) {
            // Best-effort logging only.
        }
    }

    private function rt(array $array, ?string $title = null, bool $head = true): string
    {
        $ref = 'ref';
        $text = '';
        if ($head) {
            $text = "[$ref]";
            $text .= "\n";
        }
        foreach ($array as $key => $value) {
            if ($value instanceof \CURLFile) {
                $text .= $ref.'.'.$key.'= File'.PHP_EOL;
            } elseif (is_array($value)) {
                if ($title != null) {
                    $key = $title.'.'.$key;
                }
                $text .= self::rt($value, $key, false);
            } else {
                if (is_bool($value)) {
                    $value = ($value) ? 'true' : 'false';
                }
                if ($title != '') {
                    $text .= $ref.'.'.$title.'.'.$key.'= '.$value.PHP_EOL;
                } else {
                    $text .= $ref.'.'.$key.'= '.$value.PHP_EOL;
                }
            }
        }

        return $text;
    }
}
