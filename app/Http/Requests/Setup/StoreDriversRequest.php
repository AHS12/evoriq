<?php

namespace App\Http\Requests\Setup;

use App\Enums\CacheDriver;
use App\Enums\QueueDriver;
use App\Enums\SessionDriver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDriversRequest extends FormRequest
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
        return [
            'session' => ['required', Rule::enum(SessionDriver::class)],
            'cache' => ['required', Rule::enum(CacheDriver::class)],
            'queue' => ['required', Rule::enum(QueueDriver::class)],
            'redis_host' => ['nullable', 'string', 'max:255'],
            'redis_port' => ['nullable', 'integer', 'between:1,65535'],
            'redis_password' => ['nullable', 'string', 'max:255'],
        ];
    }
}
