<?php

$optind = null;
getopt('c', [], $optind);  // This is a false positive PhanParamTooManyInternal for php 7.0, but special casing doesn't seem worth the effort.
