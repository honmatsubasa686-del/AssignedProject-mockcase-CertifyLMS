<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile;

use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_unauthenticated_user_cannot_access_settings_routes(): void
    {
        $this->get('/settings/profile')
            ->assertRedirect('/login');

        $this->patch('/settings/profile', [
            'name' => 'test',
            'bio' => null,
        ])->assertRedirect('/login');

        $this->post('/settings/avatar')
            ->assertRedirect('/login');

        $this->delete('/settings/avatar')
            ->assertRedirect('/login');

        $this->put('/settings/password')
            ->assertRedirect('/login');
    }
}
