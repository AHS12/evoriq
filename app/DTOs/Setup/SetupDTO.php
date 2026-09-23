<?php

namespace App\DTOs\Setup;

use App\Http\Requests\Setup\StoreSuperAdminRequest;

final readonly class SetupDTO
{
    public function __construct(
        public string $appName,
        public string $name,
        public string $email,
        public string $password,
    ) {}

    public static function fromRequest(StoreSuperAdminRequest $request): self
    {
        $data = $request->validated();

        return new self(
            appName: $data['app_name'],
            name: $data['name'] ?? '',
            email: $data['email'] ?? '',
            password: $data['password'] ?? '',
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'app_name' => $this->appName,
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
        ];
    }
}
