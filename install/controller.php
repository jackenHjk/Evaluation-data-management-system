<?php
/**
 * 文件名: install/controller.php
 * 功能描述: 系统安装控制器
 * 
 * 该文件负责:
 * 1. 定义安装流程和步骤
 * 2. 管理安装过程中的会话状态
 * 3. 检查系统环境要求
 * 4. 验证安装状态和数据库连接
 * 5. 提供安装步骤之间的导航功能
 * 
 * 安装流程包括:
 * - 环境检查: 验证PHP版本和必要扩展
 * - 数据库配置: 设置数据库连接参数
 * - 安装系统: 导入数据库结构和创建管理员账号
 * - 安装完成: 显示安装成功信息
 * 
 * 关联文件:
 * - install/index.php: 安装向导主界面
 * - install/steps/: 安装步骤目录
 * - install/database.sql: 数据库结构SQL文件
 * - config/install.lock: 安装锁定文件
 * - config/config.php: 系统配置文件
 */

session_start();

class InstallController {
    protected $steps = [
        'check' => [
            'title' => '环境检查',
            'file' => 'steps/check.php'
        ],
        'database' => [
            'title' => '数据库配置',
            'file' => 'steps/database.php'
        ],
        'install' => [
            'title' => '安装系统',
            'file' => 'steps/install.php'
        ],
        'complete' => [
            'title' => '安装完成',
            'file' => 'steps/complete.php'
        ]
    ];
    
    public function getSteps() {
        return $this->steps;
    }
    
    public function __construct() {
        if (!isset($_SESSION['step'])) {
            $_SESSION['step'] = 'check';
        }
    }
    
    public function checkInstalled() {
        // 如果当前步骤是complete，不进行检查
        if (isset($_SESSION['step']) && $_SESSION['step'] === 'complete') {
            return false;
        }
        
        $lock_file = __DIR__ . '/../config/install.lock';
        $config_file = __DIR__ . '/../config/config.php';
        
        if (file_exists($lock_file)) {
            // 检查数据库配置是否存在且可用
            if (file_exists($config_file)) {
                try {
                    $config = require $config_file;
                    if (isset($config['db'])) {
                        $db = $config['db'];
                        $pdo = new PDO(
                            "mysql:host={$db['host']};dbname={$db['dbname']}", 
                            $db['username'], 
                            $db['password'],
                            array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$db['charset']}")
                        );
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        
                        // 检查必要的表是否存在
                        $required_tables = ['users', 'grades', 'subjects', 'classes', 'students', 'scores'];
                        $existing_tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                        
                        $missing_tables = array_diff($required_tables, $existing_tables);
                        if (empty($missing_tables)) {
                            $_SESSION['step'] = 'complete';
                            return true;
                        }
                        
                        // 如果缺少表，删除锁定文件
                        unlink($lock_file);
                        return false;
                    }
                } catch (Exception $e) {
                    error_log("安装检查错误: " . $e->getMessage());
                    // 数据库连接失败，删除锁定文件
                    unlink($lock_file);
                    return false;
                }
            }
            
            // 配置文件不存在，删除锁定文件
            unlink($lock_file);
            return false;
        }
        
