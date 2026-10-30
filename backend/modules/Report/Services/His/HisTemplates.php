<?php

namespace Modules\Report\Services\His;

/**
 * Layout of the national HIS monthly workbooks (as exported for Stung Treng):
 *   HC1 — health-centre activity, aggregated per operational district
 *   HO2 — referral hospital activity
 * Each block is one table: header rows (read for age / sex / option / measure), data rows, row label
 * columns and, for morbidity tables, the ICD-10 column. Synced into report_import_* by
 * `php artisan report:sync-his-templates`.
 */
class HisTemplates
{
    public static function all(): array
    {
        return [
            'HC1' => [
                'name' => 'HC1 — របាយការណ៍សកម្មភាពមណ្ឌលសុខភាព (ស្រុកប្រតិបត្តិ)',
                'facility_type' => 'operational_district',
                'blocks' => self::hc1(),
            ],
            'HO2' => [
                'name' => 'HO2 — របាយការណ៍សកម្មភាពមន្ទីរពេទ្យ',
                'facility_type' => 'provincial_hospital',
                'blocks' => self::ho2(),
            ],
        ];
    }

    private static function hc1(): array
    {
        return [
            self::block('OPD_ZONE', 'ពិគ្រោះជំងឺក្រៅតាមតំបន់', 'Page 1', [5], 7, 8, ['A'], null, 'items', 'ពិគ្រោះជំងឺក្រៅ'),
            self::block('OPD', 'បញ្ហាសុខភាពករណីថ្មី (OPD)', 'Page 1', [11, 12], 13, 241, ['B'], 'C', 'diagnosis', 'ករណីថ្មីពិគ្រោះជំងឺក្រៅ', 'D'),
            self::block('IPD', 'ជំងឺ និងមរណភាពសម្រាកពេទ្យ (IPD)', 'Page2', [4, 5, 6], 7, 234, ['B'], 'C', 'diagnosis', 'អ្នកជំងឺសម្រាកពេទ្យ', 'D'),
            // Page3 — general activity, health financing
            self::block('P3_BED', 'មណ្ឌលសុខភាពមានគ្រែ', 'Page3', [4], 5, 6, ['A'], null, 'items', null, 'C'),
            self::block('P3_ACT', 'សកម្មភាពក្នុងមណ្ឌលសុខភាព', 'Page3', [10, 11], 12, 25, ['A'], null, 'items', null, 'G'),
            self::block('P3_FIN', 'សកម្មភាពហិរញ្ញប្បទានសុខាភិបាល', 'Page3', [28, 29], 30, 35, ['A'], null, 'items', null, 'F'),
            // Page4 — laboratory, malaria
            self::block('P4_LAB', 'មន្ទីរពិសោធន៍', 'Page4', [4, 5, 6], 7, 7, [], null, 'wide', null, 'A'),
            self::block('P4_SLIDE', 'គ្រុនចាញ់ — កញ្ចក់ឈាម', 'Page4', [28, 29], 30, 36, ['A'], null, 'items', null, 'C'),
            self::block('P4_RDT', 'គ្រុនចាញ់ — ឌីបស្ទីក', 'Page4', [39, 40], 41, 46, ['A'], null, 'items', null, 'C'),
            self::block('P4_VMW', 'គ្រុនចាញ់ — ឌីបស្ទីក (អ្នកស្ម័គ្រចិត្តភូមិ)', 'Page4', [52, 53], 54, 59, ['A'], null, 'items', null, 'C'),
            // Page5 — antenatal care, delivery, newborns
            self::block('P5_ANC', 'ការពិនិត្យផ្ទៃពោះមុនសម្រាល', 'Page5', [4, 5], 6, 8, ['A'], null, 'items', null, 'B'),
            self::block('P5_ANC_TEST', 'ការពិនិត្យផ្ទៃពោះ — តេស្ត', 'Page5', [10, 11, 12], 13, 13, ['A'], null, 'items', null, 'B'),
            self::block('P5_DEL', 'ការសម្រាលកូននៅមណ្ឌលសុខភាព', 'Page5', [15, 16], 17, 29, ['A', 'C'], null, 'items', null, 'K'),
            self::block('P5_DEL_TEST', 'ការសម្រាលកូន — តេស្ត', 'Page5', [31, 32], 33, 33, ['A'], null, 'items', null, 'B'),
            self::block('P5_DEL_ARV', 'ការសម្រាលកូន — ARV', 'Page5', [36], 37, 38, ['A'], null, 'items', null, 'J'),
            self::block('P5_NB_FAC', 'ទារកកើតថ្មី — នៅមូលដ្ឋានសុខាភិបាល', 'Page5', [42, 43, 44], 45, 45, [], null, 'wide', null, 'C'),
            self::block('P5_NB_FAC_DEATH', 'ទារកកើតថ្មី — ស្លាប់ (មូលដ្ឋានសុខាភិបាល)', 'Page5', [47], 48, 48, [], null, 'wide', null, 'C'),
            self::block('P5_NB_COM', 'ទារកកើតថ្មី — នៅសហគមន៍', 'Page5', [52, 53, 54], 55, 55, [], null, 'wide', null, 'C'),
            self::block('P5_NB_COM_DEATH', 'ទារកកើតថ្មី — ស្លាប់ (សហគមន៍)', 'Page5', [57], 58, 58, [], null, 'wide', null, 'C'),
            // Page6 — postnatal care, iron / folic acid, abortion, maternal deaths, screening
            // row 3 holds the visit numbers (1, 2, 3, 4) of the PNC header
            self::block('P6_PNC', 'ការថែទាំក្រោយសម្រាល (មូលដ្ឋានសុខាភិបាល)', 'Page6', [2, 3, 4], 5, 5, [], null, 'wide', null, 'A'),
            self::block('P6_PNC_PROB', 'បញ្ហាក្រោយសម្រាល (មូលដ្ឋានសុខាភិបាល)', 'Page6', [7, 8, 9], 10, 10, [], null, 'wide', null, 'A'),
            self::block('P6_PNC_COM', 'ការថែទាំក្រោយសម្រាល (សហគមន៍)', 'Page6', [13, 14, 15], 16, 16, [], null, 'wide', null, 'A'),
            self::block('P6_PNC_COM_PROB', 'បញ្ហាក្រោយសម្រាល (សហគមន៍)', 'Page6', [18, 19, 20], 21, 21, [], null, 'wide', null, 'A'),
            self::block('P6_IRON', 'ការទទួលជាតិដែក/អាស៊ីដហ្វូលីក', 'Page6', [24, 25], 26, 26, [], null, 'wide', null, 'A'),
            self::block('P6_ABORT', 'ការរំលូត និងរលូត', 'Page6', [34, 35], 36, 39, ['A', 'B'], null, 'items', null, 'C'),
            self::block('P6_MDEATH', 'ម្ដាយស្លាប់', 'Page6', [42], 43, 43, [], null, 'wide', null, 'A'),
            self::block('P6_BSE', 'ការអប់រំស្ទាបដោះដោយខ្លួនឯង', 'Page6', [47, 48], 49, 49, ['A'], null, 'items', null, 'C'),
            self::block('P6_CERV', 'ការស្រាវជ្រាវរកមហារីកមាត់ស្បូន', 'Page6', [52, 53], 54, 55, ['A'], null, 'items', null, 'C'),
            // Page7 — family planning
            self::block('P7_FP_FAC', 'ពន្យារកំណើត — មូលដ្ឋានសុខាភិបាល', 'Page7', [4, 5], 6, 15, ['A'], null, 'items', null, 'B'),
            self::block('P7_FP_COM', 'ពន្យារកំណើត — សហគមន៍', 'Page7', [18, 19], 20, 25, ['A'], null, 'items', null, 'B'),
            self::block('P7_FP_HIV', 'ពន្យារកំណើត — អតិថិជនមានផ្ទុកមេរោគអេដស៍', 'Page7', [28, 29], 30, 35, ['A'], null, 'items', null, 'B'),
            // Page8 — immunisation, growth monitoring, HPV / tetanus
            self::block('P8_EPI_IN', 'ថ្នាំបង្ការ — កុមារក្នុងចំណុច', 'Page8', [4, 5, 6], 7, 11, ['A'], null, 'items', null, 'B'),
            self::block('P8_EPI_OUT', 'ថ្នាំបង្ការ — កុមារក្រៅចំណុច', 'Page8', [13], 14, 18, ['A'], null, 'items', null, 'B'),
            self::block('P8_GROWTH', 'ការតាមដានការលូតលាស់កុមារ', 'Page8', [21, 22], 23, 23, ['A'], null, 'items', null, 'D'),
            self::block('P8_HPV_TT', 'HPV និងតេតាណុស', 'Page8', [27], 28, 31, ['A'], null, 'items', null, 'B'),
            self::block('P8_HPV_TT2', 'HPV និងតេតាណុស (ត)', 'Page8', [34, 35], 36, 38, ['A'], null, 'items', null, 'B'),
            // Page9 — deworming, micronutrients
            self::block('P9_DEWORM', 'ការបញ្ចុះព្រូន (មេបង់ដាហ្សុល)', 'Page9', [3, 4], 5, 7, ['A'], null, 'items', null, 'I'),
            self::block('P9_VITA', 'ថ្នាំជីវជាតិ អា', 'Page9', [11, 12, 13], 14, 14, [], null, 'wide', null, 'A'),
            // Page10 — HIV / STI, TB, leprosy, oral health
            self::block('P10_HIV', 'អេដស៍ — តេស្តរហ័សនៅមណ្ឌលសុខភាព', 'Page10', [4, 5, 6], 7, 31, ['C'], null, 'items', null, 'T'),
            self::block('P10_STI', 'កាមរោគ — ការព្យាបាលតាមចង្កោមរោគសញ្ញា', 'Page10', [35, 36, 37], 38, 50, ['C'], null, 'items', null, 'T'),
            self::block('P10_TB', 'របេង — លក្ខណៈជំងឺ និងប្រភេទ', 'Page10', [56, 57, 58], 59, 61, ['C'], null, 'items', null, 'K'),
            self::block('P10_TB_DOTS', 'របេង — ប្រភេទដូតស៍', 'Page10', [63, 64, 65], 66, 66, [], null, 'wide', null, 'C'),
            self::block('P10_LEPROSY', 'ហង់សិន', 'Page10', [69, 70], 71, 74, ['C'], null, 'items', null, 'I'),
            self::block('P10_ORAL', 'មាត់-ធ្មេញ', 'Page10', [79, 80], 81, 83, ['C'], null, 'items', null, 'I'),
        ];
    }

