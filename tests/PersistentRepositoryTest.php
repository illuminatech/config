<?php

namespace Illuminatech\Config\Test;

use Psr\Log\NullLogger;
use Illuminatech\Config\Item;
use Illuminate\Cache\ArrayStore;
use Illuminate\Support\Facades\Log;
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

        Log::swap(new NullLogger());

        $this->repository = new ConfigRepository();
        $this->storage = new StorageArray();
        $this->persistentRepository = new PersistentRepository($this->repository, $this->storage);
    }

    public function testSetupItems()
    {
        $this->persistentRepository->setItems([
            'plain.key',
            'array.config' => [
                'rules' => ['required', 'numeric'],
            ],
            new Item(['key' => 'explicit.object']),
        ]);

        $items = $this->persistentRepository->getItems();

        $this->assertCount(3, $items);
        $this->assertTrue($items->first() instanceof Item);
        $this->assertSame($this->repository, $items->last()->getRepository());
    }

    public function testGetRepository()
    {
        $this->assertSame($this->repository, $this->persistentRepository->getRepository());
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
            'custom.id' => [
                'id' => 'custom_id',
            ],
        ]);

        $this->persistentRepository->set('test.name', 'Test name');
        $this->persistentRepository->set('custom.id', 'Test custom id');
        $this->persistentRepository->synchronize();

        $this->assertEquals(['test.name' => 'Test name', 'custom.id' => 'Test custom id'], $this->storage->get());
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
     * @depends testRestore
     */
    public function testReset()
    {
        $this->persistentRepository->setItems([
            'test.name',
            'test.title',
        ]);

        $this->repository->set('test.name', 'origin name');
        $this->repository->set('test.title', 'origin title');

        $values = [
            'test.name' => 'New name',
            'test.title' => 'New title',
        ];
        $this->persistentRepository->save($values);

        $this->persistentRepository->reset();

        $this->assertSame([], $this->storage->get());

        $this->assertSame('origin name', $this->repository->get('test.name'));
        $this->assertSame('origin title', $this->repository->get('test.title'));
    }

    /**
     * @depends testReset
     */
    public function testResetValue()
    {
        $this->persistentRepository->setItems([
            'test.name',
            'test.title',
        ]);

        $this->repository->set('test.name', 'origin name');
        $this->repository->set('test.title', 'origin title');

        $values = [
            'test.name' => 'New name',
            'test.title' => 'New title',
        ];
        $this->persistentRepository->save($values);

        $this->persistentRepository->resetValue('test.title');

        $this->assertSame(['test.name' => 'New name'], $this->storage->get());

        $this->assertSame('New name', $this->repository->get('test.name'));
        $this->assertSame('origin title', $this->repository->get('test.title'));
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
    public function testGc()
    {
        $this->persistentRepository->setItems([
            'test.name',
        ]);

        $this->storage->save([
            'test.name' => 'test name',
            'test.obsolete' => 'test obsolete',
        ]);

        $this->persistentRepository->gc();

        $this->assertSame(['test.name' => 'test name'], $this->storage->get());
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

        $this->persistentRepository->reset();
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
     * @depends testRestore
     */
    public function testEncrypt()
    {
        $this->persistentRepository->setItems([
            'test.crypt' => [
                'encrypt' => true,
            ],
        ]);

        $values = [
            'test.crypt' => 'crypt value',
        ];
        $this->persistentRepository->save($values);

        $storedValues = $this->storage->get();
        $this->assertNotEquals('crypt value', $storedValues['test.crypt']);

        $this->persistentRepository->restore();

        $this->assertSame('crypt value', $this->persistentRepository->get('test.crypt'));
    }

    public function testConfigRepositoryContract()
    {
        $this->repository->set('test', [
            'foo' => 'test foo',
            'array' => ['one', 'two'],
        ]);
        $this->repository->set('origin', [
            'foo' => 'origin foo',
            'bar' => 'origin bar',
        ]);

        $this->persistentRepository->setItems([
            'test.foo',
            'test.bar',
        ]);

        $this->assertSame($this->repository->get('test.foo'), $this->persistentRepository->get('test.foo'));
        $this->assertSame($this->repository->has('test.foo'), $this->persistentRepository->has('test.foo'));
        $this->assertTrue($this->persistentRepository->has('test.bar'));

        $this->assertSame($this->repository->get(['origin.foo', 'origin.bar']), $this->persistentRepository->get(['origin.foo', 'origin.bar']));
        $this->assertSame($this->repository->get(['origin.foo' => 'default']), $this->persistentRepository->get(['origin.foo' => 'default']));

        $this->persistentRepository->set('new.bar', 'new bar');
        $this->assertSame('new bar', $this->repository->get('new.bar'));

        $this->persistentRepository->prepend('test.array', 'zero');
        $this->assertSame(['zero', 'one', 'two'], $this->repository->get('test.array'));

        $this->persistentRepository->push('test.array', 'three');
        $this->assertSame(['zero', 'one', 'two', 'three'], $this->repository->get('test.array'));
    }

    /**
     * @depends testConfigRepositoryContract
     */
    public function testArrayAccess()
    {
        $this->repository->set('test', [
            'foo' => 'test foo',
        ]);
        $this->repository->set('origin', [
            'foo' => 'origin foo',
            'bar' => 'origin bar',
        ]);

        $this->persistentRepository->setItems([
            'test.foo',
            'test.bar',
        ]);

        $this->assertSame($this->repository->get('test.foo'), $this->persistentRepository['test.foo']);
        $this->assertSame($this->repository->has('test.foo'), isset($this->persistentRepository['test.foo']));
        $this->assertTrue(isset($this->persistentRepository['test.bar']));

        $this->persistentRepository['new.bar'] = 'new bar';
        $this->assertSame('new bar', $this->repository->get('new.bar'));

        unset($this->persistentRepository['new.bar']);
        $this->assertNull($this->repository->get('new.bar'));
    }

    /**
     * Data provider for {@see testLazyRestore()}
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
            [['foo.name', 'foo.another'], true],
            [['foo.another', 'foo.name'], true],
            [['bar.block' => ['default']], true],
            [['another.block' => ['default']], false],
            [['bar.block' => ['default' => 'value']], true],
            [['another.block' => ['default' => 'value']], false],
        ];
    }

    /**
     * @depends testRestore
     *
     * @dataProvider dataProviderLazyRestore
     *
     * @param  array|string  $key config key.
     * @param  bool  $allowRestore whether values should be restored from storage.
     */
    public function testLazyRestore($key, bool $allowRestore)
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

    public function testSelfConfigure()
    {
        $this->persistentRepository
            ->setCacheKey('test-cache-key')
            ->setCacheTtl(12345)
            ->setGcEnabled(false);

        $this->assertSame('test-cache-key', $this->persistentRepository->cacheKey);
        $this->assertSame(12345, $this->persistentRepository->cacheTtl);
        $this->assertSame(false, $this->persistentRepository->gcEnabled);
    }

    /**
     * @see https://github.com/illuminatech/config/issues/6
     *
     * @depends testEncrypt
     */
    public function testCorruptedData()
    {
        $this->repository->set([
            'foo' => 'foo default',
            'crypt' => 'crypt default',
            'bar' => 'bar default',
        ]);

        $this->persistentRepository->setItems([
            'foo',
            'crypt' => [
                'encrypt' => true,
            ],
            'bar',
        ]);

        $values = [
            'foo' => 'foo value',
            'crypt' => 'crypt value',
            'bar' => 'bar value',
        ];
        $this->storage->save($values);

        $this->persistentRepository->restore();

        $this->assertSame('foo value', $this->persistentRepository->get('foo'));
        $this->assertSame('bar value', $this->persistentRepository->get('bar'));
        $this->assertSame('crypt default', $this->persistentRepository->get('crypt'));
    }
}
