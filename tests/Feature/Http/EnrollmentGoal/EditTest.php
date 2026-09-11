<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_student_can_open_edit_page(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create([
            'title' => '編集前の目標',
        ]);

        // Act
        $response = $this->actingAs($student)
            ->get(route('enrollment-goals.edit', $goal));

        // Assert
        $response->assertOk();
        $response->assertViewIs('enrollment-goal.edit');
        $response->assertSee('編集前の目標');
    }

    public function test_other_student_cannot_open_edit_page(): void
    {
        // Arrange
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($owner)->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        // Act
        $response = $this->actingAs($other)
            ->get(route('enrollment-goals.edit', $goal));

        // Assert
        $response->assertForbidden();
    }
}
