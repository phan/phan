<?php

function xmltest() {
    $x = new SimpleXMLElement('<a prop="c"></a>');
    var_export($x['prop']);
    $x['newprop'] = 'value';
    var_export($x);
}
xmltest();
