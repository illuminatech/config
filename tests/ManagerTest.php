<?php

namespace Illuminatech\Config\Test;

use Illuminatech\Config\Item;
use Illuminatech\Config\Manager;
use Illuminate\Cache\ArrayStore;
use Illuminatech\Config\StorageArray;
use Illuminate\Validation\ValidationException;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository as ConfigRepository;

class ManagerTest extends TestCase
{
    /**
     * @var \Illuminate\Contracts\Config\Repository test config repository.
     */
    protected $repository;

    /**
     * @var \Illuminatech\Config\StorageDb test storage.
     */
    protected $storage;

    /**
     * @var \Illuminatech\Config\Manager test manager.
     */
    protected $manager;

    /**
     * {@inheritdoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->repository = new ConfigRepository();
        $this->storage = new StorageArray();
        $this->manager = new Manager($this->repository, $this->storage);
    }

    public function testSetupItems()
    {
        $this->manager->setItems([
            'test.name',
            'test.title',
            'test.number' => [
                'rules' => ['required', 'numeric'],
            ],
        ]);

        $items = $this->manager->getItems();

        $this->assertCount(3, $items);
        $this->assertTrue($items->first() instanceof Item);
    }

    /**
     * @depends testSetupItems
     */
    public function testSave()
    {
        $this->manager->setItems([
            'test.name',
            'test.title',
        ]);

        $values = [
            'test.name' => 'Test name',
            'test.title' => 'Test title',
        ];
        $this->manager->save($values);

        $this->assertEquals($values, $this->storage->get());
        $this->assertEquals('Test name', $this->manager->getItems()['test.name']->getValue());
    }

    /**
     * @depends testSave
     */
    public function testRestore()
    {
        $this->manager->setItems([
            'test.name',
            'test.title',
        ]);

        $values = [
            'test.name' => 'Test name',
            'test.title' => 'Test title',
        ];
        $this->storage->save($values);

        $this->manager->restore();
        $this->assertEquals('Test name', $this->manager->getItems()['test.name']->getValue());
        $this->assertEquals('Test name', $this->repository->get('test.name'));
    }

    /**
     * @depends testSetupItems
     */
    public function testValidate()
    {
        $this->manager->setItems([
            'test.name',
            'test.title',
            'test.number' => [
                'rules' => ['required', 'numeric'],
            ],
        ]);

        $values = [
            'test.name' => 'name',
            'test.title' => 'title',
            'test.number' => '12',
        ];

        $validatedValues = $this->manager->validate($values);
        $this->assertEquals($values, $validatedValues);

        $values = [
            'test.name' => '',
            'test.title' => 'title',
            'test.number' => 'invalid',
        ];

        try {
            $this->manager->validate($values);
        } catch (ValidationException $validationException) {}

        $this->assertTrue(isset($validationException));
        $errors = $validationException->validator->getMessageBag()->getMessages();
        $this->assertCount(2, $errors);
        $this->assertTrue(isset($errors['test.name']));
        $this->assertTrue(isset($errors['test.number']));
    }

    /**
     * @depends testRestore
     */
    public function testCache()
    {
        $cache = new CacheRepository(new ArrayStore());

        $this->manager->setCache($cache);

        $this->manager->setItems([
            'test.name',
        ]);

        $values = [
            'test.name' => 'Cached name',
        ];
        $this->manager->save($values);

        $this->assertTrue($cache->has($this->manager->cacheKey));

        $this->storage->save([
            'test.name' => 'Changed name',
        ]);

        $this->manager->restore();

        $this->assertEquals('Cached name', $this->manager->getItems()['test.name']->getValue());

        $this->manager->clear();
        $this->assertFalse($cache->has($this->manager->cacheKey));
    }
}
