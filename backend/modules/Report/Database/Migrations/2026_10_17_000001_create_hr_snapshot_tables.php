<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly HR statistics taken from the HRM staff records.
 *
 * - report_hr_professions: the profession categories the HR indicators count by (វេជ្ជបណ្ឌិត, គិ.មធ្យម …)
 * - report_hr_skill_maps: the free-text skill written on a staff profile → profession category
 * - report_hr_snapshots: who was on staff at the end of a month, with the attributes counted (so a month's
 *   numbers never move once taken, and every number can be traced back to names)
 * - report_hr_events: staff movements dated inside a month (retirement, death, transfers …)
 * - report_indicators.hrm_*: which slice of the snapshot an indicator counts
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_hr_professions', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();
            $t->string('name');
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('report_hr_skill_maps', function (Blueprint $t) {
            $t->id();
            $t->string('skill_text')->unique();
            $t->foreignId('profession_id')->nullable()->constrained('report_hr_professions')->nullOnDelete();
            $t->boolean('auto')->default(true)->comment('guessed by the system, not yet confirmed by a person');
            $t->timestamps();
        });

        Schema::create('report_hr_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('period_id')->constrained('report_periods')->cascadeOnDelete();
            $t->unsignedBigInteger('employee_id')->index();
            $t->char('sex', 1)->nullable();
            $t->foreignId('profession_id')->nullable()->constrained('report_hr_professions')->nullOnDelete();
            $t->string('skill_text')->nullable();
            $t->string('employment', 16)->nullable()->comment('civil | contract | agreement | other');
            $t->string('workplace', 16)->nullable()->comment('phd | od | hospital | hc');
            $t->unsignedBigInteger('department_id')->nullable();
            $t->string('service_state', 16)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['period_id', 'employee_id']);
        });

        Schema::create('report_hr_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('period_id')->constrained('report_periods')->cascadeOnDelete();
            $t->unsignedBigInteger('employee_id')->index();
            $t->string('event', 32);
            $t->date('event_date')->nullable();
            // the person's attributes are kept on the event: someone who retired is no longer in the month-end snapshot
            $t->char('sex', 1)->nullable();
            $t->foreignId('profession_id')->nullable()->constrained('report_hr_professions')->nullOnDelete();
            $t->string('employment', 16)->nullable();
            $t->string('workplace', 16)->nullable();
            $t->string('source', 64)->nullable()->comment('table#id the event was read from');
            $t->timestamps();
            $t->unique(['period_id', 'employee_id', 'event']);
        });

        Schema::table('report_indicators', function (Blueprint $t) {
            $t->string('hrm_measure', 32)->nullable()->after('his_option_id')->comment('headcount or an event code');
            $t->char('hrm_sex', 1)->nullable()->after('hrm_measure');
            $t->foreignId('hrm_profession_id')->nullable()->after('hrm_sex')->constrained('report_hr_professions')->nullOnDelete();
            $t->string('hrm_employment', 24)->nullable()->after('hrm_profession_id');
            $t->string('hrm_workplace', 16)->nullable()->after('hrm_employment');
        });

        // the categories of the PHDReport HR indicators, in their order
        $now = now();
        $names = [
            'professor_master' => 'សាស្រ្តាចារ្យមហាបរិញ្ញា', 'professor_md' => 'សាស្ត្រាចារ្យ វេជ្ជបណ្ឌិត', 'assistant_professor' => 'សាស្ត្រាចារ្យជំនួយ',
            'md_specialist' => 'វេជ្ជបណ្ឌិតឯកទេស', 'md' => 'វេជ្ជបណ្ឌិត', 'medical_assistant' => 'គ្រូពេទ្យមធ្យម',
            'pharm_doctor' => 'ឱសថបណ្ឌិត', 'pharmacist' => 'ឱសថការី', 'pharm_secondary' => 'ឱសថបមធ្យម', 'pharm_primary' => 'ឱសថបឋម',
            'professor_dentist' => 'សាស្រ្តាចារ្យទន្តបណ្ឌិត', 'dentist_specialist' => 'ទន្តបណ្ឌិតឯកទេស', 'dentist' => 'ទន្តបណ្ឌិត',
            'dental_assistant' => 'ទន្ដពេទ្យ', 'dental_nurse' => 'ទន្តគិលានុបដ្ឋាក',
            'nurse_bachelor' => 'បរិញ្ញាបត្រគិលានុបដ្ឋាក', 'nurse_secondary' => 'គិលានុបដ្ឋាកមធ្យម', 'nurse_primary' => 'គិលានុបដ្ឋាកបឋម',
            'midwife_bachelor' => 'បរិញ្ញាបត្រឆ្មប', 'midwife_secondary' => 'ឆ្មបមធ្យម', 'midwife_primary' => 'ឆ្មបបឋម',
            'lab_secondary' => 'ពិសោធន៍មធ្យម', 'lab_primary' => 'ពិសោធន៍បឋម', 'physio' => 'ព្យាបាលដោយចលនា', 'radiology' => 'វិទ្យុសាស្រ្ដ',
            'it' => 'ព័ត៌មានវិទ្យា', 'accounting' => 'គណនេយ្យ', 'engineer' => 'វិស្វករ', 'driver' => 'បើកបរ', 'other' => 'ផ្សេងៗ',
        ];
        $i = 0;
        DB::table('report_hr_professions')->insert(array_map(function ($code) use ($names, &$i, $now) {
            return ['code' => $code, 'name' => $names[$code], 'sort_order' => ++$i, 'created_at' => $now, 'updated_at' => $now];
        }, array_keys($names)));
    }

    public function down(): void
    {
        Schema::table('report_indicators', function (Blueprint $t) {
            $t->dropConstrainedForeignId('hrm_profession_id');
            $t->dropColumn(['hrm_measure', 'hrm_sex', 'hrm_employment', 'hrm_workplace']);
        });
        Schema::dropIfExists('report_hr_events');
        Schema::dropIfExists('report_hr_snapshots');
        Schema::dropIfExists('report_hr_skill_maps');
        Schema::dropIfExists('report_hr_professions');
    }
};
