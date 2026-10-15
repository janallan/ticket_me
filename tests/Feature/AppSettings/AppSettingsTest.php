<?php

namespace Tests\Feature\AppSettings;

use App\Livewire\AppSettings\AppSettingsForm;
use App\Models\AppSettings;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Dotenv\Dotenv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AppSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }

    public function test_holders_of_roles_can_open_app_settings_from_the_menu(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('dashboard'))->assertSee(route('app-settings'));
        $this->get(route('app-settings'))->assertOk()->assertSee('App settings - '.config('app.name'));
    }

    public function test_users_without_roles_cannot_open_or_save_app_settings(): void
    {
        $this->actingAs($agent = User::factory()->create()->assignRole('Agent'));

        $this->get(route('dashboard'))->assertDontSee(route('app-settings'));
        $this->get(route('app-settings'))->assertForbidden();

        $this->assertFalse($agent->can('update', AppSettings::class));
    }

    public function test_text_settings_are_saved_and_empty_values_mean_the_default(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(AppSettingsForm::class)
            ->set('appSettingsForm.name', 'Acme Helpdesk')
            ->set('appSettingsForm.accentColor', '#1d4ed8')
            ->set('appSettingsForm.mailFromName', 'Acme Support')
            ->set('appSettingsForm.mailFromAddress', 'support@acme.test')
            ->set('appSettingsForm.mailFooter', 'Acme Corp, 1 Main Street')
            ->set('appSettingsForm.signInHeading', 'Welcome to Acme')
            ->set('appSettingsForm.signInMessage', 'Ask IT for an account.')
            ->call('save')
            ->assertHasNoErrors();

        $settings = AppSettings::current();

        $this->assertSame('Acme Helpdesk', $settings->name);
        $this->assertSame('#1d4ed8', $settings->accent_color);
        $this->assertSame('support@acme.test', $settings->mail_from_address);
        $this->assertSame('Welcome to Acme', $settings->sign_in_heading);

        Livewire::test(AppSettingsForm::class)
            ->assertSet('appSettingsForm.name', 'Acme Helpdesk')
            ->set('appSettingsForm.name', '')
            ->call('resetAccentColor')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull(AppSettings::current()->name);
        $this->assertNull(AppSettings::current()->accent_color);
    }

    public function test_uploads_are_stored_replaced_and_removed(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin());

        Livewire::test(AppSettingsForm::class)
            ->set('appSettingsForm.logoLight', UploadedFile::fake()->image('first.png', 400, 80))
            ->call('save')
            ->assertHasNoErrors();

        $firstPath = AppSettings::current()->logo_light_path;
        Storage::disk('public')->assertExists($firstPath);

        Livewire::test(AppSettingsForm::class)
            ->set('appSettingsForm.logoLight', UploadedFile::fake()->image('second.png', 400, 80))
            ->call('save')
            ->assertHasNoErrors();

        $secondPath = AppSettings::current()->logo_light_path;
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);

        Livewire::test(AppSettingsForm::class)
            ->call('removeUpload', 'logoLight')
            ->assertHasNoErrors();

        $this->assertNull(AppSettings::current()->logo_light_path);
        Storage::disk('public')->assertMissing($secondPath);
    }

    public function test_svg_and_oversized_uploads_are_rejected(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin());

        Livewire::test(AppSettingsForm::class)
            ->set('appSettingsForm.logoLight', UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'))
            ->set('appSettingsForm.signInBackground', UploadedFile::fake()->image('huge.jpg')->size(5000))
            ->call('save')
            ->assertHasErrors(['appSettingsForm.logoLight', 'appSettingsForm.signInBackground']);

        $this->assertNull(AppSettings::current()->logo_light_path);
    }

    public function test_the_brand_colour_must_be_a_readable_hex_colour(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(AppSettingsForm::class)
            ->set('appSettingsForm.accentColor', 'blue')
            ->call('save')
            ->assertHasErrors(['appSettingsForm.accentColor' => 'regex']);

        Livewire::test(AppSettingsForm::class)
            ->set('appSettingsForm.accentColor', '#fde047')
            ->call('save')
            ->assertHasErrors(['appSettingsForm.accentColor'])
            ->assertSee(__('This colour is too light to read on a white background. Pick a darker or stronger colour.'));

        $this->assertNull(AppSettings::current()->accent_color);
    }

    public function test_the_mail_server_is_saved_to_the_env_file_and_the_password_is_never_sent_back(): void
    {
        $envPath = $this->useTemporaryEnvironmentFile();
        $this->actingAs($this->admin());

        Livewire::test(AppSettingsForm::class)
            ->set('appSettingsForm.mailMailer', 'smtp')
            ->set('appSettingsForm.mailHost', 'smtp.acme.test')
            ->set('appSettingsForm.mailPort', '465')
            ->set('appSettingsForm.mailScheme', 'smtps')
            ->set('appSettingsForm.mailUsername', 'helpdesk')
            ->set('appSettingsForm.mailPassword', 'p@ss "w#rd" $HOME')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('appSettingsForm.mailPassword', '');

        $env = Dotenv::parse((string) file_get_contents($envPath));

        $this->assertSame('smtp', $env['MAIL_MAILER']);
        $this->assertSame('smtp.acme.test', $env['MAIL_HOST']);
        $this->assertSame('465', $env['MAIL_PORT']);
        $this->assertSame('smtps', $env['MAIL_SCHEME']);
        $this->assertSame('helpdesk', $env['MAIL_USERNAME']);
        $this->assertSame('p@ss "w#rd" $HOME', $env['MAIL_PASSWORD']);
        $this->assertSame('base64:keep-me', $env['APP_KEY']);
        $this->assertSame('smtp.acme.test', config('mail.mailers.smtp.host'));
        $this->assertNotNull(Cache::get('illuminate:queue:restart'));

        Livewire::test(AppSettingsForm::class)
            ->assertSet('appSettingsForm.mailHost', 'smtp.acme.test')
            ->assertSet('appSettingsForm.mailPassword', '')
            ->assertDontSeeHtml('p@ss')
            ->set('appSettingsForm.mailUsername', 'support')
            ->call('save')
            ->assertHasNoErrors();

        $env = Dotenv::parse((string) file_get_contents($envPath));

        $this->assertSame('support', $env['MAIL_USERNAME']);
        $this->assertSame('p@ss "w#rd" $HOME', $env['MAIL_PASSWORD']);
    }

    public function test_smtp_needs_a_host_and_port_and_the_mailer_must_be_known(): void
    {
        $envPath = $this->useTemporaryEnvironmentFile();
        $original = file_get_contents($envPath);
        $this->actingAs($this->admin());

        Livewire::test(AppSettingsForm::class)
            ->set('appSettingsForm.mailMailer', 'smtp')
            ->set('appSettingsForm.mailHost', '')
            ->set('appSettingsForm.mailPort', '')
            ->call('save')
            ->assertHasErrors(['appSettingsForm.mailHost' => 'required_if', 'appSettingsForm.mailPort' => 'required_if']);

        Livewire::test(AppSettingsForm::class)
            ->set('appSettingsForm.mailMailer', 'ses')
            ->call('save')
            ->assertHasErrors(['appSettingsForm.mailMailer']);

        Livewire::test(AppSettingsForm::class)
            ->set('appSettingsForm.mailMailer', 'smtp')
            ->set('appSettingsForm.mailHost', 'smtp.acme.test')
            ->set('appSettingsForm.mailPort', '587')
            ->set('appSettingsForm.mailPassword', "secret\nAPP_DEBUG=true")
            ->call('save')
            ->assertHasErrors(['appSettingsForm.mailPassword']);

        $this->assertSame($original, file_get_contents($envPath));
    }

    public function test_another_mailer_keeps_the_smtp_values_and_other_tabs_leave_the_env_file_alone(): void
    {
        $envPath = $this->useTemporaryEnvironmentFile();
        $this->actingAs($this->admin());

        Livewire::test(AppSettingsForm::class)
            ->set('appSettingsForm.name', 'Acme Helpdesk')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($this->environmentFileContents(), file_get_contents($envPath));

        Livewire::test(AppSettingsForm::class)
            ->set('appSettingsForm.mailMailer', 'log')
            ->call('save')
            ->assertHasNoErrors();

        $env = Dotenv::parse((string) file_get_contents($envPath));

        $this->assertSame('log', $env['MAIL_MAILER']);
        $this->assertSame('127.0.0.1', $env['MAIL_HOST']);
    }

    public function test_an_unwritable_env_file_shows_an_error_and_saves_nothing(): void
    {
        $this->app->useEnvironmentPath(storage_path('framework/testing/missing'));
        $this->actingAs($this->admin());

        Livewire::test(AppSettingsForm::class)
            ->set('appSettingsForm.name', 'Acme Helpdesk')
            ->set('appSettingsForm.mailMailer', 'log')
            ->call('save')
            ->assertHasErrors(['appSettingsForm.mailMailer']);

        $this->assertNull(AppSettings::current()->name);
    }

    public function test_a_test_email_is_sent_to_the_signed_in_user(): void
    {
        $this->actingAs($admin = $this->admin());

        Livewire::test(AppSettingsForm::class)
            ->call('sendTestEmail')
            ->assertDispatched('toast-show', fn (string $name, array $params) => $params['dataset']['variant'] === 'success');

        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();

        $this->assertCount(1, $messages);
        $this->assertSame($admin->email, $messages->first()->getEnvelope()->getRecipients()[0]->getAddress());
    }

    public function test_a_failing_test_email_shows_the_error(): void
    {
        $this->useTemporaryEnvironmentFile();
        $this->actingAs($this->admin());

        Livewire::test(AppSettingsForm::class)
            ->set('appSettingsForm.mailMailer', 'smtp')
            ->set('appSettingsForm.mailHost', '127.0.0.1')
            ->set('appSettingsForm.mailPort', '1')
            ->call('save')
            ->call('sendTestEmail')
            ->assertDispatched('toast-show', fn (string $name, array $params) => $params['dataset']['variant'] === 'danger');
    }

    public function test_the_open_tab_is_kept_and_unknown_tabs_fall_back_to_branding(): void
    {
        $this->actingAs($this->admin());

        Livewire::withQueryParams(['tab' => 'email'])->test(AppSettingsForm::class)
            ->assertSet('tab', 'email')
            ->assertSeeHtml('data-test="email-card"')
            ->assertDontSeeHtml('data-test="branding-card"');

        Livewire::withQueryParams(['tab' => 'nope'])->test(AppSettingsForm::class)
            ->assertSet('tab', 'branding');
    }

    private function admin(): User
    {
        return User::factory()->create()->assignRole('Admin');
    }

    /**
     * Point the app at a throwaway `.env` file, so tests never write the real one.
     */
    private function useTemporaryEnvironmentFile(): string
    {
        $directory = storage_path('framework/testing/env-'.Str::random(8));
        File::ensureDirectoryExists($directory);
        File::put($directory.'/.env', $this->environmentFileContents());

        $this->app->useEnvironmentPath($directory);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($directory));

        return $directory.'/.env';
    }

    private function environmentFileContents(): string
    {
        return "APP_KEY=base64:keep-me\n\n# Mail\nMAIL_MAILER=array\nMAIL_HOST=127.0.0.1\nMAIL_PORT=2525\n";
    }
}
