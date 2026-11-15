<?php

use SchoolAid\Nadota\Http\Fields\Relations\MorphedByMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphToMany;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\Attachments\BelongsToManyAttachmentService;
use SchoolAid\Nadota\Tests\Models\Tag;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Resources\TestResource;

// MorphToMany and MorphedByMany report type `belongsToMany`, so the attachment controller sends
// them to BelongsToManyAttachmentService, but they do not extend BelongsToMany: the service may
// only use what BelongsToMany adds (candidatesQuery(), rejectedIds()) on a BelongsToMany.

function morphAttachRequest(array $data = []): NadotaRequest
{
    $request = new NadotaRequest();
    $request->merge($data);
    $request->setResource(new TestResource());

    return $request;
}

beforeEach(function () {
    $this->model = TestModel::create(['name' => 'Parent']);
    $this->attached = Tag::create(['name' => 'Attached']);
    $this->free = Tag::create(['name' => 'Free']);
    $this->model->tags()->attach($this->attached->id);
});

it('lists the MorphToMany items not attached yet', function () {
    $response = (new BelongsToManyAttachmentService())
        ->getAttachableItems(morphAttachRequest(), $this->model, MorphToMany::make('Tags', 'tags'));

    expect($response->getStatusCode())->toBe(200)
        ->and(collect($response->getData(true)['data'])->pluck('id')->all())->toBe([$this->free->id]);
});

it('lists the MorphedByMany items not attached yet', function () {
    $other = TestModel::create(['name' => 'Other']);

    $response = (new BelongsToManyAttachmentService())
        ->getAttachableItems(morphAttachRequest(), $this->attached, MorphedByMany::make('Models', 'testModels'));

    expect($response->getStatusCode())->toBe(200)
        ->and(collect($response->getData(true)['data'])->pluck('id')->all())->toBe([$other->id]);
});

it('still attaches and syncs a MorphToMany through the same service', function () {
    $service = new BelongsToManyAttachmentService();
    $field = MorphToMany::make('Tags', 'tags');

    expect($service->attach(morphAttachRequest(['items' => [$this->free->id]]), $this->model, $field)->getStatusCode())->toBe(200)
        ->and($this->model->tags()->pluck('tags.id')->sort()->values()->all())->toBe([$this->attached->id, $this->free->id])
        ->and($service->sync(morphAttachRequest(['items' => [$this->free->id]]), $this->model, $field)->getStatusCode())->toBe(200)
        ->and($this->model->tags()->pluck('tags.id')->all())->toBe([$this->free->id]);
});
