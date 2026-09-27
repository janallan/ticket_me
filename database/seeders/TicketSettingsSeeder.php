<?php

namespace Database\Seeders;

use App\Enums\BadgeColor;
use App\Models\Department;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use Illuminate\Database\Seeder;

class TicketSettingsSeeder extends Seeder
{
    /**
     * The default departments.
     *
     * @var array<string, string>
     */
    private const DEPARTMENTS = [
        'HR' => 'Human resources: payroll, leave, benefits and onboarding.',
        'IT' => 'Information technology: hardware, software, accounts and access.',
    ];

    /**
     * The default statuses, in display order.
     *
     * @var list<array{name: string, color: BadgeColor, is_default?: bool, is_closed?: bool}>
     */
    private const STATUSES = [
        ['name' => 'Open', 'color' => BadgeColor::Blue, 'is_default' => true],
        ['name' => 'Pending', 'color' => BadgeColor::Amber],
        ['name' => 'Resolved', 'color' => BadgeColor::Green, 'is_closed' => true],
        ['name' => 'Closed', 'color' => BadgeColor::Zinc, 'is_closed' => true],
    ];

    /**
     * The default priorities, in display order.
     *
     * @var list<array{name: string, color: BadgeColor, is_default?: bool}>
     */
    private const PRIORITIES = [
        ['name' => 'Low', 'color' => BadgeColor::Zinc],
        ['name' => 'Normal', 'color' => BadgeColor::Blue, 'is_default' => true],
        ['name' => 'High', 'color' => BadgeColor::Orange],
        ['name' => 'Urgent', 'color' => BadgeColor::Red],
    ];

    /**
     * Seed the default departments, statuses and priorities. Existing records are left
     * untouched so re-seeding never overwrites changes made from the settings screens.
     */
    public function run(): void
    {
        foreach (self::DEPARTMENTS as $name => $description) {
            Department::firstOrCreate(['name' => $name], ['description' => $description]);
        }

        $this->seedOptions(TicketStatus::class, self::STATUSES);
        $this->seedOptions(TicketPriority::class, self::PRIORITIES);
    }

    /**
     * Create the missing options, and set the seeded default only when no default exists yet.
     *
     * @param  class-string<TicketStatus|TicketPriority>  $model
     * @param  list<array{name: string, color: BadgeColor, is_default?: bool, is_closed?: bool}>  $options
     */
    private function seedOptions(string $model, array $options): void
    {
        $hasDefault = $model::findDefault() !== null;

        foreach ($options as $index => $option) {
            $record = $model::firstOrCreate(
                ['name' => $option['name']],
                [
                    'color' => $option['color'],
                    'sort_order' => ($index + 1) * 10,
                    ...(isset($option['is_closed']) ? ['is_closed' => $option['is_closed']] : []),
                ],
            );

            if (! $hasDefault && ($option['is_default'] ?? false)) {
                $record->makeDefault();
                $hasDefault = true;
            }
        }
    }
}
