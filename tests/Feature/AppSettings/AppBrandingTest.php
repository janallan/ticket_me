<?php

namespace Tests\Feature\AppSettings;

use App\Actions\AppSettings\AppBranding;
use App\Actions\AppSettings\UpdateAppSettings;
use App\Models\User;
use App\Notifications\SetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_without_settings_the_ticketme_and_env_defaults_are_used(): void
    {
        $branding = app(AppBranding::class);
        $branding->refresh();

        $this->assertSame(config('app.name'), $branding->name());
        $this->assertSame(asset('images/ticketme-long.svg'), $branding->logoLightUrl());
        $this->assertSame(asset('images/ticketme-long-dark.svg'), $branding->logoDarkUrl());
        $this->assertNull($branding->faviconUrl());
        $this->assertNull($branding->accentColors());
        $this->assertNull($branding->mailLogoUrl());

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertSee('href="/favicon.svg"', false)
            ->assertSee(asset('images/ticketme-long.svg'), false)
            ->assertDontSee('--color-accent:', false);
    }

    public function test_pages_use_the_company_name_logos_favicon_and_brand_colour(): void
    {
        Storage::fake('public');

        app(UpdateAppSettings::class)(
            ['name' => 'Acme Helpdesk', 'accent_color' => '#1d4ed8'],
            [
                'logo_light_path' => UploadedFile::fake()->image('light.png', 400, 80),
                'logo_dark_path' => UploadedFile::fake()->image('dark.png', 400, 80),
                'favicon_path' => UploadedFile::fake()->image('favicon.png', 64, 64),
            ],
        );

        $branding = app(AppBranding::class);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertSee('<title>'."\n".'    Dashboard - Acme Helpdesk', false)
            ->assertSee($branding->logoLightUrl(), false)
            ->assertSee($branding->logoDarkUrl(), false)
            ->assertSee('<link rel="icon" href="'.$branding->faviconUrl().'">', false)
            ->assertSee('--color-accent: #1d4ed8;', false)
            ->assertSee('--color-accent-content: '.AppBranding::accentPalette('#1d4ed8', dark: true)['content'].';', false)
            ->assertSee('--color-accent-foreground: #ffffff;', false)
            ->assertDontSee('href="/favicon.svg"', false);

        $this->assertSame('Acme Helpdesk', config('app.name'));
    }

    public function test_the_dark_logo_falls_back_to_the_uploaded_light_logo(): void
    {
        Storage::fake('public');

        app(UpdateAppSettings::class)([], ['logo_light_path' => UploadedFile::fake()->image('light.png', 400, 80)]);

        $branding = app(AppBranding::class);

        $this->assertSame($branding->logoLightUrl(), $branding->logoDarkUrl());
        $this->assertSame($branding->logoLightUrl(), $branding->mailLogoUrl());
    }

    public function test_the_sign_in_page_shows_the_heading_message_and_background(): void
    {
        Storage::fake('public');

        app(UpdateAppSettings::class)(
            ['sign_in_heading' => 'Welcome to Acme', 'sign_in_message' => 'Ask IT for an account.'],
            ['sign_in_background_path' => UploadedFile::fake()->image('office.jpg', 1600, 900)],
        );

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Welcome to Acme')
            ->assertSee('Ask IT for an account.')
            ->assertSee(app(AppBranding::class)->signInBackgroundUrl(), false);
    }

    public function test_emails_use_the_sender_logo_footer_and_company_name(): void
    {
        Storage::fake('public');

        app(UpdateAppSettings::class)(
            ['name' => 'Acme Helpdesk', 'mail_from_name' => 'Acme Support', 'mail_from_address' => 'support@acme.test', 'mail_footer' => 'Acme Corp, 1 Main Street'],
            ['mail_logo_path' => UploadedFile::fake()->image('mail.png', 300, 60)],
        );

        $this->assertSame('support@acme.test', config('mail.from.address'));
        $this->assertSame('Acme Support', config('mail.from.name'));

        $user = User::factory()->create();
        $html = (string) (new SetPasswordNotification('token'))->toMail($user)->render();

        $this->assertStringContainsString(app(AppBranding::class)->mailLogoUrl(), $html);
        $this->assertStringContainsString('Acme Corp, 1 Main Street', $html);
        $this->assertStringContainsString('Acme Helpdesk', $html);
    }

    public function test_clearing_the_settings_restores_the_env_defaults(): void
    {
        $defaultName = config('app.name');
        $defaultAddress = config('mail.from.address');

        app(UpdateAppSettings::class)(['name' => 'Acme Helpdesk', 'mail_from_address' => 'support@acme.test']);
        app(UpdateAppSettings::class)(['name' => '', 'mail_from_address' => '']);

        $this->assertSame($defaultName, config('app.name'));
        $this->assertSame($defaultAddress, config('mail.from.address'));
    }

    public function test_the_accent_text_colour_follows_contrast(): void
    {
        $this->assertSame('#ffffff', AppBranding::accentPalette('#1d4ed8')['foreground']);
        $this->assertSame('#171717', AppBranding::accentPalette('#f59e0b')['foreground']);

        $this->assertTrue(AppBranding::isReadableAccent('#ec3013'));
        $this->assertTrue(AppBranding::isReadableAccent('#1d4ed8'));
        $this->assertFalse(AppBranding::isReadableAccent('#fde047'));
    }

    public function test_dark_mode_lightens_link_text_until_it_reads_on_the_dark_background(): void
    {
        $dark = AppBranding::accentPalette('#1d4ed8', dark: true);

        $this->assertSame('#1d4ed8', $dark['accent']);
        $this->assertNotSame('#1d4ed8', $dark['content']);
        $this->assertGreaterThanOrEqual(AppBranding::DARK_TEXT_CONTRAST, AppBranding::contrast($dark['content'], AppBranding::DARK_BACKGROUND));
        $this->assertSame('#1d4ed8', AppBranding::accentPalette('#1d4ed8')['content']);
    }
}
