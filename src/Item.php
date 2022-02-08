<?php
/**
 * @link https://github.com/illuminatech
 * @copyright Copyright (c) 2019 Illuminatech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace Illuminatech\Config;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use RuntimeException;

/**
 * Item represents a single configuration item.
 *
 * Item should be bound to the raw config repository (e.g. not persistent one).
 * It is responsible for setting and retrieving data from repository, performing its transformation if necessary.
 *
 * Item holds some data, which can be used for 'config setup' user interface composition, such as label, validation rules and other.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class Item implements Arrayable
{
    /**
     * @var string config parameter unique identifier.
     * This value will be used in request fields and form inputs.
     * You should avoid usage of non-alphanumeric characters here, since PHP automatically replaces them with underscore ('_')
     * during native POST request parsing, which may cause unexpected behavior.
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
     * @var string|null verbose description for the config value.
     */
    public $hint;

    /**
     * @var array validation rules for this item.
     */
    public $rules = [];

    /**
     * @var string|null native type for the value to be cast to.
     */
    public $cast;

    /**
     * @var array|null additional descriptive options for this item.
     * This field may contain any data, which can be consumed by other part of the program.
     * For example it may hold options for the from input composition:
     *
     * ```php
     * [
     *    'inputType' => 'text',
     *    'inputCssClass' => 'config-input',
     * ]
     * ```
     *
     * @since 1.0.1
     */
    public $options;

    /**
     * @var bool whether to encrypt value for the storage or not.
     */
    public $encrypt = false;

    /**
     * @var \Illuminate\Contracts\Config\Repository config repository to store this item value.
     */
    private $repository;

    /**
     * @var mixed origin (before apply persistent storage) value of this item.
     */
    private $originValue;

    /**
     * Constructor.
     *
     * @param array $config this item properties to be set in format: [name => value].
     */
    public function __construct(array $config)
    {
        if (! isset($config['key'])) {
            throw new InvalidArgumentException('"' . get_class($this) . '::$key" must be specified.');
        }

        $this->key = $config['key'];
        $this->id = $config['id'] ?? $this->key;
        $this->label = $config['label'] ?? ucwords(str_replace(['.', '-', '_'], ' ', $this->id));
        $this->hint = $config['hint'] ?? null;
        $this->rules = $config['rules'] ?? ['sometimes', 'required'];
        $this->cast = $config['cast'] ?? null;
        $this->encrypt = $config['encrypt'] ?? null;
        $this->options = $config['options'] ?? null;
    }

    /**
     * Binds this item to the given config repository.
     *
     * @param \Illuminate\Contracts\Config\Repository $configRepository config repository to store this item value.
     * @return static self reference.
     */
    public function setRepository(Repository $configRepository): self
    {
        $this->repository = $configRepository;
        $this->originValue = null;

        return $this;
    }

    /**
     * @return \Illuminate\Contracts\Config\Repository config repository to store this item value.
     */
    public function getRepository(): Repository
    {
        if ($this->repository === null) {
            throw new RuntimeException("Item '{$this->id}' is not bound to any config repository.");
        }

        return $this->repository;
    }

    /**
     * Returns value for this item from related config repository.
     *
     * @param mixed|null $default
     * @return mixed
     */
    public function getValue($default = null)
    {
        return $this->getRepository()->get($this->key, $default);
    }

    /**
     * Sets the value for this item inside related config repository.
     *
     * @param mixed $value
     * @return static self reference.
     */
    public function setValue($value): self
    {
        if ($this->originValue === null) {
            $this->originValue['value'] = $this->getValue();
        }

        $this->getRepository()->set($this->key, $value);

        return $this;
    }

    /**
     * Prepares value for the saving into persistent storage, performing typecast if necessary.
     *
     * @param mixed $value raw config value.
     * @return mixed value to be saved in persistent storage.
     */
    public function saveValue($value)
    {
        $this->setValue($value);

        $value = $this->serializeValue($value);

        if ($this->encrypt) {
            $value = $this->encrypt($value);
        }

        return $value;
    }

    /**
     * Restores value from the raw one extracted from persistent storage, performing typecast if necessary.
     *
     * @param mixed $value value from persistent storage.
     * @return mixed actual config value.
     */
    public function restoreValue($value)
    {
        if ($this->encrypt) {
            $value = $this->decrypt($value);
        }

        $value = $this->castValue($value);

        $this->setValue($value);

        return $value;
    }

    /**
     * Restores original (before apply persistent storage) value of this item.
     *
     * @return static self reference.
     */
    public function resetValue(): self
    {
        if ($this->originValue !== null) {
            $this->setValue($this->originValue['value']);
            $this->originValue = null;
        }

        return $this;
    }

    /**
     * Prepares raw value for the persistent storage according to {@see $cast} value.
     *
     * @param mixed $value raw value.
     * @return mixed serialized value.
     */
    protected function serializeValue($value)
    {
        if ($this->cast === null) {
            return $value;
        }

        if ($value === null || is_scalar($value)) {
            return $value;
        }

        return json_encode($value);
    }

    /**
     * Typecasts raw value from persistent storage to the actual one according to {@see $cast} value.
     *
     * @param string $value value from persistent storage.
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
                throw new InvalidArgumentException('Unsupported "'.get_class($this).'::$cast" value: '.print_r($this->cast, true));
        }
    }

    /**
     * Encrypts value for the storage.
     *
     * @param string|mixed $value raw value.
     * @return string encrypted value.
     */
    protected function encrypt($value)
    {
        return Crypt::encryptString($value);
    }

    /**
     * Decrypts value from the storage.
     *
     * @param string|mixed $value encrypted value.
     * @return string decrypted value.
     */
    protected function decrypt($value)
    {
        return Crypt::decryptString($value);
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'label' => $this->label,
            'hint' => $this->hint,
            'rules' => $this->rules,
            'cast' => $this->cast,
            'options' => $this->options,
            'value' => $this->getValue(),
        ];
    }
}
