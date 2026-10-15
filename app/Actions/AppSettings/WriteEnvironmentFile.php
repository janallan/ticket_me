<?php

namespace App\Actions\AppSettings;

use InvalidArgumentException;
use RuntimeException;

/**
 * Sets variables in the `.env` file: each existing `KEY=` line is replaced in place, and missing keys are
 * appended. Comments, other lines and the file's line endings are kept. Only the keys in WRITABLE_KEYS can
 * be written, so the app can never change `APP_KEY`, the database credentials or anything else.
 */
class WriteEnvironmentFile
{
    /**
     * The only `.env` variables the app may write.
     *
     * @var list<string>
     */
    public const WRITABLE_KEYS = [
        'MAIL_MAILER',
        'MAIL_HOST',
        'MAIL_PORT',
        'MAIL_SCHEME',
        'MAIL_USERNAME',
        'MAIL_PASSWORD',
    ];

    /**
     * @param  array<string, string|null>  $variables  Variable names and their new values; null writes `null`.
     *
     * @throws InvalidArgumentException When a key is not in WRITABLE_KEYS, or a value has a line break.
     * @throws RuntimeException When the file is missing or not writable.
     */
    public function __invoke(array $variables): void
    {
        $lines = [];

        foreach ($variables as $key => $value) {
            if (! in_array($key, self::WRITABLE_KEYS, true)) {
                throw new InvalidArgumentException("[{$key}] is not a writable environment variable.");
            }

            $lines[$key] = $key.'='.self::formatValue($key, $value);
        }

        $path = app()->environmentFilePath();

        if (! is_file($path) || ! is_writable($path)) {
            throw new RuntimeException(__('The .env file is missing or not writable by the web server.'));
        }

        $contents = (string) file_get_contents($path);
        $lineEnding = str_contains($contents, "\r\n") ? "\r\n" : "\n";

        foreach ($lines as $key => $line) {
            $pattern = '/^'.preg_quote($key, '/').'=[^\r\n]*/m';

            $contents = preg_match($pattern, $contents) === 1
                ? (string) preg_replace_callback($pattern, fn () => $line, $contents)
                : rtrim($contents, "\r\n").$lineEnding.$line.$lineEnding;
        }

        file_put_contents($path, $contents, LOCK_EX);
    }

    /**
     * Format a value so dotenv reads it back unchanged: simple values as they are, anything else in double
     * quotes with `\`, `"` and `$` escaped (so a `$` is never read as a variable).
     */
    public static function formatValue(string $key, ?string $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (preg_match('/[\r\n\0]/', $value) === 1) {
            throw new InvalidArgumentException("The value for [{$key}] cannot contain line breaks.");
        }

        if (preg_match('/^[A-Za-z0-9_.\-@:\/]+$/', $value) === 1) {
            return $value;
        }

        return '"'.addcslashes($value, '\\"$').'"';
    }
}
