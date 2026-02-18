@echo off
REM Windows Batch File for Auto-Renewal Cron Job
REM ใช้สำหรับ XAMPP บน Windows

REM ตั้งค่า path (แก้ตามจริง)
SET PHP_PATH=C:\xampp\php\php.exe
SET PROJECT_PATH=C:\xampp\htdocs
SET LOG_PATH=C:\xampp\htdocs\logs\auto_renew.log

REM สร้างโฟลเดอร์ logs ถ้ายังไม่มี
if not exist "%PROJECT_PATH%\logs" mkdir "%PROJECT_PATH%\logs"

REM เรียกใช้ cron job
echo [%date% %time%] Starting auto-renewal cron job... >> "%LOG_PATH%"
"%PHP_PATH%" "%PROJECT_PATH%\controller\cron_main.php" >> "%LOG_PATH%" 2>&1

REM แสดงผล (optional)
echo Cron job completed. Check log: %LOG_PATH%
pause