/* Auto-generated from php/php-langspec tests */
<?php

$a = 10;
$b = 20;

switch ($a)
{
	case 0:
		echo "Case 0 outer\n";
		break;
	case 10:
		echo "Case 10 outer\n";

		switch ($b)
		{
		case 0:
			echo "Case 0 inner\n";
			break;
		case 10:
			echo "Case 10 inner\n";
			break;
		default:
			echo "Default inner\n";
			break;
		}
		break;
	default:
		echo "Default outer\n";
		break;
}