        return false;
    }
    
    public function getCurrentStep() {
        return $_SESSION['step'];
    }
    
    public function getStepFile() {
        return $this->steps[$_SESSION['step']]['file'];
    }
    
    public function getStepTitle() {
        return $this->steps[$_SESSION['step']]['title'];
    }
    
    public function nextStep() {
        $current = array_keys($this->steps);
        $current_index = array_search($_SESSION['step'], $current);
        
        if ($current_index !== false && isset($current[$current_index + 1])) {
            $_SESSION['step'] = $current[$current_index + 1];
            return true;
        }
        
        return false;
    }
    
    public function checkRequirements() {
        $requirements = [];
        
        // PHP版本检查
        $requirements['php_version'] = [
            'name' => 'PHP版本',
            'required' => '8.1.0',
            'current' => PHP_VERSION,
            'result' => version_compare(PHP_VERSION, '8.1.0', '>='),
            'help' => 'PHP版本必须 >= 8.1.0，请升级您的PHP版本'
        ];
        
        // 必需的PHP扩展检查
        $required_extensions = [
            'pdo_mysql' => [
                'name' => 'PDO MySQL扩展',
                'alternatives' => ['pdo_mysql'],
                'help' => [
                    'windows' => '在php.ini中取消注释 extension=pdo_mysql',
                    'linux' => [
                        'apt' => 'apt-get install php8.1-mysql',
                        'yum' => 'yum install php-mysql'
                    ]
                ]
            ],
            'gd' => [
                'name' => 'GD扩展',
                'alternatives' => ['gd', 'gd2'],
                'help' => [
                    'windows' => '在php.ini中取消注释 extension=gd',
                    'linux' => [
                        'apt' => 'apt-get install php8.1-gd',
                        'yum' => 'yum install php-gd'
                    ]
                ]
            ],
            'iconv' => [
                'name' => 'ICONV扩展',
                'alternatives' => ['iconv'],
                'help' => [
                    'windows' => 'PHP 8.1+ 已内置该扩展，请在php.ini中检查是否启用',
                    'linux' => '该扩展通常随PHP一起安装，无需额外安装'
                ]
            ],
            'intl' => [
                'name' => 'INTL扩展',
                'alternatives' => ['intl'],
                'help' => [
                    'windows' => '在php.ini中取消注释 extension=intl',
                    'linux' => [
                        'apt' => 'apt-get install php8.1-intl',
                        'yum' => 'yum install php-intl'
                    ]
                ]
            ],
            'mysqli' => [
                'name' => 'MySQLi扩展',
                'alternatives' => ['mysqli'],
                'help' => [
                    'windows' => '在php.ini中取消注释 extension=mysqli',
                    'linux' => [
                        'apt' => 'apt-get install php8.1-mysql',
                        'yum' => 'yum install php-mysql'
                    ]
                ]
            ],
            'zip' => [
                'name' => 'ZIP扩展',
                'alternatives' => ['zip'],
                'help' => [
                    'windows' => '在php.ini中取消注释 extension=zip',
                    'linux' => [
                        'apt' => 'apt-get install php8.1-zip',
                        'yum' => 'yum install php-zip'
                    ]
                ]
            ],
            'zlib' => [
                'name' => 'ZLIB扩展',
                'alternatives' => ['zlib'],
                'help' => [
                    'windows' => 'PHP 8.1+ 已内置该扩展，请在php.ini中检查是否启用',
                    'linux' => '该扩展通常随PHP一起安装，无需额外安装'
                ]
            ]
        ];
        
        foreach ($required_extensions as $ext => $info) {
            $installed = false;
            foreach ($info['alternatives'] as $alt) {
                if (extension_loaded($alt)) {
                    $installed = true;
                    break;
                }
            }
            
            $os_type = stripos(PHP_OS, 'WIN') === 0 ? 'windows' : 'linux';
            $help_info = $info['help'][$os_type];
            if (is_array($help_info)) {
                $help_info = implode(' 或 ', array_values($help_info));
            }
            
            $requirements['ext_' . $ext] = [
                'name' => $info['name'],
                'required' => '已安装',
                'current' => $installed ? '已安装' : '未安装',
                'result' => $installed,
                'help' => $help_info
            ];
        }

        // PHP配置检查
        $php_configs = [
            'file_uploads' => [
                'name' => '文件上传',
                'required' => 'On',
                'current' => ini_get('file_uploads') ? 'On' : 'Off',
                'result' => ini_get('file_uploads'),
                'help' => '在php.ini中设置 file_uploads = On'
            ],
            'post_max_size' => [
                'name' => '最大POST大小',
                'required' => '8M',
                'current' => ini_get('post_max_size'),
                'result' => $this->compareSize(ini_get('post_max_size'), '8M'),
                'help' => '在php.ini中设置 post_max_size >= 8M'
            ],
            'upload_max_filesize' => [
                'name' => '最大上传文件',
                'required' => '8M',
                'current' => ini_get('upload_max_filesize'),
                'result' => $this->compareSize(ini_get('upload_max_filesize'), '8M'),
                'help' => '在php.ini中设置 upload_max_filesize >= 8M'
            ],
            'max_execution_time' => [
                'name' => '最大执行时间',
                'required' => '30秒',
                'current' => ini_get('max_execution_time') . '秒',
                'result' => ini_get('max_execution_time') >= 30,
                'help' => '在php.ini中设置 max_execution_time >= 30'
            ]
        ];
        
        $requirements = array_merge($requirements, $php_configs);
        
        // 目录权限检查
        $check_dirs = [
            '../config' => '配置目录',
            '../uploads' => '上传目录',
            '../temp' => '临时目录',
            '../logs' => '日志目录',
            '../api/logs' => 'API日志目录'
        ];
        
        foreach ($check_dirs as $dir => $name) {
            $dir_path = __DIR__ . '/' . $dir;
            if (!file_exists($dir_path)) {
                mkdir($dir_path, 0755, true);
            }
            
            $is_writable = is_writable($dir_path);
            $requirements['dir_' . str_replace('/', '_', basename($dir))] = [
                'name' => $name . '权限',
                'required' => '可写',
                'current' => $is_writable ? '可写' : '不可写',
                'result' => $is_writable,
                'help' => $is_writable ? '' : sprintf(
                    '请设置目录 %s 的权限为可写。Linux请执行: chmod -R 755 %s',
                    $dir_path,
                    $dir_path
                )
            ];
        }
        
        return $requirements;
    }

    /**
     * 比较PHP配置大小
     */
    private function compareSize($size1, $size2) {
        $size1 = $this->convertToBytes($size1);
        $size2 = $this->convertToBytes($size2);
        return $size1 >= $size2;
    }

    /**
     * 转换大小为字节数
     */
    private function convertToBytes($size) {
        $unit = strtolower(substr($size, -1));
        $value = (int)$size;
        
        switch ($unit) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    public function allRequirementsMet() {
        $requirements = $this->checkRequirements();
        foreach ($requirements as $requirement) {
            if (!$requirement['result']) {
                return false;
            }
        }
        return true;
    }
}

$controller = new InstallController(); 