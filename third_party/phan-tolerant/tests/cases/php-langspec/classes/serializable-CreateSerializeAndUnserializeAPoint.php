/* Auto-generated from php/php-langspec tests */
<?php

$p = new Point(2, 5);
echo "Point \$p = $p\n";

$s = serialize($p);
var_dump($s);

echo "------\n";

$v = unserialize($s);
var_dump($v);

echo "------\n";

class ColoredPoint extends Point implements Serializable
{
	const RED = 1;
	const BLUE = 2;

	private $color;

	public function __construct($x = 0, $y = 0, $color = RED)
	{
		parent::__construct($x, $y);
		$this->color = $color;

		echo "\nInside " . __METHOD__ . ", $this\n\n";
	}

	public function __toString()
	{
		return parent::__toString() . $this->color;
	}	

	public function serialize()
	{
		echo "\nInside " . __METHOD__ . ", $this\n\n";
		
		return serialize(array(
			'color' => $this->color,
			'baseData' => parent::serialize()
		));
	}

    public function unserialize($data)
    {
		$data = unserialize($data);
		$this->color = $data['color'];
		parent::unserialize($data['baseData']);

		echo "\nInside " . __METHOD__ . ", $this\n\n";
    }
}

