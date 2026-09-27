<?php

namespace App\Enums;

/**
 * Colors supported by Flux badges, used for ticket statuses and priorities.
 */
enum BadgeColor: string
{
    case Zinc = 'zinc';
    case Red = 'red';
    case Orange = 'orange';
    case Amber = 'amber';
    case Yellow = 'yellow';
    case Lime = 'lime';
    case Green = 'green';
    case Emerald = 'emerald';
    case Teal = 'teal';
    case Cyan = 'cyan';
    case Sky = 'sky';
    case Blue = 'blue';
    case Indigo = 'indigo';
    case Violet = 'violet';
    case Purple = 'purple';
    case Fuchsia = 'fuchsia';
    case Pink = 'pink';
    case Rose = 'rose';

    /**
     * Get the human-readable name of the color.
     */
    public function label(): string
    {
        return __(ucfirst($this->value));
    }
}
