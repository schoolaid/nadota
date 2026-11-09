<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentMediaResource;

/*
 * File, Image, MultiImage and Signature through real multipart requests
 * against StudentMediaResource (route key "student-media", disk "fx-media").
 */

uses(InteractsWithNadotaApi::class);

beforeEach(function () {
    Storage::fake(StudentMediaResource::DISK);
    $this->bootNadotaApi([StudentMediaResource::class]);
});

afterEach(fn () => Student::flushEventListeners());

/** InteractsWithNadotaApi::apiUrl() is protected, so the helpers build the URL themselves. */
function mediaUrl(string $path = ''): string
{
    return '/nadota-api/student-media/resource' . ($path === '' ? '' : '/' . $path);
}

function mediaPost($test, array $data): TestResponse
{
    return $test->post(mediaUrl(), $data, ['Accept' => 'application/json']);
}

function mediaPut($test, int $id, array $data): TestResponse
{
    return $test->put(mediaUrl((string) $id), $data, ['Accept' => 'application/json']);
}

function mediaColumn(int $id, string $column): mixed
{
    return DB::table('fx_students')->where('id', $id)->value($column);
}

function mediaShowValue($test, int $id, string $key): mixed
{
    return collect($test->getJson(mediaUrl((string) $id))->assertOk()->json('data.attributes'))
        ->firstWhere('key', $key)['value'] ?? null;
}

function mediaSignatureDataUrl(string $bytes = 'signature-png'): string
{
    return 'data:image/png;base64,' . base64_encode($bytes);
}

it('stores every upload on its disk and path and renders the file urls', function () {
    $document = UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf');
    $avatar = UploadedFile::fake()->image('me.jpg', 30, 15);
    $photos = [UploadedFile::fake()->image('p1.jpg'), UploadedFile::fake()->image('p2.png')];

    $id = mediaPost($this, ['name' => 'Ana', 'document' => $document, 'avatar' => $avatar, 'photos' => $photos])
        ->assertCreated()
        ->json('data.id');

    $disk = Storage::disk(StudentMediaResource::DISK);

    expect(mediaColumn($id, 'document'))->toBe('documents/' . $document->hashName())
        ->and(mediaColumn($id, 'avatar'))->toBe('avatars/' . $avatar->hashName())
        ->and(json_decode(mediaColumn($id, 'photos'), true))->toBe([
            'photos/' . $photos[0]->hashName(),
            'photos/' . $photos[1]->hashName(),
        ]);

    $disk->assertExists(mediaColumn($id, 'document'));
    $disk->assertExists(mediaColumn($id, 'avatar'));
    $disk->assertExists('photos/' . $photos[0]->hashName());

    $documentValue = mediaShowValue($this, $id, 'document');
    $avatarValue = mediaShowValue($this, $id, 'avatar');
    $photosValue = mediaShowValue($this, $id, 'photos');

    expect($documentValue['path'])->toBe('documents/' . $document->hashName())
        ->and($documentValue['url'])->toBe($disk->url('documents/' . $document->hashName()))
        ->and($documentValue['downloadUrl'])->toBe($documentValue['url'])
        ->and($avatarValue['isImage'])->toBeTrue()
        ->and($avatarValue['alt'])->toBe('Avatar')
        ->and($avatarValue['dimensions'])->toMatchArray(['width' => 30, 'height' => 15])
        ->and(array_column($photosValue, 'url'))->toBe([
            $disk->url('photos/' . $photos[0]->hashName()),
            $disk->url('photos/' . $photos[1]->hashName()),
        ]);
});

it('stores nothing and renders null when no file is sent', function () {
    $id = mediaPost($this, ['name' => 'Ana'])->assertCreated()->json('data.id');

    expect(mediaColumn($id, 'document'))->toBeNull()
        ->and(mediaColumn($id, 'avatar'))->toBeNull()
        ->and(mediaColumn($id, 'photos'))->toBeNull()
        ->and(mediaShowValue($this, $id, 'document'))->toBeNull()
        ->and(mediaShowValue($this, $id, 'avatar'))->toBeNull()
        ->and(mediaShowValue($this, $id, 'photos'))->toBe([])
        ->and(Storage::disk(StudentMediaResource::DISK)->allFiles())->toBe([]);
});

