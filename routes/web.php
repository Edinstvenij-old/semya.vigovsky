<?php

use Illuminate\Support\Facades\Route;

// Single-page app: every non-API path renders the Vue shell; Vue Router takes over.
Route::view('/{any?}', 'app')->where('any', '^(?!api|sanctum|up|build|storage).*$');
