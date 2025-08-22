<?php

namespace Said\Nadota\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Said\Nadota\NadotaServiceProvider;
use Said\Nadota\Contracts\ResourceAuthorizationInterface;
use Said\Nadota\Tests\Mocks\MockResourceAuthorizationService;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Said\\Nadota\\Tests\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        // Bind mock authorization service for testing
        $this->app->bind(ResourceAuthorizationInterface::class, MockResourceAuthorizationService::class);

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app)
    {
        return [
            // Don't load NadotaServiceProvider in tests to avoid resource discovery issues
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Disable resource discovery during tests
        config()->set('nadota.path', 'said');
        config()->set('nadota.namespace', 'said');
        config()->set('nadota.key_resources_cache', 'said_nadota_class_file_map');
        config()->set('nadota.path_resources', __DIR__ . '/Resources');
        config()->set('nadota.middlewares', ['api']);
        config()->set('nadota.fields', [
            'text' => [
                'type' => 'text',
                'component' => 'FieldText'
            ],
            'belongsTo' => [
                'type' => 'belongsTo',
                'component' => 'FieldBelongsTo'
            ]
        ]);
        config()->set('nadota.api.prefix', 'nadota-api');
        config()->set('nadota.frontend.prefix', 'resources');
        
        // Set app base path to package directory for testing
        $app->setBasePath(__DIR__ . '/../');
    }

    protected function setUpDatabase(): void
    {
        Schema::create('test_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->text('description')->nullable();
            $table->integer('age')->nullable();
            $table->boolean('is_active')->default(false);
            $table->datetime('published_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('related_models', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedBigInteger('test_model_id')->nullable();
            $table->timestamps();
            
            $table->foreign('test_model_id')->references('id')->on('test_models');
        });

        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->string('bio');
            $table->unsignedBigInteger('test_model_id')->nullable();
            $table->timestamps();
            
            $table->foreign('test_model_id')->references('id')->on('test_models');
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tag_id');
            $table->unsignedBigInteger('taggable_id');
            $table->string('taggable_type');
            $table->timestamps();
        });

        Schema::create('test_model_tag', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('test_model_id');
            $table->unsignedBigInteger('tag_id');
            $table->timestamps();
            
            $table->foreign('test_model_id')->references('id')->on('test_models');
            $table->foreign('tag_id')->references('id')->on('tags');
        });
    }
}