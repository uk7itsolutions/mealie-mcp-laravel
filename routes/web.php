<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\McpController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/mcp/sse', [McpController::class, 'sse']);
Route::post('/mcp/messages', [McpController::class, 'messages']);
