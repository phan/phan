<?php

rand(0, 1) ? true : false;
rand(0, 1) ?: false;
1 ? 0 : 0;
0 ?: 1;
rand(0, 1) ? print("This does something")  : false;
rand(0, 1) ? print("One\n") : print("Zero\n");
