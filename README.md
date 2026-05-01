# 生化学院党员信息管理系统

一个面向高校学院党务信息管理场景的 Web 管理系统，覆盖学生党员信息采集、修改审核、毕业归档、账号管理、统计分析、操作日志、安全监控和用户反馈等核心流程。

系统采用原生 PHP + MySQL 开发，按 `pages / api / includes / database` 进行轻量分层，适合部署在常见的 LAMP / LNMP 环境中，也便于二次开发和教学参考。

## 项目特性

- 多角色权限体系：支持学生、教师、管理员、超级管理员四类角色。
- 学生信息全流程管理：首次填写、信息查看、修改申请、审核通过/驳回、撤销申请。
- 管理员后台：账号新增、批量导入、批量删除、批量重置密码、账号启停、学生毕业归档。
- 教师端查询与导出：支持学生信息查看、毕业生信息查看、批量导出。
- 统计看板：基于 Chart.js 展示政治面貌、年级、班级、性别、民族、发展趋势等数据。
- 系统配置：维护学院、年级、班级、民族、政治面貌、发展时间等下拉选项。
- 邮箱能力：支持邮箱绑定、验证码发送、忘记密码与验证码重置密码。
- 反馈闭环：用户可提交 Bug 或功能建议，管理员可回复并流转处理状态。
- 安全防护：包含 CSRF 校验、PDO 预处理、防 XSS 输出转义、登录失败记录、操作日志、安全告警等机制。
- 多端适配：支持桌面端、平板和移动端访问，侧边栏会根据设备宽度自动切换交互方式。

## 技术栈

| 分类 | 技术 |
| --- | --- |
| 后端 | PHP 8.2+、PDO |
| 数据库 | MySQL 8.0+ |
| 邮件 | PHPMailer |
| 前端 | HTML5、CSS3、JavaScript、jQuery |
| 图表 | Chart.js |
| 表单组件 | Select2 |
| 拖拽排序 | Sortable.js |
| 部署环境 | Ubuntu 22.04 / Windows、Nginx / Apache |

## 目录结构

```text
.
├── api/                    # AJAX 与业务接口
│   ├── admin/              # 管理员接口
│   ├── feedback/           # 用户反馈接口
│   ├── student/            # 学生端接口
│   └── teacher/            # 教师端接口
├── assets/                 # CSS、JavaScript、图片等静态资源
├── database/               # 数据库初始化脚本、迁移脚本和部署说明
│   ├── linux/
│   ├── windows/
│   └── migrations/
├── docs/                   # 学生、教师、管理员、超级管理员使用手册
├── includes/               # 配置、认证、数据库、安全、公共函数和模板
├── pages/                  # 页面视图
│   ├── admin/
│   ├── student/
│   ├── superadmin/
│   └── teacher/
├── scripts/                # 备份、文档生成和清理脚本
├── uploads/                # 用户上传文件目录
├── composer.json
├── index.php
└── README.md
```

## 环境要求

- PHP 8.2 或更高版本
- MySQL 8.0 或更高版本
- Composer
- PHP 扩展：`pdo_mysql`、`mbstring`、`openssl`、`fileinfo`
- Web Server：Nginx 或 Apache

## 快速开始

### 1. 克隆项目

```bash
git clone https://github.com/your-name/your-repo.git
cd your-repo
```

### 2. 安装依赖

```bash
composer install
```

### 3. 配置环境变量

复制环境变量示例文件：

```bash
cp .env.example .env
```

根据实际环境修改 `.env`：

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=shxyinfo
DB_USER=your_database_user
DB_PASS=your_database_password

SMTP_HOST=smtp.example.com
SMTP_PORT=465
SMTP_USER=your_email@example.com
SMTP_PASS=your_smtp_password
SMTP_FROM_EMAIL=your_email@example.com
SMTP_FROM_NAME=党员信息管理系统

SITE_URL=https://your-domain.com/
SESSION_EXPIRE=3600
REMEMBER_EXPIRE=604800
```

### 4. 初始化数据库

Linux 环境可进入数据库脚本目录后执行：

```bash
cd database/linux
chmod +x run_me.sh
./run_me.sh
```

也可以手动导入 SQL：

```bash
mysql -u root -p < database/linux/database.sql
```

Windows 环境可使用：

```text
database/windows/database.sql
database/windows/一键部署.bat
```

### 5. 配置 Web Server

将站点根目录指向项目根目录，确保 `index.php` 可被访问。

Nginx 示例：

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/your-repo;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location ~ /\. {
        deny all;
    }
}
```

### 6. 登录系统

数据库初始化后默认管理员账号为：

```text
用户名：admin
密码：admin123
```

首次登录后请立即修改默认密码，并根据实际需要创建教师、学生或其他管理员账号。

## 角色说明

| 角色 | 主要能力 |
| --- | --- |
| 学生 | 填写个人信息、绑定邮箱、提交信息修改申请、查看审核状态、提交反馈、修改密码 |
| 教师 | 查看统计数据、查询学生详情、导出学生信息、查看毕业生信息 |
| 管理员 | 管理账号、审核学生信息、批量导入导出、毕业归档、系统选项配置、反馈处理 |
| 超级管理员 | 拥有更高后台权限，可查看部分敏感日志与系统管理页面 |

## 核心数据表

系统初始化脚本会创建以下主要数据表：

- `users`：系统账号
- `student_info`：学生信息
- `info_change_requests`：信息修改审核申请
- `graduated_students`：毕业生归档
- `system_options`：系统下拉选项与配置
- `operation_logs`：操作日志
- `operation_log_full_details`：完整操作日志详情
- `password_reset_codes`：密码重置验证码
- `email_send_log`：邮件发送记录
- `login_attempts`：登录尝试记录
- `blocked_ips`：临时封禁 IP
- `security_alerts`：安全告警
- `feedback`：用户反馈

## 安全说明

- 请勿将真实生产环境的 `.env` 文件提交到仓库。
- 请在首次部署后立即修改默认管理员密码。
- 生产环境建议关闭 PHP 错误显示，并将错误写入日志。
- 建议为数据库创建专用低权限账号，不要直接使用 `root` 账号连接应用。
- 建议为站点启用 HTTPS，保护登录、验证码和后台管理请求。
- `uploads/` 目录用于用户上传文件，生产环境应限制可执行权限。
- 部署后请定期备份数据库和上传目录。

## 使用文档

项目已包含多角色使用手册：

- `docs/01_学生使用手册.md`
- `docs/02_教师使用手册.md`
- `docs/03_管理员使用手册.md`
- `docs/04_超级管理员使用手册.md`

如需生成 PDF，可参考 `scripts/generate_manuals_pdf.py`。

## 常用维护

备份脚本：

```text
scripts/backup.sh
scripts/backup.bat
```

安全清理脚本：

```text
scripts/cleanup_security.php
```

数据库迁移文件位于：

```text
database/migrations/
```

## 开发建议

- 后端公共逻辑优先放入 `includes/`，避免在页面和接口中重复实现。
- 新增 POST 接口时应接入 CSRF 校验。
- 数据库访问统一使用 PDO 预处理。
- 输出用户可控内容时应进行 HTML 转义。
- 新增后台操作建议同步写入操作日志，方便审计和追踪。

## 许可证

当前项目尚未附带开源许可证。如需公开发布或允许他人复用代码，建议补充 `LICENSE` 文件并明确授权范围。
