<?php

printf("%s");
$x = sprintf("some literal");

printf("%d dollars 100% down\n", 44);  // should warn
printf("Not using args", 3);
printf("Not using" . " args\n", 3);
printf("Not using" . " args\n", 3);
printf("Not using args\n", 3, 4);
printf("Not using args\n", 3, 4);

printf([], 2);  // invalid
printf(3, 2);  // invalid

fprintf(STDERR, "Hello world\n");
fprintf(STDERR, "Hello %s", "world");
fprintf(STDERR, "Hello %10s", "world");
fprintf(STDERR, "Hello %10s", "world", "extra");
fprintf(STDERR, "Hello %10s");

printf("%1\$f dollars", 44.1);
printf("%1\$f dollars");
printf(_("%1\$f dollars"), 22.1);
printf(_("%1\$f dollars"));
printf(gettext("%1\$f dollars"), 22.1);
printf(gettext("%1\$f dollars"));
printf(\gettext("%1\$f dollars"), 22.1);
printf(\gettext("%1\$f dollars"));
printf("%1\$f " . "separated" . " dollars");
printf("%1\$f " . "separated" . " dollars", 42.1);
printf('%1$d == %1$s: %s', 42, "message");
printf("%1s of %2s\n", 5, 9);
printf("%1s of %2s", 5, 9);

printf(true);
printf(false);
printf(null);
printf(E_ERROR);
// TODO test printf("%d", $strVal) and warn
