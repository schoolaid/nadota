<?php

use Illuminate\Support\Facades\Route;
use SchoolAid\Nadota\Http\Controllers\ActionController;
use SchoolAid\Nadota\Http\Controllers\ResourceController;
use SchoolAid\Nadota\Http\Controllers\ResourceIndexController;
use SchoolAid\Nadota\Http\Controllers\ResourceOptionsController;
use SchoolAid\Nadota\Http\Controllers\MenuController;
use SchoolAid\Nadota\Http\Controllers\FieldOptionsController;
use SchoolAid\Nadota\Http\Controllers\AttachmentController;
use SchoolAid\Nadota\Http\Controllers\RelationController;

Route::get('/menu', [MenuController::class, 'menu'])->name('menu');

Route::prefix('/{resourceKey}/resource')->group(function () {
    Route::get('/config', [ResourceIndexController::class, 'config'])->name('resource.config');
    Route::get('/info', [ResourceIndexController::class, 'info'])->name('resource.info');
    Route::get('/fields', [ResourceIndexController::class, 'fields'])->name('resource.fields');
    Route::get('/filters', [ResourceIndexController::class, 'filters'])->name('resource.filters');
    Route::get('/actions', [ActionController::class, 'index'])->name('resource.actions');
    Route::get('/actions/{actionKey}/fields', [ActionController::class, 'fields'])->name('resource.actions.fields');
    Route::post('/actions/{actionKey}', [ActionController::class, 'execute'])->name('resource.actions.execute');
    Route::get('/lens', [ResourceIndexController::class, 'lens'])->name('resource.lens');
    Route::get('/data', [ResourceIndexController::class, 'compact'])->name('resource.compact');

    // Field options endpoints
    Route::get('/field/{fieldName}/options', [FieldOptionsController::class, 'index'])->name('resource.field.options');
    Route::get('/field/{fieldName}/options/paginated', [FieldOptionsController::class, 'paginated'])->name('resource.field.options.paginated');

    // Morph field options endpoint
    Route::get('/field/{fieldName}/morph-options/{morphType}', [FieldOptionsController::class, 'morphOptions'])->name('resource.field.morph.options');

    // Resource options endpoint (returns resource records as options using displayLabel)
    Route::get('/options', [ResourceOptionsController::class, 'index'])->name('resource.options');

    Route::get('/', [ResourceController::class, 'index'])->name('resource.index');
    Route::get('/create', [ResourceController::class, 'create'])->name('resource.create');
    Route::post('/', [ResourceController::class, 'store'])->name('resource.store');

    // Attachment endpoints - defined before generic {id} routes to avoid conflicts
    Route::get('/{id}/attachable/{field}', [AttachmentController::class, 'attachable'])->name('resource.attachable')->where('id', '[0-9]+');
    Route::post('/{id}/attach/{field}', [AttachmentController::class, 'attach'])->name('resource.attach')->where('id', '[0-9]+');
    Route::post('/{id}/detach/{field}', [AttachmentController::class, 'detach'])->name('resource.detach')->where('id', '[0-9]+');

    // Relation pagination endpoint
    Route::get('/{id}/relation/{field}', [RelationController::class, 'index'])->name('resource.relation.index')->where('id', '[0-9]+');

    Route::get('/{id}', [ResourceController::class, 'show'])->name('resource.show');
    Route::get('/{id}/edit', [ResourceController::class, 'edit'])->name('resource.edit');
    Route::put('/{id}', [ResourceController::class, 'update'])->name('resource.update');
    Route::patch('/{id}', [ResourceController::class, 'update'])->name('resource.patch');
    Route::delete('/{id}', [ResourceController::class, 'destroy'])->name('resource.destroy');
    Route::delete('/{id}/force', [ResourceController::class, 'forceDelete'])->name('resource.forceDelete');
    Route::post('/{id}/restore', [ResourceController::class, 'restore'])->name('resource.restore');
});
