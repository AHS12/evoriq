<?php

namespace App\Http\Requests\Connection;

use App\Models\ClockifyConnection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SelectWorkspaceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $connection = $this->route('connection');

        return [
            'clockify_id' => [
                'required',
                'string',
                Rule::exists('clockify_workspaces', 'clockify_id')->where(
                    'connection_id',
                    $connection instanceof ClockifyConnection ? $connection->id : null,
                ),
            ],
        ];
    }
}
