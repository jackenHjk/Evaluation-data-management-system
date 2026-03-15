<?php
/**
 * 文件名: core/Database.php
 * 功能描述: 数据库操作类
 * 
 * 该类负责:
 * 1. 数据库连接池的创建和管理
 * 2. 提供SQL查询执行方法
 * 3. 提供事务处理功能
 * 4. 错误处理和日志记录
 * 5. 查询缓存支持
 * 
 * 全系统通过该类进行所有数据库操作，统一管理数据库连接。
 * 使用PDO驱动，支持参数化查询，防止SQL注入。
 * 
 * 关联文件:
 * - config/config.php: 数据库配置文件，包含数据库连接参数
 * - core/Controller.php: 基础控制器，使用数据库连接
 * - core/Cache.php: 缓存类，用于查询缓存
 * - controllers/: 控制器目录，所有业务控制器通过基础控制器继承数据库功能
 */

namespace Core;

class Database {
    private static $instance = null;
    private $connections = [];
    private $activeConnections = 0;
    private $config;
    private $cache;
    private $transactionConnection = null; // 事务专用连接
    private $inTransaction = false; // 是否在事务中
    
    private function __construct() {
        try {
            // 读取配置文件
            $config_file = dirname(__DIR__) . '/config/config.php';
            if (!file_exists($config_file)) {
                throw new \Exception('配置文件不存在');
            }
            
            $config = require $config_file;
            if (!isset($config['db'])) {
                throw new \Exception('数据库配置不存在');
            }
            
            $this->config = $config['db'];
            $this->cache = Cache::getInstance();
            
            // 初始化最小连接数
            for ($i = 0; $i < $this->config['pool']['min']; $i++) {
                $this->createConnection();
            }
        } catch (\Exception $e) {
            error_log("数据库初始化错误: " . $e->getMessage());
            throw new \Exception("数据库初始化失败：" . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 创建新的数据库连接
     */
    private function createConnection() {
        if ($this->activeConnections >= $this->config['pool']['max']) {
            throw new \Exception("已达到最大连接数限制");
    }
    
        try {
            $dsn = "mysql:host={$this->config['host']};dbname={$this->config['dbname']};charset={$this->config['charset']}";
            $pdo = new \PDO($dsn, $this->config['username'], $this->config['password'], $this->config['options']);
            
            $connection = [
                'pdo' => $pdo,
                'inUse' => false,
                'lastUsed' => time()
            ];
            
            $this->connections[] = $connection;
            $this->activeConnections++;
            
            return $connection;
        } catch (\PDOException $e) {
            error_log("数据库连接创建失败: " . $e->getMessage());
            throw new \Exception("数据库连接创建失败：" . $e->getMessage());
        }
    }
    
    /**
     * 获取可用的数据库连接
     */
    private function getConnection() {
        // 如果在事务中,返回事务专用连接
        if ($this->inTransaction && $this->transactionConnection !== null) {
            return $this->transactionConnection;
        }
        
        // 查找空闲连接
        foreach ($this->connections as &$connection) {
            if (!$connection['inUse']) {
                $connection['inUse'] = true;
                $connection['lastUsed'] = time();
                return $connection['pdo'];
            }
        }
        
        // 如果没有空闲连接且未达到最大连接数，创建新连接
        if ($this->activeConnections < $this->config['pool']['max']) {
            $connection = $this->createConnection();
            $connection['inUse'] = true;
            return $connection['pdo'];
        }
        
        // 等待空闲连接
        $startTime = time();
        while (time() - $startTime < $this->config['pool']['timeout']) {
            foreach ($this->connections as &$connection) {
                if (!$connection['inUse']) {
                    $connection['inUse'] = true;
                    $connection['lastUsed'] = time();
                    return $connection['pdo'];
                }
            }
            usleep(100000); // 等待100ms
        }
        
        throw new \Exception("无法获取数据库连接：连接池已满且超时");
    }
    
    /**
     * 释放数据库连接
     */
    private function releaseConnection($pdo) {
        foreach ($this->connections as &$connection) {
            if ($connection['pdo'] === $pdo) {
                $connection['inUse'] = false;
                $connection['lastUsed'] = time();
                break;
            }
        }
    }
    
    /**
     * 生成缓存键
     */
    private function generateCacheKey($sql, $params) {
        return md5($sql . serialize($params));
        }
    
    /**
     * 执行查询，支持缓存
     */
    public function query($sql, $params = [], $cache_ttl = 0) {
        // 如果启用缓存且是SELECT查询
        if ($cache_ttl > 0 && stripos(trim($sql), 'SELECT') === 0) {
            $cache_key = $this->generateCacheKey($sql, $params);
            $result = $this->cache->get($cache_key);
            if ($result !== null) {
                return $result;
            }
        }
        
        try {
            $pdo = $this->getConnection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt;
            
            // 缓存查询结果
            if ($cache_ttl > 0 && stripos(trim($sql), 'SELECT') === 0) {
                $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $this->cache->set($cache_key, $data, $cache_ttl);
                $result = $data;
            }
            
            // 检查是否是事务相关的SQL语句
            $sqlUpper = strtoupper(trim($sql));
            
            // 处理事务开始
            if (strpos($sqlUpper, 'START TRANSACTION') === 0 || strpos($sqlUpper, 'BEGIN') === 0) {
                $this->inTransaction = true;
                $this->transactionConnection = $pdo;
                error_log("[Database] 事务开始,连接已锁定");
            }
            // 处理事务结束
            elseif (strpos($sqlUpper, 'COMMIT') === 0 || strpos($sqlUpper, 'ROLLBACK') === 0) {
                $action = strpos($sqlUpper, 'COMMIT') === 0 ? 'COMMIT' : 'ROLLBACK';
                
                // 检查PDO连接是否真的在事务中
                try {
                    // PDO的inTransaction()方法可以检查是否有活动事务
                    if (!$pdo->inTransaction()) {
                        error_log("[Database] 警告: 尝试{$action}但PDO没有活动事务,跳过");
                    }
                } catch (\PDOException $e) {
                    error_log("[Database] 检查事务状态失败: " . $e->getMessage());
                }
                
                // 无论如何都重置事务状态并释放连接
                $this->inTransaction = false;
                error_log("[Database] 事务{$action},释放连接");
                $this->releaseConnection($pdo);
                $this->transactionConnection = null;
            }
            // 普通SQL语句
            elseif (!$this->inTransaction) {
                // 不在事务中,正常释放连接
                $this->releaseConnection($pdo);
            }
            // 在事务中的普通SQL,不释放连接
            
            
            return $result;
        } catch (\PDOException $e) {
            // SQL执行失败,需要清理事务状态
            if ($this->inTransaction) {
                error_log("[Database] SQL执行失败,重置事务状态");
                $this->inTransaction = false;
                if ($this->transactionConnection !== null) {
                    $this->releaseConnection($this->transactionConnection);
                    $this->transactionConnection = null;
                }
            } elseif (isset($pdo)) {
                // 不在事务中,释放当前连接
                $this->releaseConnection($pdo);
            }
            
            error_log("SQL执行错误: " . $e->getMessage() . "\nSQL: " . $sql . "\n参数: " . json_encode($params));
            throw new \Exception("SQL执行错误:" . $e->getMessage());
        }
    }

    /**
     * 执行更新操作
     */
    public function execute($sql, $params = []) {
        try {
            $pdo = $this->getConnection();
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($params);
            $this->releaseConnection($pdo);
            return $result;
        } catch (\PDOException $e) {
            error_log("SQL执行错误: " . $e->getMessage() . "\nSQL: " . $sql . "\n参数: " . json_encode($params));
            throw new \Exception("SQL执行错误：" . $e->getMessage());
        }
    }

    /**
     * 获取所有结果
     */
    public function fetchAll($sql, $params = [], $cache_ttl = 0) {
        $result = $this->query($sql, $params, $cache_ttl);
        return is_array($result) ? $result : $result->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * 获取单行结果
     */
    public function fetch($sql, $params = [], $cache_ttl = 0) {
        if ($cache_ttl > 0) {
            $cache_key = $this->generateCacheKey($sql, $params);
            $result = $this->cache->get($cache_key);
            if ($result !== null) {
                return $result;
            }
        }
        
        try {
            $pdo = $this->getConnection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($cache_ttl > 0) {
                $this->cache->set($cache_key, $result, $cache_ttl);
            }
            
            $this->releaseConnection($pdo);
            return $result;
        } catch (\PDOException $e) {
            error_log("SQL执行错误: " . $e->getMessage() . "\nSQL: " . $sql . "\n参数: " . json_encode($params));
            throw new \Exception("SQL执行错误：" . $e->getMessage());
        }
    }
    
    /**
     * 开始事务
     */
    public function beginTransaction() {
        $pdo = $this->getConnection();
        $result = $pdo->beginTransaction();
        
        // 更新事务状态
        $this->inTransaction = true;
        $this->transactionConnection = $pdo;
        error_log("[Database] beginTransaction() 调用,事务开始,连接已锁定");
        
        return $result;
    }
    
    /**
     * 提交事务
     */
    public function commit() {
        // 检查是否有事务连接
        if ($this->transactionConnection === null) {
            error_log("[Database] commit() 调用但没有事务连接,尝试获取当前连接");
            $pdo = $this->getConnection();
        } else {
            $pdo = $this->transactionConnection;
        }
        
        // 检查PDO是否真的在事务中
        if (!$pdo->inTransaction()) {
            error_log("[Database] commit() 警告: PDO没有活动事务");
        }
        
        $result = $pdo->commit();
        
        // 重置事务状态
        $this->inTransaction = false;
        error_log("[Database] commit() 调用,事务提交,释放连接");
        $this->releaseConnection($pdo);
        $this->transactionConnection = null;
        
        return $result;
    }
    
    /**
     * 回滚事务
     */
    public function rollBack() {
        // 检查是否有事务连接
        if ($this->transactionConnection === null) {
            error_log("[Database] rollBack() 调用但没有事务连接,尝试获取当前连接");
            $pdo = $this->getConnection();
        } else {
            $pdo = $this->transactionConnection;
        }
        
        // 检查PDO是否真的在事务中
        if (!$pdo->inTransaction()) {
            error_log("[Database] rollBack() 警告: PDO没有活动事务");
        }
        
        $result = $pdo->rollBack();
        
        // 重置事务状态
        $this->inTransaction = false;
        error_log("[Database] rollBack() 调用,事务回滚,释放连接");
        $this->releaseConnection($pdo);
        $this->transactionConnection = null;
        
        return $result;
    }
    
    /**
     * 检查是否在事务中
     */
    public function inTransaction() {
        $pdo = $this->getConnection();
        return $pdo->inTransaction();
    }
    
    /**
     * 获取最后插入的ID
     */
    public function lastInsertId() {
        $pdo = $this->getConnection();
        return $pdo->lastInsertId();
    }
    
    /**
     * 清理过期连接
     */
    public function cleanupConnections() {
        $now = time();
        foreach ($this->connections as $key => $connection) {
            if (!$connection['inUse'] && ($now - $connection['lastUsed']) > 300) { // 5分钟未使用
                unset($this->connections[$key]);
                $this->activeConnections--;
            }
        }
    }
    
    private function __clone() {}
    
    public function __destruct() {
        // 关闭所有连接
        foreach ($this->connections as $connection) {
            $connection['pdo'] = null;
        }
        $this->connections = [];
        $this->activeConnections = 0;
    }
} 