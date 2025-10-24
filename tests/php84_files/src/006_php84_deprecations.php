<?php

// Test PHP 8.4 deprecations

// Deprecated functions
$_ = lcg_value();
$_ = mysqli_ping($connection);
$_ = mysqli_kill($connection, 123);
$_ = mysqli_refresh($connection, MYSQLI_REFRESH_GRANT);

// Deprecated constant
$_ = CURLOPT_BINARYTRANSFER;
