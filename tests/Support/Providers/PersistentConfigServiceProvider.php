<?php

namespace Illuminatech\Config\Test\Support\Providers;

use Illuminatech\Config\Providers\AbstractPersistentConfigServiceProvider;
use Illuminatech\Config\StorageArray;
use Illuminatech\Config\StorageContract;

class PersistentConfigServiceProvider extends AbstractPersistentConfigServiceProvider
{
    /**
     * {@inheritdoc}
     */
    protected function storage(): StorageContract
    {
        return new StorageArray();
    }

    /**
     * {@inheritdoc}
     */
    protected function items(): array
    {
        return [
            'test.name',
            'test.title',
        ];
    }
}
