<?php
/**
 * @link https://github.com/illuminatech
 * @copyright Copyright (c) 2015 Illuminatech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace Illuminatech\Config;

use Illuminate\Contracts\Config\Repository;

/**
 * Item represents a single configuration item.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class Item
{
    /**
     * @var string config parameter unique identifier.
     */
    public $id;

    /**
     * @var string key to be used for storage in config repository.
     * For example: 'mail.driver'.
     */
    public $key;

    /**
     * @var string verbose label for the config value.
     */
    public $label;

    /**
     * @var array validation rules for this item.
     */
    public $rules = [];

    /**
     * @var string|null native type for the value to be cast to.
     */
    public $cast;

    /**
     * @var \Illuminate\Contracts\Config\Repository config repository to store this item value.
     */
    private $repository;

    /**
     * Constructor.
     *
     * @param  Repository  $configRepository config repository to store this item value.
     * @param  array  $config this item properties to be set in format: [name => value].
     */
    public function __construct(Repository $configRepository, array $config)
    {
        $this->repository = $configRepository;

        if (! isset($config['key'])) {
            throw new \InvalidArgumentException('"'.get_class($this).'::$key" must be specified.');
        }

        $this->key = $config['key'];
        $this->id = $config['id'] ?? $this->key;
        $this->label = $config['label'] ?? ucwords(str_replace(['.', '-', '_'], ' ', $this->id));
        $this->rules = $config['rules'] ?? ['sometimes', 'required'];
        $this->cast = $config['cast'] ?? null;
    }

    /**
     * Returns value for this item from related config repository.
     *
     * @param  mixed|null  $default
     * @return mixed
     */
    public function getValue($default = null)
    {
        return $this->repository->get($this->key, $default);
    }

    /**
     * Sets the value for this item inside related config repository.
     *
     * @param  mixed  $value
     * @return static self reference.
     */
    public function setValue($value): self
    {
        $this->repository->set($this->key, $value);

        return $this;
    }

    /**
     * Prepares value for the saving into persistent storage, performing typecast if necessary.
     *
     * @param  mixed  $value raw config value.
     * @return mixed value to be saved in persistent storage.
     */
    public function saveValue($value)
    {
        $this->setValue($value);

        if ($this->cast === null) {
            return $value;
        }

        if ($value === null || is_scalar($value)) {
            return $value;
        }

        return json_encode($value);
    }

    /**
     * Restores value from the raw one extracted from persistent storage, performing typecast if necessary.
     *
     * @param  mixed  $value value from persistent storage.
     * @return mixed actual config value.
     */
    public function restoreValue($value)
    {
        $value = $this->castValue($value);

        $this->setValue($value);

        return $value;
    }

    /**
     * Typecasts raw value to the acount according to {@link $cast} value.
     *
     * @param  string  $value value from persistent storage.
     * @return mixed actual value after typecast.
     */
    protected function castValue($value)
    {
        if ($this->cast === null) {
            return $value;
        }

        if ($value === null) {
            return $value;
        }

        switch ($this->cast) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return (float) $value;
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'object':
                return json_decode($value);
            case 'array':
            case 'json':
                return json_decode($value, true);
            default:
                throw new \InvalidArgumentException('Unsupported "'.get_class($this).'::$cast" value: '.print_r($this->cast, true));
        }
    }
}
