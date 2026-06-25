<?php

namespace Database\Seeders;

use App\Models\CMS\HomepageSection;
use App\Models\CMS\HomepageContentBlock;
use App\Models\CMS\Announcement;
use App\Models\CMS\Event;
use App\Models\CMS\TeamMember;
use App\Models\CMS\Resource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CmsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Homepage Sections & Blocks
        $heroSection = HomepageSection::create([
            'name' => 'Hero Section',
            'key' => 'hero',
            'display_order' => 1,
            'is_visible' => true,
            'status' => 'published',
        ]);

        $heroBlocks = [
            ['key' => 'tagline', 'value' => 'Simon Fraser University', 'type' => 'text', 'display_order' => 1],
            ['key' => 'title', 'value' => 'Building Faith & Community at SFU', 'type' => 'text', 'display_order' => 2],
            ['key' => 'subtitle', 'value' => 'Nurturing student success, religious scholarship, and active community outreach at Simon Fraser University since 1977.', 'type' => 'textarea', 'display_order' => 3],
            ['key' => 'background_image', 'value' => '/FOTO2.webp', 'type' => 'image', 'display_order' => 4],
            ['key' => 'cta_primary_text', 'value' => 'Join the Community', 'type' => 'text', 'display_order' => 5],
            ['key' => 'cta_primary_url', 'value' => '/contact', 'type' => 'url', 'display_order' => 6],
            ['key' => 'cta_secondary_text', 'value' => 'Explore Events', 'type' => 'text', 'display_order' => 7],
            ['key' => 'cta_secondary_url', 'value' => '/events', 'type' => 'url', 'display_order' => 8],
        ];

        foreach ($heroBlocks as $block) {
            $heroSection->blocks()->create($block);
        }

        $offeringsSection = HomepageSection::create([
            'name' => 'Offerings Section',
            'key' => 'offerings',
            'display_order' => 2,
            'is_visible' => true,
            'status' => 'published',
        ]);

        $offeringBlocks = [
            ['key' => 'section_title', 'value' => 'What we Provide', 'type' => 'text', 'display_order' => 1],
            ['key' => 'section_subtitle', 'value' => 'Our Framework', 'type' => 'text', 'display_order' => 2],
            
            ['key' => 'offering_1_title', 'value' => 'Mentorship', 'type' => 'text', 'display_order' => 3],
            ['key' => 'offering_1_desc', 'value' => 'Personalized guidance from upper-year students to help you navigate campus life.', 'type' => 'textarea', 'display_order' => 4],
            ['key' => 'offering_1_icon', 'value' => 'GraduationCap', 'type' => 'text', 'display_order' => 5],

            ['key' => 'offering_2_title', 'value' => 'Chaplaincy', 'type' => 'text', 'display_order' => 6],
            ['key' => 'offering_2_desc', 'value' => 'Spiritual support and counseling for faith-centered needs and emotional well-being.', 'type' => 'textarea', 'display_order' => 7],
            ['key' => 'offering_2_icon', 'value' => 'Sparkles', 'type' => 'text', 'display_order' => 8],

            ['key' => 'offering_3_title', 'value' => 'Education & Dawah', 'type' => 'text', 'display_order' => 9],
            ['key' => 'offering_3_desc', 'value' => 'Weekly halaqas, workshops, and outreach that connect MSA life with dawah on campus.', 'type' => 'textarea', 'display_order' => 10],
            ['key' => 'offering_3_icon', 'value' => 'BookOpen', 'type' => 'text', 'display_order' => 11],
        ];

        foreach ($offeringBlocks as $block) {
            $offeringsSection->blocks()->create($block);
        }

        $ctaSection = HomepageSection::create([
            'name' => 'CTA Section',
            'key' => 'cta',
            'display_order' => 3,
            'is_visible' => true,
            'status' => 'published',
        ]);

        $ctaBlocks = [
            ['key' => 'title', 'value' => 'Be part of something Meaningful', 'type' => 'text', 'display_order' => 1],
            ['key' => 'subtitle', 'value' => "Join a thriving support structure dedicated to representing Muslim students, facilitating Jumu'ah services, and fostering a community of growth.", 'type' => 'textarea', 'display_order' => 2],
            ['key' => 'button_text', 'value' => 'Join the MSA Family', 'type' => 'text', 'display_order' => 3],
            ['key' => 'button_url', 'value' => '/contact', 'type' => 'url', 'display_order' => 4],
        ];

        foreach ($ctaBlocks as $block) {
            $ctaSection->blocks()->create($block);
        }

        // 2. Seed Announcements
        $announcements = [
            [
                'title' => "Jumu'ah Location Update",
                'slug' => "jumuah-location-update",
                'content' => "Jumu'ah prayers this week will be held in the West Gym to accommodate more students.",
                'summary' => "Jumu'ah prayers this week will be held in the West Gym.",
                'featured_image' => null,
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'title' => 'Volunteering Open',
                'slug' => 'volunteering-open',
                'content' => 'Applications are now open for the 2026 MSA Board committees. Apply today!',
                'summary' => 'Applications are now open for the 2026 MSA Board committees.',
                'featured_image' => null,
                'status' => 'published',
                'published_at' => now()->subDays(3),
            ]
        ];

        foreach ($announcements as $ann) {
            Announcement::create(array_merge($ann, ['uuid' => (string) Str::uuid()]));
        }

        // 3. Seed Events
        $events = [
            [
                'title' => "The Heart's Journey: Spiritual Heights",
                'description' => 'An evening dedicated to exploring the depths of spiritual growth and finding peace in a chaotic world. Featuring guest speakers and interactive reflection sessions.',
                'location' => 'SFU Burnaby, WMC 3260',
                'date' => '2026-06-15',
                'time' => '6:00 PM - 8:30 PM',
                'start_date' => '2026-06-15 18:00:00',
                'end_date' => '2026-06-15 20:30:00',
                'registration_url' => '/events',
                'image' => 'https://images.unsplash.com/photo-1519751138087-5bf79df62d5b?auto=format&fit=crop&q=80',
                'category' => 'Lecture',
                'status' => 'published',
                'spots_left' => 45,
                'featured' => true,
                'registration_deadline' => '2026-06-14',
            ],
            [
                'title' => 'Weekly Friday Jummah Prayer',
                'description' => 'Join our weekly congregation for Jummah prayer on campus. Multiple shifts available depending on room capacity.',
                'location' => 'SFU Multi-Faith Centre / MBC',
                'date' => 'Every Friday',
                'time' => '1:30 PM',
                'start_date' => '2026-06-12 13:30:00',
                'end_date' => '2026-06-12 14:30:00',
                'registration_url' => '/events',
                'image' => 'https://images.unsplash.com/photo-1542810634-71277d95dcbb?auto=format&fit=crop&q=80',
                'category' => 'Jummah',
                'status' => 'published',
                'spots_left' => 200,
                'featured' => false,
                'registration_deadline' => '2026-12-31',
            ],
            [
                'title' => 'End of Semester Community Dinner',
                'description' => "Celebrate your hard work this semester with a beautiful banquet dinner. Halal catering from Vancouver's finest vendors.",
                'location' => 'Diamond Family Courtyard',
                'date' => '2026-05-30',
                'time' => '7:00 PM',
                'start_date' => '2026-05-30 19:00:00',
                'end_date' => '2026-05-30 22:00:00',
                'registration_url' => '/events',
                'image' => 'https://images.unsplash.com/photo-1566438480900-0609be27a4be?auto=format&fit=crop&q=80',
                'category' => 'Dinner',
                'status' => 'published',
                'spots_left' => 12,
                'featured' => false,
                'registration_deadline' => '2026-05-28',
            ],
            [
                'title' => 'Post-Midterm Game Night',
                'description' => 'Unwind after exams with board games, pizza, and good company. Tournament brackets for FIFA and Super Smash Bros!',
                'location' => 'Student Union Building (SUB)',
                'date' => '2026-06-05',
                'time' => '5:00 PM - 9:00 PM',
                'start_date' => '2026-06-05 17:00:00',
                'end_date' => '2026-06-05 21:00:00',
                'registration_url' => '/events',
                'image' => 'https://images.unsplash.com/photo-1523240715630-974bb7ad1582?auto=format&fit=crop&q=80',
                'category' => 'Social',
                'status' => 'published',
                'spots_left' => 80,
                'featured' => false,
                'registration_deadline' => '2026-06-04',
            ],
            [
                'title' => 'Ramadan Prep: Fiqh of Fasting',
                'description' => 'Practical workshop on the essentials of fasting, health tips, and spiritual preparation for the upcoming holy month.',
                'location' => 'Online (Zoom)',
                'date' => '2026-07-20',
                'time' => '5:30 PM',
                'start_date' => '2026-07-20 17:30:00',
                'end_date' => '2026-07-20 19:30:00',
                'registration_url' => '/events',
                'image' => 'https://images.unsplash.com/photo-1435527173128-983b87201f4d?auto=format&fit=crop&q=80',
                'category' => 'Workshop',
                'status' => 'published',
                'spots_left' => 500,
                'featured' => false,
                'registration_deadline' => '2026-07-19',
            ],
            [
                'title' => 'Charity Bake Sale for Relief',
                'description' => 'Baking for a cause! All proceeds go towards international humanitarian relief efforts. Volunteers and bakers needed.',
                'location' => 'Convocation Mall',
                'date' => '2026-06-10',
                'time' => '10:00 AM - 4:00 PM',
                'start_date' => '2026-06-10 10:00:00',
                'end_date' => '2026-06-10 16:00:00',
                'registration_url' => '/events',
                'image' => 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&q=80',
                'category' => 'Charity',
                'status' => 'published',
                'spots_left' => 20,
                'featured' => false,
                'registration_deadline' => '2026-06-08',
            ]
        ];

        foreach ($events as $event) {
            Event::create(array_merge($event, ['uuid' => (string) Str::uuid()]));
        }

        // 4. Seed Team Members
        $team = config('website_defaults.team');

        foreach ($team as $index => $member) {
            TeamMember::create(array_merge($member, [
                'uuid' => (string) Str::uuid(),
                'display_order' => $index,
                'status' => 'published',
                'bio' => 'Serving the SFU Muslim community.',
            ]));
        }

        // 5. Seed Resources
        $resources = [
            [
                'title' => 'New Muslim Starter Kit',
                'description' => 'A comprehensive guide for those new to Islam, covering prayer basics, common terms, and community support.',
                'category' => 'New Muslim',
                'icon_name' => 'Sparkles',
                'link' => '#',
                'is_external' => false,
                'tags' => ['revert', 'basics', 'guide']
            ],
            [
                'title' => 'Peer Mentorship for Reverts',
                'description' => 'Connect with experienced community members who can support you on your new journey in faith.',
                'category' => 'New Muslim',
                'icon_name' => 'Users',
                'link' => '/contact',
                'is_external' => false,
                'tags' => ['mentorship', 'support']
            ],
            [
                'title' => 'Religious Accommodation Guide',
                'description' => 'Learn about SFU policies regarding exam rescheduling for Eid and prayer breaks during labs.',
                'category' => 'Student Guides',
                'icon_name' => 'GraduationCap',
                'link' => 'https://www.sfu.ca/students/religious-accommodations.html',
                'is_external' => true,
                'tags' => ['policy', 'exams', 'sfu']
            ],
            [
                'title' => 'Balancing Faith & Academics',
                'description' => 'Tips on managing your study schedule during Ramadan and maintaining focus while fulfilling spiritual duties.',
                'category' => 'Student Guides',
                'icon_name' => 'BookMarked',
                'link' => '#',
                'is_external' => false,
                'tags' => ['ramadan', 'academics']
            ],
            [
                'title' => 'Campus Prayer Spaces',
                'description' => 'Detailed maps and access codes for prayer rooms at Burnaby, Surrey, and Vancouver campuses.',
                'category' => 'Prayer',
                'icon_name' => 'MapPin',
                'link' => '/prayer',
                'is_external' => false,
                'tags' => ['musalla', 'burnaby', 'surrey', 'vancouver']
            ],
            [
                'title' => 'SFU Local Prayer Times',
                'description' => 'A curated prayer timetable specifically calibrated for the SFU Burnaby Mountain microclimate.',
                'category' => 'Prayer',
                'icon_name' => 'Compass',
                'link' => '#',
                'is_external' => false,
                'tags' => ['times', 'local']
            ],
            [
                'title' => 'Muslim Mental Health Directory',
                'description' => 'A list of certified Muslim counselors in the GVA who understand religious and cultural context.',
                'category' => 'Mental Health',
                'icon_name' => 'Stethoscope',
                'link' => '#',
                'is_external' => false,
                'tags' => ['counseling', 'therapy', 'wellness']
            ],
            [
                'title' => 'Bi-Weekly Sister Circles',
                'description' => 'A safe, confidential space for Muslim sisters to discuss mental health and community challenges.',
                'category' => 'Mental Health',
                'icon_name' => 'Heart',
                'link' => '#',
                'is_external' => false,
                'tags' => ['sisters', 'safe-space']
            ],
            [
                'title' => 'MSA Digital Library',
                'description' => 'Access curated PDFs, e-books, and lecture recordings on various Islamic sciences.',
                'category' => 'Learning',
                'icon_name' => 'BookMarked',
                'link' => '#',
                'is_external' => false,
                'tags' => ['books', 'learning', 'vault']
            ],
            [
                'title' => 'Halal Food Survival Guide',
                'description' => 'Every halal option on and around SFU campuses, including hidden gems and discounted meals.',
                'category' => 'Campus Survival',
                'icon_name' => 'Coffee',
                'link' => '#',
                'is_external' => false,
                'tags' => ['food', 'halal', 'cheap-eats']
            ],
            [
                'title' => 'MSA Room Booking',
                'description' => 'Book our dedicated club rooms at Burnaby campus for group study or quiet reflection.',
                'category' => 'Campus Survival',
                'icon_name' => 'MapPin',
                'link' => '#',
                'is_external' => false,
                'tags' => ['booking', 'study']
            ],
            [
                'title' => 'Meet the Chaplain',
                'description' => 'Book a one-on-one session with our campus chaplain for spiritual guidance or emotional support.',
                'category' => 'Chaplaincy',
                'icon_name' => 'MessageSquare',
                'link' => '#',
                'is_external' => false,
                'tags' => ['chaplain', 'counseling']
            ],
            [
                'title' => 'GVA Masjid Directory',
                'description' => 'A list of local masjids around Burnaby and Surrey with Jumu\'ah times and programs.',
                'category' => 'Community',
                'icon_name' => 'Compass',
                'link' => '#',
                'is_external' => false,
                'tags' => ['masjids', 'local']
            ]
        ];

        foreach ($resources as $res) {
            Resource::create(array_merge($res, [
                'uuid' => (string) Str::uuid(),
                'status' => 'published',
            ]));
        }
    }
}
