<?php

namespace App\Actions\Permissions;

use App\Enums\Permission;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\PermissionRegistrar;

class SyncPermissions
{
    public function __construct(private PermissionRegistrar $registrar) {}

    /**
     * Make the permissions table match the Permission enum.
     *
     * @return array{created: list<string>, removed: list<string>}
     */
    public function __invoke(): array
    {
        $this->registrar->forgetCachedPermissions();

        $names = array_column(Permission::cases(), 'value');
        $existing = PermissionModel::pluck('name')->all();

        $created = array_values(array_diff($names, $existing));
        $removed = array_values(array_diff($existing, $names));

        foreach ($created as $name) {
            PermissionModel::findOrCreate($name);
        }

        if ($removed !== []) {
            PermissionModel::whereIn('name', $removed)->delete();
        }

        $this->registrar->forgetCachedPermissions();

        return ['created' => $created, 'removed' => $removed];
    }
}
