<?php

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


Route::prefix("manager/approval")->name("page.manager.approval.")->group(function () {
	Route::get('/process', 'ApprovalController@pageApprovalProcess')->name('process');

});
Route::get("manager/todo/approval", 'ApprovalController@pageApprovalTodo')->name("page.manager.todo.approval");
Route::get("manager/todo/approval/{slug}", 'ApprovalController@pageApprovalTodoList')->name("page.manager.todo.approval.list");
