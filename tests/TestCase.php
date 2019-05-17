<?php

namespace Illuminatech\Config\Test;

use Illuminate\Validation\Factory;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Capsule\Manager;

/**
 * Base class for the test cases.
 */
class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Illuminate\Contracts\Container\Container test application instance.
     */
    protected $app;

    /**
     * {@inheritdoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->createApplication();

        $db = new Manager;

        $db->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);

        $db->bootEloquent();
        $db->setAsGlobal();

        Model::clearBootedModels();
    }

    /**
     * Get a database connection instance.
     *
     * @return \Illuminate\Database\Connection
     */
    protected function getConnection()
    {
        return Model::getConnectionResolver()->connection();
    }

    /**
     * Get a schema builder instance.
     *
     * @return \Illuminate\Database\Schema\Builder
     */
    protected function getSchemaBuilder()
    {
        return $this->getConnection()->getSchemaBuilder();
    }

    /**
     * Creates dummy application instance, ensuring facades functioning.
     */
    protected function createApplication()
    {
        $this->app = Container::getInstance();

        Facade::setFacadeApplication($this->app);

        $this->app->singleton('files', function () {
            return new Filesystem;
        });

        $this->app->singleton('translation.loader', function (Container $app) {
            return new FileLoader($app->make('files'), __DIR__);
        });

        $this->app->singleton('translator', function (Container $app) {
            $loader = $app->make('translation.loader');

            $trans = new Translator($loader, 'en');

            return $trans;
        });

        $this->app->singleton('validator', function (Container $app) {
            return new Factory($app->make('translator'), $app);
        });
    }
}
