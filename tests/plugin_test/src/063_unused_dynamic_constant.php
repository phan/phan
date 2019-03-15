<?php
namespace foo;

class C {
    const BAR = \foo\dynamic1;
}
define(__NAMESPACE__ . '\dynamic1', 'value');
define(__NAMESPACE__ . '\dynamic2', 'value');
echo C::BAR . "\n";
