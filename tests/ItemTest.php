<?php

namespace Illuminatech\Config\Test;

use Illuminatech\Config\Item;
use Illuminate\Config\Repository;

class ItemTest extends TestCase
{
    public function testCreate()
    {
        $item = new Item([
            'key' => 'some.key'
        ]);

        $this->assertSame('some.key', $item->key);
        $this->assertNotEmpty($item->id);
        $this->assertNotEmpty($item->label);
        $this->assertNotEmpty($item->rules);
    }

    /**
     * @depends testCreate
     */
    public function testGetValue()
    {
        $repository = new Repository;

        $item = (new Item([
            'key' => 'some.key'
        ]))->setRepository($repository);

        $this->assertNull($item->getValue());

        $repository->set('some.key', 'foo');
        $this->assertSame('foo', $item->getValue());
    }

    /**
     * @depends testGetValue
     */
    public function testSetValue()
    {
        $repository = new Repository;

        $item = (new Item([
            'key' => 'some.key'
        ]))->setRepository($repository);

        $item->setValue('foo');
        $this->assertSame('foo', $item->getValue());
        $this->assertSame('foo', $repository->get('some.key'));
    }

    /**
     * @depends testGetValue
     */
    public function testToArray()
    {
        $repository = new Repository;

        $item = (new Item([
            'key' => 'some.key'
        ]))->setRepository($repository);

        $array = $item->toArray();

        $this->assertSame($item->id, $array['id']);
        $this->assertSame($item->key, $array['key']);
        $this->assertSame($item->label, $array['label']);
        $this->assertSame($item->getValue(), $array['value']);
    }
}
