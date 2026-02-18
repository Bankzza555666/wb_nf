<?php
// controller/payment/ksher_pay_sdk.php

class KsherPay
{
    public $time;
    public $appid;
    public $privatekey;
    public $pubkey;
    public $version;
    public $pay_domain;
    public $gateway_domain;

    public function __construct($appid = '', $privatekey = '', $version = '3.0.0')
    {
        $this->time = date("YmdHis", time());
        $this->appid = $appid;
        $this->privatekey = $privatekey;
        $this->version = $version;
        $this->pay_domain = KSHER_API_DOMAIN;
        $this->gateway_domain = KSHER_GATEWAY_DOMAIN;

        $this->pubkey = <<<EOD
-----BEGIN PUBLIC KEY-----
MFwwDQYJKoZIhvcNAQEBBQADSwAwSAJBAL7955OCuN4I8eYNL/mixZWIXIgCvIVE
ivlxqdpiHPcOLdQ2RPSx/pORpsUu/E9wz0mYS2PY7hNc2mBgBOQT+wUCAwEAAQ==
-----END PUBLIC KEY-----
EOD;
    }

    /**
     * สร้างรหัสสุ่ม
     */
    public function generate_nonce_str($len = 16)
    {
        $nonce_str = "";
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";

        for ($i = 0; $i < $len; $i++) {
            $nonce_str .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $nonce_str; 
    }

    /**
     * สร้างลายเซ็น
     */
    public function ksher_sign($data)
    {
        $message = self::paramData($data);

        $private_key = openssl_pkey_get_private($this->privatekey);

        if (!$private_key) {
            throw new Exception("Invalid private key");
        }

        openssl_sign($message, $encoded_sign, $private_key, OPENSSL_ALGO_MD5);

        if (PHP_VERSION_ID < 80000) {
            openssl_free_key($private_key);
        }

        return bin2hex($encoded_sign);
    }

    /**
     * ตรวจสอบลายเซ็น
     */
    public function verify_ksher_sign($data, $sign)
    {
        $sign = pack("H*", $sign);
        $message = self::paramData($data);

        $public_key = openssl_pkey_get_public($this->pubkey);

        if (!$public_key) {
            throw new Exception("Invalid public key");
        }

        $result = openssl_verify($message, $sign, $public_key, OPENSSL_ALGO_MD5);

        if (PHP_VERSION_ID < 80000) {
            openssl_free_key($public_key);
        }

        return $result;
    }

    /**
     * จัดเรียงข้อมูล
     */
    private static function paramData($data)
    {
        ksort($data);
        $message = '';
        foreach ($data as $key => $value) {
            $message .= $key . "=" . $value;
        }
        $message = mb_convert_encoding($message, "UTF-8");
        return $message;
    }

    /**
     * ส่ง Request
     */
    public function _request($url, $data = array())
    {
        try {
            $data['sign'] = $this->ksher_sign($data);
            $queryData = http_build_query($data);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $queryData);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/x-www-form-urlencoded'
            ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            $output = curl_exec($ch);

            if ($output !== false) {
                $response_array = json_decode($output, true);
                if ($response_array['code'] == 0) {
                    if (!$this->verify_ksher_sign($response_array['data'], $response_array['sign'])) {
                        $temp = array(
                            "code" => 0,
                            "data" => array(
                                "err_code" => "VERIFY_KSHER_SIGN_FAIL",
                                "err_msg" => "verify signature failed",
                                "result" => "FAIL"
                            ),
                            "msg" => "ok",
                            "sign" => "",
                            "status_code" => "",
                            "status_msg" => "",
                            "time_stamp" => $this->time,
                            "version" => $this->version
                        );
                        return json_encode($temp);
                    }
                }
            }
            curl_close($ch);
            return $output;
        } catch (Exception $e) {
            throw new Exception('cURL error: ' . $e->getMessage());
        }
    }

    /**
     * Gateway Pay - สร้าง Payment URL
     */
    public function gateway_pay($data)
    {
        $data['appid'] = $this->appid;
        $data['nonce_str'] = $this->generate_nonce_str();
        $data['time_stamp'] = $this->time;
        $response = $this->_request($this->gateway_domain . '/gateway_pay', $data);
        return $response;
    }

    /**
     * Gateway Order Query - ตรวจสอบสถานะ
     */
    public function gateway_order_query($data)
    {
        $data['appid'] = $this->appid;
        $data['nonce_str'] = $this->generate_nonce_str();
        $data['time_stamp'] = $this->time;
        $response = $this->_request($this->gateway_domain . '/gateway_order_query', $data);
        return $response;
    }

    /**
     * Order Query - ตรวจสอบรายการ
     */
    public function order_query($data)
    {
        $data['appid'] = $this->appid;
        $data['nonce_str'] = $this->generate_nonce_str();
        $data['time_stamp'] = $this->time;
        $response = $this->_request($this->pay_domain . '/order_query', $data);
        return $response;
    }
}