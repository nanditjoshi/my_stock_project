<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CsvImportController;
use App\Http\Controllers\StockListController;
use App\Http\Controllers\ReportController;

Route::get('/', [StockListController::class, 'dashboard'])->name('dashboard');
Route::post('/dashboard/sync-stock-snapshots', [StockListController::class, 'syncStockSnapshots'])->name('dashboard.stock-snapshots.sync');
Route::get('/dashboard/download-sm-data', [StockListController::class, 'downloadSmData'])->name('dashboard.download-sm-data');

Route::resource('users', UserController::class);
Route::get('/csv-import', [CsvImportController::class, 'index'])->name('csv.import.index');
Route::post('/csv-import', [CsvImportController::class, 'store'])->name('csv.import.store');
Route::get('/stock-list', [StockListController::class, 'index'])->name('stock.list.index');
Route::post('/stock-list/watched', [StockListController::class, 'toggleWatched'])->name('stock.list.watched.toggle');
Route::post('/stock-list/watch-list', [StockListController::class, 'storeWatchList'])->name('stock.list.watch-list.store');
Route::get('/stock-list/sync', [StockListController::class, 'syncStock'])->name('stock.list.sync');
Route::get('/watch-list', [StockListController::class, 'watchList'])->name('watch.list.index');
Route::get('/report', [ReportController::class, 'index'])->name('report.index');
Route::post('/report/generate', [ReportController::class, 'generate'])->name('report.generate');
