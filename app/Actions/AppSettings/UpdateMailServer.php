<?php

namespace App\Actions\AppSettings;

use Illuminate\Support\Facades\Artisan;
use RuntimeException;

/**
 * Saves the mail server (MAIL_MAILER and the SMTP connection) to the `.env` file, then makes the change take
 * effect: in this request, on later requests (clearing a cached config) and in the queue worker (restarting it).
 */
class UpdateMailServer
{
    /**
     * The mailers the screen offers, and the label of each.
     */
    public const MAILERS = ['smtp' => 'SMTP server', 'sendmail' => 'Sendmail', 'log' => 'Log file (emails are not sent)'];

    /**
     * The SMTP connection types, and the label of each. Empty uses STARTTLS when the server offers it.
     */
    public const SCHEMES = ['' => 'STARTTLS (usually port 587)', 'smtps' => 'SSL/TLS (usually port 465)'];

    /**
     * The `.env` variables this action writes, and the config key each one feeds.
     */
    private const CONFIG_KEYS = [
        'MAIL_MAILER' => 'mail.default',
        'MAIL_HOST' => 'mail.mailers.smtp.host',
        'MAIL_PORT' => 'mail.mailers.smtp.port',
        'MAIL_SCHEME' => 'mail.mailers.smtp.scheme',
        'MAIL_USERNAME' => 'mail.mailers.smtp.username',
        'MAIL_PASSWORD' => 'mail.mailers.smtp.password',
    ];

    public function __construct(private WriteEnvironmentFile $writeEnvironmentFile) {}

    /**
     * Get the mail server as it is configured now, for filling the form. The password is left out.
     *
     * @return array{mailer: string, host: string, port: string, scheme: string, username: string, hasPassword: bool}
     */
    public static function current(): array
    {
        return [
            'mailer' => (string) config('mail.default'),
            'host' => (string) config('mail.mailers.smtp.host'),
            'port' => (string) config('mail.mailers.smtp.port'),
            'scheme' => (string) config('mail.mailers.smtp.scheme'),
            'username' => (string) config('mail.mailers.smtp.username'),
            'hasPassword' => filled(config('mail.mailers.smtp.password')),
        ];
    }

    /**
     * Write the mail server to `.env`, one variable per field. Empty fields are written as `null`, except
     * the password: an empty password keeps the saved one.
     *
     * @param  array{mailer: string, host?: string|null, port?: int|string|null, scheme?: string|null, username?: string|null, password?: string|null}  $values
     *
     * @throws RuntimeException When the `.env` file cannot be written.
     */
    public function __invoke(array $values): void
    {
        $variables = [
            'MAIL_MAILER' => $values['mailer'],
            'MAIL_HOST' => filled($values['host'] ?? null) ? $values['host'] : null,
            'MAIL_PORT' => filled($values['port'] ?? null) ? (string) $values['port'] : null,
            'MAIL_SCHEME' => filled($values['scheme'] ?? null) ? $values['scheme'] : null,
            'MAIL_USERNAME' => filled($values['username'] ?? null) ? $values['username'] : null,
        ];

        if (filled($values['password'] ?? null)) {
            $variables['MAIL_PASSWORD'] = $values['password'];
        }

        ($this->writeEnvironmentFile)($variables);

        $this->applyToConfig($variables);

        if (app()->configurationIsCached()) {
            Artisan::call('config:clear');
        }

        Artisan::call('queue:restart');
    }

    /**
     * Use the new values for the rest of this request, so a test email sent straight away uses them.
     *
     * @param  array<string, string|null>  $variables
     */
    private function applyToConfig(array $variables): void
    {
        foreach ($variables as $variable => $value) {
            config([self::CONFIG_KEYS[$variable] => $variable === 'MAIL_PORT' && $value !== null ? (int) $value : $value]);
        }

        if (app()->resolved('mail.manager')) {
            app('mail.manager')->forgetMailers();
        }
    }
}
