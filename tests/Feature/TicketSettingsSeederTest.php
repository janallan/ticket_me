<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use Database\Seeders\TicketSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketSettingsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_default_departments_statuses_and_priorities(): void
    {
        $this->seed(TicketSettingsSeeder::class);

        $this->assertEqualsCanonicalizing(['HR', 'IT'], Department::pluck('name')->all());

        $this->assertSame(['Open', 'Pending', 'Resolved', 'Closed'], TicketStatus::query()->ordered()->pluck('name')->all());
        $this->assertSame('Open', TicketStatus::findDefault()?->name);
        $this->assertEqualsCanonicalizing(['Resolved', 'Closed'], TicketStatus::where('is_closed', true)->pluck('name')->all());

        $this->assertSame(['Low', 'Normal', 'High', 'Urgent'], TicketPriority::query()->ordered()->pluck('name')->all());
        $this->assertSame('Normal', TicketPriority::findDefault()?->name);
    }

    public function test_reseeding_keeps_customized_settings(): void
    {
        $this->seed(TicketSettingsSeeder::class);

        TicketPriority::where('name', 'High')->firstOrFail()->makeDefault();
        Department::where('name', 'HR')->firstOrFail()->update(['description' => 'Custom']);

        $this->seed(TicketSettingsSeeder::class);

        $this->assertSame('High', TicketPriority::findDefault()?->name);
        $this->assertSame(1, TicketPriority::where('is_default', true)->count());
        $this->assertSame('Custom', Department::where('name', 'HR')->firstOrFail()->description);
        $this->assertSame(2, Department::count());
        $this->assertSame(4, TicketStatus::count());
    }

    public function test_each_table_is_only_seeded_when_it_is_empty(): void
    {
        Department::factory()->create(['name' => 'Facilities']);
        TicketStatus::factory()->default()->create(['name' => 'New']);

        $this->seed(TicketSettingsSeeder::class);

        $this->assertSame(['Facilities'], Department::pluck('name')->all());
        $this->assertSame(['New'], TicketStatus::pluck('name')->all());
        $this->assertSame(['Low', 'Normal', 'High', 'Urgent'], TicketPriority::query()->ordered()->pluck('name')->all());
    }
}
