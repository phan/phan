<?php

\class_alias('stdClass', 'a_615'); // throws an error because of stdClass
\class_alias('a_615_alias', 'a_615_2'); // make sure we don't throw an error for a valid case

class a_615_alias {}
