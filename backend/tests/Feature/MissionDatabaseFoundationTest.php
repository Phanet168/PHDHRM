<?php

namespace Tests\Feature;

use Carbon\Carbon;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\HumanResource\Database\Seeders\MissionFoundationSeeder;
use Modules\HumanResource\Entities\Mission;
use Modules\HumanResource\Entities\MissionAssignment;
use Modules\HumanResource\Entities\MissionDestination;
use Modules\HumanResource\Entities\MissionDocument;
use Modules\HumanResource\Entities\MissionReport;
use Modules\HumanResource\Entities\MissionStatusHistory;
use Modules\HumanResource\Entities\MissionType;
use Modules\HumanResource\Entities\TransportType;
use Modules\HumanResource\Entities\WorkflowInstance;
use Modules\HumanResource\Entities\WorkflowInstanceAction;
use Modules\HumanResource\Enums\Mission\ApprovalAction;
use Modules\HumanResource\Enums\Mission\DestinationScope;
use Modules\HumanResource\Enums\Mission\MissionCreationPath;
use Modules\HumanResource\Enums\Mission\MissionCreationSource;
use Modules\HumanResource\Enums\Mission\MissionStatus;
use Modules\HumanResource\Services\MissionResolverService;
use Tests\TestCase;

/** Real engine tests. Never migrate, truncate, or roll back the configured HRM database. */
class MissionDatabaseFoundationTest extends TestCase
{
    private ?string $ownedDatabase = null;
    private int $legacyId;
    private array $legacyBefore;

