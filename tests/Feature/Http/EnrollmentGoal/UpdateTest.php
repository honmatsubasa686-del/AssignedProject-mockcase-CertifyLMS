<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_student_can_update_goal(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        // Act
        $response = $this->actingAs($student)
            ->patch(route('enrollment-goals.update', $goal), [
                'title' => '更新後の目標',
                'description' => '更新後の詳細',
                'target_date' => now()->addMonths(2)->toDateString(),
            ]);

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
            'title' => '更新後の目標',
            'description' => '更新後の詳細',
        ]);
    }

    public function test_other_student_cannot_update_goal(): void
    {
        // Arrange
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($owner)->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create([
            'title' => '元の目標',
        ]);

        // Act
        $response = $this->actingAs($other)
            ->patchJson(route('enrollment-goals.update', $goal), [
                'title' => '勝手に変更',
            ]);

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
            'title' => '元の目標',
        ]);
    }

    public function test_owner_student_can_clear_optional_fields(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        $goal = EnrollmentGoal::factory()->for($enrollment)->create([
            'title' => '元の目標',
            'description' => '元の詳細',
            'target_date' => now()->addMonth()->toDateString(),
        ]);

        // Act
        $response = $this->actingAs($student)
            ->patch(route('enrollment-goals.update', $goal), [
                'title' => '元の目標',
                'description' => null,
                'target_date' => null,
            ]);

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
            'description' => null,
            'target_date' => null,
        ]);
    }
}
