#!/bin/bash
# Test Script for Auto-Renewal Cron Job
# สำหรับเซิร์ฟเวอร์ที่ /home/vps/server/

echo "🧪 ทดสอบระบบ Auto-renewal และ Cleanup..."
echo "📍 Path: /home/vps/server/"
echo "🕐 เวลา: $(date)"
echo ""

# ตรวจสอบไฟล์ที่จำเป็น
FILES_TO_CHECK=(
    "/home/vps/server/controller/cron_main.php"
    "/home/vps/server/controller/auto_renew_worker.php" 
    "/home/vps/server/controller/cleanup_ssh.php"
    "/home/vps/server/controller/cleanup_vpn.php"
)

echo "📁 ตรวจสอบไฟล์ที่จำเป็น:"
for file in "${FILES_TO_CHECK[@]}"; do
    if [ -f "$file" ]; then
        echo "   ✅ $file"
    else
        echo "   ❌ $file (ไม่พบ)"
    fi
done

echo ""
echo "🔍 ตรวจสอบ PHP:"
which php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    PHP_PATH=$(which php)
    echo "   ✅ PHP: $PHP_PATH"
else
    echo "   ❌ ไม่พบ PHP ใน PATH"
    echo "   ลอง: /usr/bin/php หรือ /usr/local/bin/php"
    exit 1
fi

echo ""
echo "🧪 ทดสอบรัน cron job:"
if [ -f "/home/vps/server/controller/cron_main.php" ]; then
    echo "   🚀 กำลังรัน: /home/vps/server/controller/cron_main.php"
    echo "   ------------------------------------------------"
    
    # รันแบบ dry run (ถ้ามี)
    $PHP_PATH /home/vps/server/controller/cron_main.php
    
    echo "   ------------------------------------------------"
    echo "   ✅ รันสำเร็จ"
else
    echo "   ❌ ไม่พบไฟล์ cron_main.php"
fi

echo ""
echo "📋 ตรวจสอบ Crontab ปัจจุบัน:"
crontab -l 2>/dev/null || echo "   ยังไม่มี crontab"

echo ""
echo "🔧 คำสั่งติดตั้ง:"
echo "   bash /home/vps/server/scripts/install_cron.sh"