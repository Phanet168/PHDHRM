<?php
error_reporting(E_ALL);
ini_set('display_errors','1');
require 'c:/xampp/htdocs/PHDHRM/vendor/autoload.php';
$app = require 'c:/xampp/htdocs/PHDHRM/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$employees = \Modules\HumanResource\Entities\Employee::query()->whereNotNull('date_of_birth')->take(5)->get();
foreach ($employees as $e) {
  $e->setAttribute('retirement_date', \Carbon\Carbon::parse($e->date_of_birth)->addYears(60)->toDateString());
}

$export = new \Modules\HumanResource\Exports\RetirementListExport($employees, 2026, 2026, [
  'admin_text' => 'រដ្ឋបាលខេត្តស្ទឹងត្រែង',
  'unit_text' => 'មន្ទីរសុខាភិបាលនៃរដ្ឋបាលខេត្ត',
  'location_text' => 'ស្ទឹងត្រែង',
  'approval_text' => 'ប្រធានមន្ទីរសុខាភិបាល',
  'hr_manager_text' => 'មន្ត្រីគ្រប់គ្រងបុគ្គលិក',
]);
var_dump(\Maatwebsite\Excel\Facades\Excel::store($export, 'tmp/ret_verify.xlsx', 'local'));
$sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('c:/xampp/htdocs/PHDHRM/storage/app/tmp/ret_verify.xlsx')->getActiveSheet();
echo 'A7='.$sheet->getCell('A7')->getFormattedValue().PHP_EOL;
echo 'A9='.$sheet->getCell('A9')->getFormattedValue().PHP_EOL;
foreach($sheet->getDrawingCollection() as $d){
  echo 'Drawing '.$d->getCoordinates().' w='.$d->getWidth().' h='.$d->getHeight().PHP_EOL;
}
