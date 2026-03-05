<?php
/**
 * URL Encryption Helper for KR ASSESSLY
 * Encrypts and decrypts URL parameters (e.g., test_id) using AES-256-CBC
 */

// Secret key for encryption - change this to a unique random string in production
define('URL_ENCRYPTION_KEY', 'KrAssessly@2026$SecureKey!#Portal');

/**
 * Encrypt an ID for use in URLs
 * @param int|string $id The ID to encrypt
 * @return string URL-safe encrypted string
 */
function encryptId($id) {
    $key = hash('sha256', URL_ENCRYPTION_KEY, true);
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt((string)$id, 'AES-256-CBC', $key, 0, $iv);
    $result = base64_encode($iv . '::' . $encrypted);
    // Make it URL-safe
    return strtr($result, '+/=', '-_~');
}

/**
 * Decrypt an encrypted ID from URL
 * @param string $encryptedId The encrypted string from URL
 * @return int|null The decrypted ID or null if invalid
 */
function decryptId($encryptedId) {
    try {
        // Reverse URL-safe encoding
        $data = strtr($encryptedId, '-_~', '+/=');
        $data = base64_decode($data);
        if ($data === false) return null;
        
        $parts = explode('::', $data, 2);
        if (count($parts) !== 2) return null;
        
        $iv = $parts[0];
        $encrypted = $parts[1];
        $key = hash('sha256', URL_ENCRYPTION_KEY, true);
        
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
        if ($decrypted === false) return null;
        
        $id = (int)$decrypted;
        return $id > 0 ? $id : null;
    } catch (Exception $e) {
        return null;
    }
}
