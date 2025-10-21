/* Auto-generated from php/php-langspec tests */
<?php

class C
{
	public $prop1;
	public $prop2;

	public function __destruct()
	{
		echo "\nInside " . __METHOD__ . "\n\n";
	}
}

$c = new C;

echo "at start, \$c is "; var_dump($c);

unset($c->prop1);
echo "after unset(\$c->prop1), \$c is "; var_dump($c);

unset($c->prop2);
echo "after unset(\$c->prop2), \$c is "; var_dump($c);

unset($c);
echo "after unset(\$c), \$c is undefined\n";
echo "Done\n";
