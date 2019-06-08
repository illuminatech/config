<?php
/**
 * @link https://github.com/illuminatech
 * @copyright Copyright (c) 2015 Illuminatech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace Illuminatech\Config;

use Throwable;
use ArrayAccess;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use Psr\SimpleCache\CacheInterface as CacheContract;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Contracts\Config\Repository as RepositoryContract;

/**
 * PersistentRepository is a configuration repository, which stores some of its data in persistent storage like database.
 *
 * Config data, which should be saved in persistent storage defined via {@link setItems()}. It will be automatically retrieved
 * on the first attempt to access data in this repository. It also can be done manually vai {@link restore()} method.
 * In order to save data to the persistent storage use method {@link save()}.
 *
 * PersistentRepository fully decorates the config repository and can substitute `Illuminate\Config\Repository` instance.
 * For example, you can replace Laravel standard configuration repository with this one:
 *
 * ```php
 * use Illuminatech\Config\StorageDb;
 * use Illuminate\Support\ServiceProvider;
 * use Illuminate\Contracts\Config\Repository;
 * use Illuminatech\Config\PersistentRepository;
 *
 * class AppServiceProvider extends ServiceProvider
 * {
 *     public function register()
 *     {
 *         $this->app->extend('config', function (Repository $originConfig) {
 *             $storage = new StorageDb($this->app->make('db.connection'));
 *
 *             $newConfig = (new PersistentRepository($originConfig, $storage))
 *                 ->setItems([
 *                     'mail.contact.address' => [
 *                         'label' => 'Email address receiving contact messages',
 *                         'rules' => ['sometimes', 'required', 'email'],
 *                     ],
 *                     // ...
 *                 ]);
 *
 *             return $newConfig;
 *         });
 *
 *         // ...
 *     }
 * }
 * ```
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class PersistentRepository implements ArrayAccess, RepositoryContract
{
    /**
     * @var string key used to store values into the cache.
     */
    public $cacheKey = __CLASS__;

    /**
     * @var int|\DateInterval The TTL (e.g. lifetime) value for the cache.
     */
    public $cacheTtl = 3600 * 24;

    /**
     * @var bool whether automatic garbage collection should take place on values saving.
     * @see gc()
     */
    public $gcEnabled = true;

    /**
     * @var \Illuminate\Contracts\Config\Repository wrapped config repository.
     */
    private $repository;

    /**
     * @var \Illuminatech\Config\StorageContact config persistent storage.
     */
    private $storage;

    /**
     * @var \Psr\SimpleCache\CacheInterface|null cache instance to be used.
     */
    private $cache;

    /**
     * @var \Illuminate\Support\Collection|\Illuminatech\Config\Item[] configuration items, which values should be placed in persistent storage.
     */
    private $items;

    /**
     * @var array|null cached list of config item keys.
     */
    private $itemKeys;

    /**
     * @var bool whether data has been retrieved from persistent storage and applied to the repository.
     */
    protected $isRestored = false;

    /**
     * Constructor.
     *
     * @param  RepositoryContract  $configRepository config repository to be decorated.
     * @param  StorageContact  $storage config values persistent storage.
     * @param  \Psr\SimpleCache\CacheInterface  $cache cache repository to be used.
     */
    public function __construct(RepositoryContract $configRepository, StorageContact $storage, CacheContract $cache = null)
    {
        $this->repository = $configRepository;
        $this->storage = $storage;
        $this->cache = $cache;
    }

    /**
     * @return \Illuminate\Support\Collection|\Illuminatech\Config\Item[] configuration items.
     */
    public function getItems(): Collection
    {
        if ($this->items === null) {
            $this->setItems([]);
        }

        return $this->items;
    }

    /**
     * Sets configuration items, which values should be placed in persistent storage.
     *
     * Example:
     *
     * ```php
     * [
     *     'some.config.value',
     *     'another.config.value' => [
     *         'label' => 'Custom label',
     *         'rules' => ['required', 'numeric'],
     *     ],
     *     [
     *         'key' => 'array.config.value',
     *         'rules' => ['required', 'array'],
     *         'cast' => 'array',
     *     ],
     * ]
     * ```
     *
     * @see \Illuminatech\Config\Item
     *
     * @param  \Illuminatech\Config\Item[]|array  $items
     * @return static self reference.
     */
    public function setItems(array $items): self
    {
        $collection = new Collection();

        foreach ($items as $key => $value) {
            if ($value instanceof Item) {
                $item = $value;
            } else {
                if (is_scalar($value) && is_numeric($key)) {
                    $value = [
                        'key' => $value,
                    ];
                }

                if (!isset($value['key'])) {
                    $value['key'] = $key;
                }

                $item = new Item($value);
            }

            $item->setRepository($this->repository);

            $collection->offsetSet($item->id, $item);
        }

        $this->items = $collection;
        $this->itemKeys = null;

        return $this;
    }

    /**
     * Saves config item values into persistent storage.
     *
     * @param  array  $values config item values in format: `[id => value]`.
     * @return static self reference.
     */
    public function save(array $values): self
    {
        /* @var $items Item[] */
        $items = $this->getItems()->keyBy('id');

        $storeValues = [];
        foreach ($values as $id => $value) {
            if (! isset($items[$id])) {
                continue;
            }
            $value = $items[$id]->saveValue($value);

            $storeValues[$items[$id]->key] = $value;
        }

        $this->storage->save($storeValues);

        $this->setCached($storeValues);

        if ($this->gcEnabled) {
            $this->gc();
        }

        return $this;
    }

    /**
     * Saves current values of the config items into persistent storage.
     *
     * @return static self reference.
     */
    public function synchronize(): self
    {
        $values = [];
        foreach ($this->getItems() as $item) {
            $values[$item->key] = $item->getValue();
        }

        return $this->save($values);
    }

    /**
     * Restores values from the persistent storage to the config repository.
     * In case storage failed by some reason no immediate exception is thrown - error will be sent to the log instead.
     *
     * @return static self reference.
     */
    public function restore(): self
    {
        /* @var $items Collection|Item[] */
        $items = $this->getItems()->keyBy('key');

        $values = $this->getCached();
        if ($values === null) {
            try {
                // storage may fail at some project state, like database table not yet created.
                $values = $this->storage->get();

                $this->setCached($values);
            } catch (Throwable $exception) {
                $this->logException($exception);
                $values = [];
            }
        }

        foreach ($values as $key => $value) {
            if (! $items->offsetExists($key)) {
                continue;
            }

            $items[$key]->restoreValue($value);
        }

        $this->isRestored = true;

        return $this;
    }

    /**
     * Clears all config values in persistent storage, restoring original values to repository.
     *
     * @return static self reference.
     */
    public function reset(): self
    {
        $this->deleteCached();

        $this->storage->clear();

        $this->getItems()->map(function (Item $item) {
            $item->resetValue();
        });

        return $this;
    }

    /**
     * Clear value, saved in persistent storage, for the specified item, restoring its original value.
     *
     * @param  string  $key the key of the item to be cleared.
     * @return static self reference.
     */
    public function resetValue($key): self
    {
        $this->deleteCached();

        $this->storage->clearValue($key);

        $this->getItems()->where('key', $key)->map(function (Item $item) {
            $item->resetValue();
        });

        return $this;
    }

    /**
     * Clears keys in the persistent storage, which have no match to currently configured {@link $items}.
     *
     * @return static self reference.
     */
    public function gc(): self
    {
        $existingValues = $this->storage->get();

        $itemKeys = $this->getItemKeys();

        foreach ($existingValues as $key => $value) {
            if (! in_array($key, $itemKeys, true)) {
                $this->storage->clearValue($key);
            }
        }

        return $this;
    }

    /**
     * Creates new validator instance for config item values validation.
     * This method takes into account usage of dot ('.') symbol at item IDs, performing escapes for the rule definitions.
     *
     * @param  array  $values raw input to be validated.
     * @return \Illuminate\Contracts\Validation\Validator validator instance.
     */
    public function makeValidator(array $values): Validator
    {
        $rules = [];
        foreach ($this->getItems() as $item) {
            $inputName = str_replace('.', '->', $item->id);
            $rules[$inputName] = $item->rules;
        }

        return ValidatorFacade::make($values, $rules);
    }

    /**
     * Validates data to be set as config item values.
     * This method takes into account usage of dot ('.') symbol at item IDs, ensuring input will not be considered as an array.
     *
     * @param  array  $values raw data to be validated.
     * @return array validated data.
     * @throws \Illuminate\Validation\ValidationException if validation fails.
     */
    public function validate(array $values): array
    {
        $items = $this->getItems();

        $validator = $this->makeValidator($values);

        if ($validator->fails()) {
            $errors = [];
            foreach ($validator->errors()->getMessages() as $key => $messages) {
                $itemId = str_replace('->', '.', $key);
                $errors[$itemId] = [];
                foreach ($messages as $message) {
                    $itemLabel = $items[$itemId]->label;
                    $errors[$itemId][] = str_replace(
                        [
                            $key,
                            Str::ucfirst($key),
                            Str::upper($key),
                        ],
                        [
                            $itemLabel,
                            Str::ucfirst($itemLabel),
                            Str::upper($itemLabel),
                        ],
                        $message
                    );
                }
            }

            throw ValidationException::withMessages($errors);
        }

        $itemValues = [];
        foreach ($validator->validated() as $key => $value) {
            $itemId = str_replace('->', '.', $key);
            $itemValues[$itemId] = $value;
        }

        return $itemValues;
    }

    /**
     * Writes the log about given exception.
     *
     * @param \Throwable $exception exception to be logged.
     */
    protected function logException(Throwable $exception): void
    {
        try {
            Log::error($exception->getMessage(), [
                'exception' => $exception
            ]);
        } catch (Throwable $e) {
            error_log($exception->getMessage());
        }
    }

    /**
     * @return array cached list of config item keys.
     */
    private function getItemKeys(): array
    {
        if ($this->itemKeys === null) {
            $this->itemKeys = $this->getItems()->pluck('key')->toArray();
        }

        return $this->itemKeys;
    }

    /**
     * Checks whether given config key matches some of keys from {@link $items}, e.g. whether the key should be saved
     * in persistent storage or not.
     *
     * @param  string  $key config key.
     * @return bool whether given key is a persistent one or not.
     */
    private function isPersistentKey($key): bool
    {
        if (is_array($key)) {
            $key = \array_key_first($key);
        }

        foreach ($this->getItemKeys() as $persistentKey) {
            $key = $key.'.';
            $persistentKey = $persistentKey.'.';

            if (strncmp($key, $persistentKey, min(strlen($key), strlen($persistentKey))) === 0) {
                return true;
            }
        }

        return false;
    }

    // Cache :

    /**
     * Sets cache repository to be used for config values caching.
     *
     * @param  \Psr\SimpleCache\CacheInterface  $cache cache repository to be used.
     * @return static self reference.
     */
    public function setCache(CacheContract $cache)
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * Returns config values from the cache.
     *
     * @return array|null config values, `null` - if not cached.
     */
    protected function getCached()
    {
        if ($this->cache === null) {
            return null;
        }

        return $this->cache->get($this->cacheKey);
    }

    /**
     * Stores config values into the cache.
     *
     * @param  array  $values config values to be cached.
     */
    protected function setCached(array $values): void
    {
        if ($this->cache === null) {
            return;
        }

        $this->cache->set($this->cacheKey, $values, $this->cacheTtl);
    }

    /**
     * Clears cached config values.
     */
    protected function deleteCached(): void
    {
        if ($this->cache === null) {
            return;
        }

        $this->cache->delete($this->cacheKey);
    }

    // Config Repository Contract :

    /**
     * {@inheritDoc}
     */
    public function has($key)
    {
        if ($this->repository->has($key)) {
            return true;
        }

        return $this->getItems()->where('key', $key)->count() > 0;
    }

    /**
     * {@inheritDoc}
     */
    public function get($key, $default = null)
    {
        if (! $this->isRestored && $this->isPersistentKey($key)) {
            $this->restore();
        }

        return $this->repository->get($key, $default);
    }

    /**
     * {@inheritDoc}
     */
    public function all()
    {
        if (! $this->isRestored) {
            $this->restore();
        }

        return $this->repository->all();
    }

    /**
     * {@inheritDoc}
     */
    public function set($key, $value = null)
    {
        if (! $this->isRestored && $this->isPersistentKey($key)) {
            $this->restore();
        }

        $this->repository->set($key, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function prepend($key, $value)
    {
        if (! $this->isRestored && $this->isPersistentKey($key)) {
            $this->restore();
        }

        $this->repository->prepend($key, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function push($key, $value)
    {
        if (! $this->isRestored && $this->isPersistentKey($key)) {
            $this->restore();
        }

        $this->repository->push($key, $value);
    }

    // Array Access :

    /**
     * Determine if the given configuration option exists.
     *
     * @param  string  $key
     * @return bool
     */
    public function offsetExists($key)
    {
        return $this->has($key);
    }

    /**
     * Get a configuration option.
     *
     * @param  string  $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->get($key);
    }

    /**
     * Set a configuration option.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($key, $value)
    {
        $this->set($key, $value);
    }

    /**
     * Unset a configuration option.
     *
     * @param  string  $key
     * @return void
     */
    public function offsetUnset($key)
    {
        $this->set($key, null);
    }

    // Self Configure :

    /**
     * @param  string  $cacheKey key used to store values into the cache.
     * @return static self reference.
     */
    public function setCacheKey(string $cacheKey): self
    {
        $this->cacheKey = $cacheKey;

        return $this;
    }

    /**
     * @param  int|\DateInterval  $cacheTtl The TTL (e.g. lifetime) value for the cache.
     * @return static self reference.
     */
    public function setCacheTtl($cacheTtl): self
    {
        $this->cacheTtl = $cacheTtl;

        return $this;
    }

    /**
     * @see gc()
     *
     * @param  bool  $gcEnabled whether automatic garbage collection should take place on values saving.
     * @return static self reference.
     */
    public function setGcEnabled(bool $gcEnabled): self
    {
        $this->gcEnabled = $gcEnabled;

        return $this;
    }
}
