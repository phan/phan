<?php declare(strict_types=1);

if ( !empty($_SERVER['HOST_NAME']) && $_SERVER['HOST_NAME'] == "www.some.domain" ) {
    $important_variable = true;
}
'@phan-debug-var $important_variable';
$use_variable = $important_variable;
echo count($use_variable);
