<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TrainingScheduledEvent
{
    use Dispatchable, SerializesModels;

    public string $trainingTitle;
    public string $trainingDate;
    public string $trainingLocation;
    public string $trainingDescription;
    public string $trainingSlug;
    public string $targetAudience; // e.g. All, Volunteers, Mentors

    /**
     * Create a new event instance.
     */
    public function __construct(string $trainingTitle, string $trainingDate, string $trainingLocation, string $trainingDescription = '', string $trainingSlug = '', string $targetAudience = 'All')
    {
        $this->trainingTitle = $trainingTitle;
        $this->trainingDate = $trainingDate;
        $this->trainingLocation = $trainingLocation;
        $this->trainingDescription = $trainingDescription;
        $this->trainingSlug = $trainingSlug;
        $this->targetAudience = $targetAudience;
    }
}
