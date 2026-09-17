<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Modules\HumanResource\Entities\Mission;
use Modules\HumanResource\Services\MissionResolverService;
use Modules\HumanResource\Support\OrgHierarchyAccessService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MissionManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Carbon::setTestNow(Carbon::parse('2026-09-13 09:00:00'));
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('full_name');
            $t->integer('user_type_id');
            $t->softDeletes();
        });
        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->integer('user_id')->nullable();
            $t->string('first_name');
            $t->string('last_name');
            $t->string('employee_id');
            $t->integer('department_id');
            $t->integer('position_id')->nullable();
            $t->integer('sub_department_id')->nullable();
            $t->boolean('is_active')->default(true);
            $t->softDeletes();
        });
        Schema::create('per_menus', function (Blueprint $t) {
            $t->id();
            $t->string('uuid');
            $t->string('menu_name');
            $t->integer('parentmenu_id')->nullable();
            $t->integer('lable');
            $t->timestamps();
        });
        Schema::create('positions', function (Blueprint $t) {
            $t->id();
            $t->string('position_name');
            $t->string('position_name_km')->nullable();
            $t->softDeletes();
        });
        require_once base_path('database/migrations/2022_10_10_100252_create_permission_tables.php');
        (new \CreatePermissionTables)->up();
        (require base_path('modules/HumanResource/Database/Migrations/2026_04_19_100000_create_shift_and_mission_tables.php'))->up();
        (require base_path('modules/HumanResource/Database/Migrations/2026_09_13_090000_extend_mission_management.php'))->up();
        (require base_path('modules/HumanResource/Database/Migrations/2026_09_13_091000_ensure_mission_permissions.php'))->up();
        (require base_path('modules/HumanResource/Database/Migrations/2026_09_13_092000_add_official_mission_classification.php'))->up();
        (require base_path('modules/HumanResource/Database/Migrations/2026_09_13_093000_add_mission_order_details.php'))->up();
        // The API suite uses SQLite. Engine-specific foundation DDL is covered by
        // MissionDatabaseFoundationTest against an isolated MySQL database.
        Schema::table('mission_reports', function (Blueprint $t) {
            $t->dateTime('submitted_at')->nullable();
            $t->unsignedInteger('revision')->default(1);
        });
        DB::table('users')->insert([
            ['id' => 1, 'full_name' => 'Director', 'user_type_id' => 1],
            ['id' => 2, 'full_name' => 'Officer', 'user_type_id' => 3],
            ['id' => 3, 'full_name' => 'Other unit', 'user_type_id' => 3],
            ['id' => 4, 'full_name' => 'Unit manager', 'user_type_id' => 2],
        ]);
        DB::table('employees')->insert([
            ['id' => 1, 'user_id' => 2, 'first_name' => 'សុខា', 'last_name' => 'សុខ', 'employee_id' => '001', 'department_id' => 1],
            ['id' => 2, 'user_id' => 3, 'first_name' => 'Other', 'last_name' => 'Unit', 'employee_id' => '002', 'department_id' => 2],
        ]);
        $this->mock(OrgHierarchyAccessService::class, function ($mock) {
            $mock->shouldReceive('isSystemAdmin')->andReturnUsing(fn ($user) => (int) $user?->user_type_id === 1);
            $mock->shouldReceive('managedBranchIds')->andReturnUsing(fn ($user) => (int) $user?->user_type_id === 1 ? null : [1]);
        });
        Storage::fake('local');
        $this->login(1);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function login(int $id): void
    {
        Sanctum::actingAs(User::findOrFail($id));
    }

    private function payload(array $extra = []): array
    {
        return $extra + ['title' => 'ត្រួតពិនិត្យមណ្ឌលសុខភាព', 'destination' => 'សេសាន',
            'start_date' => '2026-09-13', 'end_date' => '2026-09-15', 'purpose' => 'ត្រួតពិនិត្យសេវា', 'employee_ids' => [1]];
    }

    private function create(array $extra = []): int
    {
        return $this->postJson('/api/v1/missions', $this->payload($extra))->assertCreated()->json('response.data.id');
    }

    public function test_complete_lifecycle_and_mobile_contract(): void
    {
        $id = $this->create();
        $this->postJson("/api/v1/missions/$id/review", ['decision' => 'approved'])->assertOk();
        $this->login(2);
        $this->getJson('/api/v1/missions')->assertOk()->assertJsonPath('response.data.total', 1)
            ->assertJsonPath('response.data.data.0.duration_days', 3)
            ->assertJsonPath('response.data.data.0.assigner.name', 'Director')
            ->assertJsonPath('response.data.data.0.team_members.0.name', 'សុខ សុខា');
        $this->postJson("/api/v1/missions/$id/report", ['summary' => 'Too soon'])->assertStatus(409);
        $this->postJson("/api/v1/missions/$id/start")->assertOk()->assertJsonPath('response.data.display_status', 'in_progress');
        $this->postJson("/api/v1/missions/$id/start")->assertOk();
        $this->postJson("/api/v1/missions/$id/report", ['summary' => 'ការងារបានបញ្ចប់'])->assertOk();
        $this->postJson("/api/v1/missions/$id/report", ['summary' => 'Updated report'])->assertOk()->assertJsonCount(1, 'response.data.reports');
        $this->login(1);
        $this->postJson("/api/v1/missions/$id/complete")->assertOk()->assertJsonPath('response.data.display_status', 'completed');
        $this->login(2);
        $this->postJson("/api/v1/missions/$id/report", ['summary' => 'Cannot change'])->assertStatus(409);
        $resolved = app(MissionResolverService::class)->resolveForDate(1, now());
        $this->assertSame($id, $resolved['mission_id']);
    }

    public function test_authentication_and_officer_permissions(): void
    {
        auth()->forgetGuards();
        $this->getJson('/api/v1/missions')->assertUnauthorized();
        $this->login(1);
        $id = $this->create();
        $this->login(2);
        $this->postJson('/api/v1/missions', $this->payload())->assertForbidden();
        $this->postJson("/api/v1/missions/$id/review", ['decision' => 'approved'])->assertForbidden();
        $this->putJson("/api/v1/missions/$id", $this->payload())->assertForbidden();
        $this->deleteJson("/api/v1/missions/$id")->assertForbidden();
        $this->getJson('/api/v1/missions?scope=managed')->assertForbidden();
    }

    public function test_other_employee_cannot_read_act_or_download(): void
    {
        $id = $this->create();
        $this->postJson("/api/v1/missions/$id/documents", ['document' => UploadedFile::fake()->create('plan.pdf', 20, 'application/pdf')])->assertCreated();
        $this->login(3);
        $this->getJson('/api/v1/missions')->assertOk()->assertJsonPath('response.data.total', 0);
        foreach (["/api/v1/missions/$id", "/api/v1/missions/$id/documents/1", "/api/v1/missions/$id/documents/1/signed-url"] as $url) {
            $this->getJson($url)->assertNotFound();
        }
        $this->postJson("/api/v1/missions/$id/start")->assertNotFound();
    }

    public function test_validation_editing_rejection_and_cancel(): void
    {
        $this->postJson('/api/v1/missions', $this->payload(['status' => 'approved']))->assertUnprocessable();
        $this->postJson('/api/v1/missions', $this->payload(['end_date' => '2026-09-01']))->assertUnprocessable();
        $this->postJson('/api/v1/missions', $this->payload(['employee_ids' => [1, 1]]))->assertUnprocessable();
        $id = $this->create(['status' => 'draft']);
        $this->login(2);
        $this->getJson("/api/v1/missions/$id")->assertNotFound();
        $this->login(1);
        $this->putJson("/api/v1/missions/$id", $this->payload(['status' => 'pending', 'employee_ids' => [2]]))->assertOk();
        $this->postJson("/api/v1/missions/$id/review", ['decision' => 'rejected'])->assertUnprocessable();
        $this->postJson("/api/v1/missions/$id/review", ['decision' => 'rejected', 'reason' => 'Revise dates'])->assertOk();
        $this->deleteJson("/api/v1/missions/$id")->assertOk();
        $this->getJson("/api/v1/missions/$id")->assertNotFound();
        $id = $this->create();
        $this->postJson("/api/v1/missions/$id/review", ['decision' => 'approved'])->assertOk();
        $this->putJson("/api/v1/missions/$id", $this->payload())->assertStatus(409);
        $this->postJson("/api/v1/missions/$id/cancel")->assertOk();
        $this->assertNull(app(MissionResolverService::class)->resolveForDate(1, now()));
    }

    public function test_overlapping_approved_assignments_are_rejected(): void
    {
        $a = $this->create();
        $b = $this->create();
        $this->postJson("/api/v1/missions/$a/review", ['decision' => 'approved'])->assertOk();
        $this->postJson("/api/v1/missions/$b/review", ['decision' => 'approved'])->assertStatus(409);
        $this->assertSame('pending', Mission::find($b)->status);
    }

    public function test_managers_are_limited_to_their_units_and_permissions(): void
    {
        $foreign = $this->create(['employee_ids' => [2]]);
        $manager = User::find(4);
        $manager->givePermissionTo(Permission::whereIn('name', ['read_mission', 'create_mission', 'update_mission'])->get());
        $this->login(4);
        $this->postJson('/api/v1/missions', $this->payload(['employee_ids' => [2]]))->assertForbidden();
        $id = $this->create();
        $this->getJson('/api/v1/missions?scope=managed')->assertOk()->assertJsonPath('response.data.total', 1);
        $this->getJson("/api/v1/missions/$foreign")->assertNotFound();
        $this->putJson("/api/v1/missions/$foreign", $this->payload())->assertForbidden();
        $this->postJson("/api/v1/missions/$id/review", ['decision' => 'approved'])->assertForbidden();
        $this->getJson('/api/v1/missions/employees')->assertOk()->assertJsonCount(1, 'response.data.data');
    }

    public function test_documents_are_private_signed_and_validate_file_types(): void
    {
        $id = $this->create();
        $this->postJson("/api/v1/missions/$id/documents", ['document' => UploadedFile::fake()->create('bad.php', 1, 'application/x-php')])->assertUnprocessable();
        $this->postJson("/api/v1/missions/$id/documents", ['document' => UploadedFile::fake()->create('plan.pdf', 20, 'application/pdf')])
            ->assertCreated()->assertJsonPath('response.data.documents.0.size', 20480)->assertJsonMissingPath('response.data.documents.0.path');
        $this->login(2);
        $this->getJson("/api/v1/missions/$id/documents/1")->assertOk()->assertDownload('plan.pdf');
        $url = $this->getJson("/api/v1/missions/$id/documents/1/signed-url")->assertOk()->json('response.data.url');
        auth()->forgetGuards();
        $this->get($url)->assertOk()->assertDownload('plan.pdf');
        $this->get('/api/missions/'.$id.'/documents/1/download')->assertForbidden();
        Carbon::setTestNow(now()->addMinutes(3));
        $this->get($url)->assertForbidden();
    }

    public function test_filters_pagination_and_dates_before_start(): void
    {
        $id = $this->create(['start_date' => '2026-09-14']);
        $this->postJson("/api/v1/missions/$id/review", ['decision' => 'approved'])->assertOk();
        $this->create(['title' => 'Other']);
        $this->getJson('/api/v1/missions?scope=managed&q=Other&per_page=1')->assertOk()->assertJsonPath('response.data.total', 1);
        $this->getJson('/api/v1/missions?per_page=101')->assertUnprocessable();
        $this->getJson('/api/v1/missions?from_date=2026-10-01&to_date=2026-10-02')->assertOk()->assertJsonPath('response.data.total', 0);
        $this->login(2);
        $this->postJson("/api/v1/missions/$id/start")->assertStatus(409);
    }

    public function test_official_mission_types_orders_and_one_sided_date_filter(): void
    {
        $this->getJson('/api/v1/missions/types')->assertOk()->assertJsonCount(6, 'response.data');
        $id = $this->create(['mission_type' => 'training', 'order_number' => 'PHD-123']);
        $this->getJson("/api/v1/missions/$id")->assertOk()->assertJsonPath('response.data.mission_type', 'training')
            ->assertJsonPath('response.data.order_number', 'PHD-123');
        $this->getJson('/api/v1/missions?scope=managed&mission_type=inspection')->assertOk()->assertJsonPath('response.data.total', 0);
        $this->getJson('/api/v1/missions?scope=managed&to_date=2026-09-15')->assertOk()->assertJsonPath('response.data.total', 1);
        $this->postJson('/api/v1/missions', $this->payload(['mission_type' => 'invalid']))->assertUnprocessable();
    }

    public function test_order_fields_printing_and_officer_snapshot(): void
    {
        DB::table('positions')->insert(['id' => 1, 'position_name' => 'Health officer', 'position_name_km' => 'មន្ត្រីសុខាភិបាល']);
        DB::table('employees')->where('id', 1)->update(['position_id' => 1]);
        $details = ['issuing_authority' => 'រដ្ឋបាលខេត្តស្ទឹងត្រែង', 'issuing_department' => 'មន្ទីរសុខាភិបាល',
            'issue_place' => 'ស្ទឹងត្រែង', 'issued_on' => '2026-09-12', 'reference' => '<script>alert(1)</script>',
            'transport' => 'ម៉ូតូ', 'funding_source' => 'រដ្ឋ', 'signatory_title' => 'ប្រធានមន្ទីរសុខាភិបាលខេត្ត'];
        $id = $this->create(['order_details' => $details, 'order_number' => '123']);
        $this->getJson("/api/v1/missions/$id")->assertOk()->assertJsonPath('response.data.order_details.transport', 'ម៉ូតូ')
            ->assertJsonPath('response.data.order_officers.0.position', 'មន្ត្រីសុខាភិបាល');
        $html = $this->get("/hr/missions/$id/order")->assertOk();
        $html->assertSee('លិខិតបញ្ជាបេសកកម្ម')->assertSee('រង់ចាំអនុម័ត')->assertSee('សរុបចំនួន៖ ០១ នាក់')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        DB::table('positions')->where('id', 1)->update(['position_name_km' => 'New position']);
        DB::table('employees')->where('id', 1)->update(['first_name' => 'New name']);
        $this->get("/hr/missions/$id/order")->assertOk()->assertSee('មន្ត្រីសុខាភិបាល')->assertSee('សុខ សុខា')
            ->assertDontSee('New position')->assertDontSee('New name');
        $this->postJson("/api/v1/missions/$id/review", ['decision' => 'approved'])->assertOk();
        $this->get("/hr/missions/$id/order")->assertOk()->assertDontSee('រង់ចាំអនុម័ត');
        $this->login(2);
        $this->get("/hr/missions/$id/order")->assertOk();
        $this->login(3);
        $this->get("/hr/missions/$id/order")->assertNotFound();
    }

    public function test_order_rejects_unknown_fields_and_invalid_issue_dates(): void
    {
        $this->postJson('/api/v1/missions', $this->payload(['order_details' => ['issued_on' => 'bad-date']]))
            ->assertUnprocessable()->assertJsonValidationErrors('order_details.issued_on');
        $this->postJson('/api/v1/missions', $this->payload(['order_details' => ['signature_path' => '/private/file']]))
            ->assertUnprocessable()->assertJsonValidationErrors('order_details');
    }

    public function test_draft_report_cannot_complete_and_submission_uses_server_actor(): void
    {
        $id = $this->create();
        $this->postJson("/api/v1/missions/$id/review", ['decision' => 'approved'])->assertOk();
        $this->login(2);
        $this->postJson("/api/v1/missions/$id/start")->assertOk();
        DB::table('mission_reports')->insert(['mission_id' => $id, 'employee_id' => 1, 'submitted_by' => 2, 'summary' => 'Draft only']);
        $this->login(1);
        $this->postJson("/api/v1/missions/$id/complete")->assertStatus(409);
        $this->login(2);
        $this->postJson("/api/v1/missions/$id/report", ['summary' => 'Final submitted report', 'submitted_by' => 999, 'submitted_at' => '2000-01-01'])
            ->assertOk();
        $this->assertDatabaseHas('mission_reports', ['mission_id' => $id, 'submitted_by' => 2, 'submitted_at' => '2026-09-13 09:00:00']);
        $this->login(1);
        $this->postJson("/api/v1/missions/$id/complete")->assertOk();
    }

    public function test_client_cannot_set_new_lifecycle_or_forge_approval_actors(): void
    {
        $id = $this->create(['lifecycle_status' => 'completed', 'created_by' => 999, 'approved_by' => 999, 'approved_at' => '2000-01-01', 'issued_by' => 999]);
        $mission = Mission::findOrFail($id);
        $this->assertSame('pending', $mission->status);
        $this->assertNull($mission->approved_by);
        $this->assertNull($mission->approved_at);
        $this->assertNull($mission->lifecycle_status);
        $this->assertNull($mission->created_by);
    }
}
