<?php
/**
 * 文件名: core/ChineseNameSorter.php
 * 功能描述: 中文姓名排序工具类
 * 
 * 该类负责:
 * 1. 提供中文姓名的拼音转换功能
 * 2. 处理中文姓氏（包括复姓）的识别
 * 3. 处理多音字姓氏的正确读音
 * 4. 按拼音顺序对中文姓名数组进行排序
 * 
 * 这个类用于系统中需要对中文姓名进行排序的场景，如学生列表、
 * 成绩单等，确保中文姓名能够按照正确的拼音顺序排列。
 * 
 * 关联文件:
 * - controllers/StudentController.php: 学生控制器，使用该类进行学生姓名排序
 * - controllers/ScoreController.php: 成绩控制器，使用该类进行成绩单姓名排序
 * - api/index.php: API入口文件，引用该类
 */

namespace Core;

class ChineseNameSorter {
    // 常见的复姓列表
    private static $compoundSurnames = [
        '欧阳', '太史', '端木', '上官', '司马', '东方', '独孤', '南宫', '万俟', '闻人', 
        '夏侯', '诸葛', '尉迟', '公羊', '赫连', '澹台', '皇甫', '宗政', '濮阳', '公冶',
        '太叔', '申屠', '公孙', '慕容', '仲孙', '钟离', '长孙', '宇文', '司徒', '鲜于',
        '司空', '闾丘', '子车', '亓官', '司寇', '巫马', '公西', '颛孙', '壤驷', '公良',
        '漆雕', '乐正', '宰父', '谷梁', '拓跋', '夹谷', '轩辕', '令狐', '段干', '百里',
        '呼延', '东郭', '南门', '羊舌', '微生', '公户', '公玉', '公仪', '梁丘', '公仲',
        '公上', '公门', '公山', '公坚', '左丘', '公伯', '西门', '公祖', '第五', '公乘',
        '贯丘', '公皙', '南荣', '东里', '东宫', '仲长', '子书', '子桑', '即墨', '达奚',
        '褚师', '吴铭'
    ];

    // 常见多音字姓氏读音映射
    private static $surnameReadings = [
        '单' => 'shan', '仇' => 'qiu', '解' => 'xie', '曾' => 'zeng', '朴' => 'piao',
        '查' => 'zha', '翟' => 'di', '覃' => 'qin', '冼' => 'xian', '殷' => 'yin',
        '蓝' => 'lan', '秘' => 'bi', '乐' => 'yue', '召' => 'shao', '和' => 'he',
        '莫' => 'mo', '盖' => 'ge', '祭' => 'zhai', '折' => 'she', '黑' => 'he'
    ];

