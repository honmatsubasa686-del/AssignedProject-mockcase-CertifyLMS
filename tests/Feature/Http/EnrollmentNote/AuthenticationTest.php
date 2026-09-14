<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentNote;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_enrollment_note_routes(): void
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
        ]);

        // Act & Assert
        $this->post(route('enrollments.notes.store', $enrollment), [
            'body' => '未ログインからのメモ',
        ])->assertRedirect(route('login'));

        $this->get(route('enrollment-notes.edit', $note))
            ->assertRedirect(route('login'));

        $this->patch(route('enrollment-notes.update', $note), [
            'body' => '未ログインからの更新',
        ])->assertRedirect(route('login'));

        $this->delete(route('enrollment-notes.destroy', $note))
            ->assertRedirect(route('login'));
    }
}
