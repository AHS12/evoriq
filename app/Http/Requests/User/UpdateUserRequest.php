<?php

namespace App\Http\Requests\User;

use App\Concerns\ProfileValidationRules;
use App\Concerns\ValidatesRoleAssignment;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules, ValidatesRoleAssignment;

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
        $target = $this->route('user');

        return [
            ...$this->profileRules($target instanceof User ? $target->id : null),
            'roles' => 'array',
            'roles.*' => 'string|exists:roles,name',
            'status' => ['sometimes', Rule::enum(UserStatus::class)],
        ];
    }
}
