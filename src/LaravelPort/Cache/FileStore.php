<?php

namespace Autoframe\Components\SocketCache\LaravelPort\Cache;

use Autoframe\Components\SocketCache\LaravelPort\Support\Tap;
use Exception;
use Autoframe\Components\SocketCache\LaravelPort\Contracts\Cache\LockProvider;
use Autoframe\Components\SocketCache\LaravelPort\Contracts\Cache\Store;
use Autoframe\Components\SocketCache\LaravelPort\Contracts\Filesystem\LockTimeoutException;
use Autoframe\Components\SocketCache\LaravelPort\Filesystem\Filesystem;
use Autoframe\Components\SocketCache\LaravelPort\Filesystem\LockableFile;
use Autoframe\Components\SocketCache\LaravelPort\Support\InteractsWithTime;

class FileStore implements Store, LockProvider
{
    use InteractsWithTime, HasCacheLock, RetrievesMultipleKeys;

    /**
     * The Autoframe\Components\SocketCache\LaravelPort Filesystem instance.
     *
     * @var Filesystem
     */
    protected $files;

    /**
     * The file cache directory.
     *
     * @var string
     */
    protected $directory;

    /**
     * Octal representation of the cache file permissions.
     *
     * @var int|null
     */
    protected $filePermission;

    /**
     * Create a new file cache store instance.
     *
     * @param Filesystem $files
     * @param string $directory
     * @param int|null $filePermission
     * @return void
     */
    public function __construct(Filesystem $files, string $directory, $filePermission = null)
    {
        $this->files = $files;
        $this->directory = $directory;
        $this->filePermission = $filePermission;
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param string|array $key
     * @return mixed
     */
    public function get($key)
    {
        return $this->getPayload($key)['data'] ?? null;
    }

    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @param string $key
     * @param mixed $value
     * @param int $seconds
     * @return bool
     */
    public function put($key, $value, $seconds)
    {
        $this->ensureCacheDirectoryExists($path = $this->path($key));

        $result = $this->files->put(
            $path, $this->expiration($seconds) . serialize($value), true
        );

        if ($result !== false && $result > 0) {
            $this->ensurePermissionsAreCorrect($path);

            return true;
        }

        return false;
    }

    /**
     * Store an item in the cache if the key doesn't exist.
     *
     * @param string $key
     * @param mixed $value
     * @param int $seconds
     * @return bool
     */
    public function add($key, $value, $seconds)
    {
        $this->ensureCacheDirectoryExists($path = $this->path($key));

        $file = new LockableFile($path, 'c+');

        try {
            $file->getExclusiveLock();
        } catch (LockTimeoutException $e) {
            $file->close();

            return false;
        }

        $expire = $file->read(10);

        if (empty($expire) || $this->currentTime() >= $expire) {
            $file->truncate()
                ->write($this->expiration($seconds) . serialize($value))
                ->close();

            $this->ensurePermissionsAreCorrect($path);

            return true;
        }

        $file->close();

        return false;
    }

    /**
     * Create the file cache directory if necessary.
     *
     * @param string $path
     * @return void
     */
    protected function ensureCacheDirectoryExists($path)
    {
        $directory = dirname($path);

        if (!$this->files->exists($directory)) {
            $this->files->makeDirectory($directory, 0777, true, true);

            // We're creating two levels of directories (e.g. 7e/24), so we check them both...
            $this->ensurePermissionsAreCorrect($directory);
            $this->ensurePermissionsAreCorrect(dirname($directory));
        }
    }

    /**
     * Ensure the created node has the correct permissions.
     *
     * @param string $path
     * @return void
     */
    protected function ensurePermissionsAreCorrect($path)
    {
        if (is_null($this->filePermission) ||
            intval($this->files->chmod($path), 8) == $this->filePermission) {
            return;
        }

        $this->files->chmod($path, $this->filePermission);
    }

    /**
     * Increment the value of an item in the cache.
     *
     * @param string $key
     * @param mixed $value
     * @return int
     */
    public function increment($key, $value = 1)
    {
        $raw = $this->getPayload($key);

        return Tap::tap(((int)$raw['data']) + $value, function ($newValue) use ($key, $raw) {
            $this->put($key, $newValue, $raw['time'] ?? 0);
        });
    }

    /**
     * Decrement the value of an item in the cache.
     *
     * @param string $key
     * @param mixed $value
     * @return int
     */
    public function decrement($key, $value = 1)
    {
        return $this->increment($key, $value * -1);
    }

    /**
     * Store an item in the cache indefinitely.
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function forever($key, $value)
    {
        return $this->put($key, $value, 0);
    }

    /**
     * Remove an item from the cache.
     *
     * @param string $key
     * @return bool
     */
    public function forget($key)
    {
        if ($this->files->exists($file = $this->path($key))) {
            return $this->files->delete($file);
        }

        return false;
    }

    /**
     * Remove all items from the cache.
     *
     * @return bool
     */
    public function flush()
    {
        if (!$this->files->isDirectory($this->directory)) {
            return false;
        }

        foreach ($this->files->directories($this->directory) as $directory) {
            $deleted = $this->files->deleteDirectory($directory);

            if (!$deleted || $this->files->exists($directory)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Retrieve an item and expiry time from the cache by key.
     *
     * @param string $key
     * @return array
     */
    protected function getPayload($key)
    {
        $path = $this->path($key);

        // If the file doesn't exist, we obviously cannot return the cache so we will
        // just return null. Otherwise, we'll get the contents of the file and get
        // the expiration UNIX timestamps from the start of the file's contents.
        try {
            $expire = substr(
                $contents = $this->files->get($path, true), 0, 10
            );
        } catch (Exception $e) {
            return $this->emptyPayload();
        }

        // If the current time is greater than expiration timestamps we will delete
        // the file and return null. This helps clean up the old files and keeps
        // this directory much cleaner for us as old files aren't hanging out.
        if ($this->currentTime() >= $expire) {
            $this->forget($key);

            return $this->emptyPayload();
        }

        try {
            $data = unserialize(substr($contents, 10));
        } catch (Exception $e) {
            $this->forget($key);

            return $this->emptyPayload();
        }

        // Next, we'll extract the number of seconds that are remaining for a cache
        // so that we can properly retain the time for things like the increment
        // operation that may be performed on this cache on a later operation.
        $time = $expire - $this->currentTime();

        return compact('data', 'time');
    }

    /**
     * Get a default empty payload for the cache.
     *
     * @return array
     */
    protected function emptyPayload()
    {
        return ['data' => null, 'time' => null];
    }

    /**
     * Get the full path for the given cache key.
     *
     * @param string $key
     * @return string
     */
    protected function path($key)
    {
        $key = (string)$key;
        if (strpos($key, '\\')) {
            $parts = array_slice(explode('\\', $key), 0, 2);
            foreach ($parts as &$part) {
                $part = substr($this->s($part), 0, 16);
            }
        } else {
            $parts = array_slice(str_split($this->s((string)$key), 2), 0, 2);
        }
        foreach ($parts as $i => $part) {
            if ($part === '') {
                unset($parts[$i]);
            }
            else{
                $parts[$i] = $this->s($part);
            }
        }
        return $this->directory . '/' . implode('/', $parts) . '/' . $this->s((string)$key);
        //    $parts = array_slice(str_split($hash = sha1($key), 2), 0, 2);
        //    return $this->directory.'/'.implode('/', $parts).'/'.$hash;
    }

    protected function s(string $filename)
    {
        $allowd = "-.,"; //$allowd.='_@+';//??
        for ($i = 48; $i < 58; $i++) {
            $allowd .= chr($i);
        }//0-9
        for ($i = 65; $i < 91; $i++) {
            $allowd .= chr($i);
        }//A-Z
        for ($i = 97; $i < 123; $i++) {
            $allowd .= chr($i);
        }//a-z
        $out = '';
        $fl = strlen($filename);
        $al = strlen($allowd);
        for ($i = 0; $i < $fl; $i++) {
            for ($j = 0; $j < $al; $j++) {
                if ($filename[$i] == $allowd[$j]) {
                    $out .= $allowd[$j];
                    $j = $al + 1;
                }
            }
            if ($j == $al) {
                $out .= '-';
                $j = 0;
            }
        }
        $out = str_replace(array('-----', '----', '---', '--'), array('-', '-', '-', '-'), $out);
        if (strlen($out) > 100) {
            $out = substr($out, 0, 68) . md5($out);
        }
        return $out;
    }

    /**
     * Get the expiration time based on the given seconds.
     *
     * @param int $seconds
     * @return int
     */
    protected function expiration($seconds)
    {
        $time = $this->availableAt($seconds);

        return $seconds === 0 || $time > 9999999999 ? 9999999999 : $time;
    }

    /**
     * Get the Filesystem instance.
     *
     * @return Filesystem
     */
    public function getFilesystem()
    {
        return $this->files;
    }

    /**
     * Get the working directory of the cache.
     *
     * @return string
     */
    public function getDirectory()
    {
        return $this->directory;
    }

    /**
     * Get the cache key prefix.
     *
     * @return string
     */
    public function getPrefix()
    {
        return '';
    }
}
