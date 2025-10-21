--TEST--
PHP Spec test generated from ./constants/core_predefined_constants.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

function trace($text, $pdc)
{
	echo "$text: ";
	var_dump($pdc);
}

trace("__LINE__", __LINE__);

trace("__FILE__", __FILE__);

trace("__DIR__", __DIR__);
var_dump(dirname(__FILE__));

trace("__LINE__", __LINE__);

trace("__NAMESPACE__", __NAMESPACE__);

echo "-----------------------------------------\n";

echo "At the top level of a script\n";
trace("__FUNCTION__", __FUNCTION__);

echo "-----------------------------------------\n";

echo "At the top level of a script and outside all classes\n";
trace("__METHOD__", __METHOD__);

echo "-----------------------------------------\n";

echo "Outside all classes\n";
trace("__CLASS__", __CLASS__);

echo "-----------------------------------------\n";

echo "Outside all classes\n";
trace("__TRAIT__", __TRAIT__);

echo "-----------------------------------------\n";

function ComputeResult()
{
	echo "Inside ComputeResult\n";
	trace("__FUNCTION__", __FUNCTION__);
	trace("__METHOD__", __METHOD__);
	trace("__CLASS__", __CLASS__);
	trace("__TRAIT__", __TRAIT__);
	trace("__NAMESPACE__", __NAMESPACE__);

	function Inner()
	{
		echo "Inside ComputeResult\n";
		trace("__FUNCTION__", __FUNCTION__);
		trace("__METHOD__", __METHOD__);
	}

	Inner();
}

ComputeResult();

echo "-----------------------------------------\n";

class Date
{
	public function __construct()
	{
		echo "Inside " . __METHOD__ . "\n";
		trace("__CLASS__", __CLASS__);
		trace("__FUNCTION__", __FUNCTION__);
		trace("__TRAIT__", __TRAIT__);
		trace("__NAMESPACE__", __NAMESPACE__);

		// ...
	}

	function __destruct()
	{
		echo "Inside " . __METHOD__ . "\n";
		trace("__FUNCTION__", __FUNCTION__);

		// ...
	}

	public function setDay($day)
	{
		echo "Inside " . __METHOD__ . "\n";
		trace("__FUNCTION__", __FUNCTION__);
		trace("__LINE__", __LINE__);

		$this->priv1();
		$this->spf1();
	}

// public vs. private doesn't matter

	private function priv1()
	{
		echo "Inside " . __METHOD__ . "\n";
		trace("__FUNCTION__", __FUNCTION__);
	}

	static public function spf1()
	{
		echo "Inside " . __METHOD__ . "\n";
		trace("__FUNCTION__", __FUNCTION__);
	}

}

$date1 = new Date;
$date1->setDay(22);

echo "-----------------------------------------\n";

class DatePlus extends Date
{
	public function xx()
	{
		trace("__CLASS__", __CLASS__);
		echo "Inside " . __METHOD__ . "\n";
		trace("__FUNCTION__", __FUNCTION__);
	}
}

$datePlus1 = new DatePlus;
$datePlus1->xx();

include_once('includefile.inc');
--EXPECTF--
__LINE__: int(17)
__FILE__: string(%d) "%s/constants/core_predefined_constants.php"
__DIR__: string(%d) "%s/constants"
string(%d) "%s/constants"
__LINE__: int(24)
__NAMESPACE__: string(0) ""
-----------------------------------------
At the top level of a script
__FUNCTION__: string(0) ""
-----------------------------------------
At the top level of a script and outside all classes
__METHOD__: string(0) ""
-----------------------------------------
Outside all classes
__CLASS__: string(0) ""
-----------------------------------------
Outside all classes
__TRAIT__: string(0) ""
-----------------------------------------
Inside ComputeResult
__FUNCTION__: string(13) "ComputeResult"
__METHOD__: string(13) "ComputeResult"
__CLASS__: string(0) ""
__TRAIT__: string(0) ""
__NAMESPACE__: string(0) ""
Inside ComputeResult
__FUNCTION__: string(5) "Inner"
__METHOD__: string(5) "Inner"
-----------------------------------------
Inside Date::__construct
__CLASS__: string(4) "Date"
__FUNCTION__: string(11) "__construct"
__TRAIT__: string(0) ""
__NAMESPACE__: string(0) ""
Inside Date::setDay
__FUNCTION__: string(6) "setDay"
__LINE__: int(98)
Inside Date::priv1
__FUNCTION__: string(5) "priv1"
Inside Date::spf1
__FUNCTION__: string(4) "spf1"
-----------------------------------------
Inside Date::__construct
__CLASS__: string(4) "Date"
__FUNCTION__: string(11) "__construct"
__TRAIT__: string(0) ""
__NAMESPACE__: string(0) ""
__CLASS__: string(8) "DatePlus"
Inside DatePlus::xx
__FUNCTION__: string(2) "xx"
Inside includefile.php
string(%d) "%s/constants/includefile.inc"
string(%d) "%s/constants"
Inside Date::__destruct
__FUNCTION__: string(10) "__destruct"
Inside Date::__destruct
__FUNCTION__: string(10) "__destruct"
