<?php
// $before_needle was added to strrchr() in PHP 8.3
echo strrchr('Hello, World', 'o', true);
echo strrchr('Hello, World', 'o', true, 'extra');
