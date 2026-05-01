@echo off
chcp 65001 >nul
echo ========================================
echo 数据库一键部署工具
echo ========================================
echo.
echo 本工具将帮助您部署数据库
echo 请确保已安装 MySQL
echo.
pause

echo.
echo [步骤1] 请输入MySQL信息
echo ----------------------------------------
set /p DB_USER="MySQL用户名 (默认root): "
if "%DB_USER%"=="" set DB_USER=root

set /p DB_PASS="MySQL密码: "

echo.
echo [步骤2] 测试数据库连接...
mysql -u %DB_USER% -p%DB_PASS% -e "SELECT 1;" >nul 2>&1
if %errorlevel% neq 0 (
    echo [错误] 连接失败，请检查用户名和密码
    pause
    exit /b 1
)
echo [成功] 连接成功！

echo.
echo [步骤3] 开始导入数据库...
mysql -u %DB_USER% -p%DB_PASS% < database.sql 2>nul
if %errorlevel% neq 0 (
    echo [错误] 导入失败
    pause
    exit /b 1
)

echo [成功] 数据库导入完成！
echo.
echo ========================================
echo 部署完成
echo ========================================
echo.
echo 数据库名称: shxyinfo
echo 默认账号: admin
echo 默认密码: admin123
echo.
echo ⚠️ 重要提示:
echo 1. 请首次登录后立即修改密码
echo 2. 请妥善保管数据库密码
echo.
pause
