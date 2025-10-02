<?php
/** @suppress PhanUnusedVariable */
( static function ($val) {
    $noFlags = json_encode($val);
    '@phan-debug-var $noFlags';
    $flagsCannotBeResolved = json_encode($val, rand());
    '@phan-debug-var $flagsCannotBeResolved';
    $flagsWrongType = json_encode($val, true);
    '@phan-debug-var $flagsWrongType';
    $flagsNoThrowOnError = json_encode($val, JSON_FORCE_OBJECT);
    '@phan-debug-var $flagsNoThrowOnError';
    $throwsOnError = json_encode($val, JSON_THROW_ON_ERROR);
    '@phan-debug-var $throwsOnError';
} )( $argv[1] );
