<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sets a user's departments and default department together, so the default is always one of their departments.
 */
class SyncUserDepartments
{
    /**
     * Replace the user's departments and set their default department.
     *
     * @param  list<int|string>  $departmentIds
     *
     * @throws ValidationException
     */
    public function __invoke(User $user, array $departmentIds, int $defaultDepartmentId, string $errorKey = 'defaultDepartment'): void
    {
        $departmentIds = array_values(array_unique(array_map('intval', $departmentIds)));

        if (! in_array($defaultDepartmentId, $departmentIds, true)) {
            throw ValidationException::withMessages([
                $errorKey => __('The default department must be one of the user\'s departments.'),
            ]);
        }

        DB::transaction(function () use ($user, $departmentIds, $defaultDepartmentId) {
            $user->departments()->sync($departmentIds);
            $user->forceFill(['default_department_id' => $defaultDepartmentId])->save();
        });
    }
}
