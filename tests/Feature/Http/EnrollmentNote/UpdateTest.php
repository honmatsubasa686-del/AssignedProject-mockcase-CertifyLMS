<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentNote;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_coach_can_update_own_note(): void
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

        $note = EnrollmentNote::factory()->create([
            'enrollment_id' => $enrollment->id,
            'author_user_id' => $coach->id,
            'body' => '更新前のメモ',
        ]);

        // Act
        $response = $this->actingAs($coach)
            ->patch(route('enrollment-notes.update', $note), [
                'body' => '更新後のメモ',
            ]);

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => '更新後のメモ',
        ]);
    }

    public function test_assigned_coach_cannot_update_another_coachs_note(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $otherCoach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->create();

        $certification->coaches()->attach($coach->id, [
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $certification->coaches()->attach($otherCoach->id, [
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->learning()
            ->create();

        $note = EnrollmentNote::factory()->create([
            'enrollment_id' => $enrollment->id,
            'author_user_id' => $otherCoach->id,
            'body' => '元のメモ',
        ]);

        // Act
        $response = $this->actingAs($coach)
            ->patchJson(route('enrollment-notes.update', $note), [
                'body' => '勝手に更新しようとしたメモ',
            ]);

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => '元のメモ',
        ]);
    }

    public function test_admin_can_update_any_note(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();

        $note = EnrollmentNote::factory()->create([
            'enrollment_id' => $enrollment->id,
            'author_user_id' => $coach->id,
            'body' => '更新前のメモ',
        ]);

        // Act
        $response = $this->actingAs($admin)
            ->patch(route('enrollment-notes.update', $note), [
                'body' => '管理者が更新したメモ',
            ]);

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => '管理者が更新したメモ',
        ]);
    }

    public function test_student_cannot_update_note(): void
    {
        // Arrange
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();

        $note = EnrollmentNote::factory()->create([
            'enrollment_id' => $enrollment->id,
            'author_user_id' => $coach->id,
            'body' => '元のメモ',
        ]);

        // Act
        $response = $this->actingAs($student)
            ->patchJson(route('enrollment-notes.update', $note), [
                'body' => '受講生が更新しようとしたメモ',
            ]);

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => '元のメモ',
        ]);
    }

    public function test_unassigned_coach_cannot_update_note(): void
    {
        // Arrange
        $coach = User::factory()->coach()->inProgress()->create();
        $author = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();

        $note = EnrollmentNote::factory()->create([
            'enrollment_id' => $enrollment->id,
            'author_user_id' => $author->id,
            'body' => '元のメモ',
        ]);

        // Act
        $response = $this->actingAs($coach)
            ->patchJson(route('enrollment-notes.update', $note), [
                'body' => '担当外coachが更新しようとしたメモ',
            ]);

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => '元のメモ',
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

        $note = EnrollmentNote::factory()->create([
            'enrollment_id' => $enrollment->id,
            'author_user_id' => $admin->id,
            'body' => '元のメモ',
        ]);

        // Act
        $response = $this->actingAs($admin)
            ->patchJson(route('enrollment-notes.update', $note), []);

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

        $note = EnrollmentNote::factory()->create([
            'enrollment_id' => $enrollment->id,
            'author_user_id' => $admin->id,
            'body' => '元のメモ',
        ]);

        // Act
        $response = $this->actingAs($admin)
            ->patchJson(route('enrollment-notes.update', $note), [
                'body' => str_repeat('あ', 2001),
            ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['body']);
    }
}
