<?php
// Should be parsed as `(@$x) instanceof stdClass.
// @ has higher precedence than instanceof
echo @$x instanceof stdClass;
