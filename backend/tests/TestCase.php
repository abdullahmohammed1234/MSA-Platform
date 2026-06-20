<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function volunteerRole(): Role
    {
        return Role::firstOrCreate(
            ['slug' => 'volunteer'],
            ['name' => 'Volunteer', 'uuid' => (string) Str::uuid()]
        );
    }

    protected function createVolunteerUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->sync([$this->volunteerRole()->id]);

        return $user;
    }

    protected function assignVolunteerRole(User $user): User
    {
        $user->roles()->syncWithoutDetaching([$this->volunteerRole()->id]);

        return $user;
    }
}
