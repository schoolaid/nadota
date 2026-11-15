<?php

namespace SchoolAid\Nadota\Http\Services\Attachments;

use Illuminate\Database\Eloquent\Model;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;

/**
 * Attach/detach for a HasMany field: moves the child's foreign key. Rules in
 * OwnedChildrenAttachmentService.
 */
class HasManyAttachmentService extends OwnedChildrenAttachmentService
{
    protected function supportsRelation(Model $parentModel, Field $field): bool
    {
        return $field instanceof HasMany && HasMany::supportsAttachment($parentModel, $field->getRelation());
    }

    protected function unsupportedMessage(): string
    {
        return 'Attach and detach are only supported on a HasMany relation';
    }
}
