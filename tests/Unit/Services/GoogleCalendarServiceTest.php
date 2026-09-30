<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\GoogleCredential;
use App\Models\Meeting;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Calendar;
use Google\Service\Calendar\Resource\Events;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @group external-api
 */
class GoogleCalendarServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_busy_periods_returns_empty_collection_when_google_request_fails(): void
    {
        $credential = new GoogleCredential([
            'user_id' => 'test-user-id',
            'access_token' => 'test-access-token',
            'calendar_id' => 'primary',
        ]);

        $service = $this->getMockBuilder(GoogleCalendarService::class)
            ->onlyMethods([
                'authenticatedClient',
                'makeCalendarService',
            ])
            ->getMock();

        $service
            ->method('authenticatedClient')
            ->willReturn(new GoogleClient);

        $service
            ->method('makeCalendarService')
            ->willThrowException(
                new \RuntimeException('Google API failed')
            );

        $periods = $service->busyPeriods(
            $credential,
            Carbon::parse('2026-06-01 09:00:00'),
            Carbon::parse('2026-06-01 18:00:00')
        );

        $this->assertTrue($periods->isEmpty());
    }

    public function test_create_meeting_event_returns_null_when_google_request_fails(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();

        $credential = GoogleCredential::create([
            'user_id' => $coach->id,
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'expires_at' => now()->addHour(),
            'calendar_id' => 'primary',
            'connected_at' => now(),
        ]);

        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'enrollment_id' => $enrollment->id,
                'scheduled_at' => Carbon::parse('2026-06-01 10:00:00'),
                'topic' => '相談したい',
                'meeting_url_snapshot' => 'https://meet.example.com/coach-room',
            ]);

        $service = $this->getMockBuilder(GoogleCalendarService::class)
            ->onlyMethods([
                'authenticatedClient',
                'makeCalendarService',
            ])
            ->getMock();

        $service
            ->method('authenticatedClient')
            ->willReturn(new GoogleClient);

        $service
            ->method('makeCalendarService')
            ->willThrowException(
                new \RuntimeException('Google API failed')
            );

        $eventId = $service->createMeetingEvent($meeting);

        $this->assertNull($eventId);
    }

    public function test_delete_meeting_event_does_not_throw_when_google_request_fails(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();

        GoogleCredential::create([
            'user_id' => $coach->id,
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'expires_at' => now()->addHour(),
            'calendar_id' => 'primary',
            'connected_at' => now(),
        ]);

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDays(3)->startOfHour(),
                'google_calendar_event_id' => 'google-event-123',
            ]);

        $service = $this->getMockBuilder(GoogleCalendarService::class)
            ->onlyMethods([
                'authenticatedClient',
                'makeCalendarService',
            ])
            ->getMock();

        $service
            ->method('authenticatedClient')
            ->willReturn(new GoogleClient);

        $service
            ->method('makeCalendarService')
            ->willThrowException(
                new \RuntimeException('Google API failed')
            );

        $service->deleteMeetingEvent($meeting);

        $this->addToAssertionCount(1);
    }

    public function test_delete_meeting_event_does_not_throw_when_google_event_is_already_deleted(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();

        GoogleCredential::create([
            'user_id' => $coach->id,
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'expires_at' => now()->addHour(),
            'calendar_id' => 'primary',
            'connected_at' => now(),
        ]);

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDays(3)->startOfHour(),
                'google_calendar_event_id' => 'already-deleted-event',
            ]);

        $calendarService = $this->createMock(Calendar::class);

        $calendarService->events = $this->getMockBuilder(
            Events::class
        )

            ->disableOriginalConstructor()
            ->onlyMethods([
                'delete',
            ])
            ->getMock();

        $calendarService->events
            ->expects($this->once())
            ->method('delete')
            ->with(
                'primary',
                'already-deleted-event'
            )
            ->willThrowException(
                new \RuntimeException('Google event already deleted')
            );

        $googleClient = new GoogleClient;

        $service = $this->getMockBuilder(GoogleCalendarService::class)
            ->onlyMethods([
                'authenticatedClient',
                'makeCalendarService',
            ])
            ->getMock();

        $service
            ->method('authenticatedClient')
            ->willReturn($googleClient);

        $service
            ->method('makeCalendarService')
            ->willReturn($calendarService);

        $service->deleteMeetingEvent($meeting);

        $this->addToAssertionCount(1);
    }

    public function test_authenticated_client_refreshes_expired_credential(): void
    {
        $user = User::factory()->coach()->create();

        $credential = GoogleCredential::create([
            'user_id' => $user->id,
            'access_token' => 'expired-access-token',
            'refresh_token' => 'valid-refresh-token',
            'expires_at' => now()->subHour(),
            'calendar_id' => 'primary',
            'connected_at' => now(),
        ]);

        $googleClient = $this->getMockBuilder(GoogleClient::class)
            ->onlyMethods([
                'fetchAccessTokenWithRefreshToken',
            ])
            ->getMock();

        $googleClient
            ->expects($this->once())
            ->method('fetchAccessTokenWithRefreshToken')
            ->with('valid-refresh-token')
            ->willReturn([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 3600,
            ]);

        $service = $this->getMockBuilder(GoogleCalendarService::class)
            ->onlyMethods([
                'makeClient',
            ])
            ->getMock();

        $service
            ->method('makeClient')
            ->willReturn($googleClient);

        $client = $service->authenticatedClient($credential);

        $this->assertSame($googleClient, $client);

        $this->assertDatabaseHas('google_credentials', [
            'id' => $credential->id,
            'access_token' => 'new-access-token',
        ]);

        $this->assertDatabaseHas('google_credentials', [
            'id' => $credential->id,
            'refresh_token' => 'new-refresh-token',
        ]);
    }

    public function test_authenticated_client_deletes_credential_when_refresh_token_is_invalid(): void
    {
        $user = User::factory()->coach()->create();

        $credential = GoogleCredential::create([
            'user_id' => $user->id,
            'access_token' => 'expired-access-token',
            'refresh_token' => 'invalid-refresh-token',
            'expires_at' => now()->subHour(),
            'calendar_id' => 'primary',
            'connected_at' => now(),
        ]);

        $googleClient = $this->getMockBuilder(GoogleClient::class)
            ->onlyMethods([
                'fetchAccessTokenWithRefreshToken',
            ])
            ->getMock();

        $googleClient
            ->method('fetchAccessTokenWithRefreshToken')
            ->willReturn([
                'error' => 'invalid_grant',
            ]);

        $service = $this->getMockBuilder(GoogleCalendarService::class)
            ->onlyMethods([
                'makeClient',
            ])
            ->getMock();

        $service
            ->method('makeClient')
            ->willReturn($googleClient);

        $client = $service->authenticatedClient($credential);

        $this->assertNull($client);

        $this->assertDatabaseMissing('google_credentials', [
            'id' => $credential->id,
        ]);
    }

    public function test_authenticated_client_deletes_credential_when_refresh_token_is_missing(): void
    {
        $user = User::factory()->coach()->create();

        $credential = GoogleCredential::create([
            'user_id' => $user->id,
            'access_token' => 'expired-access-token',
            'refresh_token' => null,
            'expires_at' => now()->subHour(),
            'calendar_id' => 'primary',
            'connected_at' => now(),
        ]);

        $service = new GoogleCalendarService;

        $client = $service->authenticatedClient($credential);

        $this->assertNull($client);

        $this->assertDatabaseMissing('google_credentials', [
            'id' => $credential->id,
        ]);
    }

    public function test_authenticated_client_keeps_credential_when_refresh_temporarily_fails(): void
    {
        $user = User::factory()->coach()->create();

        $credential = GoogleCredential::create([
            'user_id' => $user->id,
            'access_token' => 'expired-access-token',
            'refresh_token' => 'valid-refresh-token',
            'expires_at' => now()->subHour(),
            'calendar_id' => 'primary',
            'connected_at' => now(),
        ]);

        $googleClient = $this->getMockBuilder(GoogleClient::class)
            ->onlyMethods([
                'fetchAccessTokenWithRefreshToken',
            ])
            ->getMock();

        $googleClient
            ->method('fetchAccessTokenWithRefreshToken')
            ->willReturn([
                'error' => 'temporarily_unavailable',
            ]);

        $service = $this->getMockBuilder(GoogleCalendarService::class)
            ->onlyMethods([
                'makeClient',
            ])
            ->getMock();

        $service
            ->method('makeClient')
            ->willReturn($googleClient);

        $client = $service->authenticatedClient($credential);

        $this->assertNull($client);

        $this->assertDatabaseHas('google_credentials', [
            'id' => $credential->id,
        ]);
    }
}
