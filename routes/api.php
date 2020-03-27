<?php

use Illuminate\Http\Request;

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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/store', ['uses' => 'API\StorageController@upload_object']);
Route::get('/store', ['uses' => 'API\StorageController@getStoredUrl']);
Route::get('/store/{id}', ['uses' => 'API\StorageController@getStoredUrlById']);

