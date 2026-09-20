<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\GoogleCredential;
use App\Services\GoogleCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleCalendarController extends Controller
{
    public function redirect(
        Request $request,
        GoogleCalendarService $googleCalendar
    ): RedirectResponse {
        $state = Str::random(40);

        $request->session()->put('google_calendar_oauth_state', $state);

        $redirectPath = $request->string('redirect_path')->toString();

        if ($redirectPath !== '/settings/availability') {
            $redirectPath = '/settings/availability';
        }

        $request->session()->put(
            'google_calendar_redirect_path',
            $redirectPath
        );

        $client = $googleCalendar->makeClient();

        $client->setScopes([
            'https://www.googleapis.com/auth/calendar',
        ]);

        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setState($state);

        return redirect()->away($client->createAuthUrl());
    }

    public function callback(
        Request $request,
        GoogleCalendarService $googleCalendar
    ): RedirectResponse {
        $expectedState = $request->session()->pull('google_calendar_oauth_state');
        $actualState = $request->string('state')->toString();

        abort_unless(
            $expectedState !== null
            && $actualState !== ''
            && hash_equals($expectedState, $actualState),
            403
        );

        $code = $request->string('code')->toString();

        abort_if($code === '', 400);

        $client = $googleCalendar->makeClient();

        $token = $client->fetchAccessTokenWithAuthCode($code);

        abort_if(
            isset($token['error']) || ! isset($token['access_token']),
            400
        );

        $user = $request->user();

        $existingCredential = GoogleCredential::query()
            ->where('user_id', $user->id)
            ->first();

        GoogleCredential::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token']
                    ?? $existingCredential?->refresh_token,
                'expires_at' => isset($token['expires_in'])
                    ? Carbon::now()->addSeconds((int) $token['expires_in'])
                    : null,
                'calendar_id' => 'primary',
                'connected_at' => now(),
            ]
        );

        $redirectPath = $request->session()->pull(
            'google_calendar_redirect_path',
            '/settings/availability'
        );

        if ($redirectPath !== '/settings/availability') {
            $redirectPath = '/settings/availability';
        }

        return redirect($redirectPath)
            ->with('success', 'Googleカレンダーと連携しました。');
    }

    public function destroy(
        Request $request,
        GoogleCalendarService $googleCalendar
    ): RedirectResponse {
        $user = $request->user();

        $credential = $user->googleCredential;

        if ($credential === null) {
            return redirect()
                ->to('/settings/availability')
                ->with('success', 'Googleカレンダー連携はすでに解除されています。');
        }

        try {
            $client = $googleCalendar->makeClient();

            $tokenToRevoke = $credential->refresh_token
                ?? $credential->access_token;

            $client->revokeToken($tokenToRevoke);
        } catch (\Throwable $e) {
            Log::warning('Google Calendar token revoke failed.', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);
        }

        $credential->delete();

        return redirect()
            ->to('/settings/availability')
            ->with('success', 'Googleカレンダー連携を解除しました。');
    }
}
