#!/bin/bash
# 数据库一键部署工具

echo "========================================"
echo "数据库一键部署工具"
echo "========================================"
echo ""
echo "本工具将帮助您部署数据库"
echo "请确保已安装 MySQL"
echo ""
read -p "按回车键继续..."

echo ""
echo "[步骤1] 请输入MySQL信息"
echo "----------------------------------------"
read -p "MySQL用户名 (默认root): " DB_USER
DB_USER=${DB_USER:-root}

read -sp "MySQL密码: " DB_PASS
echo ""

echo ""
echo "[步骤2] 测试数据库连接..."
mysql -u "$DB_USER" -p"$DB_PASS" -e "SELECT 1;" &>/dev/null
if [ $? -ne 0 ]; then
    echo "[错误] 连接失败，请检查用户名和密码"
    exit 1
fi
echo "[成功] 连接成功！"

echo ""
echo "[步骤3] 创建数据库用户"
echo "----------------------------------------"
echo "请为应用程序创建一个专用数据库用户"
echo "该用户将仅拥有 shxyinfo 数据库的必要权限（最小权限原则）"
echo ""
read -p "新用户名: " NEW_DB_USER
while [ -z "$NEW_DB_USER" ]; do
    echo "[错误] 用户名不能为空"
    read -p "新用户名: " NEW_DB_USER
done

read -sp "新用户密码: " NEW_DB_PASS
echo ""
while [ -z "$NEW_DB_PASS" ]; do
    echo "[错误] 密码不能为空"
    read -sp "新用户密码: " NEW_DB_PASS
    echo ""
done

read -sp "确认密码: " NEW_DB_PASS_CONFIRM
echo ""

# 验证密码匹配
if [ "$NEW_DB_PASS" != "$NEW_DB_PASS_CONFIRM" ]; then
    echo "[错误] 两次密码输入不一致"
    exit 1
fi

echo ""
echo "正在创建数据库..."

# 使用root先创建数据库
mysql -u "$DB_USER" -p"$DB_PASS" <<EOF 2>/dev/null
CREATE DATABASE IF NOT EXISTS \`shxyinfo\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
EOF

if [ $? -ne 0 ]; then
    echo "[错误] 数据库创建失败"
    exit 1
fi

echo "正在创建数据库用户并授权..."

# 使用root创建用户并授权（最小权限）
mysql -u "$DB_USER" -p"$DB_PASS" <<EOF 2>/dev/null
CREATE USER IF NOT EXISTS '${NEW_DB_USER}'@'localhost' IDENTIFIED BY '${NEW_DB_PASS}';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, 
      CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, TRIGGER, REFERENCES
      ON shxyinfo.* TO '${NEW_DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF

if [ $? -ne 0 ]; then
    echo "[错误] 用户创建失败"
    exit 1
fi
echo "[成功] 数据库和用户创建成功！"

echo ""
echo "[步骤4] 开始导入数据库..."
# 使用新用户导入，指定数据库名以跳过CREATE DATABASE语句的影响
mysql -u "$NEW_DB_USER" -p"$NEW_DB_PASS" shxyinfo < database.sql 2>/dev/null
if [ $? -ne 0 ]; then
    echo "[错误] 导入失败"
    exit 1
fi

echo "[成功] 数据库导入完成！"

echo ""
echo "[步骤5] 更新配置文件..."
# 获取.env文件的绝对路径（从database/linux/返回到项目根目录）
ENV_FILE="../../.env"

if [ -f "$ENV_FILE" ]; then
    # 更新.env文件中的数据库配置
    sed -i "s/^DB_USER=.*/DB_USER=${NEW_DB_USER}/" "$ENV_FILE"
    sed -i "s/^DB_PASS=.*/DB_PASS=${NEW_DB_PASS}/" "$ENV_FILE"
    sed -i "s/^DB_NAME=.*/DB_NAME=shxyinfo/" "$ENV_FILE"
    echo "[成功] 配置文件 .env 已更新！"
else
    echo "[警告] 未找到 .env 文件，请手动配置数据库连接信息"
fi
echo ""
echo "========================================"
echo "部署完成"
echo "========================================"
echo ""
echo "数据库信息:"
echo "  数据库名: shxyinfo"
echo "  数据库用户: $NEW_DB_USER"
echo "  连接范围: localhost (仅本地连接)"
echo "  权限范围: 仅限 shxyinfo 数据库"
echo ""
echo "应用程序信息:"
echo "  默认管理员账号: admin"
echo "  默认管理员密码: admin123"
echo ""
echo "⚠️ 重要提示:"
echo "1. 数据库用户已配置最小权限（仅限shxyinfo数据库）"
echo "2. 配置文件 .env 已自动更新数据库连接信息"
echo "3. 请首次登录后立即修改管理员密码"
echo "4. 请妥善保管数据库密码"
echo ""
echo "✓ 安全特性:"
echo "  - 使用专用数据库用户，非root用户"
echo "  - 权限仅限于单个数据库"
echo "  - 无法访问其他数据库或创建新用户"
echo ""
