<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentNote;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_coach_can_delete_own_note(): void
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
            'body' => '削除対象のメモ',
        ]);

        // Act
        $response = $this->actingAs($coach)
            ->delete(route('enrollment-notes.destroy', $note));

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('enrollment_notes', [
            'id' => $note->id,
        ]);
    }

    public function test_assigned_coach_cannot_delete_another_coachs_note(): void
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
            'body' => '他coachのメモ',
        ]);

        // Act
        $response = $this->actingAs($coach)
            ->deleteJson(route('enrollment-notes.destroy', $note));

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => '他coachのメモ',
        ]);
    }

    public function test_admin_can_delete_any_note(): void
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
            'body' => '管理者が削除するメモ',
        ]);

        // Act
        $response = $this->actingAs($admin)
            ->delete(route('enrollment-notes.destroy', $note));

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseMissing('enrollment_notes', [
            'id' => $note->id,
        ]);
    }

    public function test_student_cannot_delete_note(): void
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
            'body' => '削除してはいけないメモ',
        ]);

        // Act
        $response = $this->actingAs($student)
            ->deleteJson(route('enrollment-notes.destroy', $note));

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => '削除してはいけないメモ',
        ]);
    }

    public function test_unassigned_coach_cannot_delete_note(): void
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
            'body' => '削除してはいけないメモ',
        ]);

        // Act
        $response = $this->actingAs($coach)
            ->deleteJson(route('enrollment-notes.destroy', $note));

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => '削除してはいけないメモ',
        ]);
    }

    public function test_notes_are_deleted_when_enrollment_is_deleted(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();

        $note = EnrollmentNote::factory()->create([
            'enrollment_id' => $enrollment->id,
            'author_user_id' => $coach->id,
            'body' => 'Enrollment削除時に消えるメモ',
        ]);

        // Act
        $response = $this->actingAs($student)
            ->delete(route('enrollments.destroy', $enrollment));

        // Assert
        $response->assertRedirect();

        $this->assertSoftDeleted('enrollments', [
            'id' => $enrollment->id,
        ]);

        $this->assertDatabaseMissing('enrollment_notes', [
            'id' => $note->id,
        ]);
    }
}
