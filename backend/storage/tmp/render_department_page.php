<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ViewErrorBag;

view()->share('errors', new ViewErrorBag());
Auth::loginUsingId(1);

$controller = app(Modules\HumanResource\Http\Controllers\DepartmentController::class);
$request = Request::create('/hr/departments', 'GET');
$response = $controller->index($request, app(Modules\HumanResource\Support\OrgUnitRuleService::class));
$html = $response->render();
file_put_contents(__DIR__ . '/department_rendered.html', $html);

echo 'HTML_SAVED';
