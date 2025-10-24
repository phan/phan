<?php

// PHP 8.5 function deprecations

// Resource cleanup functions (no-op since PHP 8.0)
$img = imagecreate(10, 10);
imagedestroy($img);

$curl = curl_init();
curl_close($curl);

$share = curl_share_init();
curl_share_close($share);

$finfo = finfo_open(FILEINFO_MIME_TYPE);
finfo_close($finfo);

$parser = xml_parser_create();
xml_parser_free($parser);
