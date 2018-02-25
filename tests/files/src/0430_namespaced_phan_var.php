<?php

namespace Foo {

'@phan-var-force array{key:string} $x';
echo count($x);

// This is ignored for built in globals.
'@phan-var-force string $argv';
echo $argv;

}
