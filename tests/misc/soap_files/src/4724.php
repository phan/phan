<?php
declare(strict_types=1);

var_export(new SoapFault('x')); // too few arguments
var_export(new SoapFault(1, 'some string')); // int is invalid
var_export(new SoapFault("Sender", "image data query failed", "actor", []));
