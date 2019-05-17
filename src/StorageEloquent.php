<?php
/**
 * @link https://github.com/illuminatech
 * @copyright Copyright (c) 2015 Illuminatech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace Illuminatech\Config;

use Illuminate\Database\Eloquent\Model;

/**
 * StorageEloquent uses Eloquent model for the config values storage.
 *
 * This storage has degraded performance in comparison to `StorageDb`, but provides more flexibility, allowing usage
 * Eloquent features like events.
 *
 * @see \Illuminate\Database\Eloquent\Model
 * @see \Illuminatech\Config\StorageDb
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class StorageEloquent implements StorageContact
{
    /**
     * @var string|\Illuminate\Database\Eloquent\Model name of the Eloquent model class, which should store the config values.
     */
    public $model;

    /**
     * @var string name of the model attribute, which should store config item key.
     */
    public $keyAttribute = 'key';

    /**
     * @var string name of the model attribute, which should store config item value.
     */
    public $valueAttribute = 'value';

    /**
     * @var array filter condition for records query restriction.
     * @see \Illuminate\Database\Eloquent\Builder::where()
     */
    public $filter = [];

    /**
     * Constructor.
     * @param  string|null  $model name of the Eloquent model class, which should store the config values.
     */
    public function __construct(string $model = null)
    {
        $this->model = $model;
    }

    /**
     * {@inheritDoc}
     */
    public function save(array $values): bool
    {
        foreach ($values as $key => $value) {
            $this->model::query()
                ->updateOrCreate(
                    array_merge(
                        $this->filter,
                        [
                            $this->keyAttribute => $key,
                        ]
                    ),
                    array_merge(
                        $this->filter,
                        [
                            $this->keyAttribute => $key,
                            $this->valueAttribute => $value,
                        ]
                    )
                );
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function get(): array
    {
        return $this->model::query()
            ->where($this->filter)
            ->pluck($this->valueAttribute, $this->keyAttribute)
            ->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): bool
    {
        $this->model::query()
            ->where($this->filter)
            ->get()
            ->each(function (Model $model) {
                $model->delete();
            });

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function clearValue($key): bool
    {
        $this->model::query()
            ->where($this->filter)
            ->where([$this->keyAttribute => $key])
            ->get()
            ->each(function (Model $model) {
                $model->delete();
            });

        return true;
    }
}
