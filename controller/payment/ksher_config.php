<?php
// controller/payment/ksher_config.php

// ⚠️ สำคัญ! แก้ไขค่าเหล่านี้ให้ตรงกับบัญชี Ksher ของคุณ

// 1. Ksher App ID (จาก Dashboard)
define('KSHER_APPID', 'mch41555'); // ← เปลี่ยนเป็นของคุณ

// 2. Private Key (จาก Dashboard)
define('KSHER_PRIVATE_KEY', <<<EOD
-----BEGIN RSA PRIVATE KEY-----
MIICXwIBAAKBgQCJp0bI4sFYm2fSYxDD79Pnse6/n0Xmu58wZa6CvKJ0x8ZAR2//
RA5rnJC7k8R/Q7zqEPt5IQHcURYnIP+4DuF02ddM7QtTMo8uJR0BJ2W24CagmKfh
Ly19NVJBSlsrcMa4ajs+cAyOvJNgLU+30Vm7R3ZtmrJjOwO2qKFG9/qY4QIDAQAB
AoGAS6qo7VpAP+3FJ1APcjVN/YnAnJL3wLqX6emrAPCiOKFsJ/9c4CvP5XV91a6H
kBFzMhM9uLrdI/dqvv2TZFdDtSKZHMGe+co0hmd607q1kiSItPiKxwewWYOh8O6k
UCZOnXLXOCPbhVF+mG0V5zxnTxwrVcDGI4FR35P/JsYuQXUCRQCQsT4Exvq+Wljc
fvZEzTZfD/4jGntZnIqo0lPZ1BDwpBJ3E2jWeqO8VvP4rJCLdsAi9XiAc4Xv5WL7
/gxRqKQEmK6XOwI9APOL1YNKSSk7E5RPyV9YHbTyeiy1POO8pSNexDOuQ38vE3aX
sJNio9Oe7uuk0CfgjBorPprv0e3RtWUmkwJEAm/563qunqZG+O/qlh4e3FsYnN7F
VS0d6NoiL3kzD9qztO3Oxk4qk/GjCn1dsfu+INihvwgzKWdj03rkGjwNB0bLKiEC
PDNHgxpUvtjOlo3IyuanYAHkeDMHqh1tb/vljTwwegfCer+iqswtnb8GHNpC5o0u
63DrIBBxZGtFl4MHvwJEJfexTApxhL9hVMj43usjTi6z1OR4zvqLqF/X6ua/ZRi7
Mv7aM/4wVcxSyri60rK5/l4T8U/CTl2DGBnedKOqe0hQTMg=
-----END RSA PRIVATE KEY-----
EOD
);

// 3. API Domain (ต้องมี!)
define('KSHER_API_DOMAIN', 'https://api.mch.ksher.net/KsherPay');
define('KSHER_GATEWAY_DOMAIN', 'https://gateway.ksher.com/api');

// 4. Redirect URLs (⚠️ เปลี่ยนเป็น HTTPS เมื่อขึ้น Production!)
define('KSHER_REDIRECT_SUCCESS', 'https://netfree.in.th/?p=topup_success');
define('KSHER_REDIRECT_FAIL', 'https://netfree.in.th/?p=topup');

// 5. Notify URL (Webhook) - ⚠️ ต้องเป็น URL ที่เข้าถึงได้จากภายนอก!
define('KSHER_NOTIFY_URL', 'https://netfree.in.th/controller/payment/ksher_notify.php');

