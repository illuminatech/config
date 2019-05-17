<?php

namespace Illuminatech\Config\Test\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string $value
 * @property string|null $type
 */
class Config extends Model
{
    /**
     * {@inheritdoc}
     */
    public $timestamps = false;

    /**
     * {@inheritdoc}
     */
    protected $fillable = [
        'key',
        'value',
        'type',
    ];
}
