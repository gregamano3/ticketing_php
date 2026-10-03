<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('ticket'));
    }

    public function rules(): array
    {
        return [
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string', 'max:20000'],
            'department_id' => ['sometimes', 'nullable', 'exists:departments,id'],
            'category_id' => ['sometimes', 'nullable', 'exists:categories,id'],
            'priority_id' => ['sometimes', 'required', 'exists:priorities,id'],
            'status_id' => ['sometimes', 'required', 'exists:statuses,id'],
            'assignee_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['exists:tags,id'],
            'watchers' => ['sometimes', 'nullable', 'array'],
            'watchers.*' => ['exists:users,id'],
        ];
    }
}