it('replaces a file on update and deletes the old one', function () {
    $id = mediaPost($this, ['name' => 'Ana', 'document' => UploadedFile::fake()->create('v1.pdf', 5, 'application/pdf')])
        ->assertCreated()->json('data.id');
    $oldPath = mediaColumn($id, 'document');

    $replacement = UploadedFile::fake()->create('v2.txt', 1, 'text/plain');
    mediaPut($this, $id, ['name' => 'Ana', 'document' => $replacement])->assertOk();

    expect(mediaColumn($id, 'document'))->toBe('documents/' . $replacement->hashName());
    Storage::disk(StudentMediaResource::DISK)->assertMissing($oldPath);
    Storage::disk(StudentMediaResource::DISK)->assertExists('documents/' . $replacement->hashName());
});

it('keeps the stored files when an update sends none', function () {
    $id = mediaPost($this, ['name' => 'Ana', 'document' => UploadedFile::fake()->create('v1.pdf', 5, 'application/pdf')])
        ->assertCreated()->json('data.id');
    $path = mediaColumn($id, 'document');

    mediaPut($this, $id, ['name' => 'Renamed', 'document' => null])->assertOk();

    expect(mediaColumn($id, 'document'))->toBe($path)
        ->and(mediaColumn($id, 'name'))->toBe('Renamed');
    Storage::disk(StudentMediaResource::DISK)->assertExists($path);
});

it('appends new photos to the stored ones on update', function () {
    $first = UploadedFile::fake()->image('a.jpg');
    $id = mediaPost($this, ['name' => 'Ana', 'photos' => [$first]])->assertCreated()->json('data.id');

    $second = UploadedFile::fake()->image('b.jpg');
    mediaPut($this, $id, ['name' => 'Ana', 'photos' => [$second]])->assertOk();

    expect(json_decode(mediaColumn($id, 'photos'), true))->toBe([
        'photos/' . $first->hashName(),
        'photos/' . $second->hashName(),
    ]);
});

it('accepts the frontend empty array indicator for photos', function () {
    $id = mediaPost($this, ['name' => 'Ana', 'photos' => '[]'])->assertCreated()->json('data.id');

    expect(mediaColumn($id, 'photos'))->toBeNull();
});

