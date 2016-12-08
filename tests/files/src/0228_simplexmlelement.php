<?php
$x = new SimpleXMLElement('<body><foo>x</foo></body>');
var_export($x->foo[0]->asXML());
