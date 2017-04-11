<?php
/** @deprecated */
trait T { }

class C1 { use T; }
class C2 {}
new C1;
