<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DriveController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('drive/{pic_name}')
    // ->middleware('validate.pic')
    ->group(function () {
        Route::get('/', [DriveController::class, 'index']);
        Route::get('/folder/{any?}', [DriveController::class, 'browse'])
            ->where('any', '.*')
            ->name('drive.browse');
        Route::post('/edit-excel', [DriveController::class, 'editExcel'])->name('drive.edit_excel');
        Route::post('/drive/favorite/toggle', [DriveController::class, 'toggleFavorite'])->name('drive.toggle_favorite');
        Route::post('/upload', [DriveController::class, 'uploadFile'])->name('drive.upload_file');

        Route::get('/excel/{file}', [DriveController::class, 'viewExcel'])
            ->where('file', '.*')
            ->name('drive.excel_view');
        Route::post('/excel/update', [DriveController::class, 'updateExcel'])->name('drive.update_excel');
    });