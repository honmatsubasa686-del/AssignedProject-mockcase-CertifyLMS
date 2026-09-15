<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AnnouncementTargetType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(6),
            'body' => fake()->realText(300),
            'target_type' => AnnouncementTargetType::AllStudents->value,
            'target_certification_id' => null,
            'target_user_id' => User::factory(),
            'created_by_user_id' => User::factory(),
            'dispatched_count' => fake()->numberBetween(1, 20),
            'dispatched_at' => now(),
        ];
    }
}
