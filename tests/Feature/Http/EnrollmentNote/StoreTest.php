<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentNote;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_coach_can_create_note(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->create();

        $certification->coaches()->attach($coach->id, [
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->learning()
            ->create();

        // Act
        $response = $this->actingAs($coach)
            ->post(route('enrollments.notes.store', $enrollment), [
                'body' => '次回面談で進捗を確認する。',
            ]);

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'author_user_id' => $coach->id,
            'body' => '次回面談で進捗を確認する。',
        ]);
    }

    public function test_admin_can_create_note(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();

        // Act
        $response = $this->actingAs($admin)
            ->post(route('enrollments.notes.store', $enrollment), [
                'body' => '管理者メモです。',
            ]);

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseHas('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'author_user_id' => $admin->id,
            'body' => '管理者メモです。',
        ]);
    }

    public function test_unassigned_coach_cannot_create_note(): void
    {
        // Arrange
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();

        // Act
        $response = $this->actingAs($coach)
            ->postJson(route('enrollments.notes.store', $enrollment), [
                'body' => '担当外資格へのメモ',
            ]);

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseMissing('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'author_user_id' => $coach->id,
            'body' => '担当外資格へのメモ',
        ]);
    }

    public function test_student_cannot_create_note(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();

        // Act
        $response = $this->actingAs($student)
            ->postJson(route('enrollments.notes.store', $enrollment), [
                'body' => '受講生が書こうとしたメモ',
            ]);

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseMissing('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'author_user_id' => $student->id,
            'body' => '受講生が書こうとしたメモ',
        ]);
    }

    public function test_body_is_required(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();

        // Act
        $response = $this->actingAs($admin)
            ->postJson(route('enrollments.notes.store', $enrollment), []);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['body']);
    }

    public function test_body_must_not_exceed_2000_characters(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();

        // Act
        $response = $this->actingAs($admin)
            ->postJson(route('enrollments.notes.store', $enrollment), [
                'body' => str_repeat('あ', 2001),
            ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['body']);
    }
}
