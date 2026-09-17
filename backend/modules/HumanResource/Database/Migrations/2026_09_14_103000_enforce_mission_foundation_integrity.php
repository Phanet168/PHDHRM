<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $retainedForeignKeys = [
        ['mission_assignments', 'mission_id', 'missions'],
        ['mission_assignments', 'employee_id', 'employees'],
        ['mission_reports', 'mission_id', 'missions'],
        ['mission_reports', 'employee_id', 'employees'],
        ['mission_documents', 'mission_id', 'missions'],
    ];

    public function up(): void
    {
        // This application's production database is MySQL/MariaDB. Test the actual
        // engine: silently omitting these safeguards on SQLite is not equivalent.
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Mission integrity migration requires MySQL/MariaDB. Use the isolated MySQL foundation tests.');
        }
        if (DB::table('missions')->whereColumn('end_date', '<', 'start_date')->exists()) {
            throw new RuntimeException('Invalid legacy mission dates: reconcile explicitly before adding constraints.');
        }
        foreach (['mission_documents' => 'uploaded_by', 'mission_reports' => 'submitted_by'] as $table => $column) {
            if (DB::table("$table as c")->leftJoin('users as u', 'u.id', '=', "c.$column")->whereNull('u.id')->exists()) {
                throw new RuntimeException("Unresolved legacy actor in $table.$column; no actor values have been guessed.");
            }
        }

        $this->retention('RESTRICT');
        foreach (['mission_documents' => 'uploaded_by', 'mission_reports' => 'submitted_by'] as $table => $column) {
            DB::statement("ALTER TABLE `$table` ADD CONSTRAINT `{$table}_{$column}_fk` FOREIGN KEY (`$column`) REFERENCES users(id) ON DELETE RESTRICT");
        }
        $statuses = "'draft','pending_office_head','office_head_endorsed','pending_director','director_approved','pending_mission_order','preparing_mission_order','issued','on_mission','pending_report','completed','returned_for_correction','rejected','cancelled'";
        DB::statement("ALTER TABLE missions
            ADD CONSTRAINT missions_dates_check CHECK (end_date >= start_date),
            ADD CONSTRAINT missions_revision_check CHECK (revision >= 1),
            ADD CONSTRAINT missions_lifecycle_check CHECK (lifecycle_status IS NULL OR lifecycle_status IN ($statuses)),
            ADD CONSTRAINT missions_creation_check CHECK (
                (creation_path IS NULL AND creation_source IS NULL) OR
                (creation_path IS NOT NULL AND creation_source IS NOT NULL AND (
                    (creation_path = 'employee_request' AND creation_source = 'employee_request') OR
                    (creation_path = 'direct' AND creation_source IN ('invitation_letter','director_instruction','administration_direct','other'))
                ))
            )");
        DB::statement("ALTER TABLE mission_destinations ADD CONSTRAINT mission_destination_scope_check CHECK (scope IN ('within_province','outside_province'))");
        DB::statement("ALTER TABLE mission_reports ADD CONSTRAINT mission_report_submission_check CHECK (submitted_at IS NULL OR CHAR_LENGTH(TRIM(summary)) > 0), ADD CONSTRAINT mission_report_revision_check CHECK (revision >= 1)");
        DB::statement("ALTER TABLE mission_documents ADD CONSTRAINT mission_document_source_check CHECK (path IS NOT NULL OR correspondence_letter_id IS NOT NULL)");
        DB::statement("ALTER TABLE mission_status_histories
            ADD CONSTRAINT mission_history_from_check CHECK (from_status IS NULL OR from_status IN ($statuses)),
            ADD CONSTRAINT mission_history_to_check CHECK (to_status IN ($statuses)),
            ADD CONSTRAINT mission_history_revision_check CHECK (revision >= 1)");

        foreach (['UPDATE', 'DELETE'] as $event) {
            DB::unprepared("CREATE TRIGGER mission_history_no_".strtolower($event)." BEFORE $event ON mission_status_histories FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Mission history is append-only'");
        }
        DB::unprepared("CREATE TRIGGER mission_no_completed_insert BEFORE INSERT ON missions FOR EACH ROW
            BEGIN
                IF NEW.lifecycle_status = 'completed' OR NEW.completed_at IS NOT NULL THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Submit a mission report before completion';
                END IF;
            END");

        $important = ['title', 'purpose', 'start_date', 'end_date', 'destination', 'mission_type', 'order_number', 'order_details', 'creation_path', 'creation_source', 'creation_reason', 'requester_employee_id', 'source_department_id', 'issuer_department_id', 'mission_type_id', 'funding_source_id', 'sponsor_name', 'transport_type_id', 'transport_description'];
        $changes = implode(' OR ', array_map(fn ($column) => "NOT (NEW.`$column` <=> OLD.`$column`)", $important));
        DB::unprepared("CREATE TRIGGER mission_foundation_before_update BEFORE UPDATE ON missions FOR EACH ROW
            BEGIN
                IF ((NEW.lifecycle_status = 'completed' AND NOT (OLD.lifecycle_status <=> 'completed'))
                    OR (NEW.completed_at IS NOT NULL AND OLD.completed_at IS NULL))
                    AND NOT EXISTS (SELECT 1 FROM mission_reports WHERE mission_id = OLD.id AND submitted_at IS NOT NULL AND CHAR_LENGTH(TRIM(summary)) > 0) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Submit a mission report before completion';
                END IF;
                IF OLD.submitted_at IS NOT NULL AND ($changes) THEN
                    SET NEW.revision = OLD.revision + 1;
                END IF;
                IF NEW.creation_path IS NOT NULL THEN
                    SET NEW.lock_version = OLD.lock_version + 1;
                END IF;
            END");

        // All child changes are serialized through the mission row. Relationship
        // edits must invalidate the submitted revision, including query-builder writes.
        foreach (['mission_assignments', 'mission_destinations'] as $table) {
            foreach (['INSERT', 'UPDATE', 'DELETE'] as $event) {
                $ref = $event === 'DELETE' ? 'OLD' : 'NEW';
                $extra = $event === 'UPDATE' ? "IF OLD.mission_id <> NEW.mission_id THEN UPDATE missions SET revision = revision + 1 WHERE id = OLD.mission_id AND submitted_at IS NOT NULL; END IF;" : '';
                DB::unprepared("CREATE TRIGGER {$table}_revision_".strtolower($event)." AFTER $event ON $table FOR EACH ROW
                    BEGIN
                        UPDATE missions SET revision = revision + 1 WHERE id = $ref.mission_id AND submitted_at IS NOT NULL;
                        $extra
                    END");
            }
        }
        foreach (['INSERT', 'UPDATE', 'DELETE'] as $event) {
            $ref = $event === 'DELETE' ? 'OLD' : 'NEW';
            $condition = $event === 'UPDATE' ? "(NEW.document_kind = 'reference' OR OLD.document_kind = 'reference')" : "$ref.document_kind = 'reference'";
            $extra = $event === 'UPDATE' ? "IF OLD.mission_id <> NEW.mission_id AND OLD.document_kind = 'reference' THEN UPDATE missions SET revision = revision + 1 WHERE id = OLD.mission_id AND submitted_at IS NOT NULL; END IF;" : '';
            DB::unprepared("CREATE TRIGGER mission_documents_revision_".strtolower($event)." AFTER $event ON mission_documents FOR EACH ROW BEGIN
                IF $condition THEN UPDATE missions SET revision = revision + 1 WHERE id = $ref.mission_id AND submitted_at IS NOT NULL; END IF;
                $extra
            END");
        }

        // Preserve the completion invariant even when a report is changed/deleted
        // through SQL. Lock the parent against concurrent completion/withdrawals.
        foreach (['INSERT', 'UPDATE', 'DELETE'] as $event) {
            $ref = $event === 'INSERT' ? 'NEW' : 'OLD';
            $ownership = $event === 'UPDATE' ? "IF NEW.mission_id <> OLD.mission_id OR NEW.employee_id <> OLD.employee_id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Mission report ownership is immutable'; END IF;" : '';
            $withdraw = $event === 'DELETE' ? 'TRUE' : '(NEW.submitted_at IS NULL OR CHAR_LENGTH(TRIM(NEW.summary)) = 0)';
            $guard = $event === 'INSERT' ? '' : "IF (v_completed IS NOT NULL OR v_status = 'completed') AND OLD.submitted_at IS NOT NULL AND $withdraw
                AND NOT EXISTS (SELECT 1 FROM mission_reports WHERE mission_id = OLD.mission_id AND id <> OLD.id AND submitted_at IS NOT NULL AND CHAR_LENGTH(TRIM(summary)) > 0)
                THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot withdraw the last submitted report of a completed mission'; END IF;";
            DB::unprepared("CREATE TRIGGER mission_report_guard_".strtolower($event)." BEFORE $event ON mission_reports FOR EACH ROW BEGIN
                DECLARE v_completed DATETIME; DECLARE v_status VARCHAR(40);
                SELECT completed_at, lifecycle_status INTO v_completed, v_status FROM missions WHERE id = $ref.mission_id FOR UPDATE;
                $ownership
                $guard
            END");
        }
    }

    public function down(): void
    {
        foreach (['mission_history_no_update', 'mission_history_no_delete', 'mission_no_completed_insert', 'mission_foundation_before_update'] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS `$trigger`");
        }
        foreach (['insert', 'update', 'delete'] as $event) {
            foreach (['mission_assignments_revision', 'mission_destinations_revision', 'mission_documents_revision', 'mission_report_guard'] as $prefix) {
                DB::unprepared("DROP TRIGGER IF EXISTS `{$prefix}_$event`");
            }
        }
        foreach ([
            'missions' => ['missions_dates_check', 'missions_revision_check', 'missions_lifecycle_check', 'missions_creation_check'],
            'mission_destinations' => ['mission_destination_scope_check'],
            'mission_reports' => ['mission_report_submission_check', 'mission_report_revision_check'],
            'mission_documents' => ['mission_document_source_check'],
            'mission_status_histories' => ['mission_history_from_check', 'mission_history_to_check', 'mission_history_revision_check'],
        ] as $table => $checks) {
            $syntax = str_contains(DB::selectOne('SELECT VERSION() AS v')->v, 'MariaDB') ? 'DROP CONSTRAINT' : 'DROP CHECK';
            foreach ($checks as $check) {
                DB::statement("ALTER TABLE `$table` $syntax `$check`");
            }
        }
        foreach (['mission_documents' => 'uploaded_by', 'mission_reports' => 'submitted_by'] as $table => $column) {
            DB::statement("ALTER TABLE `$table` DROP FOREIGN KEY `{$table}_{$column}_fk`");
        }
        $this->retention('CASCADE');
    }

    private function retention(string $onDelete): void
    {
        foreach ($this->retainedForeignKeys as [$table, $column, $parent]) {
            // MariaDB cannot drop/re-add the same constraint name in one ALTER.
            $oldKey = "{$table}_{$column}_".($onDelete === 'RESTRICT' ? 'foreign' : 'retained_fk');
            $newKey = "{$table}_{$column}_".($onDelete === 'RESTRICT' ? 'retained_fk' : 'foreign');
            DB::statement("ALTER TABLE `$table` DROP FOREIGN KEY `$oldKey`, ADD CONSTRAINT `$newKey` FOREIGN KEY (`$column`) REFERENCES `$parent` (id) ON DELETE $onDelete");
        }
    }
};
