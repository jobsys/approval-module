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


Route::prefix("manager/approval")->name("api.manager.approval.")->group(function () {
	Route::post('/process', "ApprovalController@processEdit")->name('process.edit');
	Route::get('/process', 'ApprovalController@processItems')->name('process.items');
	Route::get('/process/{id}', 'ApprovalController@processItem')->where('id', '[0-9]+')->name('process.item');
	Route::post('/process/delete', 'ApprovalController@processDelete')->name('process.delete');
	Route::get('/task', 'ApprovalController@taskItems')->name('task.items');
	Route::post('/binding', 'ApprovalController@bindingEdit')->name('binding.edit');
	Route::post('/approve', 'ApprovalController@approve')->name('approve');
	Route::post('/approve/batch', 'ApprovalController@batchApprove')->name('approve.batch');
});
