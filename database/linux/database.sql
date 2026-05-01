-- =====================================================
-- 生化学院党员信息管理系统 - 完整数据库
-- 版本: v1.3
-- 适用于: 新服务器部署
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

-- 创建数据库
CREATE DATABASE IF NOT EXISTS `shxyinfo` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `shxyinfo`;

-- 注意：数据库用户将由部署脚本创建，此处不再创建用户
-- 部署脚本会创建具有最小权限的专用用户

-- =====================================================
-- 1. 用户表
-- =====================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL COMMENT '用户名',
    `password` VARCHAR(255) NOT NULL COMMENT '密码',
    `role` ENUM('student', 'teacher', 'admin', 'superadmin') NOT NULL DEFAULT 'student' COMMENT '角色',
    `name` VARCHAR(50) NOT NULL COMMENT '姓名',
    `email` VARCHAR(100) DEFAULT NULL COMMENT '邮箱',
    `is_first_login` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否首次登录',
    `force_change_password` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否强制修改密码',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '账号是否有效',
    `is_graduated` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否毕业',
    `remember_token` VARCHAR(255) DEFAULT NULL COMMENT '记住登录token',
    `token_expire` DATETIME DEFAULT NULL COMMENT 'token过期时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`),
    KEY `idx_role` (`role`),
    KEY `idx_is_active` (`is_active`),
    KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='用户表';

-- =====================================================
-- 2. 学生信息表
-- =====================================================
DROP TABLE IF EXISTS `student_info`;
CREATE TABLE `student_info` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL COMMENT '关联用户ID',
    `student_no` VARCHAR(50) NOT NULL COMMENT '学号',
    `name` VARCHAR(50) NOT NULL COMMENT '姓名',
    `gender` ENUM('男', '女') DEFAULT NULL COMMENT '性别',
    `college` VARCHAR(100) DEFAULT NULL COMMENT '学院',
    `grade` VARCHAR(50) DEFAULT NULL COMMENT '年级',
    `class` VARCHAR(50) DEFAULT NULL COMMENT '班级',
    `birth_date` DATE DEFAULT NULL COMMENT '出生日期',
    `ethnicity` VARCHAR(50) DEFAULT NULL COMMENT '民族',
    `id_card` VARCHAR(18) DEFAULT NULL COMMENT '身份证号',
    `address` TEXT DEFAULT NULL COMMENT '家庭住址',
    `phone` VARCHAR(20) DEFAULT NULL COMMENT '联系方式',
    `email` VARCHAR(100) DEFAULT NULL COMMENT '邮箱',
    `political_status` VARCHAR(50) DEFAULT '入党积极分子' COMMENT '政治面貌',
    `age` INT DEFAULT NULL COMMENT '年龄',
    `join_league_date` DATE DEFAULT NULL COMMENT '入团时间',
    `apply_party_date` DATE DEFAULT NULL COMMENT '递交入党申请书时间',
    `activist_date` DATE DEFAULT NULL COMMENT '确定积极分子时间',
    `probationary_date` DATE DEFAULT NULL COMMENT '确定预备党员时间',
    `full_member_date` DATE DEFAULT NULL COMMENT '转正时间',
    `graduation_year` VARCHAR(20) DEFAULT NULL COMMENT '毕业时间',
    `info_completed` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '信息是否填写完整',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_id` (`user_id`),
    UNIQUE KEY `uk_student_no` (`student_no`),
    KEY `idx_grade` (`grade`),
    KEY `idx_class` (`class`),
    KEY `idx_political_status` (`political_status`),
    CONSTRAINT `fk_student_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='学生信息表';

-- =====================================================
-- 3. 信息修改申请表
-- =====================================================
DROP TABLE IF EXISTS `info_change_requests`;
CREATE TABLE `info_change_requests` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL COMMENT '申请人ID',
    `student_no` VARCHAR(50) NOT NULL COMMENT '学号',
    `field_name` VARCHAR(50) NOT NULL COMMENT '字段名',
    `field_label` VARCHAR(50) NOT NULL COMMENT '字段标签',
    `old_value` TEXT DEFAULT NULL COMMENT '原值',
    `new_value` TEXT DEFAULT NULL COMMENT '新值',
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending' COMMENT '状态',
    `batch_id` VARCHAR(50) NOT NULL COMMENT '批次ID',
    `reviewed_by` INT UNSIGNED DEFAULT NULL COMMENT '审核人ID',
    `reviewed_at` DATETIME DEFAULT NULL COMMENT '审核时间',
    `reject_reason` TEXT DEFAULT NULL COMMENT '拒绝原因',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_batch_id` (`batch_id`),
    CONSTRAINT `fk_request_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='信息修改申请表';

