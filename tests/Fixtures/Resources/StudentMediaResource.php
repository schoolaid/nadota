<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Resources;

use SchoolAid\Nadota\Http\Fields\File;
use SchoolAid\Nadota\Http\Fields\Image;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\MultiImage;
use SchoolAid\Nadota\Http\Fields\Signature;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

/**
 * Route key: "student-media".
 *
 * The file-based fields on the Student model, kept apart from StudentResource
 * so plain JSON stores there do not need multipart uploads. Every field writes
 * to the "fx-media" disk, which tests replace with Storage::fake('fx-media') (a name of
 * its own, so concurrent test processes faking "public" cannot wipe its files).
 *
 *   document  File        (pdf / txt, 1 MB, stored under documents/)
 *   avatar    Image       (default image types, stored under avatars/)
 *   photos    MultiImage  (JSON column, at most 3 per request, under photos/)
 *   signature Signature   (canvas data URL converted to a png under signatures/)
 */
class StudentMediaResource extends Resource
{
    public string $model = Student::class;

    public const DISK = 'fx-media';

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name')->required(),
            File::make('Document', 'document')
                ->disk(self::DISK)
                ->path('documents')
                ->accept(['pdf', 'txt'])
                ->maxSizeMB(1),
            Image::make('Avatar', 'avatar')
                ->disk(self::DISK)
                ->path('avatars'),
            MultiImage::make('Photos', 'photos')
                ->disk(self::DISK)
                ->path('photos')
                ->maxFiles(3)
                ->nullable(),
            Signature::make('Signature', 'signature')
                ->storeOnDisk(self::DISK, 'signatures'),
        ];
    }
}
