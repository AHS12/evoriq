<?php

namespace App\Imports;

use App\Enums\DataEntity;
use App\Enums\UserStatus;
use App\Imports\Contracts\Importable;
use App\Models\DataProcessingJob;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\User\UserService;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Imports users from a CSV/XLSX file.
 *
 * New emails are created as invited accounts; existing emails (in the database
 * or earlier in the same file) are skipped and reported.
 */
class UserImport implements Importable
{
    private int $processed = 0;

    private int $created = 0;

    private int $skipped = 0;

    /** @var array<int, array{row: int, type: string, message: string}> */
    private array $errors = [];

    /** @var array<int, string> */
    private array $seen = [];

    public function __construct(
        private readonly DataProcessingJob $job,
        private readonly ?Closure $onProgress = null,
    ) {}

    public function entity(): DataEntity
    {
        return DataEntity::USERS;
    }

    public function chunkSize(): int
    {
        return (int) config('exports.import.chunk_size', 100);
    }

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        $users = app(UserRepositoryInterface::class);
        $invitations = app(UserService::class);
        $filters = $this->job->filters ?? [];
        $sendInvitations = (bool) ($filters['send_invitations'] ?? true);
        $defaultRole = trim((string) ($filters['default_role'] ?? ''));

        foreach ($rows as $row) {
            $this->processed++;
            $rowNumber = $this->processed + 1; // Row 1 is the heading.

            $data = $this->normalize($row);

            $violations = $this->validate($data);

            if ($violations !== []) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'type' => 'validation',
                    'message' => implode(' ', $violations),
                ];

                continue;
            }

            if (in_array($data['email'], $this->seen, true) || $users->findByEmail($data['email']) !== null) {
                $this->skipped++;
                $this->errors[] = [
                    'row' => $rowNumber,
                    'type' => 'duplicate',
                    'message' => __('Already exists: :email', ['email' => $data['email']]),
                ];

                continue;
            }

            try {
                $user = $users->create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Str::password(32),
                    'status' => UserStatus::INVITED,
                    'invitation_token' => (string) Str::uuid(),
                    'invitation_sent_at' => now(),
                    'created_by' => $this->job->user_id,
                    'updated_by' => $this->job->user_id,
                ]);

                if ($data['roles'] === [] && $defaultRole !== '') {
                    $roles = [$defaultRole];
                } else {
                    $roles = $data['roles'];
                }

                if ($roles !== []) {
                    $users->assignRoles($user, $roles);
                }

                if ($sendInvitations) {
                    $invitations->invite($user);
                }

                $this->created++;
                $this->seen[] = $data['email'];
            } catch (Throwable $e) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'type' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        if ($this->onProgress !== null) {
            ($this->onProgress)($this->processed, 'Importing rows');
        }
    }

    public function result(): ImportResult
    {
        $failed = count(array_filter(
            $this->errors,
            static fn (array $error): bool => $error['type'] !== 'duplicate',
        ));

        return new ImportResult(
            processed: $this->processed,
            created: $this->created,
            skipped: $this->skipped,
            failed: $failed,
            errors: $this->errors,
        );
    }

    /**
     * @param  Collection<string, mixed>  $row
     * @return array{name: string, email: string, roles: array<int, string>}
     */
    private function normalize(Collection $row): array
    {
        $value = static fn (string $key): string => trim((string) ($row->get($key) ?? ''));

        $roles = array_values(array_filter(array_map(
            static fn (string $role): string => trim($role),
            explode(',', $value('roles')),
        )));

        return [
            'name' => $value('name'),
            'email' => strtolower($value('email')),
            'roles' => $roles,
        ];
    }

    /**
     * @param  array{name: string, email: string, roles: array<int, string>}  $data
     * @return array<int, string>
     */
    private function validate(array $data): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = __('The name field is required.');
        } elseif (mb_strlen($data['name']) > 255) {
            $errors[] = __('The name must not exceed 255 characters.');
        }

        if ($data['email'] === '') {
            $errors[] = __('The email field is required.');
        } elseif (! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = __('The email field must be a valid email address.');
        }

        return $errors;
    }
}
