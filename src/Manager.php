<?php

namespace Illuminatech\Config;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Contracts\Config\Repository as RepositoryContract;

class Manager implements RepositoryContract
{
    /**
     * @var \Illuminate\Contracts\Config\Repository wrapped config repository.
     */
    private $repository;

    /**
     * @var \Illuminatech\Config\StorageContact config persistent storage.
     */
    private $storage;

    /**
     * @var \Illuminate\Support\Collection|\Illuminatech\Config\Item[]
     */
    private $items;

    /**
     * @var bool whether data has been retrieved from persistent storage and applied to the repository.
     */
    protected $isRestored = false;

    public function __construct(RepositoryContract $configRepository, StorageContact $storage)
    {
        $this->repository = $configRepository;
        $this->storage = $storage;
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
     * @param  array  $items
     * @return static self reference.
     */
    public function setItems(array $items): self
    {
        $collection = new Collection();

        foreach ($items as $key => $value) {
            if (is_scalar($value) && is_numeric($key)) {
                $value = [
                    'key' => $value,
                ];
            }

            if (! isset($value['key'])) {
                $value['key'] = $key;
            }

            $item = new Item($this->repository, $value);

            $collection->offsetSet($item->id, $item);
        }

        $this->items = $collection;

        return $this;
    }

    public function save(array $values): bool
    {
        /* @var $items Item[] */
        $items = $this->getItems()->keyBy('id');

        $storeValues = [];
        foreach ($values as $id => $value) {
            if (! isset($items[$id])) {
                continue;
            }
            $items[$id]->setValue($value);

            $storeValues[$items[$id]->key] = $value;
        }

        return $this->storage->save($storeValues);
    }

    public function restore(): self
    {
        /* @var $items Item[] */
        $items = $this->getItems()->keyBy('key');

        $values = $this->storage->get();

        foreach ($values as $key => $value) {
            if (! $items->offsetExists($key)) {
                continue;
            }

            $items[$key]->setValue($value);

            $this->repository->set($key, $value);
        }

        $this->isRestored = true;

        return $this;
    }

    /**
     * @param  array  $values raw data to be validated.
     * @return array validated data.
     * @throws \Illuminate\Validation\ValidationException if validation fails.
     */
    public function validate(array $values): array
    {
        $items = $this->getItems();

        $rules = [];
        $itemValues = [];
        $customAttributes = [];
        foreach ($items as $item) {
            $inputName = str_replace('.', '\.', $item->id);
            $rules[$inputName] = $item->rules;

            if (array_key_exists($item->id, $values)) {
                $itemValues[$item->id] = $values[$item->id];
            }
            $customAttributes[$item->id]= $item->label;
        }

        $validator = Validator::make($itemValues, $rules, [], $customAttributes);

        if ($validator->fails()) {
            $errors = [];
            foreach ($validator->errors()->getMessages() as $key => $messages) {
                $itemId = str_replace('->', '.', $key);
                $errors[$itemId] = [];
                foreach ($messages as $message) {
                    $errors[$itemId][] = str_replace($key, $items[$itemId]->label, $message);
                }
            }

            throw ValidationException::withMessages($errors);
        }

        return $itemValues;
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
        if (! $this->isRestored) {
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
        if (! $this->isRestored) {
            $this->restore();
        }

        $this->repository->set($key, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function prepend($key, $value)
    {
        if (! $this->isRestored) {
            $this->restore();
        }

        $this->repository->prepend($key, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function push($key, $value)
    {
        if (! $this->isRestored) {
            $this->restore();
        }

        $this->repository->push($key, $value);
    }
}
