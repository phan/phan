<?php
// curl_share_init_persistent() (PHP 8.5) returns a CurlSharePersistentHandle
function share_5964(): CurlSharePersistentHandle {
    return curl_share_init_persistent([CURL_LOCK_DATA_DNS]);
}

function signatures_85_5964(string $in, string $out, OpenSSLCertificate $cert) {
    // $cipher_algo accepts a cipher name since PHP 8.5
    var_export(openssl_cms_encrypt($in, $out, $cert, [], 0, OPENSSL_ENCODING_SMIME, 'aes-256-cbc'));
    // $use_include_path is a bool since PHP 8.5
    var_export(gzopen($in, 'r', true));
}