    private const FOUNDATION = [
        '2026_09_14_100000_create_mission_master_tables',
        '2026_09_14_101000_extend_mission_foundation',
        '2026_09_14_102000_create_mission_destinations_and_history',
        '2026_09_14_103000_enforce_mission_foundation_integrity',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('MISSION_FOUNDATION_MYSQL_TESTS') !== '1') {
            $this->markTestSkipped('Set MISSION_FOUNDATION_MYSQL_TESTS=1 to create disposable MySQL test databases.');
        }
        $this->ownedDatabase = 'phdhrm_mission_test_'.bin2hex(random_bytes(6));
        DB::connection('mysql')->statement("CREATE DATABASE `{$this->ownedDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        config(['database.connections.mission_foundation_test' => array_replace(config('database.connections.mysql'), [
            'database' => $this->ownedDatabase, 'url' => null,
        ]), 'database.default' => 'mission_foundation_test']);
        DB::purge('mission_foundation_test');
        $this->assertSame($this->ownedDatabase, DB::selectOne('SELECT DATABASE() AS name')->name);

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('full_name'); $t->softDeletes();
        });
        Schema::create('departments', function (Blueprint $t) {
            $t->id(); $t->string('department_name'); $t->softDeletes();
        });
        Schema::create('employees', function (Blueprint $t) {
            $t->id(); $t->string('first_name'); $t->string('last_name'); $t->softDeletes();
        });
        Schema::create('funding_sources', function (Blueprint $t) {
            $t->id(); $t->string('name');
        });
        Schema::create('correspondence_letters', function (Blueprint $t) {
            $t->id(); $t->string('subject'); $t->softDeletes();
        });
        DB::table('users')->insert(['id' => 1, 'full_name' => 'Foundation actor']);
        DB::table('departments')->insert(['id' => 1, 'department_name' => 'Foundation unit']);
        DB::table('employees')->insert([
            ['id' => 1, 'first_name' => 'One', 'last_name' => 'Officer'],
            ['id' => 2, 'first_name' => 'Two', 'last_name' => 'Officer'],
        ]);
        DB::table('funding_sources')->insert(['id' => 1, 'name' => 'Existing planning source']);
        DB::table('correspondence_letters')->insert(['id' => 1, 'subject' => 'Existing invitation']);
        foreach ([
            '2026_03_29_150000_create_workflow_engine_tables',
            '2026_04_19_100000_create_shift_and_mission_tables',
            '2026_09_13_090000_extend_mission_management',
            '2026_09_13_092000_add_official_mission_classification',
            '2026_09_13_093000_add_mission_order_details',
        ] as $name) {
            $this->migration($name)->up();
        }
        $this->legacyId = DB::table('missions')->insertGetId($this->legacyAttributes());
        DB::table('mission_assignments')->insert(['mission_id' => $this->legacyId, 'employee_id' => 1]);
        $this->legacyBefore = (array) DB::table('missions')->find($this->legacyId);
        $this->runFoundation();
        $this->seed(MissionFoundationSeeder::class);
    }

    protected function tearDown(): void
    {
        if ($this->ownedDatabase !== null && preg_match('/^phdhrm_mission_test_[a-f0-9]{12}$/D', $this->ownedDatabase)) {
            DB::purge('mission_foundation_test');
            // Only the random database created by this test can be removed.
            DB::connection('mysql')->statement("DROP DATABASE `{$this->ownedDatabase}`");
        }
        parent::tearDown();
    }

    private function migration(string $name): object
    {
        return require base_path('modules/HumanResource/Database/Migrations/'.$name.'.php');
    }

    private function runFoundation(): void
    {
        $this->artisan('migrate', ['--path' => array_map(fn ($name) => 'modules/HumanResource/Database/Migrations/'.$name.'.php', self::FOUNDATION), '--force' => true])->assertExitCode(0);
    }

    private function legacyAttributes(): array
    {
        return ['uuid' => (string) Str::uuid(), 'title' => 'Legacy mission', 'status' => 'approved',
            'destination' => 'Existing destination', 'start_date' => '2026-09-10', 'end_date' => '2026-09-15', 'requested_by' => 1];
    }

    private function mission(array $extra = []): Mission
    {
        $mission = new Mission;
        $mission->forceFill(array_replace($this->legacyAttributes(), ['status' => 'draft',
            'creation_path' => MissionCreationPath::Direct, 'creation_source' => MissionCreationSource::InvitationLetter,
            'lifecycle_status' => MissionStatus::Draft, 'created_by' => 1], $extra));
        $mission->save();

        return $mission->refresh();
    }

    private function report(Mission $mission, bool $submitted = false, int $employee = 1): MissionReport
    {
        $report = new MissionReport;
        $report->forceFill(['mission_id' => $mission->id, 'employee_id' => $employee, 'submitted_by' => 1,
            'summary' => 'Completed the assigned visit.', 'submitted_at' => $submitted ? now() : null]);
        $report->save();

        return $report;
    }

    private function history(Mission $mission): MissionStatusHistory
    {
        $history = new MissionStatusHistory;
        $history->forceFill(['mission_id' => $mission->id, 'from_status' => null, 'to_status' => MissionStatus::Draft,
            'action' => 'create', 'acted_by' => 1, 'revision' => 1, 'metadata' => ['source' => 'test'], 'occurred_at' => now()]);
        $history->save();

        return $history;
    }

    private function rejectSql(callable $write): void
    {
        try {
            $write();
            $this->fail('Expected the database to reject this write.');
        } catch (QueryException $exception) {
            $this->assertNotEmpty($exception->errorInfo);
        }
    }

    public function test_migrations_preserve_legacy_values_status_and_attendance_resolution(): void
    {
        $mission = Mission::findOrFail($this->legacyId);
        $after = (array) DB::table('missions')->find($this->legacyId);
        $this->assertSame($this->legacyBefore, array_intersect_key($after, $this->legacyBefore));
        $this->assertSame('approved', $mission->status);
        $this->assertNull($mission->lifecycle_status);
        $this->assertNull($mission->creation_path);
        $this->assertNull($mission->creation_source);
        $this->assertNull($mission->created_by);
        $resolver = app(MissionResolverService::class);
        $this->assertSame($mission->id, $resolver->resolveForDate(1, Carbon::parse('2026-09-12'))['mission_id']);
        $this->assertNull($resolver->resolveForDate(1, Carbon::parse('2026-09-16')));
        DB::table('missions')->where('id', $mission->id)->update(['status' => 'cancelled']);
        $this->assertNull($resolver->resolveForDate(1, Carbon::parse('2026-09-12')));
        $this->assertDatabaseCount('migrations', 4);
    }

    public function test_full_rollback_and_remigration_preserve_legacy_data_and_093_columns(): void
    {
        $this->artisan('migrate:rollback', ['--path' => array_map(fn ($name) => 'modules/HumanResource/Database/Migrations/'.$name.'.php', self::FOUNDATION), '--force' => true])->assertExitCode(0);
        $this->assertFalse(Schema::hasTable('mission_types'));
        $this->assertFalse(Schema::hasColumn('missions', 'lifecycle_status'));
        $this->assertTrue(Schema::hasColumn('missions', 'order_details'));
        $this->assertTrue(Schema::hasColumn('mission_assignments', 'sort_order'));
        $this->assertSame($this->legacyBefore, (array) DB::table('missions')->find($this->legacyId));
        $this->assertDatabaseCount('funding_sources', 1);
        $this->runFoundation();
        $this->assertDatabaseCount('migrations', 4);
        $this->assertSame('approved', Mission::findOrFail($this->legacyId)->status);
    }

    public function test_employee_request_reuses_workflow_and_direct_path_needs_no_workflow(): void
    {
        $workflow = WorkflowInstance::create(['module_key' => 'mission', 'request_type_key' => 'employee_request', 'status' => 'draft']);
        $request = $this->mission(['creation_path' => MissionCreationPath::EmployeeRequest,
            'creation_source' => MissionCreationSource::EmployeeRequest, 'requester_employee_id' => 1,
            'workflow_instance_id' => $workflow->id]);
        $this->assertSame($workflow->id, $request->currentWorkflow->id);
        $this->assertSame(1, $request->requesterEmployee->id);
        foreach ([MissionCreationSource::InvitationLetter, MissionCreationSource::DirectorInstruction, MissionCreationSource::AdministrationDirect, MissionCreationSource::Other] as $source) {
            $direct = $this->mission(['creation_source' => $source]);
            $this->assertSame(MissionCreationPath::Direct, $direct->creation_path);
            $this->assertSame($source, $direct->creation_source);
            $this->assertNull($direct->workflow_instance_id);
        }
        $this->assertFalse(Schema::hasTable('mission_approvals'));
        $this->assertFalse(Schema::hasTable('mission_participants'));
        $this->assertFalse(Schema::hasTable('mission_orders'));
    }

    public function test_invalid_creation_pairs_dates_states_and_foreign_keys_are_rejected(): void
    {
        $id = $this->mission()->id;
        foreach ([['creation_source' => 'employee_request'], ['creation_path' => null], ['creation_source' => null],
            ['creation_path' => 'unknown'], ['end_date' => '2026-09-01'], ['lifecycle_status' => 'approved'],
            ['mission_type_id' => 99999], ['workflow_instance_id' => 99999], ['revision' => 0]] as $values) {
            $this->rejectSql(fn () => DB::table('missions')->where('id', $id)->update($values));
        }
    }

    public function test_all_lifecycle_enum_values_cast_without_changing_legacy_status(): void
    {
        $mission = $this->mission(['status' => 'approved']);
        $this->report($mission, true);
        foreach (MissionStatus::cases() as $state) {
            $mission->lifecycle_status = $state;
            $mission->save();
            $this->assertSame($state, $mission->refresh()->lifecycle_status);
            $this->assertSame('approved', $mission->status);
        }
    }

    public function test_many_participants_snapshots_and_duplicate_rejection(): void
    {
        $mission = $this->mission();
        foreach ([1, 2] as $id) {
            $mission->assignments()->create(['employee_id' => $id, 'name_on_order' => 'Officer '.$id,
                'position_on_order' => 'Inspector', 'honorific_on_order' => 'Dr', 'department_on_order' => 'Original unit', 'sort_order' => $id]);
        }
        $this->assertCount(2, $mission->assignments);
        $this->rejectSql(fn () => DB::table('mission_assignments')->insert(['mission_id' => $mission->id, 'employee_id' => 1]));
        DB::table('employees')->where('id', 1)->update(['first_name' => 'Changed']);
        $this->assertSame('Officer 1', $mission->assignments()->first()->name_on_order);
        $this->assertSame(1, $mission->assignments()->first()->employee->id);
    }

    public function test_multiple_destinations_preserve_codes_and_location_snapshots(): void
    {
        $mission = $this->mission();
        foreach ([DestinationScope::WithinProvince, DestinationScope::OutsideProvince] as $scope) {
            $mission->destinations()->create(['scope' => $scope, 'destination_type' => 'health_center', 'department_id' => 1,
                'province_code' => '01', 'district_code' => '0102', 'commune_code' => '010201', 'village_code' => '01020101',
                'venue_name' => 'Original centre', 'location_snapshot' => ['province' => 'Original province']]);
        }
        $this->assertCount(2, $mission->destinations);
        $destination = $mission->destinations()->first();
        $this->assertSame(DestinationScope::WithinProvince, $destination->scope);
        $this->assertSame('01020101', $destination->village_code);
        DB::table('departments')->where('id', 1)->update(['department_name' => 'Renamed']);
        $this->assertSame(['province' => 'Original province'], $destination->refresh()->location_snapshot);
        $this->assertSame(1, $destination->department->id);
    }

    public function test_history_rejects_model_update_and_delete(): void
    {
        $history = $this->history($this->mission());
        foreach (['update', 'delete'] as $action) {
            try {
                $action === 'update' ? $history->forceFill(['reason' => 'tamper'])->save() : $history->delete();
                $this->fail('Expected append-only model guard.');
            } catch (DomainException $exception) {
                $this->assertStringContainsString('append-only', $exception->getMessage());
            }
        }
    }

    public function test_history_rejects_bulk_sql_changes_and_parent_cascades(): void
    {
        $mission = $this->mission();
        $history = $this->history($mission);
        $this->rejectSql(fn () => DB::table('mission_status_histories')->where('id', $history->id)->update(['reason' => 'tamper']));
        $this->rejectSql(fn () => DB::table('mission_status_histories')->where('id', $history->id)->delete());
        $this->rejectSql(fn () => DB::table('missions')->where('id', $mission->id)->delete());
        $this->rejectSql(fn () => DB::table('users')->where('id', 1)->delete());
        $this->assertDatabaseCount('mission_status_histories', 1);
    }

    public function test_history_links_shared_approval_without_changing_other_workflow_actions(): void
    {
        $mission = $this->mission();
        $workflow = WorkflowInstance::create(['module_key' => 'mission', 'request_type_key' => 'employee_request']);
        foreach (ApprovalAction::cases() as $action) {
            $decision = WorkflowInstanceAction::create(['workflow_instance_id' => $workflow->id, 'action_type' => $action->value]);
            $history = new MissionStatusHistory;
            $history->forceFill(['mission_id' => $mission->id, 'to_status' => 'pending_director', 'action' => $action->value,
                'workflow_action_id' => $decision->id, 'revision' => 1, 'occurred_at' => now()])->save();
            $this->assertSame($action, $history->approval_action);
            $this->assertSame($decision->id, $history->workflowAction->id);
        }
        $this->rejectSql(fn () => DB::table('workflow_instances')->where('id', $workflow->id)->delete());
        $this->assertNull($this->history($mission)->approval_action);
    }

    public function test_draft_and_attachment_only_do_not_allow_completion(): void
    {
        $mission = $this->mission();
        $mission->documents()->create(['name' => 'Evidence', 'path' => 'private/evidence.pdf', 'mime_type' => 'application/pdf', 'size' => 10, 'uploaded_by' => 1]);
        $this->assertFalse($mission->hasSubmittedReport());
        $this->report($mission);
        $this->assertFalse($mission->hasSubmittedReport());
        $this->rejectSql(fn () => DB::table('missions')->where('id', $mission->id)->update(['lifecycle_status' => 'completed']));
        $this->rejectSql(fn () => DB::table('missions')->where('id', $mission->id)->update(['completed_at' => now()]));
        $this->expectException(DomainException::class);
        $mission->lifecycle_status = MissionStatus::Completed;
        $mission->save();
    }

    public function test_one_structured_submitted_report_completes_a_group_mission(): void
    {
        $mission = $this->mission();
        foreach ([1, 2] as $employee) {
            $mission->assignments()->create(['employee_id' => $employee]);
        }
        $report = $this->report($mission, true);
        $report->fill(['title' => 'Visit report', 'activities' => 'Training', 'results' => 'Delivered', 'issues' => 'None', 'recommendations' => 'Follow-up'])->save();
        $report->documents()->create(['mission_id' => $mission->id, 'name' => 'Report evidence', 'path' => 'private/report.pdf', 'mime_type' => 'application/pdf', 'size' => 10, 'uploaded_by' => 1, 'document_kind' => 'report_attachment']);
        $mission->lifecycle_status = MissionStatus::Completed;
        $mission->save();
        $this->assertTrue($mission->hasSubmittedReport());
        $this->assertSame('Training', $report->refresh()->activities);
        $this->assertSame($mission->id, $report->mission->id);
        $this->assertSame(1, $report->submitter->id);
        $this->assertCount(1, $report->documents);
        $this->assertCount(1, $mission->reports);
        $this->rejectSql(fn () => DB::table('mission_reports')->where('id', $report->id)->update(['submitted_at' => null]));
        $this->rejectSql(fn () => DB::table('mission_reports')->where('id', $report->id)->update(['summary' => '   ']));
    }

    public function test_last_submitted_report_cannot_be_deleted_after_completion(): void
    {
        $mission = $this->mission();
        $report = $this->report($mission, true);
        DB::table('missions')->where('id', $mission->id)->update(['completed_at' => now()]);
        $this->rejectSql(fn () => DB::table('mission_reports')->where('id', $report->id)->delete());
        $second = $this->report($mission, true, 2);
        $report->delete();
        $this->assertTrue($mission->hasSubmittedReport());
        $this->rejectSql(fn () => DB::table('mission_reports')->where('id', $second->id)->delete());
    }

    public function test_documents_reuse_correspondence_and_cannot_cross_mission_reports(): void
    {
        $mission = $this->mission();
        $reference = $mission->documents()->create(['name' => 'Invitation', 'document_kind' => 'reference', 'correspondence_letter_id' => 1, 'uploaded_by' => 1]);
        $this->assertNull($reference->path);
        $this->assertSame(1, $reference->correspondenceLetter->id);
        $otherReport = $this->report($this->mission());
        $this->rejectSql(fn () => DB::table('mission_documents')->where('id', $reference->id)->update(['mission_report_id' => $otherReport->id]));
        $this->rejectSql(fn () => DB::table('correspondence_letters')->where('id', 1)->delete());
    }

    public function test_reference_only_documents_block_lossy_extension_rollback(): void
    {
        $mission = $this->mission();
        $mission->documents()->create(['name' => 'Invitation', 'document_kind' => 'reference', 'correspondence_letter_id' => 1, 'uploaded_by' => 1]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Rollback blocked');
        $this->migration(self::FOUNDATION[1])->down();
    }

    public function test_submitted_mission_edits_and_child_edits_increment_revision(): void
    {
        $mission = $this->mission();
        $mission->title = 'Draft edit'; $mission->save();
        $this->assertSame(1, $mission->refresh()->revision);
        $mission->submitted_at = now(); $mission->save();
        $mission->purpose = 'Changed scope'; $mission->save();
        $this->assertSame(2, $mission->refresh()->revision);
        $assignment = $mission->assignments()->create(['employee_id' => 1]);
        $this->assertSame(3, $mission->refresh()->revision);
        $assignment->update(['position_on_order' => 'New position']);
        $this->assertSame(4, $mission->refresh()->revision);
        $assignment->delete();
        $this->assertSame(5, $mission->refresh()->revision);
        $destination = $mission->destinations()->create(['scope' => 'within_province', 'destination_type' => 'other', 'province_code' => '01', 'venue_name' => 'Venue', 'location_snapshot' => []]);
        $this->assertSame(6, $mission->refresh()->revision);
        $destination->delete();
        $this->assertSame(7, $mission->refresh()->revision);
        $mission->documents()->create(['name' => 'Invitation', 'document_kind' => 'reference', 'correspondence_letter_id' => 1, 'uploaded_by' => 1]);
        $this->assertSame(8, $mission->refresh()->revision);
        $this->assertGreaterThan(0, $mission->lock_version);
    }

    public function test_existing_child_foreign_keys_now_retain_records(): void
    {
        $this->rejectSql(fn () => DB::table('employees')->where('id', 1)->delete());
        $this->rejectSql(fn () => DB::table('missions')->where('id', $this->legacyId)->delete());
        $mission = $this->mission();
        $this->report($mission);
        $this->rejectSql(fn () => DB::table('missions')->where('id', $mission->id)->delete());
        $this->assertDatabaseCount('mission_reports', 1);
    }

    public function test_seeders_are_idempotent_preserve_customization_and_reuse_funding(): void
    {
        $type = MissionType::where('code', 'training')->firstOrFail();
        $this->assertSame('វគ្គបណ្តុះបណ្តាល', $type->name_km);
        $type->update(['name_km' => 'Custom label', 'is_active' => false]);
        $this->seed(MissionFoundationSeeder::class);
        $this->assertDatabaseCount('mission_types', 2);
        $this->assertDatabaseCount('transport_types', 7);
        $this->assertDatabaseCount('funding_sources', 1);
        $this->assertSame('Custom label', $type->refresh()->name_km);
        $this->assertFalse($type->is_active);
        $mission = $this->mission(['mission_type_id' => $type->id, 'transport_type_id' => TransportType::first()->id, 'funding_source_id' => 1, 'issuer_department_id' => 1]);
        $this->assertSame($type->id, $mission->missionType->id);
        $this->assertSame(1, $mission->fundingSource->id);
        $this->assertSame(1, $mission->creator->id);
        $this->assertSame(1, $mission->issuerDepartment->id);
        $this->assertSame(1, $mission->transportType->missions()->count());
        $this->rejectSql(fn () => DB::table('mission_types')->where('id', $type->id)->delete());
    }

    public function test_new_server_owned_attributes_are_not_mass_assignable(): void
    {
        $mission = new Mission;
        $mission->fill(['title' => 'Allowed', 'lifecycle_status' => 'completed', 'created_by' => 999, 'revision' => 99, 'lock_version' => 99, 'issued_at' => now()]);
        $this->assertSame('Allowed', $mission->title);
        foreach (['lifecycle_status', 'created_by', 'revision', 'lock_version', 'issued_at'] as $column) {
            $this->assertArrayNotHasKey($column, $mission->getAttributes());
        }
        $report = new MissionReport;
        $report->fill(['summary' => 'Draft', 'submitted_at' => now(), 'submitted_by' => 999]);
        $this->assertNull($report->submitted_at);
        $this->assertNull($report->submitted_by);
    }
}
