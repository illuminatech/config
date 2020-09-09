<?php
/**
 * @link https://github.com/illuminatech
 * @copyright Copyright (c) 2019 Illuminatech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace Illuminatech\Config\Console;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Console\ConfigCacheCommand as BaseCommand;
use Illuminatech\Config\PersistentRepository;

/**
 * ConfigCacheCommand enhances standard Laravel's one bypassing config persistent storage.
 *
 * This command solves the problem of caching values from persistent storage, eliminating ability to restore the defaults.
 *
 * @see \Illuminate\Foundation\Console\ConfigCacheCommand
 * @see \Illuminatech\Config\PersistentRepository
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.1.1
 */
class ConfigCacheCommand extends BaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected function getFreshConfiguration(): array
    {
        /** @var $app \Illuminate\Contracts\Foundation\Application */
        $app = require $this->laravel->bootstrapPath().'/app.php';

        $app->useStoragePath($this->laravel->storagePath());

        $app->make(Kernel::class)->bootstrap();

        $config = $app->make('config');

        if ($config instanceof PersistentRepository) {
            return $config->getRepository()->all();
        }

        return $config->all();
    }
}
