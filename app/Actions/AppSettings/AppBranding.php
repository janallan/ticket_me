<?php

namespace App\Actions\AppSettings;

use App\Models\AppSettings;
use Illuminate\Database\QueryException;

/**
 * The effective branding for the install: every value from App settings, or its default when it is not
 * set. Everything that shows branding (page head, logo, sign-in page, emails) reads it from here, so the
 * fallbacks live in one place.
 */
class AppBranding
{
    /**
     * The page background in dark mode (zinc-800). Link text in dark mode is lightened until it reads on it.
     */
    public const DARK_BACKGROUND = '#262626';

    /**
     * The minimum contrast a brand colour needs against white, so links and highlights read in light mode.
     */
    public const MINIMUM_CONTRAST = 3.0;

    /**
     * The contrast dark-mode link text is lightened to reach against the dark background.
     */
    public const DARK_TEXT_CONTRAST = 4.5;

    /**
     * The config values as they were before App settings changed them, so they can be restored.
     *
     * @var array{'app.name': mixed, 'mail.from.address': mixed, 'mail.from.name': mixed}|null
     */
    private static ?array $configDefaults = null;

    private ?AppSettings $settings = null;

    private bool $loaded = false;

    /**
     * Get the settings row, or null before the table exists (for example while migrating).
     */
    public function settings(): ?AppSettings
    {
        if (! $this->loaded) {
            try {
                $this->settings = AppSettings::current();
            } catch (QueryException) {
                $this->settings = null;
            }

            $this->loaded = true;
        }

        return $this->settings;
    }

    /**
     * Forget the loaded settings, so the next call reads them again.
     */
    public function refresh(): void
    {
        $this->settings = null;
        $this->loaded = false;
    }

    public function name(): string
    {
        return $this->filled('name') ?? (string) (self::$configDefaults['app.name'] ?? config('app.name'));
    }

    public function logoLightUrl(): string
    {
        return $this->settings()?->fileUrl('logo_light_path') ?? asset('images/ticketme-long.svg');
    }

    public function logoDarkUrl(): string
    {
        return $this->settings()?->fileUrl('logo_dark_path')
            ?? $this->settings()?->fileUrl('logo_light_path')
            ?? asset('images/ticketme-long-dark.svg');
    }

    /**
     * Get the uploaded favicon, or null to use the bundled TicketMe favicons.
     */
    public function faviconUrl(): ?string
    {
        return $this->settings()?->fileUrl('favicon_path');
    }

    /**
     * Get the logo for email headers, or null to show the company name instead. The bundled logo is an
     * SVG, which many email clients don't display, so there is no bundled default.
     */
    public function mailLogoUrl(): ?string
    {
        return $this->settings()?->fileUrl('mail_logo_path') ?? $this->settings()?->fileUrl('logo_light_path');
    }

    public function mailFooter(): ?string
    {
        return $this->filled('mail_footer');
    }

    public function signInHeading(): ?string
    {
        return $this->filled('sign_in_heading');
    }

    public function signInMessage(): ?string
    {
        return $this->filled('sign_in_message');
    }

    public function signInBackgroundUrl(): ?string
    {
        return $this->settings()?->fileUrl('sign_in_background_path');
    }

    /**
     * Get the Flux accent variables for the brand colour in light and dark mode, or null to keep the
     * defaults from app.css.
     *
     * @return array{light: array{accent: string, content: string, foreground: string}, dark: array{accent: string, content: string, foreground: string}}|null
     */
    public function accentColors(): ?array
    {
        $color = $this->filled('accent_color');

        return $color === null ? null : ['light' => self::accentPalette($color), 'dark' => self::accentPalette($color, dark: true)];
    }

    /**
     * Derive the three Flux accent variables from a brand colour: the colour for fills, the colour for
     * link text (lightened in dark mode until it reads on the dark background), and white or near-black
     * for text on the colour, whichever contrasts more.
     *
     * @return array{accent: string, content: string, foreground: string}
     */
    public static function accentPalette(string $color, bool $dark = false): array
    {
        $color = strtolower($color);
        $foreground = self::contrast($color, '#ffffff') >= self::contrast($color, '#171717') ? '#ffffff' : '#171717';
        $content = $dark ? self::lightenUntilReadable($color, self::DARK_BACKGROUND, self::DARK_TEXT_CONTRAST) : $color;

        return ['accent' => $color, 'content' => $content, 'foreground' => $foreground];
    }

    /**
     * Determine whether a brand colour stands out against white, so links and highlights read in light
     * mode. Dark mode lightens the link text on its own, so it needs no check of its own.
     */
    public static function isReadableAccent(string $color): bool
    {
        return self::contrast($color, '#ffffff') >= self::MINIMUM_CONTRAST;
    }

    /**
     * Mix a colour with white, 10% at a time, until it reaches the contrast against the background.
     */
    private static function lightenUntilReadable(string $color, string $background, float $contrast): string
    {
        [$red, $green, $blue] = array_map('hexdec', str_split(ltrim($color, '#'), 2));

        for ($step = 0; $step <= 10; $step++) {
            $mix = fn (int $channel) => (int) round($channel + (255 - $channel) * $step / 10);
            $candidate = sprintf('#%02x%02x%02x', $mix($red), $mix($green), $mix($blue));

            if (self::contrast($candidate, $background) >= $contrast) {
                return $candidate;
            }
        }

        return '#ffffff';
    }

    /**
     * The WCAG contrast ratio between two `#rrggbb` colours, from 1 to 21.
     */
    public static function contrast(string $first, string $second): float
    {
        [$lighter, $darker] = [self::luminance($first), self::luminance($second)];

        if ($lighter < $darker) {
            [$lighter, $darker] = [$darker, $lighter];
        }

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Point the app name and email sender at the settings, falling back to the `.env` values. Called at
     * boot, before every queued job (so a long-running worker sees changes), and after the settings change.
     */
    public function applyToConfig(): void
    {
        self::$configDefaults ??= [
            'app.name' => config('app.name'),
            'mail.from.address' => config('mail.from.address'),
            'mail.from.name' => config('mail.from.name'),
        ];

        config([
            'app.name' => $this->filled('name') ?? self::$configDefaults['app.name'],
            'mail.from.address' => $this->filled('mail_from_address') ?? self::$configDefaults['mail.from.address'],
            'mail.from.name' => $this->filled('mail_from_name') ?? $this->filled('name') ?? self::$configDefaults['mail.from.name'],
        ]);
    }

    private function filled(string $column): ?string
    {
        $value = $this->settings()?->getAttribute($column);

        return filled($value) ? (string) $value : null;
    }

    private static function luminance(string $color): float
    {
        $channels = array_map(
            fn (string $hex) => hexdec($hex) / 255,
            str_split(ltrim($color, '#'), 2),
        );

        [$red, $green, $blue] = array_map(
            fn (float $channel) => $channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4,
            $channels,
        );

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }
}
