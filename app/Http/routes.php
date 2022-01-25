<?php

/*
|--------------------------------------------------------------------------
| Routes File
|--------------------------------------------------------------------------
|
| Here is where you will register all of the routes in an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('base','Users\UserController@BaseController');

Route::group(['prefix' => 'api/v1','middleware' => ['basicAuth','sysCutoff']],function ($router){
    Route::post('preactivation','Users\UserController@preActUser');
    Route::post('activate','Users\UserController@ActivationUser');
    Route::post('account','Users\UserController@DetailUser');
    Route::post('activity','Users\UserController@ActivityUser');
    Route::post('authorize','Trx\TransactionController@authorizeTrans');
    Route::post('finalize','Trx\TransactionController@finalizeTrans');
    Route::post('refund','Trx\TransactionController@refundTrans');
});

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| This route group applies the "web" middleware group to every route
| it contains. The "web" middleware group is defined in your HTTP
| kernel and includes session state, CSRF protection, and more.
|
*/

/*Route::group([
    /*'middleware' => ['api', 'cors'],
    'namespace' => $this->namespace,*/
    /*'prefix' => 'inq'
], function ($router) {
    //Balance
    Route::post('/balance','Inq\InquiryController@getBalance');

    //Mutasi
    Route::post('/statement','Inq\InquiryController@getStatement');

    //Transfer
    Route::post('/transfer','Tran\TransferController@doTransfer');
});*/

/*Route::group(['prefix' => 'auth'], function () {
    //auth
    Route::get('/getToken','Auth\AuthController@auth');
    //check expire
    Route::get('/cToken','Auth\AuthController@checkExpireOf');
    //signature
    Route::get('/signature','Auth\AuthController@signature');
});*/
