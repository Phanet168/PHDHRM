<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/humanresource', function (Request $request) {
    return $request->user();
});