it('rejects uploads that break the field rules', function (string $attribute, callable $value, string $errorKey) {
    mediaPost($this, ['name' => 'Ana', $attribute => $value()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errorKey);

    expect(Storage::disk(StudentMediaResource::DISK)->allFiles())->toBe([])
        ->and(Student::count())->toBe(0);
})->with([
    'document of a type not accepted' => ['document', fn () => UploadedFile::fake()->create('virus.exe', 1, 'application/x-msdownload'), 'document'],
    'document over 1 MB' => ['document', fn () => UploadedFile::fake()->create('big.pdf', 2048, 'application/pdf'), 'document'],
    'document sent as a string' => ['document', fn () => 'documents/forged.pdf', 'document'],
    'avatar that is not an image' => ['avatar', fn () => UploadedFile::fake()->create('cv.pdf', 1, 'application/pdf'), 'avatar'],
    'more photos than maxFiles' => ['photos', fn () => [
        UploadedFile::fake()->image('1.jpg'), UploadedFile::fake()->image('2.jpg'),
        UploadedFile::fake()->image('3.jpg'), UploadedFile::fake()->image('4.jpg'),
    ], 'photos'],
    'photos that are not images' => ['photos', fn () => [UploadedFile::fake()->create('a.pdf', 1, 'application/pdf')], 'photos.0'],
    'photos that are not an array' => ['photos', fn () => 'abc', 'photos'],
]);

it('reports the failing photo by its index', function () {
    mediaPost($this, ['name' => 'Ana', 'photos' => [UploadedFile::fake()->image('ok.jpg'), UploadedFile::fake()->create('bad.pdf', 1, 'application/pdf')]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('photos.1')
        ->assertJsonMissingValidationErrors('photos.0');
});

it('removes the stored uploads when the save fails', function () {
    Student::saving(fn () => throw new RuntimeException('database is down'));

    mediaPost($this, [
        'name' => 'Ana',
        'document' => UploadedFile::fake()->create('cv.pdf', 5, 'application/pdf'),
        'photos' => [UploadedFile::fake()->image('p.jpg')],
    ])->assertStatus(500)->assertJsonPath('error', 'database is down');

    expect(Storage::disk(StudentMediaResource::DISK)->allFiles())->toBe([]);
});

it('keeps the previous file when an update fails', function () {
    $id = mediaPost($this, ['name' => 'Ana', 'document' => UploadedFile::fake()->create('v1.pdf', 5, 'application/pdf')])
        ->assertCreated()->json('data.id');
    $oldPath = mediaColumn($id, 'document');

    Student::saving(fn () => throw new RuntimeException('boom'));

    mediaPut($this, $id, ['name' => 'Ana', 'document' => UploadedFile::fake()->create('v2.pdf', 5, 'application/pdf')])->assertStatus(500);

    expect(mediaColumn($id, 'document'))->toBe($oldPath)
        ->and(Storage::disk(StudentMediaResource::DISK)->allFiles())->toBe([$oldPath]);
});

it('describes the upload constraints on the create endpoint', function () {
    $fields = collect($this->getJson($this->apiUrl('student-media', 'create'))->assertOk()->json('data.attributes'))->keyBy('key');

    expect($fields->pluck('type')->all())->toBe(['text', 'file', 'image', 'multiImage', 'signature'])
        ->and($fields['document']['props'])->toMatchArray(['acceptedTypes' => ['pdf', 'txt'], 'maxSizeMB' => 1.0, 'disk' => StudentMediaResource::DISK, 'path' => 'documents'])
        ->and($fields['document']['rules'])->toContain('nullable', 'file', 'max:1024', 'mimes:pdf,txt')
        ->and($fields['avatar']['rules'])->toContain('image')
        ->and($fields['photos']['props']['maxFiles'])->toBe(3)
        ->and($fields['signature']['props']['storageMode'])->toBe('disk');
});

// ---------------------------------------------------------------------------
// Signature on disk: Signature::fill() is never called by the persist service
// ---------------------------------------------------------------------------

it('writes a disk signature as a png file and stores its path', function () {
    $id = mediaPost($this, ['name' => 'Ana', 'signature' => mediaSignatureDataUrl('PNGBYTES')])->assertCreated()->json('data.id');

    $path = mediaColumn($id, 'signature');

    expect($path)->toStartWith('signatures/signature_')->toEndWith('.png')
        ->and(Storage::disk(StudentMediaResource::DISK)->get($path))->toBe('PNGBYTES')
        ->and(mediaShowValue($this, $id, 'signature'))->toMatchArray(['type' => 'file', 'path' => $path]);
})->skip('ISSUE FLD-001: Signature::fill() never runs on store/update, the data URL is stored in the column and no file is written');

it('clears a disk signature and deletes its file', function () {
    Storage::disk(StudentMediaResource::DISK)->put('signatures/old.png', 'old');
    $student = Student::create(['name' => 'Ana', 'signature' => 'signatures/old.png']);

    mediaPut($this, $student->id, ['name' => 'Ana', 'signature' => 'clear'])->assertOk();

    expect(mediaColumn($student->id, 'signature'))->toBeNull();
    Storage::disk(StudentMediaResource::DISK)->assertMissing('signatures/old.png');
})->skip('ISSUE FLD-001: Signature::fill() never runs, the literal "clear" is stored as the signature');

it('keeps a disk signature when an update sends nothing for it', function () {
    $student = Student::create(['name' => 'Ana', 'signature' => 'signatures/old.png']);

    mediaPut($this, $student->id, ['name' => 'Renamed'])->assertOk();

    expect(mediaColumn($student->id, 'signature'))->toBe('signatures/old.png');
});

it('refuses an update that would take the photos beyond maxFiles', function () {
    $id = mediaPost($this, ['name' => 'Ana', 'photos' => [UploadedFile::fake()->image('1.jpg'), UploadedFile::fake()->image('2.jpg')]])
        ->assertCreated()->json('data.id');

    mediaPut($this, $id, ['name' => 'Ana', 'photos' => [UploadedFile::fake()->image('3.jpg'), UploadedFile::fake()->image('4.jpg')]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('photos');

    expect(json_decode(mediaColumn($id, 'photos'), true))->toHaveCount(2);
})->skip('ISSUE FLD-016: maxFiles only limits the files of one request, the stored list grows to 4 with maxFiles(3)');
