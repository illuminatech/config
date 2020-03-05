<?php

namespace Illuminatech\Config\Test\Providers;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminatech\Config\PersistentRepository;
use Illuminatech\Config\Test\Support\Providers\PersistentConfigServiceProvider;
use Illuminatech\Config\Test\TestCase;

class PersistentConfigServiceProviderTest extends TestCase
{
    public function testBoot()
    {
        $app = new Container();

        $app->singleton('config', function() {
            return new ConfigRepository([
                'test' => [
                    'name' => 'Test name',
                    'title' => 'Test title',
                ],
            ]);
        });

        $app->singleton('cache.store', function() {
            return new CacheRepository(new ArrayStore());
        });

        $serviceProvider = new PersistentConfigServiceProvider($app);
        $serviceProvider->boot();

        $config = $app->make('config');

        $this->assertTrue($config instanceof PersistentRepository);
    }
}
