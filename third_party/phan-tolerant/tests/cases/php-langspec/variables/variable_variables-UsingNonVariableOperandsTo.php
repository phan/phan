/* Auto-generated from php/php-langspec tests */
<?php

const CON = 'v';
//$CON = 5;				// seen as 1 token ($CON), not as $ and CON
//$ CON = 5;				// syntax error, unexpected 'CON' (T_STRING),
						// expecting variable (T_VARIABLE) or '$'

// Without the {}, the operand of $ must begin with a variable name (which
// excludes constants) // or another $
//*/

///*
