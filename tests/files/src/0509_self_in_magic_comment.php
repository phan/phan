<?php

namespace MyLittleProject;

/**
 * @method static self RESERVED()
 * @method static void ACCEPT_INSTANCE(self $param)
 * @property self $mySelf
 */
class NumberStatus
{
    const RESERVED = 'reserved';
}
call_user_func(function() {
	$x = NumberStatus::RESERVED();
	echo strlen($x);
	NumberStatus::ACCEPT_INSTANCE(new \stdClass());
    echo strlen($x->mySelf);
});

