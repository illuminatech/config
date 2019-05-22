<?php
/**
 * @link https://github.com/illuminatech
 * @copyright Copyright (c) 2015 Illuminatech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace Illuminatech\Config;

use Illuminate\Support\Facades\File;

/**
 * StoragePhp represents the configuration storage based on local PHP files.
 *
 * This storage provides good performance, but is not acceptable in case you have distributed web application with
 * several PHP instances and load-balancer.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class StoragePhp implements StorageContact
{
    /**
     * @var string name of the file, which should be used to store values.
     */
    public $fileName;

    /**
     * Constructor.
     *
     * @param  string  $fileName name of the file, which should be used to store values.
     */
    public function __construct(string $fileName = '')
    {
        $this->fileName = $fileName;
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $values): bool
    {
        $values = array_merge($this->get(), $values);

        $fileName = $this->fileName;
        $directoryName = dirname($fileName);
        if (! file_exists($directoryName)) {
            File::makeDirectory($directoryName, 0755, true);
        }

        $bytesWritten = file_put_contents($fileName, $this->composeFileContent($values));
        $this->invalidateScriptCache($fileName);

        return ($bytesWritten > 0);
    }

    /**
     * {@inheritdoc}
     */
    public function get(): array
    {
        if (file_exists($this->fileName)) {
            return require($this->fileName);
        }

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        if (file_exists($this->fileName)) {
            $this->invalidateScriptCache($this->fileName);

            return unlink($this->fileName);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function clearValue($key): bool
    {
        $values = $this->get();
        if (empty($values)) {
            return true;
        }

        unset($values[$key]);

        $bytesWritten = file_put_contents($this->fileName, $this->composeFileContent($values));
        $this->invalidateScriptCache($this->fileName);

        return ($bytesWritten > 0);
    }

    /**
     * Composes file content for the given values.
     * @param array $values values to be saved.
     * @return string file content.
     */
    protected function composeFileContent(array $values)
    {
        $content = "<?php\n\nreturn " . var_export($values, true) . ';';
        return $content;
    }

    /**
     * Invalidates precompiled script cache (such as OPCache or APC) for the given file.
     *
     * @param  string  $fileName file name.
     */
    protected function invalidateScriptCache($fileName)
    {
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($fileName, true);
        }
        if (function_exists('apc_delete_file')) {
            @apc_delete_file($fileName);
        }
    }
}
