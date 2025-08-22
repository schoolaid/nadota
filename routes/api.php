<?php

use Illuminate\Support\Facades\Route;
use SchoolAid\Nadota\Http\Controllers\ResourceController;
use SchoolAid\Nadota\Http\Controllers\ResourceIndexController;
use SchoolAid\Nadota\Http\Controllers\MenuController;

Route::get('/menu', [MenuController::class, 'menu'])->name('menu');

Route::prefix('/{resourceKey}/resource')->group(function () {
    Route::get('/info', [ResourceIndexController::class, 'info'])->name('resource.info');
    Route::get('/fields', [ResourceIndexController::class, 'fields'])->name('resource.fields');
    Route::get('/filters', [ResourceIndexController::class, 'filters'])->name('resource.filters');
    Route::get('/actions', [ResourceIndexController::class, 'actions'])->name('resource.actions');
    Route::get('/lens', [ResourceIndexController::class, 'lens'])->name('resource.lens');
    Route::get('/data', [ResourceIndexController::class, 'compact'])->name('resource.compact');
});
Route::prefix('/{resourceKey}/resource')->group(function () {
    Route::get('/', [ResourceController::class, 'index'])->name('resource.index');
    Route::get('/create', [ResourceController::class, 'create'])->name('resource.create');
    Route::post('/', [ResourceController::class, 'store'])->name('resource.store');
    Route::get('/{id}', [ResourceController::class, 'show'])->name('resource.show');
    Route::get('/{id}/edit', [ResourceController::class, 'edit'])->name('resource.edit');
    Route::put('/{id}', [ResourceController::class, 'update'])->name('resource.update');
    Route::patch('/{id}', [ResourceController::class, 'update'])->name('resource.patch');
    Route::delete('/{id}', [ResourceController::class, 'destroy'])->name('resource.destroy');
});
