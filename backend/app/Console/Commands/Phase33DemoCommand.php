<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class Phase33DemoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'msa:phase33-demo {--cleanup : Clean up Phase 33 demo test data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed or cleanup interactive Phase 33 demonstration data for Notification Center & Preferences';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('cleanup')) {
            return $this->cleanupDemoData();
        }

        return $this->seedDemoData();
    }

    /**
     * Seed interactive Phase 33 demonstration data.
     */
    private function seedDemoData(): int
    {
        $this->info('=====================================================');
        $this->info(' SFU MSA Platform — Phase 33 Demo Setup');
        $this->info('=====================================================');

        // 1. Create or retrieve Member 1 (Primary Demo User)
        $member1 = User::firstOrCreate(
            ['email' => 'msa.phase33.member1@example.test'],
            [
                'name' => 'Abdalla Member 1',
                'password' => bcrypt('password123'),
                'email_verified_at' => now(),
                'is_active' => true,
                'uuid' => (string) Str::uuid(),
            ]
        );

        NotificationPreference::updateOrCreate(
            ['user_id' => $member1->id],
            [
                'in_app_enabled' => true,
                'email_enabled' => true,
                'new_announcements' => true,
                'upcoming_training' => true,
                'course_completion' => true,
                'certificate_earned' => true,
            ]
        );

        // 2. Create or retrieve Member 2 (Isolation Test User)
        $member2 = User::firstOrCreate(
            ['email' => 'msa.phase33.member2@example.test'],
            [
                'name' => 'Fatima Member 2',
                'password' => bcrypt('password123'),
                'email_verified_at' => now(),
                'is_active' => true,
                'uuid' => (string) Str::uuid(),
            ]
        );

        NotificationPreference::updateOrCreate(
            ['user_id' => $member2->id],
            [
                'in_app_enabled' => true,
                'email_enabled' => true,
                'new_announcements' => true,
                'upcoming_training' => true,
                'course_completion' => true,
                'certificate_earned' => true,
            ]
        );

        // 3. Clear old demo notifications
        Notification::whereIn('user_id', [$member1->id, $member2->id])
            ->where('data->demo_tag', 'phase33_demo')
            ->delete();

        // 4. Create representative notifications for Member 1
        $demoNotifications = [
            [
                'user_id' => $member1->id,
                'type' => 'App\\Notifications\\PlatformNotification',
                'title' => 'Registration Confirmed: Annual MSA Gala 2026',
                'message' => 'Your registration (#REG-GALA-2026) for Annual MSA Gala 2026 has been confirmed.',
                'data' => [
                    'type' => 'event',
                    'event_slug' => 'annual-msa-gala-2026',
                    'reference' => 'REG-GALA-2026',
                    'demo_tag' => 'phase33_demo',
                ],
                'read_at' => null,
            ],
            [
                'user_id' => $member1->id,
                'type' => 'App\\Notifications\\VmsInAppNotification',
                'title' => 'Volunteer Shift Assigned: Friday Prayer Setup',
                'message' => 'You have been assigned to the Friday Prayer Setup shift on Oct 12, 2026.',
                'data' => [
                    'type' => 'volunteer',
                    'opportunity_slug' => 'friday-prayer-setup',
                    'vms_type' => 'vms_shift_assigned',
                    'demo_tag' => 'phase33_demo',
                ],
                'read_at' => null,
            ],
            [
                'user_id' => $member1->id,
                'type' => 'App\\Notifications\\PlatformNotification',
                'title' => 'Store Order Update (#ORD-MSA-8842)',
                'message' => 'Your merchandise order #ORD-MSA-8842 status is now: Fulfilled.',
                'data' => [
                    'type' => 'store',
                    'order_number' => 'ORD-MSA-8842',
                    'status' => 'fulfilled',
                    'demo_tag' => 'phase33_demo',
                ],
                'read_at' => null,
            ],
            [
                'user_id' => $member1->id,
                'type' => 'App\\Notifications\\NewAnnouncementNotification',
                'title' => 'New Announcement: Fall Semester Orientation',
                'message' => 'Welcome all new and returning students to the Fall 2026 semester!',
                'data' => [
                    'type' => 'announcement',
                    'announcement_slug' => 'fall-orientation-2026',
                    'demo_tag' => 'phase33_demo',
                ],
                'read_at' => now()->subDays(1),
            ],
            [
                'user_id' => $member1->id,
                'type' => 'App\\Notifications\\CourseCompletedNotification',
                'title' => 'Course Progress: Foundations of Fiqh',
                'message' => 'Congratulations on completing Lesson 4: Purification & Wudu.',
                'data' => [
                    'type' => 'course',
                    'course_slug' => 'foundations-of-fiqh',
                    'demo_tag' => 'phase33_demo',
                ],
                'read_at' => null,
            ],
            [
                'user_id' => $member1->id,
                'type' => 'App\\Notifications\\CertificateEarnedNotification',
                'title' => 'Certificate Issued: Tajweed Level 1',
                'message' => 'Your official certificate for Tajweed Level 1 has been generated.',
                'data' => [
                    'type' => 'certificate',
                    'certificate_uuid' => (string) Str::uuid(),
                    'demo_tag' => 'phase33_demo',
                ],
                'read_at' => null,
            ],
            [
                'user_id' => $member1->id,
                'type' => 'App\\Notifications\\PlatformNotification',
                'title' => 'Platform Security Alert',
                'message' => 'System health check completed cleanly.',
                'data' => [
                    'type' => 'event',
                    'url' => 'https://malicious-external-phish.com/hack',
                    'demo_tag' => 'phase33_demo',
                ],
                'read_at' => null,
            ],
        ];

        foreach ($demoNotifications as $n) {
            Notification::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $n['user_id'],
                'type' => $n['type'],
                'title' => $n['title'],
                'message' => $n['message'],
                'data' => $n['data'],
                'read_at' => $n['read_at'],
            ]);
        }

        // 5. Create Member 2 private notifications for cross-user isolation test
        Notification::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $member2->id,
            'type' => 'App\\Notifications\\PlatformNotification',
            'title' => 'Private Order Update for Fatima (Member 2)',
            'message' => 'Your private order #ORD-FATIMA-111 has been processed.',
            'data' => [
                'type' => 'store',
                'order_number' => 'ORD-FATIMA-111',
                'demo_tag' => 'phase33_demo',
            ],
            'read_at' => null,
        ]);

        $this->info('✓ Test users and representative notifications created successfully!');
        $this->newLine();

        $this->table(
            ['Role', 'Email', 'Password', 'Unread Notifs'],
            [
                ['Primary Member 1', 'msa.phase33.member1@example.test', 'password123', '5 unread, 1 read, 1 external URL test'],
                ['Secondary Member 2', 'msa.phase33.member2@example.test', 'password123', '1 unread (Isolation Test)'],
            ]
        );

        $this->newLine();
        $this->comment('Key Demo URLs to Open in Browser:');
        $this->line('  • Notification Center:        http://localhost:5173/notifications');
        $this->line('  • Account Notifications Tab:  http://localhost:5173/account/notifications');
        $this->line('  • Personal Account Summary:   http://localhost:5173/account');
        $this->line('  • Personal Member Dashboard:  http://localhost:5173/dashboard');
        $this->newLine();
        $this->info('To remove test data run: php artisan msa:phase33-demo --cleanup');

        return Command::SUCCESS;
    }

    /**
     * Clean up Phase 33 demo data.
     */
    private function cleanupDemoData(): int
    {
        $this->info('Cleaning up Phase 33 demo test data...');

        $deletedCount = Notification::where('data->demo_tag', 'phase33_demo')->delete();

        User::whereIn('email', [
            'msa.phase33.member1@example.test',
            'msa.phase33.member2@example.test',
        ])->delete();

        $this->info("✓ Cleaned up {$deletedCount} demo notifications and demo test accounts.");

        return Command::SUCCESS;
    }
}
