<?php
function getRef(&$var) { }

getRef(${0});
getRef(${''});  // TODO: Distinguish between the empty string as a variable name and an unresolvable variable name
