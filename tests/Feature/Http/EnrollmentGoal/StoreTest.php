<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_student_can_create_goal(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        $data = [
            'title' => '過去問5年分を解く',
            'description' => '毎週少しずつ進める',
            'target_date' => now()->addMonth()->toDateString(),
        ];

        // Act
        $response = $this->actingAs($student)
            ->from(route('enrollments.show', $enrollment))
            ->post(route('enrollments.goals.store', $enrollment), $data);

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('enrollment_goals', [
            'enrollment_id' => $enrollment->id,
            'title' => '過去問5年分を解く',
            'description' => '毎週少しずつ進める',
            'target_date' => $data['target_date'],
            'achieved_at' => null,
        ]);
    }

    public function test_other_student_cannot_create_goal(): void
    {
        // Arrange
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($owner)->learning()->create();

        // Act
        $response = $this->actingAs($other)
            ->postJson(route('enrollments.goals.store', $enrollment), [
                'title' => '勝手に追加する目標',
            ]);

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseMissing('enrollment_goals', [
            'enrollment_id' => $enrollment->id,
            'title' => '勝手に追加する目標',
        ]);
    }

    public function test_title_is_required(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        // Act
        $response = $this->actingAs($student)
            ->postJson(route('enrollments.goals.store', $enrollment), [
                'description' => 'タイトルなし',
                'target_date' => now()->addMonth()->toDateString(),
            ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title']);
    }

    public function test_title_must_not_exceed_100_characters(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        // Act
        $response = $this->actingAs($student)
            ->postJson(route('enrollments.goals.store', $enrollment), [
                'title' => str_repeat('あ', 101),
            ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title']);
    }

    public function test_coach_cannot_create_goal(): void
    {
        // Arrange
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        // Act
        $response = $this->actingAs($coach)
            ->postJson(route('enrollments.goals.store', $enrollment), [
                'title' => 'コーチが追加する目標',
            ]);

        // Assert
        $response->assertForbidden();
    }

    public function test_admin_cannot_create_goal(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        // Act
        $response = $this->actingAs($admin)
            ->postJson(route('enrollments.goals.store', $enrollment), [
                'title' => '管理者が追加する目標',
            ]);

        // Assert
        $response->assertForbidden();
    }

    public function test_graduated_student_cannot_create_goal(): void
    {
        // Arrange
        $student = User::factory()->student()->graduated()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        // Act
        $response = $this->actingAs($student)
            ->postJson(route('enrollments.goals.store', $enrollment), [
                'title' => '卒業後の目標',
            ]);

        // Assert
        $response->assertForbidden();
    }
}
