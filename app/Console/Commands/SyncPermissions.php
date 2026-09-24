<?php

namespace App\Console\Commands;

use App\Actions\Permissions\SyncPermissions as SyncPermissionsAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:acl-sync')]
#[Description('Sync the permissions table with the Permission enum')]
class SyncPermissions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SyncPermissionsAction $syncPermissions): int
    {
        ['created' => $created, 'removed' => $removed] = $syncPermissions();

        if ($created === [] && $removed === []) {
            $this->components->info('Permissions are already in sync.');

            return self::SUCCESS;
        }

        foreach ($created as $name) {
            $this->components->twoColumnDetail($name, '<fg=green;options=bold>CREATED</>');
        }

        foreach ($removed as $name) {
            $this->components->twoColumnDetail($name, '<fg=red;options=bold>REMOVED</>');
        }

        $this->newLine();
        $this->components->info('Permissions synced.');

        return self::SUCCESS;
    }
}