-- =====================================================
-- 4. 毕业生信息表
-- =====================================================
DROP TABLE IF EXISTS `graduated_students`;
CREATE TABLE `graduated_students` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_no` VARCHAR(50) NOT NULL COMMENT '学号',
    `name` VARCHAR(50) NOT NULL COMMENT '姓名',
    `gender` ENUM('男', '女') DEFAULT NULL COMMENT '性别',
    `college` VARCHAR(100) DEFAULT NULL COMMENT '学院',
    `grade` VARCHAR(50) DEFAULT NULL COMMENT '年级',
    `class` VARCHAR(50) DEFAULT NULL COMMENT '班级',
    `birth_date` DATE DEFAULT NULL COMMENT '出生日期',
    `ethnicity` VARCHAR(50) DEFAULT NULL COMMENT '民族',
    `id_card` VARCHAR(18) DEFAULT NULL COMMENT '身份证号',
    `address` TEXT DEFAULT NULL COMMENT '家庭住址',
    `phone` VARCHAR(20) DEFAULT NULL COMMENT '联系方式',
    `email` VARCHAR(100) DEFAULT NULL COMMENT '邮箱',
    `political_status` VARCHAR(50) DEFAULT NULL COMMENT '政治面貌',
    `age` INT DEFAULT NULL COMMENT '年龄',
    `join_league_date` DATE DEFAULT NULL COMMENT '入团时间',
    `apply_party_date` DATE DEFAULT NULL COMMENT '递交入党申请书时间',
    `activist_date` DATE DEFAULT NULL COMMENT '确定积极分子时间',
    `probationary_date` DATE DEFAULT NULL COMMENT '确定预备党员时间',
    `full_member_date` DATE DEFAULT NULL COMMENT '转正时间',
    `graduation_year` VARCHAR(20) DEFAULT NULL COMMENT '毕业时间',
    `graduated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '移入毕业生库时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_student_no` (`student_no`),
    KEY `idx_grade` (`grade`),
    KEY `idx_class` (`class`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='毕业生信息表';

-- =====================================================
-- 5. 系统选项设置表
-- =====================================================
DROP TABLE IF EXISTS `system_options`;
CREATE TABLE `system_options` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type` ENUM('college', 'grade', 'class', 'political_status', 'ethnicity', 'development_time') NOT NULL COMMENT '选项类型',
    `value` VARCHAR(100) NOT NULL COMMENT '选项值',
    `sort_order` INT DEFAULT 0 COMMENT '排序',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_type` (`type`),
    KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='系统选项设置表';

-- =====================================================
-- 6. 操作日志表
-- =====================================================
DROP TABLE IF EXISTS `operation_logs`;
CREATE TABLE `operation_logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED DEFAULT NULL COMMENT '操作人ID',
    `username` VARCHAR(50) DEFAULT NULL COMMENT '操作人用户名',
    `action` VARCHAR(100) NOT NULL COMMENT '操作类型',
    `description` TEXT DEFAULT NULL COMMENT '操作描述',
    `details` TEXT DEFAULT NULL COMMENT '操作详情',
    `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP地址',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_action` (`action`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='操作日志表';

-- =====================================================
-- 6.1. 操作日志完整明细表
-- =====================================================
DROP TABLE IF EXISTS `operation_log_full_details`;
CREATE TABLE `operation_log_full_details` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `operation_log_id` INT UNSIGNED NOT NULL COMMENT '操作日志ID',
    `detail_scope` VARCHAR(100) NOT NULL DEFAULT 'admin_sensitive_operation' COMMENT '明细范围',
    `detail_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '明细数量',
    `details_json` LONGTEXT NOT NULL COMMENT '完整明细JSON',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_operation_log_id` (`operation_log_id`),
    KEY `idx_detail_scope` (`detail_scope`),
    CONSTRAINT `fk_operation_log_full_details_log`
        FOREIGN KEY (`operation_log_id`) REFERENCES `operation_logs` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='操作日志完整明细表';

-- =====================================================
-- 7. 密码重置验证码表
-- =====================================================
DROP TABLE IF EXISTS `password_reset_codes`;
CREATE TABLE `password_reset_codes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL COMMENT '用户ID',
    `email` VARCHAR(100) NOT NULL COMMENT '邮箱地址',
    `code` VARCHAR(6) NOT NULL COMMENT '验证码',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `expires_at` TIMESTAMP NOT NULL COMMENT '过期时间',
    `used` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否已使用',
    `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP地址',
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_code` (`code`),
    KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='密码重置验证码表';

-- =====================================================
-- 8. 邮件发送日志表
-- =====================================================
DROP TABLE IF EXISTS `email_send_log`;
CREATE TABLE `email_send_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED DEFAULT NULL COMMENT '用户ID',
    `to_email` VARCHAR(100) NOT NULL COMMENT '收件人邮箱',
    `subject` VARCHAR(200) NOT NULL COMMENT '邮件主题',
    `send_status` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '发送状态(0:失败 1:成功)',
    `error_message` TEXT DEFAULT NULL COMMENT '发送失败的错误信息',
    `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP地址',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_email` (`to_email`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='邮件发送日志表';

-- =====================================================
-- 9. 登录尝试记录表
-- =====================================================
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL COMMENT '用户名',
    `ip_address` VARCHAR(45) NOT NULL COMMENT 'IP地址',
    `user_agent` VARCHAR(255) DEFAULT NULL COMMENT '用户代理',
    `success` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否成功',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_username` (`username`),
    KEY `idx_ip_address` (`ip_address`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='登录尝试记录表';

-- =====================================================
-- 10. 封禁IP表
-- =====================================================
DROP TABLE IF EXISTS `blocked_ips`;
CREATE TABLE `blocked_ips` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip_address` VARCHAR(45) NOT NULL COMMENT 'IP地址',
    `reason` VARCHAR(50) NOT NULL COMMENT '封禁原因',
    `expires_at` DATETIME DEFAULT NULL COMMENT '过期时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ip_address` (`ip_address`),
    KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='封禁IP表';

-- =====================================================
-- 11. 安全告警表
-- =====================================================
DROP TABLE IF EXISTS `security_alerts`;
CREATE TABLE `security_alerts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `alert_type` VARCHAR(50) NOT NULL COMMENT '告警类型',
    `details` TEXT COMMENT '详细信息',
    `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP地址',
    `is_handled` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否已处理',
    `handled_at` DATETIME DEFAULT NULL COMMENT '处理时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_alert_type` (`alert_type`),
    KEY `idx_is_handled` (`is_handled`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='安全告警表';

-- =====================================================
-- 插入初始数据
-- =====================================================

-- 默认管理员账号 (密码: admin123)
INSERT INTO `users` (`username`, `password`, `role`, `name`, `is_first_login`, `force_change_password`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin', '系统管理员', 1, 1);

-- 学院
INSERT INTO `system_options` (`type`, `value`, `sort_order`) VALUES ('college', '生化学院', 1);

-- 年级
INSERT INTO `system_options` (`type`, `value`, `sort_order`) VALUES
('grade', '2021级', 1), ('grade', '2022级', 2), ('grade', '2023级', 3),
('grade', '2024级', 4), ('grade', '2025级', 5), ('grade', '2026级', 6);

-- 班级
INSERT INTO `system_options` (`type`, `value`, `sort_order`) VALUES
('class', '生物科学1班', 1), ('class', '生物科学2班', 2),
('class', '化学1班', 3), ('class', '化学2班', 4),
('class', '应用化学1班', 5), ('class', '应用化学2班', 6),
('class', '生物技术1班', 7), ('class', '生物技术2班', 8);

-- 政治面貌
INSERT INTO `system_options` (`type`, `value`, `sort_order`) VALUES
('political_status', '入党积极分子', 1), ('political_status', '发展对象', 2),
('political_status', '预备党员', 3), ('political_status', '正式党员', 4),
('political_status', '共青团员', 5), ('political_status', '群众', 6);

-- 民族
INSERT INTO `system_options` (`type`, `value`, `sort_order`) VALUES
('ethnicity', '汉族', 1), ('ethnicity', '蒙古族', 2), ('ethnicity', '回族', 3),
('ethnicity', '藏族', 4), ('ethnicity', '维吾尔族', 5), ('ethnicity', '苗族', 6),
('ethnicity', '彝族', 7), ('ethnicity', '壮族', 8), ('ethnicity', '布依族', 9),
('ethnicity', '朝鲜族', 10), ('ethnicity', '满族', 11), ('ethnicity', '侗族', 12),
('ethnicity', '瑶族', 13), ('ethnicity', '白族', 14), ('ethnicity', '土家族', 15),
('ethnicity', '哈尼族', 16), ('ethnicity', '哈萨克族', 17), ('ethnicity', '傣族', 18),
('ethnicity', '黎族', 19), ('ethnicity', '傈僳族', 20), ('ethnicity', '佤族', 21),
('ethnicity', '畲族', 22), ('ethnicity', '高山族', 23), ('ethnicity', '拉祜族', 24),
('ethnicity', '水族', 25), ('ethnicity', '东乡族', 26), ('ethnicity', '纳西族', 27),
('ethnicity', '景颇族', 28), ('ethnicity', '柯尔克孜族', 29), ('ethnicity', '土族', 30),
('ethnicity', '达斡尔族', 31), ('ethnicity', '仫佬族', 32), ('ethnicity', '羌族', 33),
('ethnicity', '布朗族', 34), ('ethnicity', '撒拉族', 35), ('ethnicity', '毛南族', 36),
('ethnicity', '仡佬族', 37), ('ethnicity', '锡伯族', 38), ('ethnicity', '阿昌族', 39),
('ethnicity', '普米族', 40), ('ethnicity', '塔吉克族', 41), ('ethnicity', '怒族', 42),
('ethnicity', '乌孜别克族', 43), ('ethnicity', '俄罗斯族', 44), ('ethnicity', '鄂温克族', 45),
('ethnicity', '德昂族', 46), ('ethnicity', '保安族', 47), ('ethnicity', '裕固族', 48),
('ethnicity', '京族', 49), ('ethnicity', '塔塔尔族', 50), ('ethnicity', '独龙族', 51),
('ethnicity', '鄂伦春族', 52), ('ethnicity', '赫哲族', 53), ('ethnicity', '门巴族', 54),
('ethnicity', '珞巴族', 55), ('ethnicity', '基诺族', 56);

-- 发展时间配置（示例）
INSERT INTO `system_options` (`type`, `value`, `sort_order`) VALUES
('development_time', '确定入党积极分子时间|2024-03-15', 1),
('development_time', '确定中共预备党员时间|2024-06-20', 2),
('development_time', '确定中共党员时间|2024-12-25', 3);

-- =====================================================
-- 12. 反馈表
-- =====================================================
DROP TABLE IF EXISTS `feedback`;
CREATE TABLE `feedback` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL COMMENT '提交用户ID',
    `type` ENUM('bug','suggestion') NOT NULL COMMENT '类型：bug或建议',
    `title` VARCHAR(200) NOT NULL COMMENT '标题',
    `content` TEXT NOT NULL COMMENT '详细内容',
    `contact` VARCHAR(100) DEFAULT NULL COMMENT '联系方式（可选）',
    `device` VARCHAR(50) DEFAULT NULL COMMENT '设备类型',
    `device_model` VARCHAR(100) DEFAULT NULL COMMENT '设备机型',
    `bug_time` DATETIME DEFAULT NULL COMMENT 'Bug出现时间',
    `screenshot` VARCHAR(255) DEFAULT NULL COMMENT '截图路径',
    `status` ENUM('pending','processing','resolved','closed') DEFAULT 'pending' COMMENT '状态',
    `admin_reply` TEXT DEFAULT NULL COMMENT '管理员回复',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `type` (`type`),
    KEY `status` (`status`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='反馈表';

-- 初始化日志
INSERT INTO `operation_logs` (`user_id`, `username`, `action`, `description`, `ip_address`) VALUES
(1, 'system', 'database_init', '数据库初始化完成 - v1.3（含反馈系统）', '127.0.0.1');

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

-- 部署完成
SELECT '数据库部署完成！' AS message;
SELECT '默认账号: admin / admin123' AS info;
SELECT '请首次登录后立即修改密码！' AS warning;
