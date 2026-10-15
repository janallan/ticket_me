<?php

namespace App\Actions\AppSettings;

use App\Models\AppSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Saves the App settings: text values, new uploads (replacing and deleting the old file), and removing an
 * upload to go back to the default. The branding is re-applied so the rest of the request sees the change.
 */
class UpdateAppSettings
{
    public function __construct(private AppBranding $branding) {}

    /**
     * Save text values and uploads. Empty strings are stored as null, which means "use the default".
     *
     * @param  array<string, string|null>  $values  Text columns and their new values.
     * @param  array<string, UploadedFile|null>  $uploads  Upload columns (see AppSettings::UPLOAD_COLUMNS) and their new files.
     */
    public function __invoke(array $values, array $uploads = []): AppSettings
    {
        $settings = AppSettings::current();

        foreach ($values as $column => $value) {
            $this->ensureNotAnUploadColumn($column);
            $settings->setAttribute($column, filled($value) ? $value : null);
        }

        $replaced = [];

        foreach (array_filter($uploads) as $column => $file) {
            $this->ensureUploadColumn($column);

            $replaced[] = $settings->getAttribute($column);
            $settings->setAttribute($column, $file->store('app-settings', AppSettings::DISK));
        }

        $settings->save();
        $this->deleteFiles($replaced);
        $this->reapply();

        return $settings;
    }

    /**
     * Remove an uploaded file, so the default is used again.
     */
    public function removeUpload(string $column): AppSettings
    {
        $this->ensureUploadColumn($column);

        $settings = AppSettings::current();
        $path = $settings->getAttribute($column);

        $settings->setAttribute($column, null)->save();
        $this->deleteFiles([$path]);
        $this->reapply();

        return $settings;
    }

    /**
     * @param  array<int, mixed>  $paths
     */
    private function deleteFiles(array $paths): void
    {
        $paths = array_values(array_filter($paths, fn ($path) => is_string($path) && $path !== ''));

        if ($paths !== []) {
            Storage::disk(AppSettings::DISK)->delete($paths);
        }
    }

    private function reapply(): void
    {
        $this->branding->refresh();
        $this->branding->applyToConfig();
    }

    private function ensureUploadColumn(string $column): void
    {
        if (! in_array($column, AppSettings::UPLOAD_COLUMNS, true)) {
            throw new InvalidArgumentException("[{$column}] is not an upload column.");
        }
    }

    private function ensureNotAnUploadColumn(string $column): void
    {
        if (in_array($column, AppSettings::UPLOAD_COLUMNS, true)) {
            throw new InvalidArgumentException("Set [{$column}] through an upload, not a text value.");
        }
    }
}
