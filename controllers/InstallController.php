<?php
/**
 * 文件名: controllers/InstallController.php
 * 功能描述: 系统安装控制器
 * 
 * 该控制器负责:
 * 1. 检查系统环境和依赖是否满足安装要求
 * 2. 执行数据库结构导入
 * 3. 创建管理员账号
 * 4. 生成系统配置文件
 * 5. 创建安装锁定文件防止重复安装
 * 
 * API调用路由:
 * - install: 处理安装请求
 * 
 * 关联文件:
 * - core/Controller.php: 基础控制器
 * - core/Logger.php: 日志记录类
 * - install/database.sql: 数据库结构SQL文件
 * - install/index.php: 安装界面
 * - config/install.lock: 安装锁定文件
 * - config/config.php: 系统配置文件
 */

namespace Controllers;

use Core\Controller;
use Core\Logger;

class InstallController extends Controller {
    public function __construct() {
        parent::__construct();
        $this->logger = Logger::getInstance();
    }

    /**
     * 检查系统环境要求
     */
    public function checkRequirements() {
        $requirements = [];
        
        // 检查PHP版本
        $requirements[] = [
            'name' => 'PHP版本',
            'current' => PHP_VERSION,
            'required' => '>=7.4',
            'result' => version_compare(PHP_VERSION, '7.4', '>=')
        ];

        // 检查必需的PHP扩展
        $requiredExtensions = [
            'ctype' => '基础类型检查',
            'dom' => 'XML文档操作',
            'gd' => '图像处理',
            'iconv' => '字符集转换',
            'libxml' => 'XML处理',
            'mbstring' => '多字节字符串',
            'SimpleXML' => 'XML解析',
            'xml' => 'XML处理',
            'xmlreader' => 'XML读取',
            'xmlwriter' => 'XML写入',
            'zip' => 'ZIP文件操作',
            'zlib' => '数据压缩',
            'pdo' => '数据库操作',
            'json' => 'JSON数据处理'
        ];

        foreach ($requiredExtensions as $ext => $description) {
            $requirements[] = [
                'name' => $ext . ' 扩展 (' . $description . ')',
                'current' => extension_loaded($ext) ? '已安装' : '未安装',
                'result' => extension_loaded($ext)
            ];
        }

        // 检查目录权限
        $requiredDirs = [
            'config' => '配置目录',
            'logs' => '日志目录',
            'temp' => '临时文件目录',
            'temp/downloads' => '下载临时目录',
            'uploads' => '上传目录'
        ];

        foreach ($requiredDirs as $dir => $description) {
            $path = dirname(__DIR__) . '/' . $dir;
            $exists = file_exists($path);
            $writable = $exists && is_writable($path);
            
            if (!$exists) {
                @mkdir($path, 0777, true);
                $exists = file_exists($path);
                $writable = $exists && is_writable($path);
            }

            $requirements[] = [
                'name' => $dir . ' 目录权限 (' . $description . ')',
                'current' => $exists ? ($writable ? '可写' : '不可写') : '目录不存在',
                'result' => $exists && $writable
            ];
        }

        // 检查PHP配置
        $phpSettings = [
            'file_uploads' => ['文件上传', 'On'],
            'post_max_size' => ['POST最大尺寸', '>=8M'],
            'upload_max_filesize' => ['上传文件最大尺寸', '>=8M'],
            'max_execution_time' => ['最大执行时间', '>=30'],
            'memory_limit' => ['内存限制', '>=128M']
        ];

        foreach ($phpSettings as $setting => $info) {
            $currentValue = ini_get($setting);
            $required = $info[1];
            $name = $info[0];
            
            $result = true;
            if ($setting === 'file_uploads') {
                $result = $currentValue == '1' || $currentValue === 'On';
            } else if (in_array($setting, ['post_max_size', 'upload_max_filesize', 'memory_limit'])) {
                $current = $this->convertToBytes($currentValue);
                $required = $this->convertToBytes(substr($required, 2));
                $result = $current >= $required;
            } else {
                $current = (int)$currentValue;
                $required = (int)substr($required, 2);
                $result = $current >= $required;
            }

            $requirements[] = [
                'name' => 'PHP ' . $name,
                'current' => $currentValue,
                'required' => $info[1],
                'result' => $result
            ];
        }

        return $requirements;
    }

    /**
     * 检查所有要求是否都满足
     */
    public function allRequirementsMet() {
        $requirements = $this->checkRequirements();
        foreach ($requirements as $requirement) {
            if (!$requirement['result']) {
                return false;
            }
        }
        return true;
    }

