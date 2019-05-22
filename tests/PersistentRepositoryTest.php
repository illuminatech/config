<?php

namespace Illuminatech\Config\Test;

use Illuminatech\Config\Item;
use Illuminate\Cache\ArrayStore;
use Illuminatech\Config\StorageArray;
use Illuminatech\Config\StorageContact;
use Illuminatech\Config\PersistentRepository;
use Illuminate\Validation\ValidationException;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository as ConfigRepository;

class PersistentRepositoryTest extends TestCase
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
     * @var \Illuminatech\Config\PersistentRepository test persistent repository.
     */
    protected $persistentRepository;

    /**
     * {@inheritdoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->repository = new ConfigRepository();
        $this->storage = new StorageArray();
        $this->persistentRepository = new PersistentRepository($this->repository, $this->storage);
    }

    public function testSetupItems()
    {
        $this->persistentRepository->setItems([
            'test.name',
            'test.title',
            'test.number' => [
                'rules' => ['required', 'numeric'],
            ],
        ]);

        $items = $this->persistentRepository->getItems();

        $this->assertCount(3, $items);
        $this->assertTrue($items->first() instanceof Item);
    }

    /**
     * @depends testSetupItems
     */
    public function testSave()
    {
        $this->persistentRepository->setItems([
            'test.name',
            'test.title',
        ]);

        $values = [
            'test.name' => 'Test name',
            'test.title' => 'Test title',
        ];
        $this->persistentRepository->save($values);

        $this->assertEquals($values, $this->storage->get());
        $this->assertEquals('Test name', $this->persistentRepository->getItems()['test.name']->getValue());
    }

    /**
     * @depends testSave
     */
    public function testSynchronize()
    {
        $this->persistentRepository->setItems([
            'test.name',
        ]);

        $this->persistentRepository->set('test.name', 'Test name');
        $this->persistentRepository->synchronize();

        $this->assertEquals(['test.name' => 'Test name'], $this->storage->get());
    }

    /**
     * @depends testSave
     */
    public function testRestore()
    {
        $this->persistentRepository->setItems([
            'test.name',
            'test.title',
        ]);

        $values = [
            'test.name' => 'Test name',
            'test.title' => 'Test title',
        ];
        $this->storage->save($values);

        $this->persistentRepository->restore();
        $this->assertEquals('Test name', $this->persistentRepository->getItems()['test.name']->getValue());
        $this->assertEquals('Test name', $this->repository->get('test.name'));
    }

    /**
     * @depends testSetupItems
     */
    public function testValidate()
    {
        $this->persistentRepository->setItems([
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

        $validatedValues = $this->persistentRepository->validate($values);
        $this->assertEquals($values, $validatedValues);

        $values = [
            'test.name' => '',
            'test.title' => 'title',
            'test.number' => 'invalid',
        ];

        try {
            $this->persistentRepository->validate($values);
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

        $this->persistentRepository->setCache($cache);

        $this->persistentRepository->setItems([
            'test.name',
        ]);

        $values = [
            'test.name' => 'Cached name',
        ];
        $this->persistentRepository->save($values);

        $this->assertTrue($cache->has($this->persistentRepository->cacheKey));

        $this->storage->save([
            'test.name' => 'Changed name',
        ]);

        $this->persistentRepository->restore();

        $this->assertEquals('Cached name', $this->persistentRepository->getItems()['test.name']->getValue());

        $this->persistentRepository->clear();
        $this->assertFalse($cache->has($this->persistentRepository->cacheKey));
    }

    /**
     * @depends testRestore
     */
    public function testTypeCast()
    {
        $this->persistentRepository->setItems([
            'test.array' => [
                'cast' => 'array',
            ],
        ]);

        $values = [
            'test.array' => [
                'some' => 'array'
            ],
        ];
        $this->persistentRepository->save($values);

        $this->persistentRepository->restore();
        $this->assertEquals(['some' => 'array'], $this->persistentRepository->getItems()['test.array']->getValue());

        $this->assertTrue(is_string($this->storage->get()['test.array']));
    }

    /**
     * Data provider for {@link testLazyRestore()}
     *
     * @return array test data.
     */
    public function dataProviderLazyRestore(): array
    {
        return [
            ['another', false],
            ['foo.another', false],
            ['bar-with-suffix', false],
            ['foo.name', true],
            ['foo', true],
            ['bar.block.nested', true],
        ];
    }

    /**
     * @depends testRestore
     *
     * @dataProvider dataProviderLazyRestore
     *
     * @param  string  $key config key.
     * @param  bool  $allowRestore whether values should be restored from storage.
     */
    public function testLazyRestore(string $key, bool $allowRestore)
    {
        $storage = $this->getMockBuilder(StorageContact::class)
            ->getMock();

        $persistentRepository = (new PersistentRepository($this->repository, $storage))
            ->setItems([
                'foo.name',
                'bar.block',
            ]);

        $storage->expects($allowRestore ? $this->once() : $this->never())
            ->method('get');

        $persistentRepository->get($key);
    }
}
