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

/*
Route::get('/', function () {
    return view('welcome');
});
*/

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

Route::group(['middleware' => ['web']], function () {
	Route::get('/', 'LandingController@hours'); //FIXME need to add an info landing page at some point
	Route::get('/hours', 'LandingController@hours'); //FIXME need to add an info landing page at some point
	Route::get('/projects', 'LandingController@projects'); //FIXME need to add an info landing page at some point
	Route::get('/retainers', 'LandingController@projects'); //FIXME need to add an info landing page at some point
	Route::get('/overdue', 'LandingController@overdue'); //FIXME need to add an info landing page at some point
	Route::get('/owners', 'LandingController@owners'); //FIXME need to add an info landing page at some point
	Route::get('logout', 'LandingController@logout');
});
