<?php
/**
 * 文件名: core/Cache.php
 * 功能描述: 系统缓存类
 * 
 * 该类负责:
 * 1. 提供统一的缓存接口
 * 2. 支持文件缓存和内存缓存(Redis可选)
 * 3. 自动管理缓存生命周期
 */

namespace Core;

class Cache {
    private static $instance = null;
    private $cache_path;
    private $redis = null;
    private $use_redis = false;
    
    private function __construct() {
        // 设置文件缓存路径
        $this->cache_path = dirname(__DIR__) . '/temp/cache/';
        if (!is_dir($this->cache_path)) {
            mkdir($this->cache_path, 0777, true);
        }
        
        // 如果配置了Redis且扩展已安装，则使用Redis
        if (extension_loaded('redis')) {
            try {
                $config = require dirname(__DIR__) . '/config/config.php';
                if (isset($config['redis'])) {
                    $this->redis = new \Redis();
                    // 设置连接超时
                    $timeout = $config['redis']['timeout'] ?? 2.0;
                    if (!$this->redis->connect($config['redis']['host'], $config['redis']['port'], $timeout)) {
                        throw new \Exception("无法连接到Redis服务器");
                    }
                    
                    // 如果配置了密码，或者服务器需要密码（通过捕获异常处理）
                    if (!empty($config['redis']['password'])) {
                        if (!$this->redis->auth($config['redis']['password'])) {
                            throw new \Exception("Redis认证失败");
                        }
                    }
                    
                    // 尝试ping一下，检查连接是否可用（也会检测是否需要密码但未提供）
                    try {
                        $this->redis->ping();
                    } catch (\Exception $e) {
                         // 如果ping失败，可能是因为需要密码但配置文件中为空
                         // 这里我们记录日志并降级到文件缓存
                         throw new \Exception("Redis连接检测失败: " . $e->getMessage());
                    }

                    if (isset($config['redis']['db'])) {
                        $this->redis->select($config['redis']['db']);
                    }
                    
                    $this->use_redis = true;
                }
            } catch (\Exception $e) {
                // 读取不到config或连接失败时，记录日志并回退到文件缓存
                error_log("Redis初始化失败，降级为文件缓存: " . $e->getMessage());
                $this->use_redis = false;
                $this->redis = null;
            }
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 获取缓存数据
     * @param string $key 缓存键名
     * @return mixed|null
     */
    public function get($key) {
        if ($this->use_redis) {
            return $this->redis->get($key);
        }
        
        $cache_file = $this->cache_path . md5($key) . '.cache';
        if (file_exists($cache_file)) {
            $data = file_get_contents($cache_file);
            $cache_data = unserialize($data);
            if ($cache_data['expire'] === 0 || $cache_data['expire'] > time()) {
                return $cache_data['data'];
            }
            unlink($cache_file);
        }
        return null;
    }
    
    /**
     * 设置缓存数据
     * @param string $key 缓存键名
     * @param mixed $value 缓存数据
     * @param int $ttl 过期时间(秒)，0表示永不过期
     * @return bool
     */
    public function set($key, $value, $ttl = 3600) {
        if ($this->use_redis) {
            return $this->redis->set($key, $value, $ttl);
        }
        
        $cache_data = [
            'data' => $value,
            'expire' => $ttl > 0 ? time() + $ttl : 0
        ];
        
        $cache_file = $this->cache_path . md5($key) . '.cache';
        return file_put_contents($cache_file, serialize($cache_data)) !== false;
    }
    
    /**
     * 删除缓存数据
     * @param string $key 缓存键名
     * @return bool
     */
    public function delete($key) {
        if ($this->use_redis) {
            return $this->redis->del($key);
        }
        
        $cache_file = $this->cache_path . md5($key) . '.cache';
        if (file_exists($cache_file)) {
            return unlink($cache_file);
        }
        return true;
    }
    
    /**
     * 清空所有缓存
     * @return bool
     */
    public function clear() {
        if ($this->use_redis) {
            return $this->redis->flushDB();
        }
        
        $files = glob($this->cache_path . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        return true;
    }
    
    private function __clone() {}
} 