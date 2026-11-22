<?php
// curl_share_init_persistent() (PHP 8.5) returns a CurlSharePersistentHandle
function share_5964(): CurlSharePersistentHandle {
    return curl_share_init_persistent([CURL_LOCK_DATA_DNS]);
}
