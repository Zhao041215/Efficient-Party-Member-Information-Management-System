#!/bin/bash
# =====================================================
# 数据库备份脚本
# 创建日期: 2026-04-24
# 说明: 自动备份数据库，保留最近30天的备份
# =====================================================

# 配置变量
DB_NAME="party_management"
DB_USER="root"
DB_PASS=""  # 从环境变量读取，或使用 .my.cnf
BACKUP_DIR="/var/backups/mysql"
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="${BACKUP_DIR}/${DB_NAME}_${DATE}.sql.gz"
LOG_FILE="${BACKUP_DIR}/backup.log"
RETENTION_DAYS=30

# 创建备份目录
mkdir -p "${BACKUP_DIR}"
chmod 700 "${BACKUP_DIR}"

# 记录开始时间
echo "[$(date '+%Y-%m-%d %H:%M:%S')] 开始备份数据库: ${DB_NAME}" >> "${LOG_FILE}"

# 执行备份（使用 gzip 压缩）
if [ -z "${DB_PASS}" ]; then
    # 如果密码为空，从 .my.cnf 读取
    mysqldump --defaults-extra-file=/root/.my.cnf \
        --single-transaction \
        --quick \
        --lock-tables=false \
        --routines \
        --triggers \
        --events \
        "${DB_NAME}" | gzip > "${BACKUP_FILE}"
else
    # 使用密码参数
    mysqldump -u"${DB_USER}" -p"${DB_PASS}" \
        --single-transaction \
        --quick \
        --lock-tables=false \
        --routines \
        --triggers \
        --events \
        "${DB_NAME}" | gzip > "${BACKUP_FILE}"
fi

# 检查备份是否成功
if [ $? -eq 0 ]; then
    # 设置备份文件权限
    chmod 600 "${BACKUP_FILE}"

    # 获取文件大小
    FILE_SIZE=$(du -h "${BACKUP_FILE}" | cut -f1)

    echo "[$(date '+%Y-%m-%d %H:%M:%S')] 备份成功: ${BACKUP_FILE} (大小: ${FILE_SIZE})" >> "${LOG_FILE}"

    # 清理旧备份（保留最近30天）
    find "${BACKUP_DIR}" -name "${DB_NAME}_*.sql.gz" -type f -mtime +${RETENTION_DAYS} -delete

    echo "[$(date '+%Y-%m-%d %H:%M:%S')] 已清理 ${RETENTION_DAYS} 天前的旧备份" >> "${LOG_FILE}"
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] 备份失败！" >> "${LOG_FILE}"
    exit 1
fi

# 可选：上传到远程服务器或云存储
# rsync -avz "${BACKUP_FILE}" user@remote-server:/backup/
# aws s3 cp "${BACKUP_FILE}" s3://your-bucket/backups/

echo "[$(date '+%Y-%m-%d %H:%M:%S')] 备份流程完成" >> "${LOG_FILE}"
echo "----------------------------------------" >> "${LOG_FILE}"

exit 0
