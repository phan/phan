<?php

function get_nullable_int() : ?int {
    return rand() % 2 > 0 ? 2 : null;
}

printf("Hello, %s", 3.3);
printf("Hello, %s", 2);
printf("Hello, %s", get_nullable_int());
printf("Hello, %s", false);
printf("Hello, %s", []);
printf("Hello, %f %s", "World", 2);
printf('Hello, %1$d %1$f', 'x');
