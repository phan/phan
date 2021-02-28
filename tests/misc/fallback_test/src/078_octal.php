<?php
function expect_string(string $x) {}
expect_string(0o17) // explicit octal literals cannot be tokenized prior to php 8.0. Also, there's a missing semicolon.
