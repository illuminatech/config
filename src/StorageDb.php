<?php
/**
 * @link https://github.com/illuminatech
 * @copyright Copyright (c) 2015 Illuminatech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace Illuminatech\Config;

use Illuminate\Database\Connection;

/**
 * StorageDb uses database table for the config values storage.
 *
 * Database migration example:
 *
 * ```php
 * Schema::create('configs', function (Blueprint $table) {
 *     $table->string('key')->primary();
 *     $table->string('value')->nullable();
 * });
 * ```
 *
 * Instantiation example:
 *
 * ```php
 * use Illuminatech\Config\StorageDb;
 * use Illuminate\Support\Facades\App;
 *
 * $storage = (new StorageDb(App::make('db.connection')))
 *     ->setTable('configs')
 *     ->setKeyColumn('key')
 *     ->setValueColumn('value')
 *     ->setFilter(['category_id' => 'global']);
 * ```
 *
 * > Note: this storage requires "illuminate/database" package installed.
 *
 * @see \Illuminatech\Config\StorageEloquent
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
     */
    public $keyColumn = 'key';

    /**
     * @var string name of the column, which should store config item value.
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

    /**
     * Constructor.
     * @param  Connection  $connection DB connection to be used.
     */
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

    // Self Configure :

    /**
     * @param  string  $table name of the table, which should store values.
     * @return static self reference.
     */
    public function setTable(string $table): self
    {
        $this->table = $table;

        return $this;
    }

    /**
     * @param  string  $keyColumn name of the column, which should store config item key.
     * @return static self reference.
     */
    public function setKeyColumn(string $keyColumn): self
    {
        $this->keyColumn = $keyColumn;

        return $this;
    }

    /**
     * @param  string  $valueColumn name of the column, which should store config item value.
     * @return static self reference.
     */
    public function setValueColumn(string $valueColumn): self
    {
        $this->valueColumn = $valueColumn;

        return $this;
    }

    /**
     * @see \Illuminate\Database\Builder::where()
     *
     * @param  array  $filter filter condition for records query restriction.
     * @return static self reference.
     */
    public function setFilter($filter): self
    {
        $this->filter = $filter;

        return $this;
    }
}
