<?php

namespace App\Mlibms\Enums;

enum MembershipType: string
{
    CASE STUDENT = 'student';
    CASE FACULTY_STAFF = 'faculty_staff';
    CASE COMMUNITY_GUEST = 'community_guest';

    public function label(): string
    {
        return match ($this) {
            self::STUDENT => 'Student',
            self::FACULTY_STAFF => 'Faculty / Staff',
            self::COMMUNITY_GUEST => 'Community Guest',
        };
    }
}
