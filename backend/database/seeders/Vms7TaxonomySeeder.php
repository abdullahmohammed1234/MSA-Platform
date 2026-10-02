<?php

namespace Database\Seeders;

use App\Volunteering\Models\Interest;
use App\Volunteering\Models\Skill;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class Vms7TaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $skills = [
            ['name' => 'Event Setup & Logistics', 'category' => 'Operations', 'description' => 'Experience setting up venues, audio/visual gear, and stage management.'],
            ['name' => 'Public Speaking & Emcee', 'category' => 'Communications', 'description' => 'Ability to host events, address crowds, and manage announcements.'],
            ['name' => 'Graphic Design & Media', 'category' => 'Creative', 'description' => 'Skills in Canva, Photoshop, or media content creation.'],
            ['name' => 'First Aid & Safety', 'category' => 'Operations', 'description' => 'Certified first aid response and crowd safety management.'],
            ['name' => 'Food Service & Preparation', 'category' => 'Operations', 'description' => 'Handling food distribution, catering prep, and hygiene.'],
            ['name' => 'Registration & Check-in', 'category' => 'Administration', 'description' => 'Managing guest check-ins, ticketing, and badge issuance.'],
            ['name' => 'Photography & Videography', 'category' => 'Creative', 'description' => 'Capturing high quality event photos and video recaps.'],
            ['name' => 'Youth Mentorship', 'category' => 'Education', 'description' => 'Guiding and supporting junior volunteers or students.'],
        ];

        foreach ($skills as $i => $s) {
            Skill::firstOrCreate(
                ['slug' => Str::slug($s['name'])],
                [
                    'name' => $s['name'],
                    'category' => $s['category'],
                    'description' => $s['description'],
                    'is_active' => true,
                    'sort_order' => $i + 1,
                ]
            );
        }

        $interests = [
            ['name' => 'Community Outreach', 'description' => 'Engaging with the local community and university students.'],
            ['name' => 'Education & Workshops', 'description' => 'Supporting educational programs, talks, and workshops.'],
            ['name' => 'Social & Youth Events', 'description' => 'Helping organize social gatherings, sports, and youth events.'],
            ['name' => 'Ramadan & Spiritual Programs', 'description' => 'Assisting with Iftar distribution and taraweeh operations.'],
            ['name' => 'Charity & Fundraising', 'description' => 'Participating in food drives, fundraising campaigns, and relief efforts.'],
            ['name' => 'Media & Technology', 'description' => 'Contributing to livestreaming, social media, and web development.'],
        ];

        foreach ($interests as $i => $item) {
            Interest::firstOrCreate(
                ['slug' => Str::slug($item['name'])],
                [
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'is_active' => true,
                    'sort_order' => $i + 1,
                ]
            );
        }
    }
}
