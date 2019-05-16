<?php

namespace Illuminatech\Config;

/**
 * StorageArray uses internal array for the config storage.
 *
 * This class can be useful in unit tests.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class StorageArray implements StorageContact
{
    /**
     * @var array stored data.
     */
    protected $data = [];

    /**
     * {@inheritDoc}
     */
    public function save(array $values): bool
    {
        $this->data = array_merge($this->data, $values);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function get(): array
    {
        return $this->data;
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): bool
    {
        $this->data = [];

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function clearValue($key): bool
    {
        unset($this->data[$key]);

        return true;
    }
}
