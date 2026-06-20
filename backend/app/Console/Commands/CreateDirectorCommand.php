<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\CreatesRoleUser;
use Illuminate\Console\Command;

class CreateDirectorCommand extends Command
{
    use CreatesRoleUser;

    protected $signature = 'make:director {email} {name} {password}';

    protected $description = 'Create a new director user account';

    public function handle(): int
    {
        return $this->createRoleUser(
            'director',
            'Director',
            $this->argument('email'),
            $this->argument('name'),
            $this->argument('password')
        );
    }
}
