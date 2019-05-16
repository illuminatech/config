<?php

namespace Illuminatech\Config\Test;

use Illuminatech\Config\StorageDb;
use Illuminate\Database\Schema\Blueprint;

class StorageDbTest extends TestCase
{
    /**
     * @var \Illuminatech\Config\StorageDb test storage.
     */
    protected $storage;

    /**
     * {@inheritdoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->createSchema();

        $this->storage = new StorageDb($this->getConnection());
    }

    /**
     * Setup the database schema.
     *
     * @return void
     */
    protected function createSchema(): void
    {
        $this->getSchemaBuilder()->create('configs', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('value');
        });
    }

    public function testSave()
    {
        $values = [
            'test.name' => 'Test name',
            'test.title' => 'Test title',
        ];

        $this->storage->save($values);

        $returnedValues = $this->storage->get();

        $this->assertEquals($values, $returnedValues);
    }

    /**
     * @depends testSave
     */
    public function testUpdate()
    {
        $values = [
            'test.name' => 'Origin name',
            'test.title' => 'Origin title',
        ];

        $this->storage->save($values);

        $this->storage->save([
            'test.title' => 'Updated title'
        ]);

        $returnedValues = $this->storage->get();

        $this->assertSame('Updated title', $returnedValues['test.title']);
        $this->assertSame('Origin name', $returnedValues['test.name']);
    }

    /**
     * @depends testSave
     */
    public function testClear()
    {
        $values = [
            'test.name' => 'Test name',
            'test.title' => 'Test title',
        ];

        $this->storage->save($values);
        $this->storage->clear();

        $this->assertEmpty($this->storage->get());
    }

    /**
     * @depends testSave
     */
    public function testClearValue()
    {
        $values = [
            'test.name' => 'Test name',
            'test.title' => 'Test title',
        ];

        $this->storage->save($values);
        $this->storage->clearValue('test.name');

        $returnedValues = $this->storage->get();

        $this->assertFalse(array_key_exists('test.name', $returnedValues));
        $this->assertTrue(array_key_exists('test.title', $returnedValues));
    }
}
