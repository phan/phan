<?php
$x = 1;
echo b"a $x b\n", B"$x", b"{$x}\t";
echo    "a $x b\n";
echo b<<<E
h $x
E;
echo B<<<"E"
  h $x b
  E;
$s = b<<<'E'
  q
  E;
echo b'q', b"plain\n";
echo b"a
$x
b";
