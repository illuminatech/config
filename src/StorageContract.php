<?php
/**
 * @link https://github.com/illuminatech
 * @copyright Copyright (c) 2019 Illuminatech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace Illuminatech\Config;

/**
 * StorageContract defines config persistent storage interface.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
interface StorageContract
{
    /**
     * Saves given values.
     *
     * @param array $values in format: 'key' => 'value'
     * @return bool success.
     */
    public function save(array $values): bool;

    /**
     * Returns previously saved values.
     *
     * @return array values in format: 'key' => 'value'
     */
    public function get(): array;

    /**
     * Clears all saved values.
     *
     * @return bool success.
     */
    public function clear(): bool;

    /**
     * Clear saved value for the specified item.
     *
     * @param string $key the key of the item to be cleared.
     * @return bool success.
     */
    public function clearValue($key): bool;
}
