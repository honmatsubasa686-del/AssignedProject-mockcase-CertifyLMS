<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentNote;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_coach_can_edit_own_note(): void
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
        ]);

        // Act
        $response = $this->actingAs($coach)
            ->get(route('enrollment-notes.edit', $note));

        // Assert
        $response->assertOk();
        $response->assertViewIs('enrollment-note.edit');
        $response->assertViewHas('note', fn (EnrollmentNote $viewNote) => $viewNote->is($note));
    }

    public function test_assigned_coach_cannot_edit_another_coachs_note(): void
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
        ]);

        // Act
        $response = $this->actingAs($coach)
            ->get(route('enrollment-notes.edit', $note));

        // Assert
        $response->assertForbidden();
    }

    public function test_admin_can_edit_any_note(): void
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
        ]);

        // Act
        $response = $this->actingAs($admin)
            ->get(route('enrollment-notes.edit', $note));

        // Assert
        $response->assertOk();
        $response->assertViewIs('enrollment-note.edit');
    }

    public function test_student_cannot_edit_note(): void
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
        ]);

        // Act
        $response = $this->actingAs($student)
            ->get(route('enrollment-notes.edit', $note));

        // Assert
        $response->assertForbidden();
    }

    public function test_unassigned_coach_cannot_edit_note(): void
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
        ]);

        // Act
        $response = $this->actingAs($coach)
            ->get(route('enrollment-notes.edit', $note));

        // Assert
        $response->assertForbidden();
    }
}
