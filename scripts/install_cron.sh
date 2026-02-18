#!/bin/bash
# Installation Script for Auto-Renewal Cron Job
# สำหรับเซิร์ฟเวอร์ที่ /home/vps/server/

echo "🔧 กำลังติดตั้ง Crontab สำหรับระบบต่ออายุอัตโนมัติ..."
echo "📍 Path: /home/vps/server/"
echo ""

# ตรวจสอบว่ามีไฟล์ cron_main.php หรือไม่
if [ ! -f "/home/vps/server/controller/cron_main.php" ]; then
    echo "❌ ไม่พบไฟล์ cron_main.php ที่ /home/vps/server/controller/cron_main.php"
    echo "   กรุณาตรวจสอบ path ให้ถูกต้อง"
    exit 1
fi

echo "✅ พบไฟล์ cron_main.php"

# สร้างไฟล์ crontab ชั่วคราว
TEMP_CRON="/tmp/auto_renew_cron.tmp"
CRON_COMMAND="0 * * * * /usr/bin/php /home/vps/server/controller/cron_main.php >> /var/log/auto_renew.log 2>&1"

# ตรวจสอบ crontab ปัจจุบัน
crontab -l > $TEMP_CRON 2>/dev/null || echo "# Auto-renewal crontab" > $TEMP_CRON

# ตรวจสอบว่ามีคำสั่งนี้อยู่แล้วหรือไม่
if grep -q "cron_main.php" $TEMP_CRON; then
    echo "ℹ️  Crontab สำหรับ auto-renewal มีอยู่แล้ว"
    echo "🔄 กำลังอัปเดต..."
    # ลบบรรทัดเก่าและเพิ่มใหม่
    sed -i '/cron_main.php/d' $TEMP_CRON
fi

# เพิ่มคำสั่งใหม่
echo "" >> $TEMP_CRON
echo "# Auto-renewal SSH & VPN - Every 1 hour" >> $TEMP_CRON
echo "$CRON_COMMAND" >> $TEMP_CRON

# ติดตั้ง crontab ใหม่
crontab $TEMP_CRON
rm $TEMP_CRON

echo "✅ ติดตั้ง Crontab สำเร็จแล้ว!"
echo ""
echo "📋 รายละเอียด:"
echo "   • ทำงาน: ทุก 1 ชั่วโมง"
echo "   • ไฟล์: /home/vps/server/controller/cron_main.php"
echo "   • Log: /var/log/auto_renew.log"
echo ""
echo "🔍 ตรวจสอบสถานะ:"
echo "   crontab -l"
echo "   tail -f /var/log/auto_renew.log"
echo ""
echo "🚀 ระบบพร้อมทำงาน!"