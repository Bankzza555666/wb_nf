#!/bin/bash
# Linux Shell Script for Auto-Renewal Cron Job
# ใช้สำหรับ Linux Server ที่ /home/vps/server/

# ตั้งค่า path สำหรับเซิร์ฟเวอร์ของคุณ
PHP_PATH="/usr/bin/php"
PROJECT_PATH="/home/vps/server"
LOG_PATH="/var/log/auto_renew.log"

# สร้างโฟลเดอร์ logs ถ้ายังไม่มี
mkdir -p "$(dirname "$LOG_PATH")"

# เรียกใช้ cron job
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting auto-renewal cron job..." >> "$LOG_PATH"
"$PHP_PATH" "$PROJECT_PATH/controller/cron_main.php" >> "$LOG_PATH" 2>&1

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Auto-renewal cron job completed." >> "$LOG_PATH"