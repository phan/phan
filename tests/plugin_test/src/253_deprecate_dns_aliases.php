<?php

// Should NOT warn - these are the canonical functions
$result = checkdnsrr('example.com');
getmxrr('example.com', $mx);

// Should warn - these are aliases
dns_check_record('example.com');
dns_get_mx('example.com', $mx);
