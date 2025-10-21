<?php
// dnf types must be of the form (A&B)|C, not &C.
function example((A&B)&C $param) {}
