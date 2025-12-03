<?php

namespace SchoolAid\Nadota\Resources;

use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\DateTime;
use SchoolAid\Nadota\Http\Fields\Select;
use SchoolAid\Nadota\Http\Fields\Textarea;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Fields\Relations\MorphTo;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Models\ActionEvent;
use SchoolAid\Nadota\Http\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ActionEventResource extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public string $model = ActionEvent::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public ?string $title = 'Action Events';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static array $search = [
        'batch_id',
        'name',
        'actionable_type',
        'model_type',
        'status',
    ];

    /**
     * Indicates if the resource uses soft deletes.
     *
     * @var bool
     */
    protected bool $softDelete = false;

    public array $with = ['user'];

    /**
     * Get the fields displayed by the resource.
     *
     * @param NadotaRequest $request
     * @return array
     */
    public function fields(NadotaRequest $request): array
    {
        return [

            Input::make('Batch ID', 'batch_id')

                ->filterable()
                ->sortable()
                ->readonly()
                ->help('UUID for grouping related actions')
                ,

            BelongsTo::make('User', 'user')
                ->sortable()
                ->displayAttribute('name')
                ->readonly(),

            Input::make('Action Name', 'name')
                ->sortable()
                ->readonly()
                ->help('The name of the action performed'),

            Select::make('Status', 'status')
                ->options([
                    'running' => 'Running',
                    'finished' => 'Finished',
                    'failed' => 'Failed',
                ])
                ->sortable()
                ->readonly()
                ->default('running'),

            Input::make('Actionable Type', 'actionable_type')
                ->readonly()
                
                ->help('The resource class that was acted upon'),

            Input::make('Actionable ID', 'actionable_id')
                ->readonly()
                ,

            Input::make('Target Type', 'target_type')
                ->readonly()
                
                ->help('The model class that was targeted'),

            Input::make('Target ID', 'target_id')
                ->readonly()
                ,

            Input::make('Model Type', 'model_type')
                ->readonly()
                ->help('The model class that was affected'),

            Input::make('Model ID', 'model_id')
                ->readonly()
                ,

            Textarea::make('Fields', 'fields')
                ->readonly()
                ->help('JSON data of fields involved in the action'),

            Textarea::make('Original Data', 'original')
                ->readonly()
                ->help('Original state before changes'),

            Textarea::make('Changes', 'changes')
                ->readonly()
                ->help('Changes made during the action'),

            Textarea::make('Exception', 'exception')
                ->readonly()
                ->help('Error message if action failed'),

            DateTime::make('Created At', 'created_at')
                ->sortable()
                ->readonly()
                ->dateOnly(),

            DateTime::make('Updated At', 'updated_at')
                ->sortable()
                ->readonly()
                
                ->dateOnly(),
        ];
    }

}