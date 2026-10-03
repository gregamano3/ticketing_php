<?php

namespace Tests\Concerns;

use App\Models\Department;
use App\Models\Priority;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\HelpdeskSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

trait SeedsHelpdesk
{
    protected function seedHelpdesk(): void
    {
        $this->seed([RolesAndPermissionsSeeder::class, HelpdeskSeeder::class]);
    }

    protected function department(string $name = 'IT Support'): Department
    {
        return Department::where('name', $name)->firstOrFail();
    }

    protected function statusNamed(string $name): Status
    {
        return Status::where('name', $name)->firstOrFail();
    }

    protected function priorityNamed(string $name): Priority
    {
        return Priority::where('name', $name)->firstOrFail();
    }

    protected function admin(array $attrs = []): User
    {
        return User::factory()->admin()->create($attrs);
    }

    protected function agent(?Department $department = null, array $attrs = []): User
    {
        return User::factory()->agent()->create(['department_id' => ($department ?? $this->department())->id, ...$attrs]);
    }

    protected function requester(array $attrs = []): User
    {
        return User::factory()->requester()->create($attrs);
    }
}
