<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Certificate;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CertificateDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_download_own_certificate(): void
    {
        Storage::fake('private');

        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->passed()
            ->create();

        $certificate = Certificate::factory()
            ->forEnrollment($enrollment)
            ->create();

        Storage::disk('private')->put(
            $certificate->pdf_path,
            'fake pdf content',
        );

        $response = $this->actingAs($student)
            ->get(route('certificates.download', $certificate));

        $response->assertOk();

        $response->assertDownload('certificate.pdf');
    }

    public function test_student_cannot_download_another_students_certificate(): void
    {
        Storage::fake('private');

        $owner = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()
            ->for($owner)
            ->for($certification)
            ->passed()
            ->create();

        $certificate = Certificate::factory()
            ->forEnrollment($enrollment)
            ->create();

        Storage::disk('private')->put(
            $certificate->pdf_path,
            'fake pdf content',
        );

        $response = $this->actingAs($otherStudent)
            ->get(route('certificates.download', $certificate));

        $response->assertForbidden();
    }

    public function test_assigned_coach_can_download_certificate(): void
    {
        Storage::fake('private');

        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->passed()
            ->create();

        $certificate = Certificate::factory()
            ->forEnrollment($enrollment)
            ->create();

        Storage::disk('private')->put(
            $certificate->pdf_path,
            'fake pdf content',
        );

        $response = $this->actingAs($coach)
            ->get(route('certificates.download', $certificate));

        $response->assertOk();
    }

    public function test_unassigned_coach_cannot_download_certificate(): void
    {
        Storage::fake('private');

        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->passed()
            ->create();

        $certificate = Certificate::factory()
            ->forEnrollment($enrollment)
            ->create();

        Storage::disk('private')->put(
            $certificate->pdf_path,
            'fake pdf content',
        );

        $response = $this->actingAs($coach)
            ->get(route('certificates.download', $certificate));

        $response->assertForbidden();
    }

    public function test_admin_can_download_any_certificate(): void
    {
        Storage::fake('private');

        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->passed()
            ->create();

        $certificate = Certificate::factory()
            ->forEnrollment($enrollment)
            ->create();

        Storage::disk('private')->put(
            $certificate->pdf_path,
            'fake pdf content',
        );

        $response = $this->actingAs($admin)
            ->get(route('certificates.download', $certificate));

        $response->assertOk();
    }

    public function test_cannot_download_when_pdf_file_is_missing(): void
    {
        Storage::fake('private');

        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->passed()
            ->create();

        $certificate = Certificate::factory()
            ->forEnrollment($enrollment)
            ->create();

        $response = $this->actingAs($student)
            ->get(route('certificates.download', $certificate));

        $response->assertNotFound();
    }

    public function test_graduated_student_can_download_own_certificate(): void
    {
        Storage::fake('private');

        $student = User::factory()
            ->student()
            ->graduated()
            ->create();

        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->passed()
            ->create();

        $certificate = Certificate::factory()
            ->forEnrollment($enrollment)
            ->create();

        Storage::disk('private')->put(
            $certificate->pdf_path,
            'fake pdf content',
        );

        $response = $this->actingAs($student)
            ->get(route('certificates.download', $certificate));

        $response->assertOk();
    }
}
