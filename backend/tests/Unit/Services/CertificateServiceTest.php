<?php
# d:\projects\msa + dawah\MSA Platform\backend\tests\Unit\Services\CertificateServiceTest.php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\Course;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\CertificateAward;
use App\Repositories\CertificateRepository;
use App\Repositories\CertificateAwardRepository;
use App\Repositories\CourseRepository;
use App\Services\CertificateRequirementEngine;
use App\Services\CertificateService;
use App\Services\AchievementService;
use App\Services\BadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CertificateServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CertificateService $certificateService;
    protected CertificateRepository $certificateRepository;
    protected CertificateAwardRepository $certificateAwardRepository;
    protected CourseRepository $courseRepository;
    protected CertificateRequirementEngine $requirementEngine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->certificateRepository = new CertificateRepository();
        $this->certificateAwardRepository = new CertificateAwardRepository();
        $this->courseRepository = new CourseRepository();
        $this->requirementEngine = new CertificateRequirementEngine();

        // Register fake versions of Achievement/Badge services if resolving from container
        $this->app->instance(AchievementService::class, $this->createMock(AchievementService::class));
        $this->app->instance(BadgeService::class, $this->createMock(BadgeService::class));

        $this->certificateService = new CertificateService(
            $this->certificateRepository,
            $this->certificateAwardRepository,
            $this->courseRepository,
            $this->requirementEngine
        );
    }

    public function test_check_eligibility_returns_false_for_invalid_records()
    {
        $eligible = $this->certificateService->checkEligibility(999, 999);
        $this->assertFalse($eligible);
    }

    public function test_issue_certificate_creates_award_and_dispatches_jobs()
    {
        Queue::fake();
        Notification::fake();

        $user = User::factory()->create();
        $course = Course::factory()->create();
        
        $template = CertificateTemplate::factory()->create();
        $certificate = Certificate::factory()->create([
            'certificate_template_id' => $template->id,
            'course_id' => $course->id,
            'type' => 'course',
        ]);

        // Mock checking eligibility to be true
        $mockEngine = $this->createMock(CertificateRequirementEngine::class);
        $mockEngine->method('evaluateRequirement')->willReturn(true);

        $service = new CertificateService(
            $this->certificateRepository,
            $this->certificateAwardRepository,
            $this->courseRepository,
            $mockEngine
        );

        $award = $service->issueCertificate($user->id, $course->id);

        $this->assertInstanceOf(CertificateAward::class, $award);
        $this->assertEquals($certificate->id, $award->certificate_id);
        $this->assertEquals($user->id, $award->user_id);

        Queue::assertPushed(\App\Jobs\GenerateCertificatePdfJob::class);
        Notification::assertSentTo($user, \App\Notifications\CertificateEarnedNotification::class);
    }

    public function test_verify_certificate_logs_lookup_and_returns_award()
    {
        $award = CertificateAward::factory()->create();

        $result = $this->certificateService->verifyCertificate($award->code, '127.0.0.1', 'Mozilla');

        $this->assertEquals($award->id, $result->id);
        $this->assertDatabaseHas('certificate_verifications', [
            'certificate_award_id' => $award->id,
            'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_get_user_certificates()
    {
        $user = User::factory()->create();
        CertificateAward::factory()->create(['user_id' => $user->id]);

        $results = $this->certificateService->getUserCertificates($user->id);

        $this->assertCount(1, $results);
    }

    public function test_revoke_certificate_award()
    {
        $award = CertificateAward::factory()->create();

        $revoked = $this->certificateService->revokeCertificateAward($award->id);

        $this->assertTrue($revoked);
        $this->assertSoftDeleted('certificate_awards', ['id' => $award->id]);
    }
}
