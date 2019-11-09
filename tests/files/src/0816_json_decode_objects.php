<?php
$content = json_decode($_GET['whatever']);
$content_type = is_object($content) ? $content->content_type : $content[0]->content_type;
// This emits PhanTypeArraySuspiciousNullable twice as a result of the right hand expression being analyzed twice (once when analyzing all expressions, again when inferring type of that expression)
// One of those times, the type got normalized.