    // 拼音转换表
    private static $pinyinTable = [
        // 声母表
        '啊' => 'a', '芭' => 'ba', '擦' => 'ca', '搭' => 'da', '蛾' => 'e', '发' => 'fa', '噶' => 'ga', '哈' => 'ha',
        '击' => 'ji', '喀' => 'ka', '垃' => 'la', '妈' => 'ma', '拿' => 'na', '哦' => 'o', '啪' => 'pa', '期' => 'qi',
        '然' => 'ran', '撒' => 'sa', '塌' => 'ta', '挖' => 'wa', '昔' => 'xi', '压' => 'ya', '匝' => 'za',
        
        // 常用姓氏
        '张' => 'zhang', '陈' => 'chen', '李' => 'li', '王' => 'wang', '刘' => 'liu', '吴' => 'wu', '赵' => 'zhao',
        '孙' => 'sun', '周' => 'zhou', '吕' => 'lv', '徐' => 'xu', '何' => 'he', '马' => 'ma', '朱' => 'zhu',
        '胡' => 'hu', '郭' => 'guo', '林' => 'lin', '高' => 'gao', '梁' => 'liang', '郑' => 'zheng',
        '罗' => 'luo', '宋' => 'song', '谢' => 'xie', '唐' => 'tang', '韩' => 'han', '曹' => 'cao', '许' => 'xu',
        '邓' => 'deng', '萧' => 'xiao', '冯' => 'feng', '曾' => 'zeng', '程' => 'cheng', '蔡' => 'cai', '彭' => 'peng',
        '潘' => 'pan', '袁' => 'yuan', '于' => 'yu', '董' => 'dong', '余' => 'yu', '苏' => 'su', '叶' => 'ye',
        '魏' => 'wei', '孟' => 'meng', '文' => 'wen', '范' => 'fan', '金' => 'jin', '欧' => 'ou', '黄' => 'huang',
        
        // 常用字
        '丹' => 'dan', '建' => 'jian', '坤' => 'kun', '子' => 'zi', '萱' => 'xuan', '蓉' => 'rong',
        '佳' => 'jia', '明' => 'ming', '慧' => 'hui', '秀' => 'xiu', '娜' => 'na', '静' => 'jing',
        '淑' => 'shu', '雅' => 'ya', '婷' => 'ting', '颖' => 'ying', '琳' => 'lin', '璐' => 'lu',
        '晶' => 'jing', '妍' => 'yan', '茜' => 'qian', '秋' => 'qiu', '珊' => 'shan', '莹' => 'ying',
        '美' => 'mei', '玲' => 'ling', '凤' => 'feng', '荣' => 'rong', '兰' => 'lan', '月' => 'yue',
        '洋' => 'yang', '海' => 'hai', '亮' => 'liang', '天' => 'tian', '小' => 'xiao', '大' => 'da',
        '中' => 'zhong', '国' => 'guo', '平' => 'ping', '安' => 'an', '家' => 'jia', '和' => 'he',
        '爱' => 'ai', '德' => 'de', '智' => 'zhi', '信' => 'xin', '永' => 'yong', '春' => 'chun',
        '志' => 'zhi', '伟' => 'wei', '嘉' => 'jia', '东' => 'dong', '南' => 'nan', '西' => 'xi',
        '北' => 'bei', '中' => 'zhong', '山' => 'shan', '水' => 'shui', '木' => 'mu', '火' => 'huo',
        '土' => 'tu', '金' => 'jin', '竹' => 'zhu', '雨' => 'yu', '风' => 'feng', '花' => 'hua',
        '雪' => 'xue', '月' => 'yue', '日' => 'ri', '龙' => 'long', '凤' => 'feng', '云' => 'yun'
    ];

    /**
     * 获取姓氏（支持复姓）
     */
    public static function getSurname($name) {
        foreach (self::$compoundSurnames as $surname) {
            if (mb_strpos($name, $surname) === 0) {
                return $surname;
            }
        }
        return mb_substr($name, 0, 1);
    }

    /**
     * 获取姓氏拼音（处理多音字）
     */
    public static function getSurnamePinyin($surname) {
        if (isset(self::$surnameReadings[$surname])) {
            return self::$surnameReadings[$surname];
        }
        return self::getPinyin($surname);
    }

    /**
     * 获取完整姓名的拼音（姓氏优先处理）
     */
    public static function getFullNamePinyin($name) {
        $surname = self::getSurname($name);
        $surnamePinyin = self::getSurnamePinyin($surname);
        
        $givenName = mb_substr($name, mb_strlen($surname));
        $givenNamePinyin = self::getPinyin($givenName);
        
        return $surnamePinyin . ' ' . $givenNamePinyin;
    }

    /**
     * 获取文字的拼音
     */
    private static function getPinyin($text) {
        $result = '';
        $len = mb_strlen($text);
        
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($text, $i, 1);
            if (isset(self::$pinyinTable[$char])) {
                $result .= self::$pinyinTable[$char];
            } else {
                // 如果找不到拼音，使用原字符
                $result .= $char;
            }
            
            // 除最后一个字外，每个字后面加空格
            if ($i < $len - 1) {
                $result .= ' ';
            }
        }
        
        return $result;
    }

    /**
     * 排序中文姓名数组
     */
    public static function sort(&$names) {
        error_log("开始排序姓名数组: " . implode(", ", $names));
        
        try {
            usort($names, function($a, $b) {
                try {
                    // 获取完整姓名的拼音
                    $pinyinA = self::getFullNamePinyin($a);
                    $pinyinB = self::getFullNamePinyin($b);
                    
                    error_log("比较: {$a}({$pinyinA}) vs {$b}({$pinyinB})");
                    
                    // 首先按拼音排序
                    $result = strcmp($pinyinA, $pinyinB);
                    
                    // 如果拼音相同，则按原始文字排序
                    if ($result === 0) {
                        return strcmp($a, $b);
                    }
                    
                    return $result;
                } catch (\Exception $e) {
                    error_log("姓名比较出错: " . $e->getMessage());
                    throw $e;
                }
            });
            
            error_log("排序完成: " . implode(", ", $names));
        } catch (\Exception $e) {
            error_log("排序过程出错: " . $e->getMessage());
            throw $e;
        }
    }
} 