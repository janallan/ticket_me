<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared behavior for configurable ticket options (statuses and priorities): exactly one
 * record is the default, and records are listed by their sort order.
 */
trait HasDefaultOption
{
    /**
     * Get the default option, if one has been set.
     */
    public static function findDefault(): ?static
    {
        return static::query()->where('is_default', true)->first();
    }

    /**
     * Mark this option as the default and unset the flag on every other option.
     */
    public function makeDefault(): void
    {
        static::query()->whereKeyNot($this->getKey())->where('is_default', true)->update(['is_default' => false]);

        if (! $this->is_default) {
            $this->forceFill(['is_default' => true])->save();
        }
    }

    /**
     * Scope a query to list options by sort order, then name.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }
}
