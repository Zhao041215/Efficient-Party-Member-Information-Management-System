<?php
/**
 * 安全数据清理脚本
 * 用于定期清理过期的登录记录、封禁IP等
 *
 * 使用方法：
 * 1. 通过 cron 定时执行：0 3 * * * php /path/to/cleanup_security.php
 * 2. 手动执行：php cleanup_security.php
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/security_monitor.php';
require_once __DIR__ . '/../includes/functions.php';

// 记录开始时间
$startTime = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] 开始清理安全数据...\n";

try {
    $monitor = SecurityMonitor::getInstance();

    // 1. 清理过期的IP封禁
    echo "清理过期的IP封禁...\n";
    $monitor->cleanExpiredBlocks();

    // 2. 清理旧的登录记录（保留30天）
    echo "清理旧的登录记录...\n";
    $monitor->cleanOldLoginAttempts();

    // 3. 清理旧的日志文件（保留30天）
    echo "清理旧的日志文件...\n";
    cleanOldLogs();

    // 4. 清理已处理的安全告警（保留90天）
    echo "清理已处理的安全告警...\n";
    $db = Database::getInstance();
    $db->execute(
        "DELETE FROM security_alerts
         WHERE is_handled = 1
         AND handled_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
    );

    // 5. 清理旧的操作日志（保留180天）
    echo "清理旧的操作日志...\n";
    $db->execute(
        "DELETE FROM operation_logs
         WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)"
    );

    // 6. 清理旧的邮件发送日志（保留90天）
    echo "清理旧的邮件发送日志...\n";
    $db->execute(
        "DELETE FROM email_send_log
         WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
    );

    // 计算执行时间
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);

    echo "[" . date('Y-m-d H:i:s') . "] 清理完成！耗时: {$duration}秒\n";

    // 记录到日志
    logSecurity('SECURITY_CLEANUP_COMPLETED', [
        'duration' => $duration . '秒',
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] 清理失败: " . $e->getMessage() . "\n";

    // 记录错误
    logError('安全数据清理失败', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    exit(1);
}

exit(0);
