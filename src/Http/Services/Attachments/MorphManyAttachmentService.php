<?php

namespace SchoolAid\Nadota\Http\Services\Attachments;

use Illuminate\Database\Eloquent\Model;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Fields\Relations\MorphMany;

/**
 * Attach/detach for a MorphMany field: writes the child's morph type and id. Rules in
 * OwnedChildrenAttachmentService.
 */
class MorphManyAttachmentService extends OwnedChildrenAttachmentService
{
    protected function supportsRelation(Model $parentModel, Field $field): bool
    {
        return $field instanceof MorphMany && MorphMany::supportsAttachment($parentModel, $field->getRelation());
    }

    protected function unsupportedMessage(): string
    {
        return 'Attach and detach are only supported on a MorphMany relation';
    }
}
