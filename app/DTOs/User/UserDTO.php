<?php

namespace App\DTOs\User;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

final readonly class UserDTO
{
    /**
     * @param  array<int, string>  $roles
     */
    public function __construct(
        public string $name,
        public string $email,
        public array $roles = [],
        public ?UserStatus $status = null,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        /** @var array<string, mixed> $data */
        $data = $request->validated();

        /** @var array<int, string> $roles */
        $roles = $data['roles'] ?? [];

        return new self(
            name: (string) $data['name'],
            email: (string) $data['email'],
            roles: $roles,
            status: isset($data['status']) ? UserStatus::from((string) $data['status']) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
