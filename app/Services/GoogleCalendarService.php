<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GoogleCredential;
use App\Models\Meeting;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Calendar\FreeBusyRequest;
use Google\Service\Calendar\FreeBusyRequestItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class GoogleCalendarService
{
    public function makeClient(): GoogleClient
    {
        $client = new GoogleClient;

        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect_uri'));

        return $client;
    }

    protected function makeCalendarService(GoogleClient $client): Calendar
    {
        return new Calendar($client);
    }

    public function authenticatedClient(
        GoogleCredential $credential
    ): ?GoogleClient {
        $client = $this->makeClient();

        if (
            $credential->expires_at === null
            || $credential->expires_at->isFuture()
        ) {
            $client->setAccessToken($credential->access_token);

            return $client;
        }

        if ($credential->refresh_token === null) {
            Log::warning('Google Calendar refresh token is missing.', [
                'user_id' => $credential->user_id,
            ]);

            $credential->delete();

            return null;
        }

        try {
            $token = $client->fetchAccessTokenWithRefreshToken(
                $credential->refresh_token
            );

            if (($token['error'] ?? null) === 'invalid_grant') {
                Log::warning('Google Calendar credential is no longer valid.', [
                    'user_id' => $credential->user_id,
                ]);

                $credential->delete();

                return null;
            }

            if (isset($token['error']) || ! isset($token['access_token'])) {
                Log::warning('Google Calendar token refresh failed.', [
                    'user_id' => $credential->user_id,
                    'error' => $token['error'] ?? 'unknown',
                ]);

                return null;
            }

            $credential->update([
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token']
                    ?? $credential->refresh_token,
                'expires_at' => isset($token['expires_in'])
                    ? now()->addSeconds((int) $token['expires_in'])
                    : null,
            ]);

            return $client;
        } catch (\Throwable $e) {
            Log::warning('Google Calendar token refresh failed.', [
                'user_id' => $credential->user_id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function busyPeriods(
        GoogleCredential $credential,
        Carbon $from,
        Carbon $to
    ): Collection {
        $client = $this->authenticatedClient($credential);

        if ($client === null) {
            return collect();
        }

        try {
            $calendarService = $this->makeCalendarService($client);

            $item = new FreeBusyRequestItem;
            $item->setId($credential->calendar_id);

            $request = new FreeBusyRequest;
            $request->setTimeMin($from->toRfc3339String());
            $request->setTimeMax($to->toRfc3339String());
            $request->setItems([$item]);

            $response = $calendarService->freebusy->query($request);

            $calendars = $response->getCalendars();

            $calendar = $calendars[$credential->calendar_id] ?? null;

            if ($calendar === null) {
                return collect();
            }

            if (! empty($calendar->getErrors())) {
                Log::warning('Google Calendar FreeBusy returned errors.', [
                    'user_id' => $credential->user_id,
                ]);

                return collect();
            }

            return collect($calendar->getBusy())
                ->map(function ($period): array {
                    return [
                        'start' => Carbon::parse($period->getStart()),
                        'end' => Carbon::parse($period->getEnd()),
                    ];
                });
        } catch (\Throwable $e) {
            Log::warning('Google Calendar FreeBusy request failed.', [
                'user_id' => $credential->user_id,
                'message' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    public function createMeetingEvent(Meeting $meeting): ?string
    {
        $meeting->loadMissing([
            'student',
            'coach',
            'enrollment.certification',
        ]);

        $coach = $meeting->coach;

        if ($coach === null) {
            return null;
        }

        $credential = $coach->googleCredential;

        if ($credential === null) {
            return null;
        }

        $client = $this->authenticatedClient($credential);

        if ($client === null) {
            return null;
        }

        try {
            $calendarService = $this->makeCalendarService($client);

            $start = new EventDateTime;
            $start->setDateTime($meeting->scheduled_at->toRfc3339String());

            $end = new EventDateTime;
            $end->setDateTime(
                $meeting->scheduled_at->copy()->addHour()->toRfc3339String()
            );

            $event = new Event;
            $event->setSummary(
                $meeting->student->name
                .' - '
                .$meeting->enrollment->certification->name
            );

            $event->setDescription(
                $meeting->topic
                ."\n\n"
                .'面談URL: '
                .$meeting->meeting_url_snapshot
            );

            $event->setLocation(
                $meeting->meeting_url_snapshot
            );

            $event->setStart($start);
            $event->setEnd($end);

            $createdEvent = $calendarService->events->insert(
                $credential->calendar_id,
                $event
            );

            return $createdEvent->getId();
        } catch (\Throwable $e) {
            Log::warning('Google Calendar event creation failed.', [
                'meeting_id' => $meeting->id,
                'coach_id' => $meeting->coach_id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function deleteMeetingEvent(Meeting $meeting): void
    {
        if ($meeting->google_calendar_event_id === null) {
            return;
        }

        $meeting->loadMissing('coach.googleCredential');

        $credential = $meeting->coach?->googleCredential;

        if ($credential === null) {
            return;
        }

        $client = $this->authenticatedClient($credential);

        if ($client === null) {
            return;
        }

        try {
            $calendarService = $this->makeCalendarService($client);

            $calendarService->events->delete(
                $credential->calendar_id,
                $meeting->google_calendar_event_id
            );
        } catch (\Throwable $e) {
            Log::warning('Google Calendar event deletion failed.', [
                'meeting_id' => $meeting->id,
                'coach_id' => $meeting->coach_id,
                'google_calendar_event_id' => $meeting->google_calendar_event_id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
