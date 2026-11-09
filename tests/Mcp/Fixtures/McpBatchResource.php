<?php

namespace SchoolAid\Nadota\Tests\Mcp\Fixtures;

use Illuminate\Database\Eloquent\Model;
use SchoolAid\Nadota\Http\Fields\Checkbox;
use SchoolAid\Nadota\Http\Fields\Code;
use SchoolAid\Nadota\Http\Fields\Currency;
use SchoolAid\Nadota\Http\Fields\Date;
use SchoolAid\Nadota\Http\Fields\DateTime;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Http\Fields\Status;
use SchoolAid\Nadota\Http\Fields\Textarea;
use SchoolAid\Nadota\Http\Fields\Time;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;

/**
 * One field of each kind the MCP tools handle, plus two that must never leave them: `xml` (a
 * blocked type) and `api_key` (a forbidden attribute in a plain Input).
 */
class McpBatchResource extends Resource
{
    public string $model = McpBatch::class;

    protected array $searchableAttributes = ['reference'];

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Reference', 'reference')->sortable()->filterable(),
            Currency::make('Total', 'total')->sortable(),
            Status::make('Status', 'status')->map([
                'open' => 'Abierto',
                'processed' => 'Procesado',
                'sealed' => 'Sellado',
            ])->filterable(),
            Checkbox::make('Open', 'is_open')->filterable(),
            BelongsTo::make('Owner', 'owner', McpOwnerResource::class)->filterable()->hideFromIndex(),
            Textarea::make('Notes', 'notes')->onlyOnDetail(),
            Code::make('Xml', 'xml')->hideFromIndex(),
            Input::make('Api key', 'api_key'),
            Date::make('Closed on', 'closed_on')->filterableRange(),
            Time::make('Starts at', 'starts_at')->filterable(),
            DateTime::make('Created at', 'created_at')->sortable()->filterable(),
            HasMany::make('Logs', 'logs', McpLogResource::class)->onlyOnDetail(),
        ];
    }

    /**
     * The panel's show payload loads the logs; the MCP detail must not, since they never leave
     * (their table does not even exist in the tests).
     *
     * @return array<int, string>
     */
    public function getWithOnShow(): array
    {
        return ['logs'];
    }

    public function displayLabel(Model $model): string
    {
        return "Lote {$model->reference}";
    }
}
