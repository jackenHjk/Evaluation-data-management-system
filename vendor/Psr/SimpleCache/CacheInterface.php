<?php
namespace Psr\SimpleCache;

interface CacheInterface
{
    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = null);

    /**
     * @param string $key
     * @param mixed $value
     * @param null|int|DateInterval $ttl
     * @return bool
     */
    public function set($key, $value, $ttl = null);

    /**
     * @param string $key
     * @return bool
     */
    public function delete($key);

    /**
     * @return bool
     */
    public function clear();

    /**
     * @param iterable $keys
     * @param mixed $default
     * @return iterable
     */
    public function getMultiple($keys, $default = null);

    /**
     * @param iterable $values
     * @param null|int|DateInterval $ttl
     * @return bool
     */
    public function setMultiple($values, $ttl = null);

    /**
     * @param iterable $keys
     * @return bool
     */
    public function deleteMultiple($keys);

    /**
     * @param string $key
     * @return bool
     */
    public function has($key);
} 