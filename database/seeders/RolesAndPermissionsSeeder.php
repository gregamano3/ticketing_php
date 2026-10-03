<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public const PERMISSIONS = [
        'tickets.work',          // work the queue: assign, change status, internal notes
        'reports.view',
        'kb.manage',
        'canned.manage-shared',
        'users.manage',
        'settings.manage',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::findOrCreate('admin', 'web')->syncPermissions(self::PERMISSIONS);
        Role::findOrCreate('agent', 'web')->syncPermissions(['tickets.work', 'reports.view', 'kb.manage']);
        Role::findOrCreate('requester', 'web')->syncPermissions([]);
    }
}
