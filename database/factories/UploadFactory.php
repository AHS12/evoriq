<?php

namespace Database\Factories;

use App\Enums\UploadType;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Upload>
 */
class UploadFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Upload>
     */
    protected $model = Upload::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->word().'.pdf';

        return [
            'uuid' => (string) Str::uuid(),
            'type' => UploadType::DOCUMENT,
            'name' => $name,
            'file_name' => $name,
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1024, 1048576),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the upload is an image.
     */
    public function image(): static
    {
        return $this->state(fn (): array => [
            'type' => UploadType::IMAGE,
            'name' => 'image.jpg',
            'file_name' => 'image.jpg',
            'mime_type' => 'image/jpeg',
        ]);
    }
}
