<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\CreatesRoleUser;
use Illuminate\Console\Command;

class CreateMentorCommand extends Command
{
    use CreatesRoleUser;

    protected $signature = 'make:mentor {email} {name} {password}';

    protected $description = 'Create a new mentor user account';

    public function handle(): int
    {
        return $this->createRoleUser(
            'mentor',
            'Mentor',
            $this->argument('email'),
            $this->argument('name'),
            $this->argument('password')
        );
    }
}
