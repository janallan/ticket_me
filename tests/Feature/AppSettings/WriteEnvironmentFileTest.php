<?php

namespace Tests\Feature\AppSettings;

use App\Actions\AppSettings\WriteEnvironmentFile;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class WriteEnvironmentFileTest extends TestCase
{
    public function test_existing_keys_are_replaced_in_place_and_missing_ones_appended(): void
    {
        $path = $this->temporaryEnvironmentFile("APP_NAME=Ticket\r\n# MAIL_HOST=commented\r\nMAIL_HOST=127.0.0.1\r\nMAIL_PORT=2525\r\n");

        app(WriteEnvironmentFile::class)(['MAIL_HOST' => 'smtp.acme.test', 'MAIL_USERNAME' => null]);

        $this->assertSame(
            "APP_NAME=Ticket\r\n# MAIL_HOST=commented\r\nMAIL_HOST=smtp.acme.test\r\nMAIL_PORT=2525\r\nMAIL_USERNAME=null\r\n",
            file_get_contents($path),
        );
    }

    public function test_values_with_special_characters_read_back_unchanged(): void
    {
        $path = $this->temporaryEnvironmentFile("MAIL_PASSWORD=old\n");
        $password = 'p@ss "w#rd" $HOME \\n ${APP_KEY} \'quoted\'';

        app(WriteEnvironmentFile::class)(['MAIL_PASSWORD' => $password, 'MAIL_USERNAME' => 'Acme Support']);

        $env = Dotenv::parse((string) file_get_contents($path));

        $this->assertSame($password, $env['MAIL_PASSWORD']);
        $this->assertSame('Acme Support', $env['MAIL_USERNAME']);
    }

    public function test_line_breaks_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        WriteEnvironmentFile::formatValue('MAIL_PASSWORD', "secret\nAPP_DEBUG=true");
    }

    public function test_keys_outside_the_allowlist_are_rejected_before_anything_is_written(): void
    {
        $path = $this->temporaryEnvironmentFile("APP_KEY=base64:keep-me\nMAIL_HOST=127.0.0.1\n");

        try {
            app(WriteEnvironmentFile::class)(['MAIL_HOST' => 'smtp.acme.test', 'APP_KEY' => 'base64:attacker']);
            $this->fail('Writing APP_KEY should be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertSame("APP_KEY=base64:keep-me\nMAIL_HOST=127.0.0.1\n", file_get_contents($path));
        }
    }

    public function test_a_missing_file_is_reported(): void
    {
        $this->app->useEnvironmentPath(storage_path('framework/testing/missing'));

        $this->expectException(RuntimeException::class);

        app(WriteEnvironmentFile::class)(['MAIL_HOST' => 'smtp.acme.test']);
    }

    private function temporaryEnvironmentFile(string $contents): string
    {
        $directory = storage_path('framework/testing/env-'.Str::random(8));
        File::ensureDirectoryExists($directory);
        File::put($directory.'/.env', $contents);

        $this->app->useEnvironmentPath($directory);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($directory));

        return $directory.'/.env';
    }
}
