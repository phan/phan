<?php

$length = "Hello World" |> strlen(...);

$upper = "hello" |> strtoupper(...);

$mapped = [1, 2, 3] |> array_map(fn (int $value): int => $value * 2, ...);
