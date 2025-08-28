<?php

namespace App\Services;

use Exception;

class DecryptService
{
    /**
     * Predefined key templates for decryption
     */
    private $keyTemplates = ["185Hcomic3PAPP7R", "18comicAPPContent"];
    
    /**
     * Calculate MD5 hash
     */
    public function calculateMD5($data)
    {
        return md5($data);
    }
    
    /**
     * Generate token for API requests
     */
    public function generateToken($key)
    {
        return [
            'token' => $this->calculateMD5($key . "185Hcomic3PAPP7R"),
            'tokenParam' => $key . ',1.8.0'
        ];
    }
    
    /**
     * Decrypt AES encrypted data
     */
    public function decryptData($key, $cipherText)
    {
        foreach ($this->keyTemplates as $template) {
            try {
                // Generate the dynamic key (MD5 hash as string)
                $dynamicKey = $this->calculateMD5($key . $template);
                
                // IMPORTANT: CryptoJS uses the MD5 string as UTF-8 bytes for the key
                // NOT the hex representation. So we need to use the 32-character MD5 string directly
                // Since AES-128 needs 16 bytes and MD5 string is 32 chars, we need to truncate
                $keyForAES = substr($dynamicKey, 0, 32); // Use full MD5 string (32 chars)
                
                // Decode base64 cipher text
                $cipherData = base64_decode($cipherText);
                
                // AES-256-ECB decryption (because 32 char string = 256 bits)
                $decrypted = openssl_decrypt(
                    $cipherData,
                    'AES-256-ECB',
                    $keyForAES,
                    OPENSSL_RAW_DATA
                );
                
                if ($decrypted !== false) {
                    // Try to parse JSON
                    $data = json_decode($decrypted, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $data;
                    }
                }
            } catch (Exception $e) {
                // Continue to next template
                continue;
            }
        }
        
        throw new Exception("Decryption failed with all key templates");
    }
    
    /**
     * Remove PKCS7 padding
     */
    private function removePKCS7Padding($data) 
    {
        $length = strlen($data);
        if ($length > 0) {
            $padding = ord($data[$length - 1]);
            if ($padding > 0 && $padding <= 16) {
                // Verify padding
                for ($i = $length - $padding; $i < $length; $i++) {
                    if (ord($data[$i]) !== $padding) {
                        return $data; // Invalid padding, return as is
                    }
                }
                return substr($data, 0, $length - $padding);
            }
        }
        return $data;
    }
    
    /**
     * Make API request with decryption
     */
    public function fetchAndDecrypt($url, $params = [])
    {
        $timestamp = time();
        $tokenData = $this->generateToken($timestamp);
        
        // Add token to headers
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'token' => $tokenData['token'],
            'tokenparam' => $tokenData['tokenParam'],
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ];
        
        // Make HTTP request
        $client = new \GuzzleHttp\Client();
        
        try {
            $response = $client->request('GET', $url, [
                'headers' => $headers,
                'query' => $params,
                'timeout' => 30,
                'verify' => false
            ]);
            
            $responseData = json_decode($response->getBody()->getContents(), true);
            
            // Check if response needs decryption
            if (isset($responseData['data']) && is_string($responseData['data'])) {
                // Response is encrypted
                $decrypted = $this->decryptData($timestamp, $responseData['data']);
                $responseData['data'] = $decrypted;
            }
            
            return [
                'success' => true,
                'data' => $responseData
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}