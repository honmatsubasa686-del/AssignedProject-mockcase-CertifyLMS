<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentNote;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_coach_can_see_notes_on_enrollment_detail(): void
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

        EnrollmentNote::factory()->create([
            'enrollment_id' => $enrollment->id,
            'author_user_id' => $coach->id,
            'body' => '担当coachに見えるメモです。',
        ]);

        // Act
        $response = $this->actingAs($coach)
            ->get(route('enrollments.show', $enrollment));

        // Assert
        $response->assertOk();
        $response->assertSee('担当coachに見えるメモです。');
    }

    public function test_assigned_coach_can_see_another_coachs_note(): void
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
            'body' => '別coachが書いた共有メモです。',
        ]);

        // Act
        $response = $this->actingAs($coach)
            ->get(route('enrollments.show', $enrollment));

        // Assert
        $response->assertOk();
        $response->assertSee('別coachが書いた共有メモです。');

        $response->assertDontSee(
            route('enrollment-notes.edit', $note),
            false,
        );

        $response->assertDontSee(
            route('enrollment-notes.destroy', $note),
            false,
        );
    }

    public function test_admin_can_see_note_and_management_links(): void
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
            'body' => 'adminにも見えるメモです。',
        ]);

        // Act
        $response = $this->actingAs($admin)
            ->get(route('enrollments.show', $enrollment));

        // Assert
        $response->assertOk();
        $response->assertSee('adminにも見えるメモです。');

        $response->assertSee(
            route('enrollment-notes.edit', $note),
            false,
        );

        $response->assertSee(
            route('enrollment-notes.destroy', $note),
            false,
        );
    }

    public function test_student_cannot_see_notes_on_enrollment_detail(): void
    {
        // Arrange
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();

        EnrollmentNote::factory()->create([
            'enrollment_id' => $enrollment->id,
            'author_user_id' => $coach->id,
            'body' => '受講生には見えてはいけないメモです。',
        ]);

        // Act
        $response = $this->actingAs($student)
            ->get(route('enrollments.show', $enrollment));

        // Assert
        $response->assertOk();
        $response->assertDontSee('受講生には見えてはいけないメモです。');
    }

    public function test_unassigned_coach_cannot_view_enrollment_notes(): void
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
            ->get(route('enrollments.show', $enrollment));

        // Assert
        $response->assertForbidden();
    }

    public function test_notes_are_shown_newest_first(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();

        EnrollmentNote::factory()->create([
            'enrollment_id' => $enrollment->id,
            'author_user_id' => $admin->id,
            'body' => '古いメモ',
            'created_at' => now()->subDay(),
        ]);

        EnrollmentNote::factory()->create([
            'enrollment_id' => $enrollment->id,
            'author_user_id' => $admin->id,
            'body' => '新しいメモ',
            'created_at' => now(),
        ]);

        // Act
        $response = $this->actingAs($admin)
            ->get(route('enrollments.show', $enrollment));

        // Assert
        $response->assertOk();
        $response->assertSeeInOrder([
            '新しいメモ',
            '古いメモ',
        ]);
    }
}
