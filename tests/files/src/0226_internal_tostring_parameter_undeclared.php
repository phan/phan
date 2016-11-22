<?php

/**
 * @return urlstring
 * @suppress PhanTypeMismatchReturn
 */
function foo() {return 'a';}

error_log(foo());
