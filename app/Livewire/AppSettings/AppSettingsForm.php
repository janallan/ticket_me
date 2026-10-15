<?php

namespace App\Livewire\AppSettings;

use App\Actions\AppSettings\AppBranding;
use App\Actions\AppSettings\UpdateAppSettings;
use App\Models\AppSettings;
use Closure;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The App settings screen: the install's branding, email details and sign-in page, one tab per area.
 *
 * @property-read AppSettings $settings
 */
#[Title('App settings')]
class AppSettingsForm extends Component
{
    use WithFileUploads;

    /**
     * The tabs on the screen, and the label of each.
     */
    public const TABS = ['branding' => 'Branding', 'email' => 'Email', 'sign-in' => 'Sign-in page'];

    /**
     * The upload fields on the form, and the settings column each one fills.
     */
    public const UPLOADS = [
        'logoLight' => 'logo_light_path',
        'logoDark' => 'logo_dark_path',
        'favicon' => 'favicon_path',
        'mailLogo' => 'mail_logo_path',
        'signInBackground' => 'sign_in_background_path',
    ];

    /**
     * The text fields on the form, and the settings column each one fills.
     */
    public const TEXT_FIELDS = [
        'name' => 'name',
        'accentColor' => 'accent_color',
        'mailFromName' => 'mail_from_name',
        'mailFromAddress' => 'mail_from_address',
        'mailFooter' => 'mail_footer',
        'signInHeading' => 'sign_in_heading',
        'signInMessage' => 'sign_in_message',
    ];

    #[Url(except: 'branding')]
    public string $tab = 'branding';

    /**
     * The form's fields, bound in the view as `appSettingsForm.<field>`. Upload fields hold a newly chosen
     * file until the form is saved.
     *
     * @var array{name: string, accentColor: string, mailFromName: string, mailFromAddress: string, mailFooter: string, signInHeading: string, signInMessage: string, logoLight: UploadedFile|null, logoDark: UploadedFile|null, favicon: UploadedFile|null, mailLogo: UploadedFile|null, signInBackground: UploadedFile|null}
     */
    public array $appSettingsForm = [
        'name' => '',
        'accentColor' => '',
        'mailFromName' => '',
        'mailFromAddress' => '',
        'mailFooter' => '',
        'signInHeading' => '',
        'signInMessage' => '',
        'logoLight' => null,
        'logoDark' => null,
        'favicon' => null,
        'mailLogo' => null,
        'signInBackground' => null,
    ];

    public function mount(): void
    {
        $this->authorize('view', AppSettings::class);

        if (! array_key_exists($this->tab, self::TABS)) {
            $this->tab = 'branding';
        }

        $this->fillFromSettings();
    }

    #[Computed]
    public function settings(): AppSettings
    {
        return AppSettings::current();
    }

    /**
     * Save every tab's fields and any newly chosen uploads.
     */
    public function save(UpdateAppSettings $updateAppSettings): void
    {
        $this->authorize('update', AppSettings::class);

        $images = ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'];

        $validated = $this->validate([
            'appSettingsForm.name' => ['nullable', 'string', 'max:100'],
            'appSettingsForm.accentColor' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/', $this->readableAccent()],
            'appSettingsForm.mailFromName' => ['nullable', 'string', 'max:100'],
            'appSettingsForm.mailFromAddress' => ['nullable', 'email', 'max:255'],
            'appSettingsForm.mailFooter' => ['nullable', 'string', 'max:1000'],
            'appSettingsForm.signInHeading' => ['nullable', 'string', 'max:100'],
            'appSettingsForm.signInMessage' => ['nullable', 'string', 'max:1000'],
            'appSettingsForm.logoLight' => $images,
            'appSettingsForm.logoDark' => $images,
            'appSettingsForm.mailLogo' => $images,
            'appSettingsForm.favicon' => ['nullable', 'file', 'mimes:png,ico', 'max:256'],
            'appSettingsForm.signInBackground' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
        ], [
            'appSettingsForm.accentColor.regex' => __('Enter the colour as #rrggbb, for example #ec3013.'),
        ], [
            'appSettingsForm.name' => __('company name'),
            'appSettingsForm.accentColor' => __('brand colour'),
            'appSettingsForm.mailFromName' => __('sender name'),
            'appSettingsForm.mailFromAddress' => __('sender address'),
            'appSettingsForm.mailFooter' => __('email footer'),
            'appSettingsForm.signInHeading' => __('heading'),
            'appSettingsForm.signInMessage' => __('message'),
            'appSettingsForm.logoLight' => __('light logo'),
            'appSettingsForm.logoDark' => __('dark logo'),
            'appSettingsForm.mailLogo' => __('email logo'),
            'appSettingsForm.favicon' => __('favicon'),
            'appSettingsForm.signInBackground' => __('background image'),
        ])['appSettingsForm'];

        $updateAppSettings(
            collect(self::TEXT_FIELDS)->mapWithKeys(fn (string $column, string $field) => [$column => $validated[$field] ?? null])->all(),
            collect(self::UPLOADS)->mapWithKeys(fn (string $column, string $field) => [$column => $this->appSettingsForm[$field]])->all(),
        );

        $this->resetUploads();
        unset($this->settings);

        Flux::toast(variant: 'success', text: __('App settings saved.'));
    }

    /**
     * Remove an uploaded file straight away, so the default is used again.
     */
    public function removeUpload(UpdateAppSettings $updateAppSettings, string $field): void
    {
        $this->authorize('update', AppSettings::class);

        abort_unless(array_key_exists($field, self::UPLOADS), 404);

        $updateAppSettings->removeUpload(self::UPLOADS[$field]);

        $this->appSettingsForm[$field] = null;
        unset($this->settings);

        Flux::toast(variant: 'success', text: __('Removed. The default is used again.'));
    }

    /**
     * Clear the brand colour on the form, so saving goes back to the default colours.
     */
    public function resetAccentColor(): void
    {
        $this->appSettingsForm['accentColor'] = '';
        $this->resetValidation('appSettingsForm.accentColor');
    }

    /**
     * The rule rejecting brand colours too light to read on white. Dark mode lightens link text on its own.
     */
    private function readableAccent(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) && ! AppBranding::isReadableAccent($value)) {
                $fail(__('This colour is too light to read on a white background. Pick a darker or stronger colour.'));
            }
        };
    }

    private function fillFromSettings(): void
    {
        foreach (self::TEXT_FIELDS as $field => $column) {
            $this->appSettingsForm[$field] = (string) $this->settings->getAttribute($column);
        }
    }

    private function resetUploads(): void
    {
        foreach (array_keys(self::UPLOADS) as $field) {
            $this->appSettingsForm[$field] = null;
        }
    }

    public function render(): View
    {
        return view('livewire.app-settings.app-settings-form');
    }
}
