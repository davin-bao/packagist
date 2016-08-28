<?php


Route::get('/packages.json', 'IndexController@getPackages');

Route::controller('/', 'IndexController');
