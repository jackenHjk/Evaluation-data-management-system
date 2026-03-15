<?php

// 定义基础目录
$baseDir = __DIR__;

// 注册自动加载器
spl_autoload_register(function ($class) use ($baseDir) {
    // 记录类加载尝试
    error_log("Trying to load class: " . $class);
    
    // 命名空间映射
    $map = [
        'PhpOffice\\PhpSpreadsheet\\' => $baseDir . '/PhpOffice/PhpSpreadsheet/src/',
        'Psr\\SimpleCache\\' => $baseDir . '/Psr/SimpleCache/',
        'Matrix\\' => $baseDir . '/Matrix/src/',
        'Complex\\' => $baseDir . '/Complex/src/',
        'ZipStream\\' => $baseDir . '/ZipStream/',
        'MyCLabs\\Enum\\' => $baseDir . '/MyCLabs/Enum/'
    ];

    // 遍历映射查找类文件
    foreach ($map as $namespace => $dir) {
        if (strpos($class, $namespace) === 0) {
            $relativeClass = substr($class, strlen($namespace));
            $file = $dir . str_replace('\\', '/', $relativeClass) . '.php';
            
            error_log("Looking for class file: " . $file);
            
            if (file_exists($file)) {
                require $file;
                error_log("Successfully loaded: " . $file);
                return true;
            }
            
            error_log("File not found: " . $file);
        }
    }

    // 如果在vendor中找不到，尝试在项目根目录中查找
    $projectFile = $baseDir . '/../' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($projectFile)) {
        require $projectFile;
        error_log("Loaded from project root: " . $projectFile);
        return true;
    }

    error_log("Could not find class: " . $class);
    return false;
});