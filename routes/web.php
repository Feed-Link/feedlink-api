<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()
           ->json(['message' => 'Application is running'], 200);
});


