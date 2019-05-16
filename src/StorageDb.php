<?php

namespace Illuminatech\Config;

use Illuminate\Database\Connection;

/**
 * StorageDb uses database table for the config values storage.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class StorageDb implements StorageContact
{
    /**
     * @var string name of the table, which should store values.
     */
    public $table = 'configs';

    /**
     * @var string name of the column, which should store config item key.
     * @since 1.0.7
     */
    public $keyColumn = 'key';

    /**
     * @var string name of the column, which should store config item value.
     * @since 1.0.7
     */
    public $valueColumn = 'value';

    /**
     * @var array filter condition for records query restriction.
     * @see \Illuminate\Database\Query\Builder::where()
     */
    public $filter = [];

    /**
     * @var Connection the DB connection object.
     */
    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * {@inheritDoc}
     */
    public function save(array $values): bool
    {
        $existingValues = $this->get();

        foreach ($values as $key => $value) {
            if (array_key_exists($key, $existingValues)) {
                if ($value === $existingValues[$key]) {
                    continue;
                }

                $this->connection->table($this->table)
                    ->where($this->filter)
                    ->where([$this->keyColumn => $key])
                    ->update([$this->valueColumn => $value]);
            } else {
                $this->connection->table($this->table)
                    ->insert(array_merge(
                        $this->filter,
                        [
                            $this->keyColumn => $key,
                            $this->valueColumn => $value,
                        ]
                    ));
            }
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function get(): array
    {
        return $this->connection->table($this->table)
            ->where($this->filter)
            ->get()
            ->pluck($this->valueColumn, $this->keyColumn)
            ->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): bool
    {
        $this->connection->table($this->table)
            ->where($this->filter)
            ->delete();

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function clearValue($key): bool
    {
        $this->connection->table($this->table)
            ->where($this->filter)
            ->where([$this->keyColumn => $key])
            ->delete();

        return true;
    }
}
