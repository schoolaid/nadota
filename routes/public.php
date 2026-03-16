<?php

use Illuminate\Support\Facades\Route;
use SchoolAid\Nadota\Http\Controllers\GlobalOptionsController;

Route::get('/options', [GlobalOptionsController::class, 'index'])->name('global.options');
