<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TranslateDbEnglishToKhmer extends Command
{
    protected $signature = 'db:translate-en-km {--dry-run : Show what would be updated without writing changes}';

    protected $description = 'Translate common HR master-data values in DB from English to Khmer (exact-match only)';

    /**
     * @return int
     */
    public function handle()
    {
        $dryRun = (bool) $this->option('dry-run');

        $targets = [
            ['table' => 'genders', 'column' => 'gender_name', 'map' => [
                'Male' => 'ប្រុស',
                'Female' => 'ស្រី',
                'Transgender' => 'អន្តរភេទ',
            ]],
            ['table' => 'marital_statuses', 'column' => 'name', 'map' => [
                'Single' => 'នៅលីវ',
                'Married' => 'រៀបការ',
                'Divorced' => 'លែងលះ',
                'Widowed' => 'មេម៉ាយ/ពោះម៉ាយ',
                'Other' => 'ផ្សេងៗ',
            ]],
            ['table' => 'duty_types', 'column' => 'type_name', 'map' => [
                'Full Time' => 'ពេញម៉ោង',
                'Part Time' => 'ក្រៅម៉ោង',
                'Contractual' => 'កិច្ចសន្យា',
                'Other' => 'ផ្សេងៗ',
            ]],
            ['table' => 'employee_types', 'column' => 'name', 'map' => [
                'Intern' => 'បុគ្គលិកហាត់ការ',
                'Contractual' => 'បុគ្គលិកកិច្ចសន្យា',
                'Full Time' => 'បុគ្គលិកពេញម៉ោង',
                'Remote' => 'បុគ្គលិកពីចម្ងាយ',
            ]],
            ['table' => 'pay_frequencies', 'column' => 'frequency_name', 'map' => [
                'Weekly' => 'ប្រចាំសប្ដាហ៍',
                'Biweekly' => 'រៀងរាល់ពីរសប្ដាហ៍',
                'Monthly' => 'ប្រចាំខែ',
                'Yearly' => 'ប្រចាំឆ្នាំ',
            ]],
            ['table' => 'user_types', 'column' => 'user_type_title', 'map' => [
                'Admin' => 'អ្នកគ្រប់គ្រងប្រព័ន្ធ',
                'Employee' => 'បុគ្គលិក',
            ]],
            ['table' => 'leave_types', 'column' => 'leave_type', 'map' => [
                'Casual' => 'ឈប់សម្រាកខ្លី',
                'Sick' => 'ឈប់សម្រាកព្យាបាលជំងឺ',
                'Annual' => 'ឈប់សម្រាកប្រចាំឆ្នាំ',
                'ឈបសម្រាករយៈពេលខ្លី' => 'ឈប់សម្រាករយៈពេលខ្លី',
            ]],
            ['table' => 'certificate_types', 'column' => 'name', 'map' => [
                'SSC' => 'វិញ្ញាបនបត្រមធ្យមសិក្សាទុតិយភូមិ',
                'Single Domain SSL Certificates' => 'វិញ្ញាបនបត្រ SSL ដែនតែមួយ',
            ]],
            ['table' => 'skill_types', 'column' => 'name', 'map' => [
                'Non-Technical' => 'ជំនាញមិនមែនបច្ចេកទេស',
                'Non Technical' => 'ជំនាញមិនមែនបច្ចេកទេស',
                'PHP' => 'PHP',
                'Programing' => 'កម្មវិធីកុំព្យូទ័រ',
            ]],
        ];

        $report = [];

        DB::beginTransaction();
        try {
            foreach ($targets as $target) {
                $table = $target['table'];
                $column = $target['column'];
                $map = $target['map'];

                $count = 0;
                foreach ($map as $from => $to) {
                    $query = DB::table($table)->where($column, $from);
                    if ($dryRun) {
                        $affected = $query->count();
                    } else {
                        $affected = $query->update([$column => $to]);
                    }
                    $count += (int) $affected;
                }

                $report[] = [$table . '.' . $column, $count];
            }

            $langLegacyAffected = 0;
            if (DB::getSchemaBuilder()->hasTable('lang')) {
                $langLegacyQuery = DB::table('lang')->where('value', 'km');
                $langLegacyAffected = $dryRun
                    ? $langLegacyQuery->count()
                    : $langLegacyQuery->update(['name' => 'Khmer']);
            }
            $report[] = ['lang.name(value=km)', (int) $langLegacyAffected];

            $languageAffected = 0;
            $languageInsert = 0;
            if (DB::getSchemaBuilder()->hasTable('languages')) {
                $languagesQuery = DB::table('languages')->where('value', 'km');
                $existsKm = $languagesQuery->exists();

                if ($existsKm) {
                    $languageAffected = $dryRun
                        ? $languagesQuery->count()
                        : $languagesQuery->update(['langname' => 'Khmer', 'updated_at' => now()]);
                } elseif (!$dryRun) {
                    DB::table('languages')->insert([
                        'langname' => 'Khmer',
                        'value' => 'km',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $languageInsert = 1;
                }
            }

            $report[] = ['languages.langname(value=km)', (int) $languageAffected];
            $report[] = ['languages.insert(km)', (int) $languageInsert];

            if ($dryRun) {
                DB::rollBack();
                $this->warn('Dry-run complete. No data was changed.');
            } else {
                DB::commit();
                $this->info('Translation update completed successfully.');
            }

            $this->table(['Target', 'Affected Rows'], $report);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            DB::rollBack();
            $this->error('Translation failed: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }
}
