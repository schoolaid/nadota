<?php

use Illuminate\Support\Facades\Route;
use SchoolAid\Nadota\Http\Controllers\ResourceController;
use SchoolAid\Nadota\Http\Controllers\ResourceIndexController;
use SchoolAid\Nadota\Http\Controllers\MenuController;
use SchoolAid\Nadota\Http\Controllers\FieldOptionsController;

Route::get('/menu', [MenuController::class, 'menu'])->name('menu');

Route::prefix('/{resourceKey}/resource')->group(function () {
    Route::get('/info', [ResourceIndexController::class, 'info'])->name('resource.info');
    Route::get('/fields', [ResourceIndexController::class, 'fields'])->name('resource.fields');
    Route::get('/filters', [ResourceIndexController::class, 'filters'])->name('resource.filters');
    Route::get('/actions', [ResourceIndexController::class, 'actions'])->name('resource.actions');
    Route::get('/lens', [ResourceIndexController::class, 'lens'])->name('resource.lens');
    Route::get('/data', [ResourceIndexController::class, 'compact'])->name('resource.compact');

    // Field options endpoints
    Route::get('/field/{fieldName}/options', [FieldOptionsController::class, 'index'])->name('resource.field.options');
    Route::get('/field/{fieldName}/options/paginated', [FieldOptionsController::class, 'paginated'])->name('resource.field.options.paginated');

    // Morph field options endpoint
    Route::get('/field/{fieldName}/morph-options/{morphType}', [FieldOptionsController::class, 'morphOptions'])->name('resource.field.morph.options');
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
    Route::delete('/{id}/force', [ResourceController::class, 'forceDelete'])->name('resource.forceDelete');
    Route::post('/{id}/restore', [ResourceController::class, 'restore'])->name('resource.restore');
});
