<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $att = config('helpdesk.attachments');

        return [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:20000'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'priority_id' => ['nullable', 'exists:priorities,id'],
            'requester_id' => ['nullable', 'exists:users,id'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:tags,id'],
            'watchers' => ['nullable', 'array'],
            'watchers.*' => ['exists:users,id'],
            'attachments' => ['nullable', 'array', 'max:'.$att['max_files']],
            'attachments.*' => ['file', 'max:'.$att['max_kb'], 'mimes:'.$att['mimes']],
        ];
    }
}
