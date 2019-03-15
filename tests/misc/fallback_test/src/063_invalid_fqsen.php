<?php
call_user_func_array('', []);
call_user_func('\\', []);
''();
'\\'();
('')::foo();
('??')::foo();
(',,')::foo();
'\\'::foo();
'\\\\'::foo();
'a\\\\b'::foo();
'a\\\\'::foo();
(1)::foo();
(0)::foo();
call_user_func('aaa\\\\b', []);
call_user_func('aaa\\', []);
