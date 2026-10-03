<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');

        return $this->boolean('is_internal')
            ? $this->user()->can('addInternalNote', $ticket)
            : $this->user()->can('reply', $ticket);
    }

    public function rules(): array
    {
        $att = config('helpdesk.attachments');

        return [
            'body' => ['required', 'string', 'max:20000'],
            'is_internal' => ['nullable', 'boolean'],
            'status_id' => ['nullable', 'exists:statuses,id'],
            'attachments' => ['nullable', 'array', 'max:'.$att['max_files']],
            'attachments.*' => ['file', 'max:'.$att['max_kb'], 'mimes:'.$att['mimes']],
        ];
    }
}
