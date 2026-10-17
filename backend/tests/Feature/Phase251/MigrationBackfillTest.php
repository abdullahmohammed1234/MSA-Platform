<?php

namespace Tests\Feature\Phase251;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MigrationBackfillTest extends TestCase
{
    use RefreshDatabase;

    private function migration(string $file): object
    {
        return require database_path('migrations/'.$file);
    }

    private function volunteer(string $name, Carbon $createdAt): void
    {
        DB::table('volunteer_registrations')->insert([
            'uuid' => (string) Str::uuid(), 'name' => $name, 'email' => $name.'@example.test',
            'student_number' => '300000000', 'department' => 'Synthetic', 'interests' => '[]',
            'status' => 'new', 'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);
    }

    private function digestedNames(): array
    {
        return DB::table('volunteer_registrations')->whereNotNull('digested_at')->orderBy('name')->pluck('name')->all();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_digest_backfill_mid_day_leaves_rows_after_last_legacy_run_for_new_digest(): void
    {
        $migration = $this->migration('2026_10_14_120100_add_digest_tracking_to_volunteer_registrations_table.php');
        $migration->down();

        Carbon::setTestNow('2026-10-14 10:00:00');
        $this->volunteer('a_yesterday_noon', Carbon::parse('2026-10-13 12:00:00'));
        $this->volunteer('b_yesterday_2357', Carbon::parse('2026-10-13 23:57:00'));
        $this->volunteer('c_today_0005', Carbon::parse('2026-10-14 00:05:00'));

        $migration->up();

        $this->assertSame(['a_yesterday_noon'], $this->digestedNames());
    }

    public function test_digest_backfill_after_legacy_run_marks_todays_sent_rows_to_avoid_duplicates(): void
    {
        $migration = $this->migration('2026_10_14_120100_add_digest_tracking_to_volunteer_registrations_table.php');
        $migration->down();

        Carbon::setTestNow('2026-10-14 23:58:00');
        $this->volunteer('a_today_noon', Carbon::parse('2026-10-14 12:00:00'));
        $this->volunteer('b_today_2357', Carbon::parse('2026-10-14 23:57:00'));

        $migration->up();

        $this->assertSame(['a_today_noon'], $this->digestedNames());
    }

    public function test_announcement_backfill_marks_only_announcements_that_were_published(): void
    {
        $migration = $this->migration('2026_10_14_130000_add_notified_at_to_announcements_table.php');
        $migration->down();

        foreach ([
            'published' => ['status' => 'published', 'published_at' => now()->subDays(3)],
            'archived-was-published' => ['status' => 'archived', 'published_at' => now()->subDays(30)],
            'draft' => ['status' => 'draft', 'published_at' => null],
        ] as $slug => $attrs) {
            DB::table('announcements')->insert(array_merge([
                'uuid' => (string) Str::uuid(), 'title' => $slug, 'slug' => $slug, 'content' => 'x',
                'created_at' => now()->subDays(40), 'updated_at' => now(),
            ], $attrs));
        }

        $migration->up();

        $this->assertSame(
            ['archived-was-published', 'published'],
            DB::table('announcements')->whereNotNull('notified_at')->orderBy('slug')->pluck('slug')->all()
        );
    }

    public function test_payload_scrub_clears_every_row_across_chunk_boundaries_and_keeps_other_columns(): void
    {
        $rows = [];
        for ($i = 1; $i <= 2500; $i++) {
            $rows[] = [
                'job_uuid' => (string) Str::uuid(), 'job_name' => 'Job'.$i, 'queue' => 'default',
                'status' => 'failed', 'failure_reason' => 'RuntimeException', 'attempts' => 1,
                'payload' => $i % 5 === 0 ? null : '{"token":"SYNTHETIC-'.$i.'"}',
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('job_logs')->insert($chunk);
        }

        $this->migration('2026_10_14_120000_scrub_job_logs_payload.php')->up();

        $this->assertSame(0, DB::table('job_logs')->whereNotNull('payload')->count());
        $this->assertSame(2500, DB::table('job_logs')->whereNotNull('failure_reason')->count());
    }
}
