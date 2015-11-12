<?php
strtr("abc","def","ghi");
strtr("abc",["def"=>"ghi"]);
strtr(["def"=>"ghi"]);
