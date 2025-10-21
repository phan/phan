<?php
// This is surprisingly parsed by php 8 as throw(new RuntimeException() && printf("Meant to be unreachable"))
throw new RuntimeException() && printf("Meant to be unreachable but isn't\n");
