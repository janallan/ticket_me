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
     * Seed the default departments, statuses and priorities. Each table is only seeded when it is
     * empty, so seeding never adds to or changes settings on an existing database.
     */
    public function run(): void
    {
        if (Department::query()->doesntExist()) {
            foreach (self::DEPARTMENTS as $name => $description) {
                Department::create(['name' => $name, 'description' => $description]);
            }
        }

        $this->seedOptions(TicketStatus::class, self::STATUSES);
        $this->seedOptions(TicketPriority::class, self::PRIORITIES);
    }

    /**
     * Create the options and mark the seeded default, but only when the table is empty.
     *
     * @param  class-string<TicketStatus|TicketPriority>  $model
     * @param  list<array{name: string, color: BadgeColor, is_default?: bool, is_closed?: bool}>  $options
     */
    private function seedOptions(string $model, array $options): void
    {
        if ($model::query()->exists()) {
            return;
        }

        foreach ($options as $index => $option) {
            $record = $model::create([
                'name' => $option['name'],
                'color' => $option['color'],
                'sort_order' => ($index + 1) * 10,
                ...(isset($option['is_closed']) ? ['is_closed' => $option['is_closed']] : []),
            ]);

            if ($option['is_default'] ?? false) {
                $record->makeDefault();
            }
        }
    }
}
