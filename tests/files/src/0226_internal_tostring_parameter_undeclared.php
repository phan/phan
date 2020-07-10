<?php

/**
 * @return urlstring
 * @suppress PhanTypeMismatchReturnProbablyReal
 */
function foo() {return 'a';}

error_log(foo());
