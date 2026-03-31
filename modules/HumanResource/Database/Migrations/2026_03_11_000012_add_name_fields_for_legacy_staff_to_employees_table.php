<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('last_name_latin')->nullable()->after('last_name');
            $table->string('first_name_latin')->nullable()->after('last_name_latin');
            $table->integer('legacy_pob_code')->nullable()->after('official_id_10');
            $table->integer('legacy_pa_code')->nullable()->after('legacy_pob_code');
            $table->text('legacy_other_info')->nullable()->after('legacy_pa_code');
        });

        DB::table('employees')
            ->select(['id', 'first_name', 'last_name', 'maiden_name', 'last_name_latin', 'first_name_latin'])
            ->orderBy('id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    $updates = [];

                    $khFirst = trim((string) ($row->first_name ?? ''));
                    $khLast = trim((string) ($row->last_name ?? ''));
                    if ($khFirst !== '' && $khLast === '') {
                        [$surname, $given] = $this->splitName($khFirst);
                        if ($surname !== '' && $given !== '') {
                            $updates['last_name'] = $surname;
                            $updates['first_name'] = $given;
                        }
                    }

                    $latinLast = trim((string) ($row->last_name_latin ?? ''));
                    $latinFirst = trim((string) ($row->first_name_latin ?? ''));
                    if ($latinLast === '' || $latinFirst === '') {
                        $latinFull = trim((string) ($row->maiden_name ?? ''));
                        if ($latinFull !== '') {
                            [$surnameEn, $givenEn] = $this->splitName($latinFull);
                            if ($latinLast === '' && $surnameEn !== '') {
                                $updates['last_name_latin'] = $surnameEn;
                            }
                            if ($latinFirst === '' && $givenEn !== '') {
                                $updates['first_name_latin'] = $givenEn;
                            }
                        }
                    }

                    if (!empty($updates)) {
                        DB::table('employees')->where('id', $row->id)->update($updates);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'last_name_latin',
                'first_name_latin',
                'legacy_pob_code',
                'legacy_pa_code',
                'legacy_other_info',
            ]);
        });
    }

    /**
     * Split full name into [surname, given_name] based on first token.
     */
    protected function splitName(string $fullName): array
    {
        $fullName = trim(preg_replace('/\s+/', ' ', $fullName));
        if ($fullName === '') {
            return ['', ''];
        }

        $parts = explode(' ', $fullName);
        if (count($parts) === 1) {
            return [$parts[0], ''];
        }

        $surname = array_shift($parts);
        $given = implode(' ', $parts);

        return [trim($surname), trim($given)];
    }
};
