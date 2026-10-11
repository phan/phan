<?php
// Regression test: narrowing the condition of a ternary that is used as a call argument
// (ArgumentType::analyzeConditionalArgumentBranches()) must not change the type of the variable
// in the global scope, which is shared by the whole file.
function takes_bool5942(bool $b): void
{
    var_export($b);
}

$options = getopt('t');
$flag = false;
if (isset($options['t'])) {
    $flag = true;
}
echo sprintf('%s', $flag ? 'a' : 'b');
'@phan-debug-var $flag';
takes_bool5942($flag);

$other = (bool)rand();
echo strlen($other ? 'x' : 'yy');
'@phan-debug-var $other';
takes_bool5942($other);

$maybe = rand() ? 'str' : null;
echo strlen($maybe ?: 'default');
'@phan-debug-var $maybe';
