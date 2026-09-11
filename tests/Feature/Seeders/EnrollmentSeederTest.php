<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Models\EnrollmentGoal;
use Database\Seeders\CertificationCategorySeeder;
use Database\Seeders\CertificationSeeder;
use Database\Seeders\EnrollmentSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixed_student_has_achieved_and_unachieved_goals(): void
    {
        // Arrange
        $this->seed(UserSeeder::class);
        $this->seed(CertificationCategorySeeder::class);
        $this->seed(CertificationSeeder::class);

        // Act
        $this->seed(EnrollmentSeeder::class);

        // Assert
        $this->assertDatabaseHas('enrollment_goals', [
            'title' => '過去問5年分を解き終える',
            'achieved_at' => null,
        ]);

        $this->assertTrue(
            EnrollmentGoal::query()
                ->where('title', '基礎教材を一周する')
                ->whereNotNull('achieved_at')
                ->exists(),
        );
    }

    public function test_demo_students_have_mixed_goals(): void
    {
        // Arrange
        $this->seed(UserSeeder::class);
        $this->seed(CertificationCategorySeeder::class);
        $this->seed(CertificationSeeder::class);

        // Act
        $this->seed(EnrollmentSeeder::class);

        // Assert
        $this->assertTrue(
            EnrollmentGoal::query()
                ->where('title', 'デモ用の個人目標')
                ->whereNull('achieved_at')
                ->exists(),
        );

        $this->assertTrue(
            EnrollmentGoal::query()
                ->where('title', 'デモ用の個人目標')
                ->whereNotNull('achieved_at')
                ->exists(),
        );
    }
}
