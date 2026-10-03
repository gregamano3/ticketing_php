<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Department;
use App\Models\KbCategory;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Tag;
use Illuminate\Database\Seeder;

/** Reference data every installation needs: statuses, priorities, departments, categories, tags. */
class HelpdeskSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Open', 'color' => 'primary', 'is_default' => true, 'sort_order' => 1],
            ['name' => 'In Progress', 'color' => 'info', 'sort_order' => 2],
            ['name' => 'Pending', 'color' => 'warning', 'pauses_sla' => true, 'sort_order' => 3],
            ['name' => 'On Hold', 'color' => 'secondary', 'pauses_sla' => true, 'sort_order' => 4],
            ['name' => 'Resolved', 'color' => 'success', 'is_resolved' => true, 'sort_order' => 5],
            ['name' => 'Closed', 'color' => 'dark', 'is_resolved' => true, 'is_closed' => true, 'sort_order' => 6],
        ] as $status) {
            Status::updateOrCreate(['name' => $status['name']], $status);
        }

        foreach ([
            ['name' => 'Low', 'level' => 1, 'color' => 'secondary', 'response_minutes' => 8 * 60, 'resolution_minutes' => 5 * 24 * 60],
            ['name' => 'Medium', 'level' => 2, 'color' => 'info', 'response_minutes' => 4 * 60, 'resolution_minutes' => 2 * 24 * 60, 'is_default' => true],
            ['name' => 'High', 'level' => 3, 'color' => 'warning', 'response_minutes' => 60, 'resolution_minutes' => 8 * 60],
            ['name' => 'Urgent', 'level' => 4, 'color' => 'danger', 'response_minutes' => 15, 'resolution_minutes' => 4 * 60],
        ] as $priority) {
            Priority::updateOrCreate(['name' => $priority['name']], $priority);
        }

        $tree = [
            'IT Support' => ['Hardware' => ['Laptop', 'Printer', 'Peripherals'], 'Software' => ['Installation', 'Licensing'], 'Network & VPN' => [], 'Email & Calendar' => [], 'Accounts & Access' => []],
            'Human Resources' => ['Payroll' => [], 'Leave & Attendance' => [], 'Benefits' => [], 'Onboarding' => []],
            'Facilities' => ['Office Maintenance' => [], 'Meeting Rooms' => [], 'Parking' => []],
            'Finance' => ['Expense Claims' => [], 'Purchase Requests' => [], 'Invoices' => []],
        ];

        foreach ($tree as $deptName => $categories) {
            $dept = Department::firstOrCreate(['name' => $deptName], ['description' => "{$deptName} requests"]);
            foreach ($categories as $catName => $children) {
                $parent = Category::firstOrCreate(['name' => $catName, 'department_id' => $dept->id]);
                foreach ($children as $child) {
                    Category::firstOrCreate(['name' => $child, 'department_id' => $dept->id, 'parent_id' => $parent->id]);
                }
            }
        }

        // Some categories are never low priority.
        $high = Priority::where('name', 'High')->value('id');
        $medium = Priority::where('name', 'Medium')->value('id');
        Category::where('name', 'Accounts & Access')->update(['default_priority_id' => $high]);
        Category::whereIn('name', ['Payroll', 'Network & VPN'])->update(['default_priority_id' => $medium]);

        foreach (['bug' => 'danger', 'question' => 'info', 'feature-request' => 'success', 'vip' => 'warning', 'recurring' => 'secondary', 'remote' => 'primary'] as $name => $color) {
            Tag::firstOrCreate(['name' => $name], ['color' => $color]);
        }

        foreach ([
            ['name' => 'Getting Started', 'icon' => 'bi bi-rocket-takeoff', 'description' => 'New here? Start with these.', 'sort_order' => 1],
            ['name' => 'Accounts & Passwords', 'icon' => 'bi bi-key', 'description' => 'Sign-in, MFA and password resets.', 'sort_order' => 2],
            ['name' => 'Network & VPN', 'icon' => 'bi bi-wifi', 'description' => 'Wi-Fi, VPN and remote access.', 'sort_order' => 3],
            ['name' => 'Hardware', 'icon' => 'bi bi-laptop', 'description' => 'Laptops, printers and peripherals.', 'sort_order' => 4],
            ['name' => 'HR Policies', 'icon' => 'bi bi-people', 'description' => 'Leave, payroll and benefits.', 'sort_order' => 5],
        ] as $kb) {
            KbCategory::firstOrCreate(['name' => $kb['name']], $kb);
        }
    }
}
