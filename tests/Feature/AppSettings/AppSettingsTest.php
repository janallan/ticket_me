<?php

namespace Tests\Feature\AppSettings;

use App\Livewire\AppSettings\AppSettingsForm;
use App\Models\AppSettings;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
}
