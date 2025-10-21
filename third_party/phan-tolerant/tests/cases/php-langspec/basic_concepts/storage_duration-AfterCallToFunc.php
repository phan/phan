/* Auto-generated from php/php-langspec tests */
<?php

function factorial($i)
{
  if ($i > 1) return $i * factorial($i - 1);
  else if ($i == 1) return $i;
  else return 0;
}

$count = 10;
$result = factorial($count);
echo "\$result = $result\n";

