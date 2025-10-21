/* Auto-generated from php/php-langspec tests */
<?php

class D
{
	private function f()
	{
		echo "Inside method >>" . __METHOD__ . "<<\n";
	}

	public function compute(array $values)
	{
		$count = 0;

		$callback = function ($p1, $p2) use (&$count, $values)
		{
			echo "Inside method >>" . __METHOD__ . "<<\n";	// called {closure}
			++$count;

			$this->f();	// $this is available automatically; can't put it in use clause anyway
		};

		echo "--\n";
		var_dump(gettype($callback));
		echo "--\n";
		var_dump($callback);
		echo "--\n";
		var_dump($callback instanceof Closure);
		echo "--\n";

		$callback(1,2,3);
		echo "\$count = $count\n";
		$callback(5,6,7);
		echo "\$count = $count\n";

		$callback2 = function()
		{
			echo "Inside method >>" . __METHOD__ . "<<\n";	// ALSO called {closure}
		};

		echo "--\n";
		var_dump(gettype($callback2));
		echo "--\n";
		var_dump($callback2);
		echo "--\n";
		var_dump($callback2 instanceof Closure);
		echo "--\n";

		$callback2();
	}

	public static function stcompute(array $values)
	{
		$count = 0;

		$callback = function ($p1, $p2) use (&$count, $values)
		{
			echo "Inside method >>" . __METHOD__ . "<<\n";	// called D::{closure}
			++$count;
		};

		echo "--\n";
		var_dump(gettype($callback));
		echo "--\n";
		var_dump($callback);
		echo "--\n";
		var_dump($callback instanceof Closure);
		echo "--\n";

		$callback(1,2,3);
		echo "\$count = $count\n";
		$callback(5,6,7);
		echo "\$count = $count\n";
	}

}

$d1 = new D;
$d1->compute(["red" => 3, 10]);

