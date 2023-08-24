<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

$route_prefix = config('module.Approval.route_prefix', 'manager');
$route_url_prefix = $route_prefix ? $route_prefix . '/' : '';
$route_name_prefix = $route_prefix ? $route_prefix . '.' : '';

Route::prefix("{$route_url_prefix}approval")->name("api.{$route_name_prefix}approval.")->group(function () {
    Route::post('/process', "ApprovalController@processEdit")->name('process.edit');
    Route::get('/process', 'ApprovalController@processItems')->name('process.items');
    Route::get('/process/{id}', 'ApprovalController@processItem')->where('id', '[0-9]+')->name('process.item');
    Route::post('/process/delete', 'ApprovalController@processDelete')->name('process.delete');
    Route::post('/approve', 'ApprovalController@approve')->name('approve');
});
