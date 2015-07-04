<?php
namespace phan;

error_reporting(-1);
ini_set("memory_limit", -1);

// Parse command line args
$opts = getopt("f:m:hasuqp");
$pruneargv = array();
$files = [];
$dump_ast = $dump_scope = $dump_user_functions = $quick_mode = $progress_bar = false;
foreach($opts as $key=>$value) {
	switch($key) {
		case 'h': usage(); break;
		case 'f':
			if(is_file($value) && is_readable($value)) {
				$files = file($value,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
			} else {
				Log::err(Log::EFATAL, "Unable to open $value");
			}
			break;
		case 'm':
			if(!in_array($value, ['verbose','short','json','csv'])) usage("Unknown output mode: $value");
			Log::setOutputMode($value);
			break;
		case 'a':
			$dump_ast = true;
			break;
		case 's':
			$dump_scope = true;
			break;
		case 'u':
			$dump_user_functions = true;
			break;
		case 'q':
			$quick_mode = true;
			break;
		case 'p':
			$progress_bar = true;
			break;
		default: usage("Unknown option '-$key'"); break;
	}
}
foreach($opts as $opt => $value) {
	foreach($argv as $key=>$chunk) {
		$regex = '/^'. (isset($opt[1]) ? '--' : '-') . $opt . '/';
		if ($chunk == $value && $argv[$key-1][0] == '-' || preg_match($regex, $chunk)) {
			array_push($pruneargv, $key);
		}
	 }
}
while($key = array_pop($pruneargv)) unset($argv[$key]);
if(empty($files) && count($argv) < 2) Log::err(Log::EFATAL, "No files to analyze");
foreach($argv as $arg) if($arg[0]=='-') usage("Unknown option '{$arg}'");

$files = array_merge($files,array_slice($argv,1));

function usage($msg='') {
	global $argv;

	if(!empty($msg)) echo "$msg\n";
	echo <<<EOB
Usage: {$argv[0]} [options] [files...]
  -f <filename>   A file containing a list of PHP files to be analyzed
  -q              Quick mode - doesn't recurse into all function calls
  -m <mode>       Output mode: verbose, short, json, csv
  -p              Show progress bar
  -a              Dump AST of provides files (for debugging)
  -s              Dump scope tree (for debugging)
  -u              Dump user defined functions (for debugging)
  -h			  This help

EOB;
  exit;
}

function progress(string $msg, float $p) {
	echo "\r$msg ";
	echo str_repeat("\u{25b0}", (int)($p*60));
	echo str_repeat("\u{25b1}", (int)((1-$p)*60));
	echo " ".(int)(100*$p)."%";
}
/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: sw=4 ts=4 fdm=marker
 * vim<600: sw=4 ts=4
 */
