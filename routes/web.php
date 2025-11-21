<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return response()->json([
        'message' => 'API Works!',
        'status' => 'success'
    ]);
});

// Route lainnya...