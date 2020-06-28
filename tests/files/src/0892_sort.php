<?php
$i = mt_rand();
$a = [];

if ( $i > 0.5 ) {
    $a[] = 'test';
}

'@phan-debug-var $a'; # PhanDebugAnnotation @phan-debug-var requested for variable $a - it has union type array{}|non-empty-list<'test'>(real=array{}|non-empty-list<'test'>)
sort( $a );
'@phan-debug-var $a'; # PhanDebugAnnotation @phan-debug-var requested for variable $a - it has union type non-empty-list<'test'>(real=non-empty-list<'test'>)

if ( $a === [] ) { # PhanImpossibleTypeComparison Impossible attempt to check if $a of type non-empty-list<'test'> is identical to [] of type array{}
    echo "empty";
} else {
    echo "non-empty";
}
