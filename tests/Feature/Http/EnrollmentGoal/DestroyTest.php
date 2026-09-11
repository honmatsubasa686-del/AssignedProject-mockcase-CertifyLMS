<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_student_can_delete_goal(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        // Act
        $response = $this->actingAs($student)
            ->delete(route('enrollment-goals.destroy', $goal));

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('enrollment_goals', [
            'id' => $goal->id,
        ]);
    }

    public function test_other_student_cannot_delete_goal(): void
    {
        // Arrange
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($owner)->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        // Act
        $response = $this->actingAs($other)
            ->deleteJson(route('enrollment-goals.destroy', $goal));

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
        ]);
    }

    public function test_goals_are_deleted_when_enrollment_is_deleted(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        // Act
        $response = $this->actingAs($student)
            ->delete(route('enrollments.destroy', $enrollment));

        // Assert
        $response->assertRedirect();

        $this->assertSoftDeleted('enrollments', [
            'id' => $enrollment->id,
        ]);

        $this->assertDatabaseMissing('enrollment_goals', [
            'id' => $goal->id,
        ]);
    }
}
