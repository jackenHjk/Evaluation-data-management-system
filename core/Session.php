<?php
/**
 * 文件名: core/Session.php
 * 功能描述: 会话管理类
 * 
 * 该类负责:
 * 1. 会话的创建和管理
 * 2. 使用Redis存储会话数据
 * 3. 提供会话操作方法
 * 4. 自动清理过期会话
 */

namespace Core;

class Session {
    private static $instance = null;
    private $redis = null;
    private $session_id = null;
    private $config;
    
    private function __construct() {
        // 读取配置
        $config = require dirname(__DIR__) . '/config/config.php';
        $this->config = $config;
        
        // 如果配置使用Redis存储会话
        if ($config['app']['session_handler'] === 'redis' && extension_loaded('redis')) {
            try {
                $this->redis = new \Redis();
                $this->redis->connect(
                    $config['redis']['host'],
                    $config['redis']['port']
                );
                if (!empty($config['redis']['password'])) {
                    $this->redis->auth($config['redis']['password']);
                }
                
                // 设置会话处理器
                ini_set('session.save_handler', 'redis');
                ini_set('session.save_path', "tcp://{$config['redis']['host']}:{$config['redis']['port']}");
                
                // 设置会话配置
                ini_set('session.gc_maxlifetime', $config['app']['session_lifetime']);
                ini_set('session.cookie_lifetime', $config['app']['session_lifetime']);
                
                // 设置会话Cookie参数
                session_set_cookie_params([
                    'lifetime' => $config['app']['session_lifetime'],
                    'path' => '/',
                    'domain' => '',
                    'secure' => !empty($_SERVER['HTTPS']),
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            } catch (\Exception $e) {
                error_log("Redis连接失败，使用默认文件会话存储: " . $e->getMessage());
            }
        }
        
        // 启动会话
        if (!session_id()) {
            session_start();
        }
        $this->session_id = session_id();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 设置会话数据
     */
    public function set($key, $value) {
        $_SESSION[$key] = $value;
        return true;
    }
    
    /**
     * 获取会话数据
     */
    public function get($key, $default = null) {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }
    
    /**
     * 删除会话数据
     */
    public function delete($key) {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
            return true;
        }
        return false;
    }
    
    /**
     * 清空会话数据
     */
    public function clear() {
        session_unset();
        return session_destroy();
    }
    
    /**
     * 重新生成会话ID
     */
    public function regenerate() {
        if ($this->redis) {
            // 在Redis中复制旧会话数据到新会话
            $old_id = $this->session_id;
            $old_data = $_SESSION;
            
            session_regenerate_id(true);
            $this->session_id = session_id();
            
            $_SESSION = $old_data;
            $this->redis->del('PHPREDIS_SESSION:' . $old_id);
        } else {
            session_regenerate_id(true);
            $this->session_id = session_id();
        }
        return true;
    }
    
    /**
     * 获取会话ID
     */
    public function getId() {
        return $this->session_id;
    }
    
    /**
     * 检查会话是否存在某个键
     */
    public function has($key) {
        return isset($_SESSION[$key]);
    }
    
    /**
     * 获取所有会话数据
     */
    public function all() {
        return $_SESSION;
    }
    
    /**
     * 获取会话剩余有效期（秒）
     */
    public function getTTL() {
        if ($this->redis) {
            return $this->redis->ttl('PHPREDIS_SESSION:' . $this->session_id);
        }
        return ini_get('session.gc_maxlifetime');
    }
    
    /**
     * 更新会话最后访问时间
     */
    public function touch() {
        if ($this->redis) {
            $this->redis->expire(
                'PHPREDIS_SESSION:' . $this->session_id,
                $this->config['app']['session_lifetime']
            );
        }
        return true;
    }
    
    private function __clone() {}
} 