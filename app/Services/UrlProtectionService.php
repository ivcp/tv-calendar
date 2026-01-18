<?php

namespace App\Services;

class UrlProtectionService
{
    private $key;
    private $cipher = "aes-256-cbc";

    public function __construct($secret_key)
    {
        $this->key = hash('sha256', $secret_key, true);
    }

    public function encrypt($url)
    {
        $iv_length = openssl_cipher_iv_length($this->cipher);
        $iv = openssl_random_pseudo_bytes($iv_length);

        $encrypted = openssl_encrypt($url, $this->cipher, $this->key, 0, $iv);

        return base64_encode($iv . $encrypted);
    }

    public function decrypt($data)
    {
        $data = base64_decode($data);
        $iv_length = openssl_cipher_iv_length($this->cipher);

        $iv = substr($data, 0, $iv_length);
        $encryptedText = substr($data, $iv_length);

        return openssl_decrypt($encryptedText, $this->cipher, $this->key, 0, $iv);
    }
}
