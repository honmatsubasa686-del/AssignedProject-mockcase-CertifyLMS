<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchieveTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_student_can_mark_goal_as_achieved(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create([
            'achieved_at' => null,
        ]);

        // Act
        $response = $this->actingAs($student)
            ->post(route('enrollment-goals.markAchieved', $goal));

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $response->assertSessionHas('success');

        $this->assertNotNull($goal->fresh()->achieved_at);
    }

    public function test_owner_student_can_unmark_goal_as_achieved(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->achieved()
            ->create();

        // Act
        $response = $this->actingAs($student)
            ->delete(route('enrollment-goals.unmarkAchieved', $goal));

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $response->assertSessionHas('success');

        $this->assertNull($goal->fresh()->achieved_at);
    }

    public function test_other_student_cannot_mark_goal_as_achieved(): void
    {
        // Arrange
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($owner)->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create([
            'achieved_at' => null,
        ]);

        // Act
        $response = $this->actingAs($other)
            ->postJson(route('enrollment-goals.markAchieved', $goal));

        // Assert
        $response->assertForbidden();

        $this->assertNull($goal->fresh()->achieved_at);
    }
}
