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
     * @var \Illuminate\Contracts\Config\Repository config repository to store this item value.
     */
    private $repository;

    public function __construct(Repository $configRepository, array $data)
    {
        $this->repository = $configRepository;

        if (! isset($data['key'])) {
            throw new \InvalidArgumentException('"'.get_class($this).'::$key" must be specified.');
        }

        $this->key = $data['key'];
        $this->id = $data['id'] ?? $this->key;
        $this->label = $data['label'] ?? ucwords(str_replace(['.', '-', '_'], ' ', $this->id));
        $this->rules = $data['rules'] ?? ['required'];
    }

    public function getValue($default = null)
    {
        return $this->repository->get($this->key, $default);
    }

    public function setValue($value): self
    {
        $this->repository->set($this->key, $value);

        return $this;
    }
}
