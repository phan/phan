/* Auto-generated from php/php-langspec tests */
<?php

trait T5
{
    public static $prop;
}

class C5a
{
    use T5;
}

class C5b
{
    use T5;
}

C5a::$prop = 123;
C5b::$prop = "red";
echo C5a::$prop . "\n";	// ==> 123
echo C5b::$prop . "\n";	// ==> red

