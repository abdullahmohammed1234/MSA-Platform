<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', function () {
    return response()->json(['success' => false, 'message' => 'Unauthenticated. Please log in.'], 401);
})->name('login');

Route::get('/event-documents/{documentUuid}/{token}', [\App\Ems\Http\Controllers\V1\Public\PublicDocumentAccessController::class, 'access'])
    ->name('event-documents.access');