    private static function ho2(): array
    {
        return [
            self::block('OPD_VOLUME', 'បរិមាណសកម្មភាពពិគ្រោះជំងឺក្រៅ', 'Page1', [5], 6, 10, ['B'], null, 'items', 'ពិគ្រោះជំងឺក្រៅ'),
            self::block('OPD', 'បញ្ហាសុខភាពករណីថ្មី (OPD)', 'Page1', [12, 13], 14, 335, ['B'], 'C', 'diagnosis', 'ករណីថ្មីពិគ្រោះជំងឺក្រៅ', 'D'),
            self::block('IPD', 'ជំងឺ និងមរណភាពសម្រាកពេទ្យ (IPD)', 'Page2', [3, 4, 5], 6, 331, ['B'], 'C', 'diagnosis', 'អ្នកជំងឺសម្រាកពេទ្យ', 'D'),
            // Page3 — activity by ward, surgery, by age, financing
            self::block('P3_WARD', 'សកម្មភាពទូទៅតាមផ្នែក', 'Page3', [3], 4, 21, ['A'], null, 'items', null, 'B'),
            self::block('P3_SURG', 'សកម្មភាពវះកាត់', 'Page3', [26], 27, 54, ['A'], null, 'items', null, 'B'),
            self::block('P3_AGE', 'សកម្មភាពក្នុងមន្ទីរពេទ្យតាមអាយុ', 'Page3', [57, 58], 59, 70, ['A'], null, 'items', null, 'B'),
            self::block('P3_FIN', 'សកម្មភាពហិរញ្ញប្បទានសុខាភិបាល', 'Page3', [72, 73], 74, 79, ['A'], null, 'items', null, 'B'),
            // Page4 — laboratory, malaria
            self::block('P4_LAB', 'មន្ទីរពិសោធន៍', 'Page4', [4, 5], 6, 6, [], null, 'wide', null, 'A'),
            self::block('P4_SLIDE', 'គ្រុនចាញ់ — កញ្ចក់ឈាម', 'Page4', [32, 33], 34, 40, ['A'], null, 'items', null, 'C'),
            self::block('P4_RDT', 'គ្រុនចាញ់ — ឌីបស្ទីក', 'Page4', [43, 44], 45, 50, ['A'], null, 'items', null, 'C'),
            // Page5 — antenatal care, delivery, newborns
            self::block('P5_ANC', 'ការពិនិត្យផ្ទៃពោះមុនសម្រាល', 'Page5', [4, 5], 6, 6, ['A'], null, 'items', null, 'B'),
            self::block('P5_ANC_TEST', 'ការពិនិត្យផ្ទៃពោះ — តេស្ត', 'Page5', [8, 9, 10], 11, 11, ['A'], null, 'items', null, 'B'),
            self::block('P5_DEL', 'ការសម្រាលកូននៅមន្ទីរពេទ្យ', 'Page5', [13, 14], 15, 28, ['A', 'C'], null, 'items', null, 'K'),
            self::block('P5_DEL_TEST', 'ការសម្រាលកូន — តេស្ត', 'Page5', [30, 31], 32, 32, ['A'], null, 'items', null, 'B'),
            self::block('P5_NB_FAC', 'ទារកកើតថ្មី — នៅមូលដ្ឋានសុខាភិបាល', 'Page5', [37, 38, 39], 40, 40, [], null, 'wide', null, 'C'),
            self::block('P5_NB_FAC_DEATH', 'ទារកកើតថ្មី — ស្លាប់ (មូលដ្ឋានសុខាភិបាល)', 'Page5', [42], 43, 43, [], null, 'wide', null, 'C'),
            // Page6 — postnatal care, iron / folic acid, abortion, maternal deaths, screening
            self::block('P6_PNC', 'ការថែទាំក្រោយសម្រាល (មូលដ្ឋានសុខាភិបាល)', 'Page6', [2, 3, 4], 5, 5, [], null, 'wide', null, 'A'),
            self::block('P6_PNC_PROB', 'បញ្ហាក្រោយសម្រាល (មូលដ្ឋានសុខាភិបាល)', 'Page6', [7, 8, 9], 10, 10, [], null, 'wide', null, 'A'),
            self::block('P6_IRON', 'ការទទួលជាតិដែក/អាស៊ីដហ្វូលីក', 'Page6', [13, 14], 15, 15, [], null, 'wide', null, 'A'),
            self::block('P6_ABORT', 'ការរំលូត និងរលូត', 'Page6', [22, 23], 24, 27, ['A', 'B', 'C'], null, 'items', null, 'D'),
            self::block('P6_MDEATH', 'ម្ដាយស្លាប់', 'Page6', [29], 30, 30, [], null, 'wide', null, 'A'),
            self::block('P6_BSE', 'ការអប់រំស្ទាបដោះដោយខ្លួនឯង', 'Page6', [34, 35], 36, 36, ['A'], null, 'items', null, 'B'),
            self::block('P6_CERV', 'ការស្រាវជ្រាវរកមហារីកមាត់ស្បូន', 'Page6', [39, 40], 41, 42, ['A'], null, 'items', null, 'D'),
            // Page7 — family planning
            self::block('P7_FP_FAC', 'ពន្យារកំណើត — មូលដ្ឋានសុខាភិបាល', 'Page7', [4, 5], 6, 11, ['A'], null, 'items', null, 'B'),
            self::block('P7_FP_HIV', 'ពន្យារកំណើត — អតិថិជនមានផ្ទុកមេរោគអេដស៍', 'Page7', [14, 15], 16, 21, ['A'], null, 'items', null, 'B'),
            self::block('P7_FP_PP', 'ពន្យារកំណើត — ក្រោយសម្រាល ក្រោយរំលូត', 'Page7', [23, 24], 25, 30, ['A'], null, 'items', null, 'B'),
            // Page8 — immunisation, growth monitoring, HPV / tetanus
            self::block('P8_EPI_IN', 'ថ្នាំបង្ការ — កុមារក្នុងចំណុច', 'Page8', [4, 5, 6], 7, 7, ['A'], null, 'items', null, 'B'),
            self::block('P8_EPI_OUT', 'ថ្នាំបង្ការ — កុមារក្រៅចំណុច', 'Page8', [10], 11, 11, ['A'], null, 'items', null, 'B'),
            self::block('P8_GROWTH', 'ការតាមដានការលូតលាស់កុមារ', 'Page8', [15], 16, 16, ['A'], null, 'items', null, 'F'),
            self::block('P8_HPV_TT', 'HPV និងតេតាណុស', 'Page8', [19, 20], 21, 21, ['A'], null, 'items', null, 'B'),
            // Page9 — deworming, micronutrients
            self::block('P9_DEWORM', 'ការបញ្ចុះព្រូន (មេបង់ដាហ្សុល)', 'Page9', [3, 4], 5, 5, ['A'], null, 'items', null, 'B'),
            self::block('P9_VITA', 'ថ្នាំជីវជាតិ អា', 'Page9', [10, 11, 12], 13, 13, [], null, 'wide', null, 'A'),
            // Page10 — HIV / STI, TB, leprosy, oral health
            self::block('P10_HIV_TEST', 'អេដស៍ — ការផ្តល់ប្រឹក្សា និងតេស្ត', 'Page10', [35, 36], 37, 58, ['B'], null, 'items', null, 'S'),
            self::block('P10_STI_CLIENT', 'កាមរោគ — ប្រភេទអតិថិជនមកពិនិត្យ', 'Page10', [62], 63, 78, ['B'], null, 'items', null, 'AG'),
            self::block('P10_STI', 'កាមរោគ — ការព្យាបាលតាមចង្កោមរោគសញ្ញា', 'Page10', [81], 82, 135, ['B'], null, 'items', null, 'S'),
            self::block('P10_ART', 'អេដស៍ — ការព្យាបាល', 'Page10', [139, 140], 141, 155, ['B'], null, 'items', null, 'AM'),
            self::block('P10_ART2', 'អេដស៍ — ការព្យាបាល (ត)', 'Page10', [158, 159], 160, 168, ['B'], null, 'items', null, 'AD'),
            self::block('P10_TB', 'របេង — លក្ខណៈជំងឺ និងប្រភេទ', 'Page10', [175, 176, 177], 178, 180, ['B'], null, 'items', null, 'J'),
            self::block('P10_TB_DOTS', 'របេង — ប្រភេទដូតស៍', 'Page10', [183, 184], 185, 185, [], null, 'wide', null, 'B'),
            self::block('P10_LEPROSY', 'ហង់សិន', 'Page10', [188, 189], 190, 194, ['B'], null, 'items', null, 'H'),
            self::block('P10_ORAL', 'មាត់-ធ្មេញ', 'Page10', [200, 201], 202, 203, ['B'], null, 'items', null, 'H'),
            // Page11 — blood bank, imaging, physiotherapy
            self::block('P11_BLOOD', 'ការផ្តល់ឈាម — ប្រមូល និងពិនិត្យ', 'Page11', [4, 5, 6], 7, 9, ['A'], null, 'items', null, 'C'),
            self::block('P11_BLOOD_USE', 'ការផ្តល់ឈាម — ប្រើប្រាស់តាមផ្នែក', 'Page11', [13], 14, 14, ['A'], null, 'items', null, 'E'),
            self::block('P11_IMAGING', 'រូបភាពវេជ្ជសាស្ត្រ', 'Page11', [17], 18, 18, ['A'], null, 'items', null, 'C'),
            self::block('P11_PHYSIO', 'ការព្យាបាលដោយចលនា', 'Page11', [22, 23], 24, 25, ['A'], null, 'items', null, 'D'),
        ];
    }

    private static function block(string $code, string $title, string $sheet, array $headerRows, int $rowStart, int $rowEnd,
        array $labelCols, ?string $codeCol, string $kind, ?string $measure = null, ?string $firstCol = null): array
    {
        return [
            'code' => $code, 'title' => $title, 'sheet' => $sheet, 'header_rows' => $headerRows,
            'row_start' => $rowStart, 'row_end' => $rowEnd, 'label_cols' => $labelCols, 'code_col' => $codeCol,
            'kind' => $kind, 'measure' => $measure, 'first_col' => $firstCol,
        ];
    }
}
