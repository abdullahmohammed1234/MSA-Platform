<?php

namespace Tests\Feature\Pm;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Pm\Enums\DepartmentRole;
use App\Pm\Enums\ProjectPriority;
use App\Pm\Enums\ProjectRole;
use App\Pm\Enums\ProjectStatus;
use App\Pm\Enums\ProjectVisibility;
use App\Pm\Enums\TaskStatus;
use App\Pm\Models\PmAttachment;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmProjectMember;
use App\Pm\Models\PmTask;
use App\Pm\Models\PmTaskDependency;
use App\Pm\Models\PmTaskList;
use App\Pm\Services\DepartmentMembershipService;
use App\Pm\Services\ProjectAccessService;
use App\Services\ApplicationAccessService;
use Database\Seeders\PmDepartmentSeeder;
use Database\Seeders\PmRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PmTaskFoundationsTest extends TestCase
{
    use RefreshDatabase;

    protected ApplicationAccessService $appAccessService;
    protected DepartmentMembershipService $deptService;
    protected ProjectAccessService $accessService;

    protected Department $itDept;
    protected Department $marketingDept;
    protected User $adminUser;
    protected User $ownerUser;
    protected User $managerUser;
    protected User $contributorUser;
    protected User $viewerUser;
    protected User $deptViewerUser;
    protected User $externalUser;
    protected PmProject $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PmDepartmentSeeder::class);
        $this->seed(PmRolePermissionSeeder::class);

        $this->appAccessService = app(ApplicationAccessService::class);
        $this->deptService = app(DepartmentMembershipService::class);
        $this->accessService = app(ProjectAccessService::class);

        $this->itDept = Department::where('slug', 'it')->firstOrFail();
        $this->marketingDept = Department::where('slug', 'marketing')->firstOrFail();

        // Admin
        $this->adminUser = User::factory()->create();
        $adminRole = Role::where('slug', 'super-admin')->firstOrFail();
        $this->adminUser->roles()->sync([$adminRole->id]);
        $this->appAccessService->grant($this->adminUser, 'projects', $this->adminUser);

        // Project Owner
        $this->ownerUser = $this->createPmsUser('team-member');
        $this->deptService->addMember($this->itDept, $this->ownerUser, DepartmentRole::Member);

        // Project Manager
        $this->managerUser = $this->createPmsUser('team-member');
        $this->deptService->addMember($this->itDept, $this->managerUser, DepartmentRole::Member);

        // Project Contributor
        $this->contributorUser = $this->createPmsUser('team-member');
        $this->deptService->addMember($this->itDept, $this->contributorUser, DepartmentRole::Member);

        // Project Viewer
        $this->viewerUser = $this->createPmsUser('team-member');
        $this->deptService->addMember($this->itDept, $this->viewerUser, DepartmentRole::Member);

        // Department-only Viewer (belongs to owning department, but has NO explicit project role)
        $this->deptViewerUser = $this->createPmsUser('team-member');
        $this->deptService->addMember($this->itDept, $this->deptViewerUser, DepartmentRole::Member);

        // External User (marketing department, no access to IT private project)
        $this->externalUser = $this->createPmsUser('team-member');
        $this->deptService->addMember($this->marketingDept, $this->externalUser, DepartmentRole::Member);

        // Create Project
        $this->project = PmProject::create([
            'department_id' => $this->itDept->id,
            'name' => 'Task Foundations Core Project',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::Medium,
            'owner_id' => $this->ownerUser->id,
        ]);

        PmProjectMember::create([
            'project_id' => $this->project->id,
            'user_id' => $this->ownerUser->id,
            'project_role' => ProjectRole::Owner,
            'invitation_status' => 'accepted',
        ]);

        PmProjectMember::create([
            'project_id' => $this->project->id,
            'user_id' => $this->managerUser->id,
            'project_role' => ProjectRole::Manager,
            'invitation_status' => 'accepted',
        ]);

        PmProjectMember::create([
            'project_id' => $this->project->id,
            'user_id' => $this->contributorUser->id,
            'project_role' => ProjectRole::Contributor,
            'invitation_status' => 'accepted',
        ]);

        PmProjectMember::create([
            'project_id' => $this->project->id,
            'user_id' => $this->viewerUser->id,
            'project_role' => ProjectRole::Viewer,
            'invitation_status' => 'accepted',
        ]);
    }

    private function createPmsUser(string $pmsRole = 'team-member'): User
    {
        $role = Role::where('slug', $pmsRole)->firstOrFail();
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->sync([$role->id]);
        $this->appAccessService->grant($user, 'projects', $this->adminUser);

        return $user;
    }

    // =========================================================================
    // Decision 1: Multiple Task Assignees
    // =========================================================================

    public function test_can_retrieve_eligible_assignees_limited_to_contributors_managers_owners(): void
    {
        Sanctum::actingAs($this->contributorUser);

        $response = $this->getJson("/api/v1/pm/projects/{$this->project->uuid}/eligible-assignees");
        $response->assertStatus(200);

        $userIds = collect($response->json('users'))->pluck('id')->all();

        // Must include owner, manager, contributor
        $this->assertContains($this->ownerUser->id, $userIds);
        $this->assertContains($this->managerUser->id, $userIds);
        $this->assertContains($this->contributorUser->id, $userIds);

        // Must NOT include project viewer or department-only viewer
        $this->assertNotContains($this->viewerUser->id, $userIds);
        $this->assertNotContains($this->deptViewerUser->id, $userIds);
        $this->assertNotContains($this->externalUser->id, $userIds);
    }

    public function test_task_creation_with_multiple_valid_assignees(): void
    {
        Sanctum::actingAs($this->contributorUser);

        $payload = [
            'title' => 'Build Multiple Assignees Feature',
            'description' => 'Implement many-to-many assignees relationship.',
            'priority' => 'high',
            'status' => 'todo',
            'assignee_ids' => [$this->ownerUser->id, $this->contributorUser->id],
        ];

        $response = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/tasks", $payload);
        $response->assertStatus(201);

        $taskUuid = $response->json('task.uuid');
        $task = PmTask::where('uuid', $taskUuid)->firstOrFail();

        // Check pivot table
        $this->assertCount(2, $task->assignees);
        $this->assertTrue($task->assignees->contains($this->ownerUser));
        $this->assertTrue($task->assignees->contains($this->contributorUser));

        // Check primary assignee consistency
        $this->assertNotNull($task->assigned_to);
        $this->assertTrue(in_array($task->assigned_to, [$this->ownerUser->id, $this->contributorUser->id], true));

        // Response format includes assignees array
        $this->assertCount(2, $response->json('task.assignees'));
    }

    public function test_cannot_assign_department_viewer_without_explicit_project_role(): void
    {
        Sanctum::actingAs($this->contributorUser);

        $payload = [
            'title' => 'Attempt Invalid Assignment',
            'assignee_ids' => [$this->deptViewerUser->id],
        ];

        $response = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/tasks", $payload);
        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => "User [{$this->deptViewerUser->id}] is not eligible for assignment on this project. Only active PMS users with an explicit Contributor, Manager, or Owner role can be assigned.",
        ]);
    }

    public function test_cannot_assign_president_or_executive_without_explicit_project_role(): void
    {
        Sanctum::actingAs($this->contributorUser);

        $president = $this->createPmsUser('president');
        // President has NO explicit role on this private project

        $payload = [
            'title' => 'Attempt Assigning President',
            'assignee_ids' => [$president->id],
        ];

        $response = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/tasks", $payload);
        $response->assertStatus(422);
    }

    public function test_cannot_assign_inactive_user(): void
    {
        Sanctum::actingAs($this->contributorUser);

        $inactiveUser = $this->createPmsUser('team-member');
        $inactiveUser->update(['is_active' => false]);

        PmProjectMember::create([
            'project_id' => $this->project->id,
            'user_id' => $inactiveUser->id,
            'project_role' => ProjectRole::Contributor,
            'invitation_status' => 'accepted',
        ]);

        $payload = [
            'title' => 'Attempt Assigning Inactive User',
            'assignee_ids' => [$inactiveUser->id],
        ];

        $response = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/tasks", $payload);
        $response->assertStatus(422);
    }

    public function test_updating_task_assignees_preserves_legacy_assigned_to(): void
    {
        Sanctum::actingAs($this->contributorUser);

        $task = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Legacy Assignment Preservation',
            'status' => TaskStatus::Todo,
            'priority' => ProjectPriority::Medium,
            'assigned_to' => $this->ownerUser->id,
            'created_by' => $this->ownerUser->id,
        ]);
        $task->assignees()->attach($this->ownerUser->id, ['assigned_by' => $this->ownerUser->id]);

        $updateResponse = $this->putJson("/api/v1/pm/tasks/{$task->uuid}", [
            'assignee_ids' => [$this->managerUser->id, $this->contributorUser->id],
            'assigned_to' => $this->managerUser->id,
        ]);

        $updateResponse->assertStatus(200);

        $task->refresh();
        $this->assertEquals($this->managerUser->id, $task->assigned_to);
        $this->assertCount(2, $task->assignees);
        $this->assertTrue($task->assignees->contains($this->managerUser));
        $this->assertTrue($task->assignees->contains($this->contributorUser));
        $this->assertFalse($task->assignees->contains($this->ownerUser));
    }

    // =========================================================================
    // Decision 2: Independent Task Lists / Custom Sections
    // =========================================================================

    public function test_manager_can_create_task_list(): void
    {
        Sanctum::actingAs($this->managerUser);

        $response = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/lists", [
            'name' => 'Sprint 1 Backlog',
            'color' => '#3b82f6',
        ]);

        $response->assertStatus(201);
        $this->assertEquals('Sprint 1 Backlog', $response->json('list.name'));
        $this->assertEquals(1, $response->json('list.order_index'));

        $this->assertDatabaseHas('pm_task_lists', [
            'project_id' => $this->project->id,
            'name' => 'Sprint 1 Backlog',
        ]);
    }

    public function test_contributor_cannot_create_or_delete_task_list(): void
    {
        Sanctum::actingAs($this->contributorUser);

        $createResponse = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/lists", [
            'name' => 'Unauthorized List',
        ]);
        $createResponse->assertStatus(403);
    }

    public function test_task_list_reordering(): void
    {
        $list1 = PmTaskList::create(['project_id' => $this->project->id, 'name' => 'List 1', 'order_index' => 1]);
        $list2 = PmTaskList::create(['project_id' => $this->project->id, 'name' => 'List 2', 'order_index' => 2]);

        Sanctum::actingAs($this->managerUser);

        $response = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/lists/reorder", [
            'list_uuids' => [$list2->uuid, $list1->uuid],
        ]);

        $response->assertStatus(200);
        $this->assertEquals(1, $list2->fresh()->order_index);
        $this->assertEquals(2, $list1->fresh()->order_index);
    }

    public function test_deleting_task_list_preserves_tasks_in_uncategorized(): void
    {
        $list = PmTaskList::create(['project_id' => $this->project->id, 'name' => 'Ephemeral List', 'order_index' => 1]);

        $task = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Critical Business Deliverable',
            'task_list_id' => $list->id,
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::High,
            'created_by' => $this->ownerUser->id,
        ]);

        Sanctum::actingAs($this->ownerUser);

        $deleteResponse = $this->deleteJson("/api/v1/pm/lists/{$list->uuid}");
        $deleteResponse->assertStatus(200);

        // List is soft deleted
        $this->assertSoftDeleted('pm_task_lists', ['id' => $list->id]);

        // Task still exists and task_list_id is null (Uncategorized)
        $task->refresh();
        $this->assertNotNull($task);
        $this->assertNull($task->task_list_id);
        $this->assertEquals('Critical Business Deliverable', $task->title);
    }

    public function test_moving_task_between_lists_preserves_independent_status(): void
    {
        $listA = PmTaskList::create(['project_id' => $this->project->id, 'name' => 'Frontend', 'order_index' => 1]);
        $listB = PmTaskList::create(['project_id' => $this->project->id, 'name' => 'Backend', 'order_index' => 2]);

        $task = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'API Integration',
            'task_list_id' => $listA->id,
            'status' => TaskStatus::Review,
            'priority' => ProjectPriority::Urgent,
            'created_by' => $this->ownerUser->id,
        ]);

        Sanctum::actingAs($this->contributorUser);

        $response = $this->putJson("/api/v1/pm/tasks/{$task->uuid}", [
            'task_list_id' => $listB->id,
        ]);

        $response->assertStatus(200);
        $task->refresh();
        $this->assertEquals($listB->id, $task->task_list_id);
        // Status remains Review, NOT changed by moving list
        $this->assertEquals(TaskStatus::Review, $task->status);
    }

    // =========================================================================
    // Decision 3: Attachments
    // =========================================================================

    public function test_upload_and_stream_download_project_attachment(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->contributorUser);

        $file = UploadedFile::fake()->create('project-spec.pdf', 1024, 'application/pdf');

        $response = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", [
            'file' => $file,
        ]);

        $response->assertStatus(201);
        $attachmentUuid = $response->json('attachment.uuid');
        $this->assertNotNull($attachmentUuid);

        $attachment = PmAttachment::where('uuid', $attachmentUuid)->firstOrFail();
        Storage::disk('local')->assertExists($attachment->file_path);

        // Download streaming test
        $downloadResponse = $this->get("/api/v1/pm/attachments/{$attachmentUuid}/download");
        $downloadResponse->assertStatus(200);
        $downloadResponse->assertHeader('Content-Disposition', 'attachment; filename="project-spec.pdf"');
    }

    public function test_unauthorized_user_cannot_download_private_attachment(): void
    {
        Storage::fake('local');

        $attachment = PmAttachment::create([
            'project_id' => $this->project->id,
            'user_id' => $this->ownerUser->id,
            'original_name' => 'confidential.docx',
            'storage_name' => 'confidential.docx',
            'file_path' => 'pm_attachments/test.docx',
            'disk' => 'local',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => 500,
        ]);
        Storage::disk('local')->put('pm_attachments/test.docx', 'secret content');

        // External user has no access to IT department private project
        Sanctum::actingAs($this->externalUser);

        $response = $this->getJson("/api/v1/pm/attachments/{$attachment->uuid}/download");
        $response->assertStatus(403);
    }

    public function test_rejects_oversized_attachments_above_25mb(): void
    {
        Sanctum::actingAs($this->contributorUser);

        // 26 MB file (26624 KB > 25600 KB limit)
        $file = UploadedFile::fake()->create('giant.zip', 26624, 'application/zip');

        $response = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", [
            'file' => $file,
        ]);

        $response->assertStatus(422);
    }

    public function test_rejects_dangerous_executable_and_script_formats(): void
    {
        Sanctum::actingAs($this->contributorUser);

        $phpFile = UploadedFile::fake()->create('malicious.php', 10, 'text/x-php');

        $response = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", [
            'file' => $phpFile,
        ]);

        $response->assertStatus(422);
    }

    public function test_attachment_deletion_authorization_rules(): void
    {
        Storage::fake('local');

        $attachment = PmAttachment::create([
            'project_id' => $this->project->id,
            'user_id' => $this->contributorUser->id,
            'original_name' => 'contributor-doc.pdf',
            'storage_name' => 'contributor-doc.pdf',
            'file_path' => 'pm_attachments/doc.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
        ]);
        Storage::disk('local')->put('pm_attachments/doc.pdf', 'dummy');

        // Another contributor cannot delete it
        $otherContributor = $this->createPmsUser('team-member');
        $this->deptService->addMember($this->itDept, $otherContributor, DepartmentRole::Member);
        PmProjectMember::create([
            'project_id' => $this->project->id,
            'user_id' => $otherContributor->id,
            'project_role' => ProjectRole::Contributor,
            'invitation_status' => 'accepted',
        ]);

        Sanctum::actingAs($otherContributor);
        $unauthorizedResponse = $this->deleteJson("/api/v1/pm/attachments/{$attachment->uuid}");
        $unauthorizedResponse->assertStatus(403);

        // Uploader CAN delete their own attachment
        Sanctum::actingAs($this->contributorUser);
        $uploaderResponse = $this->deleteJson("/api/v1/pm/attachments/{$attachment->uuid}");
        $uploaderResponse->assertStatus(200);

        // Re-create to test Manager deletion
        $attachment2 = PmAttachment::create([
            'project_id' => $this->project->id,
            'user_id' => $this->contributorUser->id,
            'original_name' => 'doc2.pdf',
            'storage_name' => 'doc2.pdf',
            'file_path' => 'pm_attachments/doc2.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
        ]);
        Storage::disk('local')->put('pm_attachments/doc2.pdf', 'dummy');

        // Project Manager CAN delete any attachment in their project
        Sanctum::actingAs($this->managerUser);
        $managerResponse = $this->deleteJson("/api/v1/pm/attachments/{$attachment2->uuid}");
        $managerResponse->assertStatus(200);
    }

    // =========================================================================
    // Decision 4: Task Dependencies & DAG
    // =========================================================================

    public function test_can_add_valid_task_dependency(): void
    {
        $taskA = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Task A (Design)',
            'status' => TaskStatus::Completed,
            'priority' => ProjectPriority::Medium,
            'created_by' => $this->ownerUser->id,
        ]);

        $taskB = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Task B (Development)',
            'status' => TaskStatus::Todo,
            'priority' => ProjectPriority::High,
            'created_by' => $this->ownerUser->id,
        ]);

        Sanctum::actingAs($this->contributorUser);

        // Task B depends on Task A
        $response = $this->postJson("/api/v1/pm/tasks/{$taskB->uuid}/dependencies", [
            'depends_on_task_uuid' => $taskA->uuid,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('pm_task_dependencies', [
            'project_id' => $this->project->id,
            'task_id' => $taskB->id,
            'depends_on_task_id' => $taskA->id,
        ]);
    }

    public function test_rejects_self_dependency(): void
    {
        $task = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Self Dependency Test',
            'status' => TaskStatus::Todo,
            'priority' => ProjectPriority::Medium,
            'created_by' => $this->ownerUser->id,
        ]);

        Sanctum::actingAs($this->contributorUser);

        $response = $this->postJson("/api/v1/pm/tasks/{$task->uuid}/dependencies", [
            'depends_on_task_uuid' => $task->uuid,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'A task cannot depend on itself.']);
    }

    public function test_rejects_cross_project_dependencies(): void
    {
        $otherProject = PmProject::create([
            'department_id' => $this->marketingDept->id,
            'name' => 'Marketing Campaign',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'status' => ProjectStatus::Planning,
            'priority' => ProjectPriority::Low,
            'owner_id' => $this->externalUser->id,
        ]);

        $taskA = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'IT Task',
            'status' => TaskStatus::Todo,
            'priority' => ProjectPriority::Medium,
            'created_by' => $this->ownerUser->id,
        ]);

        $taskMarketing = PmTask::create([
            'project_id' => $otherProject->id,
            'title' => 'Marketing Task',
            'status' => TaskStatus::Todo,
            'priority' => ProjectPriority::Medium,
            'created_by' => $this->externalUser->id,
        ]);

        Sanctum::actingAs($this->ownerUser);

        $response = $this->postJson("/api/v1/pm/tasks/{$taskA->uuid}/dependencies", [
            'depends_on_task_uuid' => $taskMarketing->uuid,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Cross-project dependencies are forbidden. Both tasks must belong to the same project.']);
    }

    public function test_rejects_circular_dependencies(): void
    {
        $task1 = PmTask::create(['project_id' => $this->project->id, 'title' => 'Task 1', 'status' => TaskStatus::Todo, 'priority' => ProjectPriority::Medium, 'created_by' => $this->ownerUser->id]);
        $task2 = PmTask::create(['project_id' => $this->project->id, 'title' => 'Task 2', 'status' => TaskStatus::Todo, 'priority' => ProjectPriority::Medium, 'created_by' => $this->ownerUser->id]);
        $task3 = PmTask::create(['project_id' => $this->project->id, 'title' => 'Task 3', 'status' => TaskStatus::Todo, 'priority' => ProjectPriority::Medium, 'created_by' => $this->ownerUser->id]);

        // Task 2 depends on Task 1
        PmTaskDependency::create(['project_id' => $this->project->id, 'task_id' => $task2->id, 'depends_on_task_id' => $task1->id]);
        // Task 3 depends on Task 2
        PmTaskDependency::create(['project_id' => $this->project->id, 'task_id' => $task3->id, 'depends_on_task_id' => $task2->id]);

        Sanctum::actingAs($this->contributorUser);

        // Now attempt to make Task 1 depend on Task 3 (which would create 1 -> 3 -> 2 -> 1 cycle!)
        $response = $this->postJson("/api/v1/pm/tasks/{$task1->uuid}/dependencies", [
            'depends_on_task_uuid' => $task3->uuid,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Circular dependency detected: adding this dependency would create a cycle in the task graph.',
        ]);
    }

    // =========================================================================
    // Decision 5: Bulk Task Actions
    // =========================================================================

    public function test_bulk_status_update_across_authorized_tasks(): void
    {
        $task1 = PmTask::create(['project_id' => $this->project->id, 'title' => 'Bulk 1', 'status' => TaskStatus::Todo, 'priority' => ProjectPriority::Low, 'created_by' => $this->ownerUser->id]);
        $task2 = PmTask::create(['project_id' => $this->project->id, 'title' => 'Bulk 2', 'status' => TaskStatus::Todo, 'priority' => ProjectPriority::Medium, 'created_by' => $this->ownerUser->id]);

        Sanctum::actingAs($this->contributorUser);

        $response = $this->postJson('/api/v1/pm/tasks/bulk', [
            'project_uuid' => $this->project->uuid,
            'task_uuids' => [$task1->uuid, $task2->uuid],
            'action' => 'status',
            'value' => 'in_progress',
        ]);

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('updated_count'));

        $this->assertEquals(TaskStatus::InProgress, $task1->fresh()->status);
        $this->assertEquals(TaskStatus::InProgress, $task2->fresh()->status);
    }

    public function test_bulk_operation_rejects_cross_project_tasks(): void
    {
        $otherProject = PmProject::create([
            'department_id' => $this->marketingDept->id,
            'name' => 'Marketing Other',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'status' => ProjectStatus::Planning,
            'priority' => ProjectPriority::Low,
            'owner_id' => $this->externalUser->id,
        ]);

        $task1 = PmTask::create(['project_id' => $this->project->id, 'title' => 'IT Task', 'status' => TaskStatus::Todo, 'priority' => ProjectPriority::Low, 'created_by' => $this->ownerUser->id]);
        $task2 = PmTask::create(['project_id' => $otherProject->id, 'title' => 'Marketing Task', 'status' => TaskStatus::Todo, 'priority' => ProjectPriority::Low, 'created_by' => $this->externalUser->id]);

        Sanctum::actingAs($this->ownerUser);

        $response = $this->postJson('/api/v1/pm/tasks/bulk', [
            'project_uuid' => $this->project->uuid,
            'task_uuids' => [$task1->uuid, $task2->uuid],
            'action' => 'status',
            'value' => 'completed',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Cross-project bulk operations are not permitted. All tasks must belong to the specified project.']);
    }

    public function test_bulk_assign_validates_eligible_users(): void
    {
        $task1 = PmTask::create(['project_id' => $this->project->id, 'title' => 'Bulk Assign 1', 'status' => TaskStatus::Todo, 'priority' => ProjectPriority::Low, 'created_by' => $this->ownerUser->id]);

        Sanctum::actingAs($this->managerUser);

        // Attempting to bulk-assign deptViewerUser (who has no explicit Contributor/Manager/Owner role)
        $response = $this->postJson('/api/v1/pm/tasks/bulk', [
            'project_uuid' => $this->project->uuid,
            'task_uuids' => [$task1->uuid],
            'action' => 'assign',
            'value' => [$this->deptViewerUser->id],
        ]);

        $response->assertStatus(422);
    }

    public function test_zero_executive_bypass_on_private_attachment_download(): void
    {
        Storage::fake('local');

        $attachment = PmAttachment::create([
            'project_id' => $this->project->id,
            'user_id' => $this->ownerUser->id,
            'original_name' => 'secret_roadmap.pdf',
            'storage_name' => 'secret_roadmap.pdf',
            'file_path' => 'pm_attachments/secret_roadmap.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'file_size' => 200,
        ]);
        Storage::disk('local')->put('pm_attachments/secret_roadmap.pdf', 'classified');

        // President without qualifying department membership or project role
        $president = $this->createPmsUser('president');
        Sanctum::actingAs($president);

        $response = $this->getJson("/api/v1/pm/attachments/{$attachment->uuid}/download");
        $response->assertStatus(403);

        // Super Admin without department membership or project role
        $superAdmin = $this->createPmsUser('super-admin');
        Sanctum::actingAs($superAdmin);

        $responseAdmin = $this->getJson("/api/v1/pm/attachments/{$attachment->uuid}/download");
        $responseAdmin->assertStatus(403);
    }

    public function test_task_start_date_and_due_date_ordering_validation(): void
    {
        Sanctum::actingAs($this->contributorUser);

        // due_date earlier than start_date must fail validation
        $response = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/tasks", [
            'title' => 'Invalid Date Sequence Task',
            'start_date' => '2026-11-20',
            'due_date' => '2026-11-10',
        ]);

        $response->assertStatus(422);
    }

    // =========================================================================
    // PART 5 Certification Gaps: Bulk 50/51 Boundary, Cycles & Assignees
    // =========================================================================

    public function test_bulk_operation_accepts_exactly_fifty_tasks(): void
    {
        Sanctum::actingAs($this->ownerUser);

        $taskUuids = [];
        for ($i = 1; $i <= 50; $i++) {
            $task = PmTask::create([
                'project_id' => $this->project->id,
                'title' => "Boundary Task {$i}",
                'status' => TaskStatus::Todo,
                'priority' => ProjectPriority::Low,
                'created_by' => $this->ownerUser->id,
            ]);
            $taskUuids[] = $task->uuid;
        }

        $response = $this->postJson('/api/v1/pm/tasks/bulk', [
            'project_uuid' => $this->project->uuid,
            'task_uuids' => $taskUuids,
            'action' => 'status',
            'value' => 'in_progress',
        ]);

        $response->assertStatus(200);
        $this->assertEquals(50, $response->json('updated_count'));
        $this->assertEquals(50, PmTask::where('project_id', $this->project->id)->where('status', TaskStatus::InProgress)->count());
    }

    public function test_bulk_operation_rejects_fifty_one_tasks(): void
    {
        Sanctum::actingAs($this->ownerUser);

        $taskUuids = [];
        for ($i = 1; $i <= 51; $i++) {
            $task = PmTask::create([
                'project_id' => $this->project->id,
                'title' => "Oversized Task {$i}",
                'status' => TaskStatus::Todo,
                'priority' => ProjectPriority::Low,
                'created_by' => $this->ownerUser->id,
            ]);
            $taskUuids[] = $task->uuid;
        }

        $response = $this->postJson('/api/v1/pm/tasks/bulk', [
            'project_uuid' => $this->project->uuid,
            'task_uuids' => $taskUuids,
            'action' => 'status',
            'value' => 'in_progress',
        ]);

        $response->assertStatus(422);
        // None of the 51 tasks should have been updated
        $this->assertEquals(0, PmTask::where('project_id', $this->project->id)->where('status', TaskStatus::InProgress)->count());
    }

    public function test_bulk_operation_atomic_rollback_on_single_failure(): void
    {
        Sanctum::actingAs($this->ownerUser);

        $task1 = PmTask::create(['project_id' => $this->project->id, 'title' => 'Atomic Task 1', 'status' => TaskStatus::Todo, 'priority' => ProjectPriority::Low, 'created_by' => $this->ownerUser->id]);
        $task2 = PmTask::create(['project_id' => $this->project->id, 'title' => 'Atomic Task 2', 'status' => TaskStatus::Todo, 'priority' => ProjectPriority::Low, 'created_by' => $this->ownerUser->id]);

        $response = $this->postJson('/api/v1/pm/tasks/bulk', [
            'project_uuid' => $this->project->uuid,
            'task_uuids' => [$task1->uuid, $task2->uuid],
            'action' => 'status',
            'value' => 'invalid_status_xyz',
        ]);

        $response->assertStatus(422);
        // Both tasks must retain their original status
        $this->assertEquals(TaskStatus::Todo, $task1->fresh()->status);
        $this->assertEquals(TaskStatus::Todo, $task2->fresh()->status);
    }

    public function test_bulk_operation_rejects_nonexistent_task_uuid(): void
    {
        Sanctum::actingAs($this->ownerUser);

        $task1 = PmTask::create(['project_id' => $this->project->id, 'title' => 'Real Task', 'status' => TaskStatus::Todo, 'priority' => ProjectPriority::Low, 'created_by' => $this->ownerUser->id]);
        $fakeUuid = (string) \Illuminate\Support\Str::uuid();

        $response = $this->postJson('/api/v1/pm/tasks/bulk', [
            'project_uuid' => $this->project->uuid,
            'task_uuids' => [$task1->uuid, $fakeUuid],
            'action' => 'status',
            'value' => 'completed',
        ]);

        $response->assertStatus(422);
        $this->assertEquals(TaskStatus::Todo, $task1->fresh()->status);
    }

    public function test_unauthorized_user_forbidden_from_bulk_operations(): void
    {
        $task1 = PmTask::create(['project_id' => $this->project->id, 'title' => 'Viewer Bulk Test', 'status' => TaskStatus::Todo, 'priority' => ProjectPriority::Low, 'created_by' => $this->ownerUser->id]);

        // Project Viewer is read-only
        Sanctum::actingAs($this->viewerUser);

        $response = $this->postJson('/api/v1/pm/tasks/bulk', [
            'project_uuid' => $this->project->uuid,
            'task_uuids' => [$task1->uuid],
            'action' => 'status',
            'value' => 'completed',
        ]);

        $response->assertStatus(403);
    }

    public function test_task_assignment_with_duplicate_assignee_ids_handled_safely(): void
    {
        Sanctum::actingAs($this->managerUser);

        $task = PmTask::create(['project_id' => $this->project->id, 'title' => 'Dedup Assignee Task', 'status' => TaskStatus::Todo, 'priority' => ProjectPriority::Low, 'created_by' => $this->ownerUser->id]);

        $response = $this->putJson("/api/v1/pm/tasks/{$task->uuid}", [
            'assignee_ids' => [$this->contributorUser->id, $this->contributorUser->id],
        ]);

        $response->assertStatus(200);
        // Only 1 row should be in pm_task_assignees for this user
        $this->assertEquals(1, $task->assignees()->where('user_id', $this->contributorUser->id)->count());
    }

    public function test_task_assignment_fails_completely_if_one_assignee_is_invalid(): void
    {
        Sanctum::actingAs($this->managerUser);

        $task = PmTask::create(['project_id' => $this->project->id, 'title' => 'Atomic Assignee Task', 'status' => TaskStatus::Todo, 'priority' => ProjectPriority::Low, 'created_by' => $this->ownerUser->id]);

        // Attempting to assign 1 valid contributor and 1 invalid department viewer
        $response = $this->putJson("/api/v1/pm/tasks/{$task->uuid}", [
            'assignee_ids' => [$this->contributorUser->id, $this->deptViewerUser->id],
        ]);

        $response->assertStatus(422);
        // No assignees should have been attached
        $this->assertEquals(0, $task->assignees()->count());
        $this->assertNull($task->fresh()->assigned_to);
    }

    public function test_removing_secondary_assignee_preserves_primary_assignee(): void
    {
        $otherContributor = $this->createPmsUser('team-member');
        $this->deptService->addMember($this->itDept, $otherContributor, DepartmentRole::Member);
        PmProjectMember::create([
            'project_id' => $this->project->id,
            'user_id' => $otherContributor->id,
            'project_role' => ProjectRole::Contributor,
            'invitation_status' => 'accepted',
        ]);

        Sanctum::actingAs($this->managerUser);

        $task = PmTask::create(['project_id' => $this->project->id, 'title' => 'Multi Assignee Removal', 'status' => TaskStatus::Todo, 'priority' => ProjectPriority::Low, 'created_by' => $this->ownerUser->id]);

        // Assign both contributors (primary = contributorUser, secondary = otherContributor)
        $this->putJson("/api/v1/pm/tasks/{$task->uuid}", [
            'assignee_ids' => [$this->contributorUser->id, $otherContributor->id],
        ])->assertStatus(200);

        $this->assertEquals($this->contributorUser->id, $task->fresh()->assigned_to);

        // Remove secondary assignee
        $this->putJson("/api/v1/pm/tasks/{$task->uuid}", [
            'assignee_ids' => [$this->contributorUser->id],
        ])->assertStatus(200);

        // Primary assigned_to must still be contributorUser
        $this->assertEquals($this->contributorUser->id, $task->fresh()->assigned_to);
        $this->assertEquals(1, $task->fresh()->assignees()->count());
    }

    public function test_rejects_multi_hop_dependency_cycles(): void
    {
        $taskA = PmTask::create(['project_id' => $this->project->id, 'title' => 'Node A', 'status' => TaskStatus::Todo, 'priority' => ProjectPriority::Medium, 'created_by' => $this->ownerUser->id]);
        $taskB = PmTask::create(['project_id' => $this->project->id, 'title' => 'Node B', 'status' => TaskStatus::Todo, 'priority' => ProjectPriority::Medium, 'created_by' => $this->ownerUser->id]);
        $taskC = PmTask::create(['project_id' => $this->project->id, 'title' => 'Node C', 'status' => TaskStatus::Todo, 'priority' => ProjectPriority::Medium, 'created_by' => $this->ownerUser->id]);

        Sanctum::actingAs($this->contributorUser);

        // B depends on A
        $this->postJson("/api/v1/pm/tasks/{$taskB->uuid}/dependencies", ['depends_on_task_uuid' => $taskA->uuid])->assertStatus(201);

        // C depends on B
        $this->postJson("/api/v1/pm/tasks/{$taskC->uuid}/dependencies", ['depends_on_task_uuid' => $taskB->uuid])->assertStatus(201);

        // Multi-hop cycle: Attempt to make A depend on C (Chain: A -> C -> B -> A)
        $response = $this->postJson("/api/v1/pm/tasks/{$taskA->uuid}/dependencies", ['depends_on_task_uuid' => $taskC->uuid]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Circular dependency detected: adding this dependency would create a cycle in the task graph.',
        ]);
    }

    public function test_can_remove_task_dependency(): void
    {
        $taskA = PmTask::create(['project_id' => $this->project->id, 'title' => 'Dep Source', 'status' => TaskStatus::Todo, 'priority' => ProjectPriority::Medium, 'created_by' => $this->ownerUser->id]);
        $taskB = PmTask::create(['project_id' => $this->project->id, 'title' => 'Dep Target', 'status' => TaskStatus::Todo, 'priority' => ProjectPriority::Medium, 'created_by' => $this->ownerUser->id]);

        Sanctum::actingAs($this->contributorUser);

        $this->postJson("/api/v1/pm/tasks/{$taskB->uuid}/dependencies", ['depends_on_task_uuid' => $taskA->uuid])->assertStatus(201);
        $this->assertDatabaseHas('pm_task_dependencies', ['task_id' => $taskB->id, 'depends_on_task_id' => $taskA->id]);

        // Remove dependency
        $deleteRes = $this->deleteJson("/api/v1/pm/tasks/{$taskB->uuid}/dependencies/{$taskA->uuid}");
        $deleteRes->assertStatus(200);

        $this->assertDatabaseMissing('pm_task_dependencies', ['task_id' => $taskB->id, 'depends_on_task_id' => $taskA->id]);
    }

    public function test_rejects_duplicate_task_dependency(): void
    {
        $taskA = PmTask::create(['project_id' => $this->project->id, 'title' => 'Dep Source Dup', 'status' => TaskStatus::Todo, 'priority' => ProjectPriority::Medium, 'created_by' => $this->ownerUser->id]);
        $taskB = PmTask::create(['project_id' => $this->project->id, 'title' => 'Dep Target Dup', 'status' => TaskStatus::Todo, 'priority' => ProjectPriority::Medium, 'created_by' => $this->ownerUser->id]);

        Sanctum::actingAs($this->contributorUser);

        $this->postJson("/api/v1/pm/tasks/{$taskB->uuid}/dependencies", ['depends_on_task_uuid' => $taskA->uuid])->assertStatus(201);

        // Attempt duplicate addition
        $dupRes = $this->postJson("/api/v1/pm/tasks/{$taskB->uuid}/dependencies", ['depends_on_task_uuid' => $taskA->uuid]);
        $dupRes->assertStatus(422);
        $dupRes->assertJsonFragment(['message' => 'This dependency relationship already exists.']);
    }

    public function test_tasks_can_be_uncategorized_with_null_task_list_id(): void
    {
        Sanctum::actingAs($this->contributorUser);

        $response = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/tasks", [
            'title' => 'Uncategorized Task',
            'task_list_id' => null,
        ]);

        $response->assertStatus(201);
        $this->assertNull($response->json('task.task_list_id'));
    }

    public function test_rejects_cross_project_task_list_assignment(): void
    {
        $otherProject = PmProject::create([
            'department_id' => $this->marketingDept->id,
            'name' => 'Marketing List Project',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'status' => ProjectStatus::Planning,
            'priority' => ProjectPriority::Low,
            'owner_id' => $this->externalUser->id,
        ]);

        $otherList = PmTaskList::create([
            'project_id' => $otherProject->id,
            'name' => 'Marketing List',
            'position' => 1,
            'created_by' => $this->externalUser->id,
        ]);

        Sanctum::actingAs($this->contributorUser);

        $response = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/tasks", [
            'title' => 'Cross List Task',
            'task_list_id' => $otherList->id,
        ]);

        $response->assertStatus(422);
    }
}
