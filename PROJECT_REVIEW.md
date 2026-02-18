# รายงานตรวจสอบโปรเจค NF~SHOP

## สรุปความสมบูรณ์

โปรเจค **ครบด้านฟีเจอร์หลัก** แล้ว: ลงทะเบียน/ล็อกอิน, OTP, ลืมรหัส, Dashboard สมาชิก, เช่า VPN/SSH, เติมเงิน, แอดมิน (ผู้ใช้, เซิร์ฟเวอร์, โปรดักต์, แชท, AI, การแจ้งเตือน), Webhook ชำระเงิน, Telegram แจ้งเตือน ฯลฯ

มีการใช้ **prepared statements** ในจุดสำคัญ, มี **CSRF** ใน login/topup, มี **Security Headers** และ **Rate limiting** ตอนสมัคร

---

## ✅ สิ่งที่แก้ไขแล้ว (ในรอบนี้)

| รายการ | รายละเอียด |
|--------|------------|
| **register_conf** | ใช้ `register_at` แทน `created_at` ใน rate limit (ตาราง `users` ไม่มี `created_at`) |
| **register_conf** | ใช้ `random_int()` แทน `rand()` สำหรับ OTP |
| **register_conf** | ปิด `display_errors` ใน production |
| **admin/notifications** | ใช้ `checkAdminAuth()` แทนแค่เช็ค session (ป้องกัน user เข้าหน้าแอดมิน) |
| **admin/notifications** | แก้ redirect จาก `?p=login` (ไม่มีใน router) เป็นใช้ `admin_config` |
| **home/dashboard** | ปิด `display_errors` |
| **admin/dashboard** | ปิด `display_errors`, แก้ `require` ให้ใช้ `__DIR__`, ไม่ `die()` แสดง SQL/error ให้ user |
| **admin_api** | ใช้ prepared statement ตอนเช็ค role แทน query แบบต่อสตริง |
| **auth_check** | ตรวจสอบรูปแบบ cookie Remember Me ก่อน `explode` |
| **topup_conf** | ปิด `display_errors` |

---

## ⚠️ สิ่งที่ควรแก้ไข/พิจารณาเพิ่ม

### 1. ความปลอดภัย – คอนฟิกและ API Keys (สำคัญมาก)

ใน `controller/config.php` มีการเก็บค่าลับในโค้ดโดยตรง:

- รหัส DB, SMTP (รวมรหัสอีเมล)
- Telegram Bot Token / Chat ID
- XDROID API Key, Typhoon AI API Key

**แนะนำ:** ย้ายไปใช้ **ตัวแปรสภาพแวดล้อม (Environment variables)** หรือไฟล์ `.env` นอก web root แล้ว `define()` จากค่าพวกนั้น ไม่ควร commit ค่าจริงขึ้น Git

---

### 2. CSRF ใน API ที่เปลี่ยนข้อมูล

API เหล่านี้รับ POST และแก้ข้อมูล แต่ยังไม่ได้เช็ค CSRF:

- `Server_price/api/delete_rental.php`
- `Server_price/api/extend_days.php`
- `Server_price/api/extend_days_fixed.php`
- `Server_price/api/edit_rental_name.php`
- `Server_price/api/toggle_auto_renew.php`
- `Server_price/api/add_data.php`, `add_devices.php`, `reset_uuid.php`

**แนะนำ:** ถ้าเรียกจากฟอร์ม/AJAX ในเว็บเดียวกัน ให้ส่ง `csrf_token` (หรือ header `X-CSRF-Token`) แล้วใช้ `verifyCsrfToken()` ก่อนทำงาน

---

### 3. Admin API – ค้นหาผู้ใช้ (Search)

ใน `admin_api.php` ตอน `get_users` ใช้ `$conn->real_escape_string($search)` แล้วต่อลงใน SQL  
ใช้ได้แต่วิธีที่แข็งแรงกว่าคือ **prepared statement** สำหรับค่าค้นหา

---

### 4. ai_helper – LIKE และ LIMIT

ใน `findGlobalSolutions()` ใช้ `real_escape_string` + concatenate ในคำสั่ง LIKE  
ถ้าเลื่อนไปใช้ prepared statements ทั้งชุด จะลดความเสี่ยงและสอดคล้องกับจุดอื่นในโปรเจค  
`LIMIT` ตอนนี้มาจากค่าคงที่ (= 3) อยู่แล้ว ไม่มีปัญหา

---

### 5. topup_conf – allowed_origins

ตอนนี้รองรับแค่ `https://netfree.in.th`  
ถ้า dev บน local (เช่น `http://localhost`) จะโดนบล็อกจาก origin check

**แนะนำ:** แยก config ตาม environment (เช่น local vs production) หรือให้ origin เพิ่มเติมในโหมด dev

---

### 6. Database

- ยืนยันให้ชัวร์ว่ามีตาราง `auth_tokens` (ใช้โดย Remember Me) และโครงสร้างตรงกับที่ `auth_check` อ้างอิง
- ถ้ามี migration / SQL แยก (เช่น `as2.sql`, `ssh_system_migration.sql`) ควรมีสคริปต์หรือเอกสารให้รันตามลำดับที่ถูกต้อง

---

### 7. Logs และไฟล์รองรับ

- โฟลเดอร์ `logs/` มี `.htaccess` ป้องกันให้แล้ว ตรวจสอบว่าไม่มีการส่ง path ออกไปให้ client
- ควรกำหนดนโยบาย rotation/lock ไฟล์ log เพื่อไม่ให้เขียนเกินขนาดหรือเปิดอ่านจากภายนอก

---

## สรุป

โปรเจคใช้งานได้ครบและมีมาตรฐานความปลอดภัยพื้นฐานอยู่แล้ว  
หลังจากแก้จุดด้านบน (โดยเฉพาะการย้าย secrets ออกจาก config และเพิ่ม CSRF ให้ API ที่แก้ข้อมูล) จะเหมาะกับการใช้ใน production มากขึ้น
