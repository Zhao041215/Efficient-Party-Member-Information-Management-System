@echo off
REM =====================================================
REM Windows 数据库备份脚本
REM 创建日期: 2026-04-24
REM 说明: 自动备份数据库，保留最近30天的备份
REM =====================================================

setlocal enabledelayedexpansion

REM 配置变量
set DB_NAME=party_management
set DB_USER=root
set DB_PASS=
set BACKUP_DIR=C:\backups\mysql
set DATE=%date:~0,4%%date:~5,2%%date:~8,2%_%time:~0,2%%time:~3,2%%time:~6,2%
set DATE=%DATE: =0%
set BACKUP_FILE=%BACKUP_DIR%\%DB_NAME%_%DATE%.sql
set LOG_FILE=%BACKUP_DIR%\backup.log

REM 创建备份目录
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

REM 记录开始时间
echo [%date% %time%] 开始备份数据库: %DB_NAME% >> "%LOG_FILE%"

REM 执行备份
if "%DB_PASS%"=="" (
    mysqldump -u%DB_USER% --single-transaction --quick --lock-tables=false --routines --triggers --events %DB_NAME% > "%BACKUP_FILE%"
) else (
    mysqldump -u%DB_USER% -p%DB_PASS% --single-transaction --quick --lock-tables=false --routines --triggers --events %DB_NAME% > "%BACKUP_FILE%"
)

REM 检查备份是否成功
if %errorlevel% equ 0 (
    echo [%date% %time%] 备份成功: %BACKUP_FILE% >> "%LOG_FILE%"

    REM 压缩备份文件（需要安装 7-Zip）
    if exist "C:\Program Files\7-Zip\7z.exe" (
        "C:\Program Files\7-Zip\7z.exe" a -tgzip "%BACKUP_FILE%.gz" "%BACKUP_FILE%"
        del "%BACKUP_FILE%"
        echo [%date% %time%] 备份已压缩 >> "%LOG_FILE%"
    )

    REM 清理30天前的旧备份
    forfiles /p "%BACKUP_DIR%" /m "%DB_NAME%_*.sql*" /d -30 /c "cmd /c del @path" 2>nul
    echo [%date% %time%] 已清理30天前的旧备份 >> "%LOG_FILE%"
) else (
    echo [%date% %time%] 备份失败！ >> "%LOG_FILE%"
    exit /b 1
)

echo [%date% %time%] 备份流程完成 >> "%LOG_FILE%"
echo ---------------------------------------- >> "%LOG_FILE%"

endlocal
exit /b 0
