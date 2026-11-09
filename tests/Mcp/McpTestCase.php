<?php

namespace SchoolAid\Nadota\Tests\Mcp;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Server\McpServiceProvider as LaravelMcpServiceProvider;
use Laravel\Mcp\Server\Testing\TestResponse;
use SchoolAid\Nadota\Mcp\McpServiceProvider;
use SchoolAid\Nadota\ResourceManager;
use SchoolAid\Nadota\Tests\Mcp\Fixtures\McpBatch;
use SchoolAid\Nadota\Tests\Mcp\Fixtures\McpBatchPolicy;
use SchoolAid\Nadota\Tests\Mcp\Fixtures\McpBatchResource;
use SchoolAid\Nadota\Tests\Mcp\Fixtures\McpHalf;
use SchoolAid\Nadota\Tests\Mcp\Fixtures\McpHalfPolicy;
use SchoolAid\Nadota\Tests\Mcp\Fixtures\McpHalfResource;
use SchoolAid\Nadota\Tests\Mcp\Fixtures\McpLooseResource;
use SchoolAid\Nadota\Tests\Mcp\Fixtures\McpOwner;
use SchoolAid\Nadota\Tests\Mcp\Fixtures\McpOwnerPolicy;
use SchoolAid\Nadota\Tests\Mcp\Fixtures\McpOwnerResource;
use SchoolAid\Nadota\Tests\TestCase;

/**
 * The MCP read tools over two fixture resources (`mcp-batch`, `mcp-owner`) and two that the
 * gatekeeper must refuse (`mcp-loose` has no policy, `mcp-half` has no `view`).
 */
class McpTestCase extends TestCase
{
    /**
     * The batch entry most tests use.
     *
     * @return array<string, mixed>
     */
    public static function batchEntry(): array
    {
        return [
            'fields' => ['reference', 'total', 'status', 'is_open', 'owner_id', 'notes', 'closed_on', 'starts_at', 'created_at'],
            'money' => ['total' => 'currency_code'],
            'search' => true,
            'hints' => ['total' => 'Positivo = cargo.'],
        ];
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelMcpServiceProvider::class,
            McpServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('mcp_owners', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('mcp_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('reference');
            $table->decimal('total', 12, 2)->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->string('status')->default('open');
            $table->boolean('is_open')->default(false);
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->text('notes')->nullable();
            $table->text('xml')->nullable();
            $table->string('api_key')->nullable();
            $table->date('closed_on')->nullable();
            $table->time('starts_at')->nullable();
            $table->timestamps();
        });

        foreach ([McpBatchResource::class, McpOwnerResource::class, McpLooseResource::class, McpHalfResource::class] as $resource) {
            ResourceManager::registerResourceClass($resource);
        }

        Gate::policy(McpBatch::class, McpBatchPolicy::class);
        Gate::policy(McpOwner::class, McpOwnerPolicy::class);
        Gate::policy(McpHalf::class, McpHalfPolicy::class);

        config()->set('nadota.mcp.resources', [
            'mcp-batch' => self::batchEntry(),
            'mcp-owner' => ['fields' => ['name'], 'search' => true],
        ]);
        config()->set('nadota.mcp.default_currency', 'GTQ');

        TestResponse::macro('toolJson', function (): mixed {
            $text = $this->response->toArray()['result']['content'][0]['text'] ?? null;

            return $text === null ? null : json_decode($text, true);
        });

        TestResponse::macro('toolError', function (): ?string {
            $response = $this->response->toArray();

            if (data_get($response, 'result.isError', false)) {
                return $response['result']['content'][0]['text'] ?? '';
            }

            return $response['error']['message'] ?? null;
        });
    }
}
