<?php

/**
 * @suppress PhanPluginAlwaysReturnFunction
 * @suppress PhanPluginUnreachableCode
 */
function function_suppression_test_fn() : int {
	if (false) {
		return 1;
		return 2;
	}
}
function_suppression_test_fn();
