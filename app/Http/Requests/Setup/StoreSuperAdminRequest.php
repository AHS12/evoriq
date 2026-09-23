<?php

namespace App\Http\Requests\Setup;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Services\Setup\SetupService;
use Illuminate\Foundation\Http\FormRequest;

class StoreSuperAdminRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

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
        // When a super admin already exists the wizard skips the account step,
        // so the profile/password fields become optional.
        if (app(SetupService::class)->hasSuperAdmin()) {
            return [
                'app_name' => ['required', 'string', 'max:255'],
                'name' => ['nullable', 'string', 'max:255'],
                'email' => ['nullable', 'string', 'email', 'max:255'],
                'password' => ['nullable', 'string'],
            ];
        }

        return [
            'app_name' => ['required', 'string', 'max:255'],
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ];
    }
}
