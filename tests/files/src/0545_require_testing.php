<?php
require_once [];
require_once (new stdClass());
require_once 11;
require_once '';
include_once '.';
require __DIR__;
include __DIR__ . '/..';
require_once 'file_found_in_include_folder.php';  // should not warn about missing file
require_once 'file_missing_from_any_folder.php';
require_once __DIR__ . '/file_missing_from_any_folder.php';
require_once '0101_one_of_each.php';  // should not warn about missing file
require_once __DIR__ . '/0101_one_of_each.php';  // should not warn about missing file
