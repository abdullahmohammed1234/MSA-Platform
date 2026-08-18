<?php

namespace Database\Seeders\Ems;

use App\Ems\Models\EventCategory;
use App\Ems\Models\EventTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EmsEventTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $categories = EventCategory::all()->keyBy('slug');

        $templates = [
            [
                'name' => 'Blank',
                'description' => 'Start with a clean slate. No preconfigured settings.',
                'category_slug' => 'other',
                'capacity' => 100,
                'is_public' => false,
                'waitlist_enabled' => false,
                'max_tickets_per_order' => 5,
                'max_registrations_per_attendee' => 1,
                'registration_deadline_offset_days' => 0,
                'settings' => [
                    'ticket_types' => [],
                    'reminders' => [],
                    'custom_fields' => [],
                    'staff_roles' => [],
                ],
                'is_default' => true,
            ],
            [
                'name' => 'Welcome Night',
                'description' => 'Standard configuration for welcoming new students and members.',
                'category_slug' => 'social',
                'capacity' => 150,
                'is_public' => true,
                'waitlist_enabled' => true,
                'max_tickets_per_order' => 4,
                'max_registrations_per_attendee' => 1,
                'registration_deadline_offset_days' => 1,
                'settings' => [
                    'ticket_types' => [
                        ['name' => 'General Admission', 'price' => 0.00, 'quantity' => 150],
                    ],
                    'reminders' => [
                        ['offset_hours' => 24, 'channel' => 'email', 'template_slug' => 'event-reminder'],
                    ],
                    'custom_fields' => [
                        ['name' => 'Dietary Restrictions', 'type' => 'text', 'required' => false],
                    ],
                ],
                'is_default' => false,
            ],
            [
                'name' => 'Halaqah',
                'description' => 'Configured for educational lectures and religious study circles.',
                'category_slug' => 'education',
                'capacity' => 80,
                'is_public' => true,
                'waitlist_enabled' => true,
                'max_tickets_per_order' => 2,
                'max_registrations_per_attendee' => 1,
                'registration_deadline_offset_days' => 0,
                'settings' => [
                    'ticket_types' => [
                        ['name' => 'Free Admission', 'price' => 0.00, 'quantity' => 80],
                    ],
                    'reminders' => [
                        ['offset_hours' => 24, 'channel' => 'email', 'template_slug' => 'event-reminder'],
                    ],
                ],
                'is_default' => false,
            ],
            [
                'name' => 'Charity Dinner',
                'description' => 'Setup for paid fundraising dinners with tables and ticketing limits.',
                'category_slug' => 'social',
                'capacity' => 200,
                'is_public' => true,
                'waitlist_enabled' => true,
                'max_tickets_per_order' => 10,
                'max_registrations_per_attendee' => 1,
                'registration_deadline_offset_days' => 3,
                'settings' => [
                    'ticket_types' => [
                        ['name' => 'Standard Ticket', 'price' => 50.00, 'quantity' => 150],
                        ['name' => 'VIP Ticket', 'price' => 100.00, 'quantity' => 50],
                    ],
                    'reminders' => [
                        ['offset_hours' => 72, 'channel' => 'email', 'template_slug' => 'event-reminder'],
                        ['offset_hours' => 24, 'channel' => 'email', 'template_slug' => 'event-reminder'],
                    ],
                    'custom_fields' => [
                        ['name' => 'Dietary Restrictions', 'type' => 'text', 'required' => false],
                        ['name' => 'Seating Preference', 'type' => 'text', 'required' => false],
                    ],
                ],
                'is_default' => false,
            ],
            [
                'name' => 'Brothers Event',
                'description' => 'Configured specifically for brothers activities, sports or socials.',
                'category_slug' => 'social',
                'capacity' => 50,
                'is_public' => true,
                'waitlist_enabled' => true,
                'max_tickets_per_order' => 2,
                'max_registrations_per_attendee' => 1,
                'registration_deadline_offset_days' => 1,
                'settings' => [
                    'ticket_types' => [
                        ['name' => 'Brothers Ticket', 'price' => 0.00, 'quantity' => 50],
                    ],
                ],
                'is_default' => false,
            ],
            [
                'name' => 'Sisters Event',
                'description' => 'Configured specifically for sisters activities, halaqahs or socials.',
                'category_slug' => 'social',
                'capacity' => 50,
                'is_public' => true,
                'waitlist_enabled' => true,
                'max_tickets_per_order' => 2,
                'max_registrations_per_attendee' => 1,
                'registration_deadline_offset_days' => 1,
                'settings' => [
                    'ticket_types' => [
                        ['name' => 'Sisters Ticket', 'price' => 0.00, 'quantity' => 50],
                    ],
                ],
                'is_default' => false,
            ],
            [
                'name' => 'Ramadan Event',
                'description' => 'Configured for high-capacity Ramadan community dinners or lectures.',
                'category_slug' => 'social',
                'capacity' => 300,
                'is_public' => true,
                'waitlist_enabled' => true,
                'max_tickets_per_order' => 5,
                'max_registrations_per_attendee' => 1,
                'registration_deadline_offset_days' => 1,
                'settings' => [
                    'ticket_types' => [
                        ['name' => 'General Admission', 'price' => 0.00, 'quantity' => 300],
                    ],
                    'reminders' => [
                        ['offset_hours' => 24, 'channel' => 'email', 'template_slug' => 'event-reminder'],
                    ],
                ],
                'is_default' => false,
            ],
        ];

        foreach ($templates as $tmpl) {
            $catSlug = $tmpl['category_slug'];
            $categoryId = isset($categories[$catSlug]) ? $categories[$catSlug]->id : null;

            EventTemplate::firstOrCreate(
                ['name' => $tmpl['name']],
                [
                    'uuid' => (string) Str::uuid(),
                    'description' => $tmpl['description'],
                    'category_id' => $categoryId,
                    'capacity' => $tmpl['capacity'],
                    'is_public' => $tmpl['is_public'],
                    'waitlist_enabled' => $tmpl['waitlist_enabled'],
                    'max_tickets_per_order' => $tmpl['max_tickets_per_order'],
                    'max_registrations_per_attendee' => $tmpl['max_registrations_per_attendee'],
                    'registration_deadline_offset_days' => $tmpl['registration_deadline_offset_days'],
                    'settings' => $tmpl['settings'],
                    'is_default' => $tmpl['is_default'],
                ]
            );
        }
    }
}
