<?php

namespace App\Events;

use App\Models\CertificateAward;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CertificateAwardedEvent
{
    use Dispatchable, SerializesModels;

    public CertificateAward $award;

    /**
     * Create a new event instance.
     */
    public function __construct(CertificateAward $award)
    {
        $this->award = $award;
    }
}
