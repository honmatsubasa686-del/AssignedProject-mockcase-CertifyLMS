<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Models\GoogleCredential;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Google\Client as GoogleClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleCalendarControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_callback_rejects_invalid_state(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this
            ->actingAs($coach)
            ->withSession([
                'google_calendar_oauth_state' => 'expected-state',
            ])
            ->get(route('settings.google-calendar.callback', [
                'state' => 'wrong-state',
                'code' => 'test-code',
            ]));

        $response->assertForbidden();
    }

    public function test_redirect_stores_oauth_state_and_safe_redirect_path(): void
    {
        $coach = User::factory()->coach()->create();

        $googleClient = $this->getMockBuilder(GoogleClient::class)
            ->onlyMethods([
                'createAuthUrl',
            ])
            ->getMock();

        $googleClient
            ->method('createAuthUrl')
            ->willReturn('https://accounts.google.com/test-auth');

        $googleCalendar = $this->getMockBuilder(GoogleCalendarService::class)
            ->onlyMethods([
                'makeClient',
            ])
            ->getMock();

        $googleCalendar
            ->method('makeClient')
            ->willReturn($googleClient);

        $this->app->instance(
            GoogleCalendarService::class,
            $googleCalendar
        );

        $response = $this
            ->actingAs($coach)
            ->get(route('settings.google-calendar.redirect', [
                'redirect_path' => '//evil.example.com',
            ]));

        $response->assertRedirect(
            'https://accounts.google.com/test-auth'
        );

        $response->assertSessionHas(
            'google_calendar_redirect_path',
            '/settings/availability'
        );

        $response->assertSessionHas(
            'google_calendar_oauth_state'
        );
    }

    public function test_callback_stores_google_credential(): void
    {
        $coach = User::factory()->coach()->create();

        $googleClient = $this->getMockBuilder(GoogleClient::class)
            ->onlyMethods([
                'fetchAccessTokenWithAuthCode',
            ])
            ->getMock();

        $googleClient
            ->method('fetchAccessTokenWithAuthCode')
            ->with('test-code')
            ->willReturn([
                'access_token' => 'test-access-token',
                'refresh_token' => 'test-refresh-token',
                'expires_in' => 3600,
            ]);

        $googleCalendar = $this->getMockBuilder(GoogleCalendarService::class)
            ->onlyMethods([
                'makeClient',
            ])
            ->getMock();

        $googleCalendar
            ->method('makeClient')
            ->willReturn($googleClient);

        $this->app->instance(
            GoogleCalendarService::class,
            $googleCalendar
        );

        $response = $this
            ->actingAs($coach)
            ->withSession([
                'google_calendar_oauth_state' => 'expected-state',
                'google_calendar_redirect_path' => '/settings/availability',
            ])
            ->get(route('settings.google-calendar.callback', [
                'state' => 'expected-state',
                'code' => 'test-code',
            ]));

        $response->assertRedirect('/settings/availability');

        $this->assertDatabaseHas('google_credentials', [
            'user_id' => $coach->id,
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'calendar_id' => 'primary',
        ]);

        $this->assertNotNull(
            GoogleCredential::query()
                ->where('user_id', $coach->id)
                ->first()?->connected_at
        );
    }

    public function test_destroy_revokes_token_and_deletes_credential(): void
    {
        $coach = User::factory()->coach()->create();

        $credential = GoogleCredential::create([
            'user_id' => $coach->id,
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'expires_at' => now()->addHour(),
            'calendar_id' => 'primary',
            'connected_at' => now(),
        ]);

        $googleClient = $this->getMockBuilder(GoogleClient::class)
            ->onlyMethods([
                'revokeToken',
            ])
            ->getMock();

        $googleClient
            ->expects($this->once())
            ->method('revokeToken')
            ->with('test-refresh-token')
            ->willReturn(true);

        $googleCalendar = $this->getMockBuilder(GoogleCalendarService::class)
            ->onlyMethods([
                'makeClient',
            ])
            ->getMock();

        $googleCalendar
            ->method('makeClient')
            ->willReturn($googleClient);

        $this->app->instance(
            GoogleCalendarService::class,
            $googleCalendar
        );

        $response = $this
            ->actingAs($coach)
            ->delete(route('settings.google-calendar.destroy'));

        $response->assertRedirect('/settings/availability');

        $this->assertDatabaseMissing('google_credentials', [
            'id' => $credential->id,
        ]);
    }

    public function test_destroy_deletes_credential_even_when_revoke_fails(): void
    {
        $coach = User::factory()->coach()->create();

        $credential = GoogleCredential::create([
            'user_id' => $coach->id,
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'expires_at' => now()->addHour(),
            'calendar_id' => 'primary',
            'connected_at' => now(),
        ]);

        $googleClient = $this->getMockBuilder(GoogleClient::class)
            ->onlyMethods([
                'revokeToken',
            ])
            ->getMock();

        $googleClient
            ->method('revokeToken')
            ->willThrowException(
                new \RuntimeException('Google revoke failed')
            );

        $googleCalendar = $this->getMockBuilder(GoogleCalendarService::class)
            ->onlyMethods([
                'makeClient',
            ])
            ->getMock();

        $googleCalendar
            ->method('makeClient')
            ->willReturn($googleClient);

        $this->app->instance(
            GoogleCalendarService::class,
            $googleCalendar
        );

        $response = $this
            ->actingAs($coach)
            ->delete(route('settings.google-calendar.destroy'));

        $response->assertRedirect('/settings/availability');

        $this->assertDatabaseMissing('google_credentials', [
            'id' => $credential->id,
        ]);
    }
}
