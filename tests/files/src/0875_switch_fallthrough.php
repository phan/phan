<?php
/**
 * @param string|bool $action
 */
function buggedFunc( $db = null, $action = false ) {
	switch ( $action ) {
		case false:
			'@phan-debug-var $action';
			break;
		default:
			'@phan-debug-var $action';
			return "Foo $action"; // Here: PhanTypeSuspiciousStringExpression
	}
}

/**
 * @param string|bool $action
 */
function buggedFunc2( $db = null, $action = false ) {
	switch ( false ) {
		case $action:
			'@phan-debug-var $action';
			break;
		default:
			'@phan-debug-var $action';
			return "Foo $action"; // Here: PhanTypeSuspiciousStringExpression
	}
}
