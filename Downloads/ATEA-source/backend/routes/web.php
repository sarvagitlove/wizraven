<?php

use Illuminate\Support\Facades\Route;


use Illuminate\Http\Request;
use App\Services\GmailService;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/google/callback', function (Request $request) {
    $code = $request->input('code');
    $gmail = new GmailService(true);
    $tokens = $gmail->handleAuthCallback($code);
    return response()->json($tokens);
});
