<?php
/**
 * Test PhanImpossibleCondition Fail
 *
 * @param array $setting
 */
function phanImpossibleConditionFail( $setting ) {
    $setting['RELATION'] = 'OR'; // Comment this to not trigger error.

    if ( ! is_string( $setting['IDS'] ) ) { // Or comment this if.
        return;
    }
    '@phan-debug-var $setting';
}
