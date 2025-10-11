<?php

// Should NOT warn - these are the canonical functions
checkdnsrr('example.com');
getmxrr('example.com', $mx);

// Should warn - these are aliases
dns_check_record('example.com');
dns_get_mx('example.com', $mx);
