<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * The install's customization: company name, logos, brand colour, email details and sign-in page.
 * There is a single row; every column is optional, and an empty one means "use the default".
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $logo_light_path
 * @property string|null $logo_dark_path
 * @property string|null $favicon_path
 * @property string|null $accent_color
 * @property string|null $mail_from_name
 * @property string|null $mail_from_address
 * @property string|null $mail_logo_path
 * @property string|null $mail_footer
 * @property string|null $sign_in_heading
 * @property string|null $sign_in_message
 * @property string|null $sign_in_background_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name', 'logo_light_path', 'logo_dark_path', 'favicon_path', 'accent_color',
    'mail_from_name', 'mail_from_address', 'mail_logo_path', 'mail_footer',
    'sign_in_heading', 'sign_in_message', 'sign_in_background_path',
])]
class AppSettings extends Model
{
    /**
     * The cache key holding the settings row.
     */
    public const CACHE_KEY = 'app-settings';

    /**
     * The disk the uploaded logos, favicon and sign-in background are stored on.
     */
    public const DISK = 'public';

    /**
     * The columns that hold the path of an uploaded file.
     *
     * @var list<string>
     */
    public const UPLOAD_COLUMNS = ['logo_light_path', 'logo_dark_path', 'favicon_path', 'mail_logo_path', 'sign_in_background_path'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * Get the settings row, creating an empty one the first time. The row's values are cached until it
     * changes. Only the plain attribute array is cached, because the cache refuses to unserialize objects.
     */
    public static function current(): self
    {
        $attributes = Cache::rememberForever(
            self::CACHE_KEY,
            fn () => (self::query()->oldest('id')->first() ?? self::create())->getAttributes(),
        );

        return (new self)->newFromBuilder($attributes);
    }

    /**
     * Get the public URL of an uploaded file, or null when none is uploaded.
     */
    public function fileUrl(string $column): ?string
    {
        $path = $this->getAttribute($column);

        return filled($path) ? Storage::disk(self::DISK)->url($path) : null;
    }
}
