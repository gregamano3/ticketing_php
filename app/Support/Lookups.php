<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Department;
use App\Models\KbCategory;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * Field definitions for the simple admin-managed lookup tables. The generic
 * Admin\LookupController and its views render index/create/edit from these.
 */
class Lookups
{
    public const COLORS = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'dark', 'light'];

    public static function all(): array
    {
        return [
            'departments' => [
                'model' => Department::class,
                'title' => 'Departments',
                'singular' => 'department',
                'icon' => 'bi bi-building',
                'with' => ['lead'],
                'counts' => ['users', 'tickets'],
                'columns' => ['name' => 'Name', 'lead.name' => 'Lead', 'users_count' => 'Members', 'tickets_count' => 'Tickets'],
                'fields' => [
                    'name' => ['label' => 'Name', 'type' => 'text', 'rules' => fn ($id) => ['required', 'string', 'max:255', Rule::unique('departments')->ignore($id)]],
                    'lead_id' => ['label' => 'Department lead', 'type' => 'select', 'options' => fn () => User::agents()->active()->orderBy('name')->pluck('name', 'id'), 'rules' => fn () => ['nullable', 'exists:users,id'], 'help' => 'Receives level 1 SLA escalations.'],
                    'description' => ['label' => 'Description', 'type' => 'textarea', 'rules' => fn () => ['nullable', 'string', 'max:2000']],
                ],
            ],
            'categories' => [
                'model' => Category::class,
                'title' => 'Ticket categories',
                'singular' => 'category',
                'icon' => 'bi bi-diagram-3',
                'with' => ['department', 'parent'],
                'counts' => ['tickets'],
                'columns' => ['name' => 'Name', 'parent.name' => 'Parent', 'department.name' => 'Department', 'is_active' => 'Active', 'tickets_count' => 'Tickets'],
                'fields' => [
                    'name' => ['label' => 'Name', 'type' => 'text', 'rules' => fn () => ['required', 'string', 'max:255']],
                    'department_id' => ['label' => 'Department', 'type' => 'select', 'options' => fn () => Department::orderBy('name')->pluck('name', 'id'), 'rules' => fn () => ['nullable', 'exists:departments,id'], 'help' => 'Tickets in this category are routed to this department.'],
                    'parent_id' => ['label' => 'Parent category', 'type' => 'select', 'options' => fn ($id) => Category::whereNull('parent_id')->whereKeyNot($id ?? 0)->orderBy('name')->pluck('name', 'id'), 'rules' => fn ($id) => ['nullable', 'exists:categories,id', Rule::notIn([$id])]],
                    'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => true, 'rules' => fn () => ['boolean']],
                ],
            ],
            'priorities' => [
                'model' => Priority::class,
                'title' => 'Priorities & SLA',
                'singular' => 'priority',
                'icon' => 'bi bi-flag',
                'order' => 'level',
                'counts' => ['tickets'],
                'columns' => ['name' => 'Name', 'level' => 'Level', 'color' => 'Color', 'response_minutes' => 'Response (min)', 'resolution_minutes' => 'Resolution (min)', 'is_default' => 'Default', 'tickets_count' => 'Tickets'],
                'fields' => [
                    'name' => ['label' => 'Name', 'type' => 'text', 'rules' => fn ($id) => ['required', 'string', 'max:255', Rule::unique('priorities')->ignore($id)]],
                    'level' => ['label' => 'Level', 'type' => 'number', 'rules' => fn () => ['required', 'integer', 'min:1', 'max:100'], 'help' => 'Higher is more urgent.'],
                    'color' => ['label' => 'Color', 'type' => 'color', 'rules' => fn () => ['required', Rule::in(self::COLORS)]],
                    'response_minutes' => ['label' => 'First response target (minutes)', 'type' => 'number', 'rules' => fn () => ['required', 'integer', 'min:1']],
                    'resolution_minutes' => ['label' => 'Resolution target (minutes)', 'type' => 'number', 'rules' => fn () => ['required', 'integer', 'min:1', 'gte:response_minutes']],
                    'is_default' => ['label' => 'Default for new tickets', 'type' => 'checkbox', 'unique_flag' => true, 'rules' => fn () => ['boolean']],
                ],
            ],
            'statuses' => [
                'model' => Status::class,
                'title' => 'Statuses',
                'singular' => 'status',
                'icon' => 'bi bi-signpost-split',
                'order' => 'sort_order',
                'counts' => ['tickets'],
                'columns' => ['name' => 'Name', 'color' => 'Color', 'sort_order' => 'Order', 'is_default' => 'Default', 'pauses_sla' => 'Pauses SLA', 'is_resolved' => 'Resolved', 'is_closed' => 'Closed', 'tickets_count' => 'Tickets'],
                'fields' => [
                    'name' => ['label' => 'Name', 'type' => 'text', 'rules' => fn ($id) => ['required', 'string', 'max:255', Rule::unique('statuses')->ignore($id)]],
                    'color' => ['label' => 'Color', 'type' => 'color', 'rules' => fn () => ['required', Rule::in(self::COLORS)]],
                    'sort_order' => ['label' => 'Sort order', 'type' => 'number', 'rules' => fn () => ['required', 'integer', 'min:0']],
                    'is_default' => ['label' => 'Default for new tickets', 'type' => 'checkbox', 'unique_flag' => true, 'rules' => fn () => ['boolean']],
                    'pauses_sla' => ['label' => 'Pauses the SLA clock (e.g. waiting on requester)', 'type' => 'checkbox', 'rules' => fn () => ['boolean']],
                    'is_resolved' => ['label' => 'Counts as resolved', 'type' => 'checkbox', 'rules' => fn () => ['boolean']],
                    'is_closed' => ['label' => 'Counts as closed', 'type' => 'checkbox', 'rules' => fn () => ['boolean']],
                ],
            ],
            'tags' => [
                'model' => Tag::class,
                'title' => 'Tags',
                'singular' => 'tag',
                'icon' => 'bi bi-tags',
                'counts' => ['tickets'],
                'columns' => ['name' => 'Name', 'color' => 'Color', 'tickets_count' => 'Tickets'],
                'fields' => [
                    'name' => ['label' => 'Name', 'type' => 'text', 'rules' => fn ($id) => ['required', 'string', 'max:50', Rule::unique('tags')->ignore($id)]],
                    'color' => ['label' => 'Color', 'type' => 'color', 'rules' => fn () => ['required', Rule::in(self::COLORS)]],
                ],
            ],
            'kb-categories' => [
                'model' => KbCategory::class,
                'title' => 'Knowledge base categories',
                'singular' => 'KB category',
                'icon' => 'bi bi-journal-bookmark',
                'order' => 'sort_order',
                'counts' => ['articles'],
                'columns' => ['icon' => 'Icon', 'name' => 'Name', 'slug' => 'Slug', 'sort_order' => 'Order', 'articles_count' => 'Articles'],
                'fields' => [
                    'name' => ['label' => 'Name', 'type' => 'text', 'rules' => fn () => ['required', 'string', 'max:255']],
                    'slug' => ['label' => 'Slug', 'type' => 'text', 'rules' => fn ($id) => ['nullable', 'alpha_dash', 'max:255', Rule::unique('kb_categories')->ignore($id)], 'help' => 'Leave empty to generate from the name.'],
                    'icon' => ['label' => 'Icon (Bootstrap Icons class)', 'type' => 'text', 'default' => 'bi bi-folder', 'rules' => fn () => ['required', 'string', 'max:100', 'regex:/^bi bi-[a-z0-9-]+$/']],
                    'description' => ['label' => 'Description', 'type' => 'textarea', 'rules' => fn () => ['nullable', 'string', 'max:2000']],
                    'sort_order' => ['label' => 'Sort order', 'type' => 'number', 'default' => 0, 'rules' => fn () => ['required', 'integer', 'min:0']],
                ],
            ],
        ];
    }

    public static function get(string $type): array
    {
        return self::all()[$type] ?? abort(404);
    }
}
