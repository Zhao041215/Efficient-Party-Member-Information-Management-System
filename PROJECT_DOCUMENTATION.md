# 生化学院党员信息管理系统 - 项目技术文档

## 文档信息

| 项目 | 信息 |
|------|------|
| 项目名称 | 生化学院党员信息管理系统 |
| 当前版本 | v2.0.4 |
| 文档版本 | v1.1 |
| 更新日期 | 2026-04-28 |
| 文档类型 | 技术文档 |
| 适用对象 | 开发、运维、测试、系统管理员 |

---

## 目录

- [一、项目概述](#一项目概述)
- [二、系统架构](#二系统架构)
- [三、数据库设计](#三数据库设计)
- [四、页面与权限](#四页面与权限)
- [五、API 接口清单](#五api-接口清单)
- [六、前端实现说明](#六前端实现说明)
- [七、安全机制](#七安全机制)
- [八、部署与运维](#八部署与运维)
- [九、更新记录](#九更新记录)

---

## 一、项目概述

### 1.1 项目目标

本系统面向高校党务信息管理场景，提供学生党员信息采集、修改审核、统计分析、毕业归档、密码找回、用户反馈与后台配置等功能。

### 1.2 核心能力

- 多角色权限控制：学生、教师、管理员、系统管理员。
- 学生信息全流程管理：首次填写、后续修改、审核、归档。
- 党建数据看板：统计卡片、图表、实时刷新。
- 批量操作：导入账户、导入学生信息、批量导出、批量毕业、批量重置密码。
- 反馈闭环：用户提交 Bug / 建议，管理员回复并更新状态。
- 安全防护：登录限制、CSRF、防注入、防 XSS、日志审计、告警。
- 多端适配：PC / Mac 常驻侧边栏，移动端折叠，平板按宽度策略切换。

### 1.3 技术栈

| 分类 | 方案 |
|------|------|
| 后端 | PHP 8.2，原生 PHP，PDO |
| 数据库 | MySQL 8.0 |
| 邮件 | PHPMailer |
| 前端 | HTML5，CSS3，JavaScript ES6+，jQuery |
| 图表 | Chart.js |
| 组件 | Select2，Sortable.js，Font Awesome |
| 部署 | Ubuntu 22.04，Nginx / Apache，宝塔面板 |

### 1.4 当前代码规模

| 目录 | 说明 | 当前数量 |
|------|------|------|
| `api/` | 业务接口 | 40 个 PHP 文件 |
| `pages/` | 页面视图 | 27 个 PHP 文件 |
| `includes/` | 核心类与模板 | 11 个 PHP 文件 |
| `assets/` | CSS / JS / 图片 | 11 个文件 |
| `database/` | SQL、迁移、部署脚本 | 9 个文件 |
| `scripts/` | 备份与清理脚本 | 3 个文件 |

---

## 二、系统架构

### 2.1 分层结构

系统采用轻量分层架构，可理解为 `Pages / API / Includes / Database` 的 MVC 变体：

- `pages/`：负责页面渲染与用户交互入口。
- `api/`：负责表单、AJAX 与批量业务处理。
- `includes/`：负责认证、数据库、工具函数、安全控制、页头页脚。
- `database/`：负责建表、迁移、部署数据库脚本。

### 2.2 请求流

1. 用户访问 `pages/*` 页面。
2. 页面通过 `includes/header.php` 进入统一认证流程。
3. 页面内操作通过 `api/*` 发起请求。
4. `api/*` 使用 `includes/database.php`、`includes/auth.php`、`includes/security.php` 等完成业务。
5. 数据写入 MySQL，并同时在必要时记录操作日志或安全日志。

### 2.3 关键公共模块

| 文件 | 作用 |
|------|------|
| `includes/auth.php` | 登录、登出、角色校验、强制修改密码 |
| `includes/database.php` | PDO 单例与数据库访问封装 |
| `includes/security.php` | CSRF、安全响应头、输入安全控制 |
| `includes/validator.php` | 表单字段清洗与格式验证 |
| `includes/header.php` | 公共页头、登录检查、侧边栏容器 |
| `includes/footer.php` | 公共页尾、脚本引入 |
| `assets/js/main.js` | 通用交互、AJAX、Toast、侧边栏状态控制 |
| `assets/css/style.css` | 全局设计系统与响应式布局 |

### 2.4 目录说明

| 路径 | 说明 |
|------|------|
| `pages/student/` | 学生端页面，5 个 |
| `pages/teacher/` | 教师端页面，5 个 |
| `pages/admin/` | 管理员端页面，10 个 |
| `pages/superadmin/` | 系统管理员页面，3 个 |
| `pages/feedback.php` | 公共反馈页面 |
| `pages/forgot_password.php` | 忘记密码页面 |
| `pages/reset_password.php` | 验证码重置密码页面 |
| `api/student/` | 学生端接口，6 个 |
| `api/teacher/` | 教师端接口，6 个 |
| `api/admin/` | 管理员端接口，22 个 |
| `api/feedback/` | 反馈接口，1 个 |

---

## 三、数据库设计

### 3.1 数据库概览

数据库名：`shxyinfo`

核心表共 12 张：

- `users`
- `student_info`
- `info_change_requests`
- `graduated_students`
- `system_options`
- `operation_logs`
- `password_reset_codes`
- `email_send_log`
- `login_attempts`
- `blocked_ips`
- `security_alerts`
- `feedback`

### 3.2 核心业务表

#### `users`

用途：系统账户表。

关键字段：

- `username`：登录名，学生通常为学号。
- `role`：`student / teacher / admin / superadmin`。
- `is_first_login`：是否首次登录。
- `force_change_password`：是否强制下次修改密码。
- `is_active`：是否启用。
- `is_graduated`：学生是否已毕业。

#### `student_info`

用途：学生党员信息主表。

关键字段：

- 基本信息：`student_no`、`name`、`gender`、`college`、`grade`、`class`
- 身份信息：`birth_date`、`ethnicity`、`id_card`
- 联系信息：`address`、`phone`、`email`
- 党员发展信息：`political_status`、`join_league_date`、`apply_party_date`、`activist_date`、`probationary_date`、`full_member_date`
- 状态字段：`graduation_year`、`info_completed`

规则：

- 首次信息填写完成后将 `info_completed` 置为 `1`。
- 除邮箱外，学生修改其余字段需走审核流程。

#### `info_change_requests`

用途：学生提交的信息修改审核表。

关键字段：

- `field_name` / `field_label`
- `old_value` / `new_value`
- `status`
- `batch_id`
- `reviewed_by` / `reviewed_at`

状态值：

- `pending`
- `approved`
- `rejected`

#### `graduated_students`

用途：毕业生档案永久保留表。

说明：

- 结构与 `student_info` 接近。
- 删除学生账户时，已归档毕业生信息不删除。

#### `system_options`

用途：系统下拉项与配置项。

当前类型：

- `college`
- `grade`
- `class`
- `political_status`
- `ethnicity`
- `development_time`

#### `operation_logs`

用途：记录后台和关键用户操作。

常见 `action`：

- `login`
- `logout`
- `add_user`
- `delete_user`
- `reset_password`
- `change_password`
- `fill_info`
- `edit_info`
- `audit_approve`
- `audit_reject`
- `batch_graduate`
- `import_users`
- `export_students`

### 3.3 安全与辅助表

#### `password_reset_codes`

用途：邮箱验证码找回密码。

关键字段：

- `code`
- `expires_at`
- `used`
- `ip_address`

#### `email_send_log`

用途：记录邮件发送情况并做频率限制。

#### `login_attempts`

用途：记录登录成功与失败，用于暴力破解检测。

#### `blocked_ips`

用途：存储临时封禁 IP。

#### `security_alerts`

用途：安全监控告警留痕。

### 3.4 反馈表

#### `feedback`

用途：记录系统内用户提交的 Bug / 建议，并支持管理员跟进。

关键字段：

- `user_id`
- `type`：`bug` 或 `suggestion`
- `title`
- `content`
- `contact`
- `device`
- `device_model`
- `bug_time`
- `screenshot`
- `status`：`pending / processing / resolved / closed`
- `admin_reply`

规则：

- 所有登录用户都可以提交反馈。
- `bug` 类型可上传截图，保存在 `uploads/feedback/`。
- 管理员回复后，状态自动进入 `processing`。
- 管理员也可手动切换为 `resolved` 或 `closed`。

---

## 四、页面与权限

### 4.1 学生端

页面：

- `pages/student/index.php`
- `pages/student/fill_info.php`
- `pages/student/edit_info.php`
- `pages/student/pending_list.php`
- `pages/student/change_password.php`

能力：

- 查看个人信息
- 首次填写信息
- 修改信息并提交审核
- 绑定邮箱
- 查看待审核申请
- 修改密码

### 4.2 教师端

页面：

- `pages/teacher/index.php`
- `pages/teacher/student_list.php`
- `pages/teacher/graduated.php`
- `pages/teacher/change_password.php`
- `pages/teacher/sidebar.php`

能力：

- 查看统计看板
- 查询学生详情
- 导出学生信息
- 批量导出学生
- 查询 / 导出毕业生

### 4.3 管理员端

页面：

- `pages/admin/index.php`
- `pages/admin/accounts.php`
- `pages/admin/audit.php`
- `pages/admin/student_list.php`
- `pages/admin/graduated.php`
- `pages/admin/settings.php`
- `pages/admin/logs.php`
- `pages/admin/change_password.php`
- `pages/admin/feedback.php`
- `pages/admin/sidebar.php`

能力：

- 账户管理
- 学生信息审核
- 学生与毕业生管理
- 系统选项配置
- 操作日志查看
- 反馈回复与状态流转

### 4.4 系统管理员端

系统管理员复用管理员大部分页面，并拥有更高账户范围权限。

说明：

- `pages/superadmin/index.php`
- `pages/superadmin/change_password.php`
- `pages/superadmin/sidebar.php`

### 4.5 公共页面

- `pages/feedback.php`
- `pages/forgot_password.php`
- `pages/reset_password.php`

---

## 五、API 接口清单

### 5.1 公共接口（5 个）

- `POST /api/login.php`：登录
- `POST /api/logout.php`：登出
- `POST /api/forgot_password.php`：发送找回密码验证码
- `POST /api/verify_reset_code.php`：校验重置验证码
- `POST /api/reset_password_with_code.php`：验证码重置密码

### 5.2 学生端接口（6 个）

- `POST /api/student/fill_info.php`
- `POST /api/student/edit_info.php`
- `POST /api/student/cancel_request.php`
- `POST /api/student/change_password.php`
- `POST /api/student/send_email_code.php`
- `POST /api/student/verify_and_bind_email.php`

### 5.3 教师端接口（6 个）

- `GET /api/teacher/student_detail.php`
- `POST /api/teacher/change_password.php`
- `POST /api/teacher/export_students.php`
- `POST /api/teacher/export_students_batch.php`
- `GET /api/teacher/graduated_detail.php`
- `POST /api/teacher/export_graduated.php`

### 5.4 管理员端接口（22 个）

- `POST /api/admin/add_user.php`
- `POST /api/admin/import_users.php`
- `POST /api/admin/import_student_info.php`
- `POST /api/admin/reset_password.php`
- `POST /api/admin/batch_reset_password.php`
- `POST /api/admin/change_password.php`
- `POST /api/admin/delete_user.php`
- `POST /api/admin/batch_delete.php`
- `POST /api/admin/toggle_status.php`
- `POST /api/admin/student_change.php`
- `POST /api/admin/batch_graduate.php`
- `POST /api/admin/audit_action.php`
- `GET /api/admin/audit_detail.php`
- `GET /api/admin/get_audit_list.php`
- `GET /api/admin/get_updates.php`
- `POST /api/admin/save_option.php`
- `POST /api/admin/delete_option.php`
- `POST /api/admin/sort_options.php`
- `GET /api/admin/download_template.php`
- `GET /api/admin/download_student_template.php`
- `POST /api/admin/feedback_reply.php`
- `POST /api/admin/feedback_status.php`

### 5.5 反馈接口（1 个）

- `POST /api/feedback/submit.php`

参数说明：

- 必填：`type`、`title`、`content`
- 可选：`contact`、`device`、`device_model`、`bug_time`、`screenshot`

---

## 六、前端实现说明

### 6.1 设计系统

全局样式位于 `assets/css/style.css`，核心设计点：

- 登录页与非登录页视觉分离
- 非登录页采用红色主视觉
- 统一按钮、表单、卡片、徽章、提示、表格规范

### 6.2 侧边栏与响应式逻辑

当前侧边栏由 `assets/js/main.js` 和 `assets/css/style.css` 协同控制。

核心策略：

- `desktop`：PC / Mac 保持侧边栏常驻
- `phone`：默认收起，通过菜单按钮与遮罩开关
- `tablet`：按宽度策略切换是否进入折叠模式
- 使用 `body` class：`device-desktop`、`device-tablet`、`device-phone`、`sidebar-collapsible`、`sidebar-open`

本次版本修复点：

- 不再仅通过窄视口判断“移动端”
- 避免 Edge 桌面侧栏容器中出现“按钮显示但侧边栏打不开”的问题
- 将状态切换从内联样式改为 class 驱动

### 6.3 通用交互

`assets/js/main.js` 中包含：

- AJAX 封装
- CSRF Token 自动携带
- Toast 提示
- Modal 对话框
- 表格全选 / 反选
- 页面级初始化逻辑

### 6.4 图表与看板

教师端与管理员端首页使用 Chart.js 渲染：

- 政治面貌分布
- 年级分布
- 班级分布
- 性别分布
- 民族 TOP10
- 党员发展趋势

### 6.5 反馈前端

`pages/feedback.php`：

- 支持 Bug 与建议两种提交模式
- Bug 模式显示设备、机型、发生时间、截图上传
- 提交后在同页查看历史反馈与管理员回复

`pages/admin/feedback.php`：

- 支持筛选 `type`、`status`
- 支持回复反馈
- 支持状态切换到 `processing`、`resolved`、`closed`

---

## 七、安全机制

### 7.1 认证与权限

- 所有后台页通过 `requireLogin()` / `requireRole()` 保护
- 强制修改密码流程受 `force_change_password` 控制
- 学生首次完善信息前限制部分页面访问

### 7.2 输入与请求安全

- PDO 预处理防 SQL 注入
- 输出转义防 XSS
- CSRF Token 校验
- `Validator` 做长度、格式、白名单清洗

### 7.3 登录与邮件安全

- 登录失败记录在 `login_attempts`
- 异常 IP 可写入 `blocked_ips`
- 邮件验证码频率限制写入 `email_send_log`
- 验证码支持过期和已使用状态控制

### 7.4 审计与告警

- 关键操作写入 `operation_logs`
- 安全事件进入 `security_alerts`
- 反馈系统保留问题与回复留痕

---

## 八、部署与运维

### 8.1 运行环境

- Ubuntu 22.04
- PHP 8.2
- MySQL 8.0
- Nginx 1.24 或 Apache

### 8.2 关键配置

- `.env`：数据库、SMTP、站点地址、管理员邮箱
- `includes/version.php`：静态资源版本号
- `database/windows/database.sql` / `database/linux/database.sql`：初始化库表

### 8.3 运维建议

- 定期备份数据库与 `uploads/`
- 定期清理日志与反馈截图冗余文件
- 检查 SMTP 发信能力
- 检查安全日志与登录失败趋势
- 对生产环境关闭调试输出

### 8.4 验证点

- 登录、登出、强制改密流程正常
- 学生信息填写与修改审核正常
- 教师 / 管理员导出功能正常
- 反馈提交、回复、状态更新正常
- PC / Mac 常驻侧边栏正常，移动端折叠正常

---

## 九、更新记录

### v2.0.4 - 2026-04-28

- 非登录页统一强化红色主题
- 重构侧边栏响应式逻辑
- 修复 Edge 中侧边栏打不开的问题
- 文档补充反馈系统、公共页面、接口清单、前端兼容策略

### v1.3.1 - 2024-12-31

- 完善强制修改密码功能
- 修复教师端退出按钮问题

### v1.3.0 - 2024-12-30

- 新增强制修改密码
- 新增学生批量选中导出
- 优化删除学生账户时保留毕业生信息

---

文档结束。
