<?php

namespace App\DTOs\Role;

use Illuminate\Foundation\Http\FormRequest;

final readonly class RoleDTO
{
    /**
     * @param  array<int, string>  $permissions
     */
    public function __construct(
        public string $name,
        public array $permissions = [],
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        /** @var array<string, mixed> $data */
        $data = $request->validated();

        /** @var array<int, string> $permissions */
        $permissions = $data['permissions'] ?? [];

        return new self(
            name: (string) $data['name'],
            permissions: $permissions,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
        ];
    }
}
