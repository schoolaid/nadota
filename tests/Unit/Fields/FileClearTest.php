<?php

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use SchoolAid\Nadota\Http\Fields\File;
use SchoolAid\Nadota\Http\Fields\Image;
use SchoolAid\Nadota\Tests\Models\TestModel;

/*
 * A stored file could not be removed: fill() only acted on an upload. Removing now takes the explicit
 * value `clear` (the same one Signature takes). An empty value keeps meaning "no change", because
 * forms send it when nothing was picked, or when a picked file was dropped again. Like a replaced
 * file, the cleared one is deleted only once the save commits (commitPendingFiles).
 */
beforeEach(function () {
    Storage::fake('local');
    config(['filesystems.default' => 'local']);
    Storage::disk('local')->put('uploads/old.png', 'old');

    $this->model = new TestModel(['description' => 'uploads/old.png']);
    $this->model->syncOriginal();
});

function fileRequest(array $input = [], array $files = []): Request
{
    return Request::create('/', 'POST', $input, [], $files);
}

it('removes the stored file and empties the attribute on clear', function (string $class) {
    $field = $class::make('Photo', 'description')->nullable();
    $field->fill(fileRequest(['description' => 'clear']), $this->model);

    expect($this->model->description)->toBeNull();
    Storage::disk('local')->assertExists('uploads/old.png');

    $field->commitPendingFiles($this->model);
    Storage::disk('local')->assertMissing('uploads/old.png');
})->with([File::class, Image::class]);

it('keeps the file when the save that cleared it is rolled back', function () {
    $field = File::make('Photo', 'description')->nullable();
    $field->fill(fileRequest(['description' => 'clear']), $this->model);
    $field->discardPendingFiles();

    Storage::disk('local')->assertExists('uploads/old.png');
});

it('removes through deleteUsing when the field defines it', function () {
    $deleted = null;
    $field = File::make('Photo', 'description')->nullable()
        ->deleteUsing(function ($model, $disk, $oldPath) use (&$deleted) { $deleted = $oldPath; });
    $field->fill(fileRequest(['description' => 'clear']), $this->model);
    $field->commitPendingFiles($this->model);

    expect($deleted)->toBe('uploads/old.png')
        ->and($this->model->description)->toBeNull();
    Storage::disk('local')->assertExists('uploads/old.png');
});

it('keeps the file when the value is empty, missing or the saved data sent back', function (array $input) {
    File::make('Photo', 'description')->nullable()->fill(fileRequest($input), $this->model);

    expect($this->model->description)->toBe('uploads/old.png');
    Storage::disk('local')->assertExists('uploads/old.png');
})->with([
    'empty' => [['description' => '']],
    'missing' => [[]],
    'saved data' => [['description' => ['path' => 'uploads/old.png', 'url' => 'https://x']]],
]);

it('does not clear a required file', function () {
    File::make('Photo', 'description')->required()->fill(fileRequest(['description' => 'clear']), $this->model);

    expect($this->model->description)->toBe('uploads/old.png');
    Storage::disk('local')->assertExists('uploads/old.png');
});

it('still replaces the file on upload', function () {
    $upload = UploadedFile::fake()->image('new.png');
    $field = File::make('Photo', 'description')->nullable();
    $field->fill(fileRequest([], ['description' => $upload]), $this->model);
    $field->commitPendingFiles($this->model);

    expect($this->model->description)->toBe('uploads/'.$upload->hashName());
    Storage::disk('local')->assertMissing('uploads/old.png');
    Storage::disk('local')->assertExists('uploads/'.$upload->hashName());
});