    /**
     * 将PHP配置的大小值转换为字节数
     */
    private function convertToBytes($value) {
        $value = trim($value);
        $last = strtolower($value[strlen($value)-1]);
        $value = (int)$value;
        
        switch($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }

    public function install() {
        try {
            $this->logger->debug('开始安装系统');

            // 首先检查环境要求
            if (!$this->allRequirementsMet()) {
                $this->logger->warning('环境要求未满足', [
                    'requirements' => $this->checkRequirements()
                ]);
                return $this->json(['error' => '环境要求未满足，请检查安装要求'], 400);
            }

            // 检查是否已安装
            if (file_exists('config/install.lock')) {
                $this->logger->warning('系统已安装，尝试重新安装', [
                    'ip' => $_SERVER['REMOTE_ADDR']
                ]);
                return $this->json(['error' => '系统已安装'], 400);
            }

            // 获取安装参数
            $dbHost = $_POST['db_host'] ?? '';
            $dbName = $_POST['db_name'] ?? '';
            $dbUser = $_POST['db_user'] ?? '';
            $dbPass = $_POST['db_pass'] ?? '';
            $adminUser = $_POST['admin_user'] ?? '';
            $adminPass = $_POST['admin_pass'] ?? '';

            // 验证参数
            if (empty($dbHost) || empty($dbName) || empty($dbUser) || empty($adminUser) || empty($adminPass)) {
                $this->logger->warning('安装参数不完整', [
                    'db_host' => $dbHost,
                    'db_name' => $dbName,
                    'db_user' => $dbUser,
                    'admin_user' => $adminUser,
                    'ip' => $_SERVER['REMOTE_ADDR']
                ]);
                return $this->json(['error' => '请填写完整信息'], 400);
            }

            $this->logger->debug('开始验证数据库连接', [
                'db_host' => $dbHost,
                'db_name' => $dbName,
                'db_user' => $dbUser
            ]);

            // 测试数据库连接
            try {
                $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
                $db = new \PDO($dsn, $dbUser, $dbPass);
                $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            } catch (\PDOException $e) {
                $this->logger->error('数据库连接失败', [
                    'error' => $e->getMessage(),
                    'db_host' => $dbHost,
                    'db_name' => $dbName,
                    'db_user' => $dbUser
                ]);
                return $this->json(['error' => '数据库连接失败：' . $e->getMessage()], 500);
            }

            $this->logger->debug('数据库连接成功，开始导入数据库结构');

            // 导入数据库结构
            try {
                $sql = file_get_contents('install/database.sql');
                $db->exec($sql);
            } catch (\PDOException $e) {
                $this->logger->error('导入数据库结构失败', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return $this->json(['error' => '导入数据库结构失败：' . $e->getMessage()], 500);
            }

            $this->logger->debug('数据库结构导入成功，开始创建管理员账号');

            // 创建管理员账号
            try {
                $stmt = $db->prepare("INSERT INTO users (username, password, real_name, role, status) VALUES (?, ?, ?, 'admin', 1)");
                $stmt->execute([$adminUser, password_hash($adminPass, PASSWORD_DEFAULT), '系统管理员']);
            } catch (\PDOException $e) {
                $this->logger->error('创建管理员账号失败', [
                    'error' => $e->getMessage(),
                    'admin_user' => $adminUser
                ]);
                return $this->json(['error' => '创建管理员账号失败：' . $e->getMessage()], 500);
            }

            $this->logger->debug('管理员账号创建成功，开始生成配置文件');

            // 生成配置文件
            try {
                $config = "<?php\nreturn [\n    'db' => [\n        'host' => '{$dbHost}',\n        'name' => '{$dbName}',\n        'user' => '{$dbUser}',\n        'pass' => '{$dbPass}'\n    ]\n];";
                file_put_contents('config/config.php', $config);
            } catch (\Exception $e) {
                $this->logger->error('生成配置文件失败', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return $this->json(['error' => '生成配置文件失败：' . $e->getMessage()], 500);
            }

            $this->logger->debug('配置文件生成成功，开始创建安装锁定文件');

            // 创建安装锁定文件
            try {
                file_put_contents('config/install.lock', date('Y-m-d H:i:s'));
            } catch (\Exception $e) {
                $this->logger->error('创建安装锁定文件失败', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return $this->json(['error' => '创建安装锁定文件失败：' . $e->getMessage()], 500);
            }

            $this->logger->debug('系统安装完成');

            return $this->json([
                'success' => true,
                'message' => '安装成功'
            ]);
        } catch (\Exception $e) {
            $this->logger->error('系统安装过程发生错误', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 