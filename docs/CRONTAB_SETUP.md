# CRONTAB สำหรับระบบต่ออายุอัตโนมัติ SSH & VPN
# ติดตั้งครั้งเดียวครอบคลุมทั้งระบบ

# เปิด crontab editor:
# crontab -e

# เพิ่มบรรทัดต่อไปนี้:

# ===== AUTO-RENEWAL & CLEANUP CRON JOBS =====

# สำหรับเซิร์ฟเวอร์ของคุณที่ /home/vps/server/
# ทำงานทุก 1 ชั่วโมง - ต่ออายุอัตโนมัติ (SSH + VPN) + ลบรายการหมดอายุ
0 * * * * /usr/bin/php /home/vps/server/controller/cron_main.php >> /var/log/auto_renew.log 2>&1

# หรือใช้ถ้าอยู่บน Windows XAMPP:
# สร้างไฟล์ .bat แล้วใช้ Windows Task Scheduler ตั้งเวลา

# ===== คำอธิบาย =====
# 0 * * * * = ทุกชั่วโมงที่นาที 0
# /usr/bin/php = path ของ PHP (ปรับตาม server)
# /path/to/your/htdocs = แก้เป็น path จริงของ project
# >> /var/log/auto_renew_cron.log 2>&1 = บันทึก log

# ===== ถ้าต้องการความถี่ที่แตกต่าง =====

# ทุก 30 นาที:
*/30 * * * * /usr/bin/php /path/to/your/htdocs/controller/cron_main.php >> /var/log/auto_renew_cron.log 2>&1

# ทุก 6 ชั่วโมง:
0 */6 * * * /usr/bin/php /path/to/your/htdocs/controller/cron_main.php >> /var/log/auto_renew_cron.log 2>&1

# ===== ตรวจสอบว่า cron ทำงาน =====
# ดู log: tail -f /var/log/auto_renew_cron.log
# ดู cron log: tail -f /var/log/cron.log (Ubuntu/Debian)
# หรือ: grep CRON /var/log/syslog