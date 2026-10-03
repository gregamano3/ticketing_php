<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TriageTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('triage', $this->route('ticket'));
    }

    public function rules(): array
    {
        return [
            'department_id' => ['required', 'exists:departments,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'priority_id' => ['required', 'exists:priorities,id'],
            'assignee_id' => ['nullable', 'exists:users,id'],
        ];
    }
}
