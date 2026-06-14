<?php

namespace Modules\Planning\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Planning\Entities\ActivityCluster;
use Modules\Planning\Entities\ChartOfAccount;
use Modules\Planning\Entities\FundingSource;
use Modules\Planning\Entities\Indicator;
use Modules\Planning\Entities\Program;
use Modules\Planning\Entities\SubProgram;

class PlanningMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $programs = [
            [
                'code' => 'PRG-001',
                'name' => 'Health Service Delivery',
                'sub_programs' => [
                    [
                        'code' => 'SPR-001',
                        'name' => 'Primary Health Care',
                        'clusters' => [
                            ['code' => 'ACL-001', 'name' => 'Community Outreach'],
                            ['code' => 'ACL-002', 'name' => 'Maternal and Child Health'],
                        ],
                    ],
                    [
                        'code' => 'SPR-002',
                        'name' => 'Referral Care',
                        'clusters' => [
                            ['code' => 'ACL-003', 'name' => 'Hospital Support'],
                        ],
                    ],
                ],
            ],
            [
                'code' => 'PRG-002',
                'name' => 'Health System Strengthening',
                'sub_programs' => [
                    [
                        'code' => 'SPR-003',
                        'name' => 'Administration and Governance',
                        'clusters' => [
                            ['code' => 'ACL-004', 'name' => 'Management and Leadership'],
                            ['code' => 'ACL-005', 'name' => 'Monitoring and Evaluation'],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($programs as $programData) {
            $program = Program::updateOrCreate(
                ['code' => $programData['code']],
                ['name' => $programData['name'], 'is_active' => true]
            );

            foreach ($programData['sub_programs'] as $subProgramData) {
                $subProgram = SubProgram::updateOrCreate(
                    ['code' => $subProgramData['code']],
                    [
                        'program_id' => $program->id,
                        'name' => $subProgramData['name'],
                        'is_active' => true,
                    ]
                );

                foreach ($subProgramData['clusters'] as $clusterData) {
                    ActivityCluster::updateOrCreate(
                        ['code' => $clusterData['code']],
                        [
                            'sub_program_id' => $subProgram->id,
                            'name' => $clusterData['name'],
                            'is_active' => true,
                        ]
                    );
                }
            }
        }

        $accounts = [
            ['code' => '60021', 'chapter_code' => '60', 'chapter_name' => 'Operating Expenditure', 'account_code' => '021', 'account_name' => 'Office Supplies', 'subaccount_code' => '00', 'subaccount_name' => 'Office supplies', 'expense_type' => 'goods', 'name' => 'Office supplies'],
            ['code' => '60022', 'chapter_code' => '60', 'chapter_name' => 'Operating Expenditure', 'account_code' => '022', 'account_name' => 'Books and Documents', 'subaccount_code' => '00', 'subaccount_name' => 'Books and documents', 'expense_type' => 'goods', 'name' => 'Books and documents'],
            ['code' => '60023', 'chapter_code' => '60', 'chapter_name' => 'Operating Expenditure', 'account_code' => '023', 'account_name' => 'Printing', 'subaccount_code' => '00', 'subaccount_name' => 'Printing', 'expense_type' => 'services', 'name' => 'Printing'],
            ['code' => '61103', 'chapter_code' => '61', 'chapter_name' => 'Service Expenditure', 'account_code' => '103', 'account_name' => 'Meetings and Workshops', 'subaccount_code' => '00', 'subaccount_name' => 'Meetings and workshops', 'expense_type' => 'services', 'name' => 'Meetings and workshops'],
            ['code' => '61122', 'chapter_code' => '61', 'chapter_name' => 'Service Expenditure', 'account_code' => '122', 'account_name' => 'Mission and Per Diem', 'subaccount_code' => '00', 'subaccount_name' => 'Mission and per diem', 'expense_type' => 'travel', 'name' => 'Mission and per diem'],
            ['code' => '61123', 'chapter_code' => '61', 'chapter_name' => 'Service Expenditure', 'account_code' => '123', 'account_name' => 'Accommodation', 'subaccount_code' => '00', 'subaccount_name' => 'Accommodation', 'expense_type' => 'travel', 'name' => 'Accommodation'],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::updateOrCreate(['code' => $account['code']], $account + ['is_active' => true]);
        }

        $fundingSources = [
            ['code' => 'FS-GOV', 'name' => 'Government Budget'],
            ['code' => 'FS-DON', 'name' => 'Development Partner'],
            ['code' => 'FS-OWN', 'name' => 'Own Source Revenue'],
        ];

        foreach ($fundingSources as $fundingSource) {
            FundingSource::updateOrCreate(['code' => $fundingSource['code']], $fundingSource + ['is_active' => true]);
        }

        $indicators = [
            ['code' => 'IND-001', 'name' => 'Coverage rate', 'unit_of_measure' => '%', 'value_type' => 'percentage'],
            ['code' => 'IND-002', 'name' => 'Beneficiaries reached', 'unit_of_measure' => 'persons', 'value_type' => 'number'],
            ['code' => 'IND-003', 'name' => 'Budget execution', 'unit_of_measure' => 'USD', 'value_type' => 'currency'],
        ];

        foreach ($indicators as $indicator) {
            Indicator::updateOrCreate(['code' => $indicator['code']], $indicator + ['is_active' => true]);
        }
    }
}
