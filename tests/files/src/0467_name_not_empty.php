<?php
call_user_func_array('', []);
call_user_func('\\', []);
''();
'\\'();  // Phan knows that ''() as a literal will definitely throw an error, so this warns about unreachable code. We don't bother checking for all cases because we already warn about the invalid FQSEN.
('')::foo();
('??')::foo();
(',,')::foo();
'\\'::foo();
'\\\\'::foo();
'a\\\\b'::foo();
'a\\\\'::foo();
(1)::foo();
(0)::foo();
