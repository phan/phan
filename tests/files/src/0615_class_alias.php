<?php

\class_alias('stdClass', 'a_615_1'); // throws an error because of stdClass
\class_alias('std'.'Class', 'a_615_2'); // check also when 1st param is a 'node' (not just a plain string)

\class_alias('a_615_alias', 'a_615_3'); // make sure we don't throw an error for a valid case

class a_615_alias {}
