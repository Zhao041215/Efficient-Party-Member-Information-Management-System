<?php
/**
 * 系统版本配置
 * 用于CSS/JS文件的缓存控制
 */

// 系统版本号
define('SYSTEM_VERSION', '2.3.3');

// CSS版本号
define('CSS_VERSION', '2.3.3');

// JS版本号
define('JS_VERSION', '2.3.2');

// 获取带版本号的资源URL
function getVersionedAsset($path) {
    $version = CSS_VERSION;
    if (strpos($path, '.js') !== false) {
        $version = JS_VERSION;
    }
    return $path . '?v=' . $version;
}
