This folder asserts that when `target_php_version` is `'7.0'`, Phan emits certain errors if the code would be invalid in php 7.0.
Note that for best results, PHP 7.0 code should be analyzed by a PHP 7.0 binary running Phan.
(E.g. Reflection from the PHP binary is used to check if functions/methods exist)
