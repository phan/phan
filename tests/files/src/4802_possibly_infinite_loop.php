<?php
/** @phan-file-suppress PhanUnreferencedFunction */
function issue4802(array $pages, array $fallback): array {
    if (!$pages) {
        return $pages;
    }
    $albumCount = count($fallback);
    while (count($pages) < $albumCount) {
        $pages[] = '<!-- my-placeholder -->';
    }
    return $pages;
}
