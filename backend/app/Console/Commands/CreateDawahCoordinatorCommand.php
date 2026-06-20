<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\CreatesRoleUser;
use Illuminate\Console\Command;

class CreateDawahCoordinatorCommand extends Command
{
    use CreatesRoleUser;

    protected $signature = 'make:dawah-coordinator {email} {name} {password}';

    protected $description = 'Create a new Dawah coordinator user account';

    public function handle(): int
    {
        return $this->createRoleUser(
            'dawah-coordinator',
            'Dawah Coordinator',
            $this->argument('email'),
            $this->argument('name'),
            $this->argument('password')
        );
    }
}
