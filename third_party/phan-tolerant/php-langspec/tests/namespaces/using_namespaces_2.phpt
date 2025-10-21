--TEST--
PHP Spec test generated from ./namespaces/using_namespaces_2.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

use Graphics\D2, Graphics\D3 as D3;

namespace foo\bar
{
	use my\space\MyClass;
}

namespace another\bar
{
	use my\space\MyClass, xx\xxx as XX, yy\yyy as YY;
	use my\space\AnotherClass;
}
--EXPECT--
