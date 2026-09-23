<?php

namespace App\Http\Requests\Setup;

use App\Enums\DatabaseDriver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDatabaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $driver = $this->input('driver');

        if ($driver === DatabaseDriver::SQLITE->value) {
            return [
                'driver' => ['required', Rule::enum(DatabaseDriver::class)],
                'database' => ['required', 'string', 'max:1000'],
                'create_database' => ['boolean'],
            ];
        }

        return [
            'driver' => ['required', Rule::enum(DatabaseDriver::class)],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_]+$/'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'create_database' => ['boolean'],
        ];
    }
}
