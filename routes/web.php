<?php

use App\Http\Controllers\SqlDumpController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', [SqlDumpController::class, 'create'])->name('sql-dump.create');
Route::post('/prepare', [SqlDumpController::class, 'store'])->name('sql-dump.store');
Route::post('/import', [SqlDumpController::class, 'import'])->name('sql-dump.import');
