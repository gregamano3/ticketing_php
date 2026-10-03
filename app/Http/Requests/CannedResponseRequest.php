<?php

namespace App\Http\Requests;

use App\Models\CannedResponse;
use Illuminate\Foundation\Http\FormRequest;

class CannedResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $response = $this->route('canned_response');

        return $response ? $this->user()->can('update', $response) : $this->user()->can('create', CannedResponse::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'shared' => ['nullable', 'boolean'],
        ];
    }
}
