<?php

namespace Tests\Unit\Ems;

use App\Ems\Enums\CheckInStatus;
use PHPUnit\Framework\TestCase;

class CheckInStatusTest extends TestCase
{
    public function test_attendance_labels_are_distinct(): void
    {
        $this->assertSame('Not Checked In', CheckInStatus::NotCheckedIn->label());
        $this->assertSame('Attending', CheckInStatus::CheckedIn->label());
        $this->assertSame("Didn't come", CheckInStatus::NoShow->label());
    }

    public function test_values_include_no_show(): void
    {
        $this->assertContains('no_show', CheckInStatus::values());
        $this->assertContains('checked_in', CheckInStatus::values());
        $this->assertContains('not_checked_in', CheckInStatus::values());
    }
}
