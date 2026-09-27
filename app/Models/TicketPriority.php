<?php

namespace App\Models;

use App\Enums\BadgeColor;
use App\Models\Concerns\HasDefaultOption;
use Database\Factories\TicketPriorityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property BadgeColor $color
 * @property bool $is_default
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'color', 'sort_order'])]
class TicketPriority extends Model
{
    /** @use HasFactory<TicketPriorityFactory> */
    use HasDefaultOption, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'color' => BadgeColor::class,
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
