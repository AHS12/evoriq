<?php

namespace App\Http\Requests\User;

use App\Concerns\ValidatesRoleAssignment;
use Illuminate\Foundation\Http\FormRequest;

class AssignRolesRequest extends FormRequest
{
    use ValidatesRoleAssignment;

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
        return [
            'roles' => 'array',
            'roles.*' => 'string|exists:roles,name',
        ];
    }
}
