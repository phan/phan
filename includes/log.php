<?php
namespace phan;

/**
 * Log
 *
 */
class Log {
	protected static $instance;
	protected $output_mode  = 'verbose'; // 'json', 'csv', ?
	protected $output_order = 'chrono';  // 'type', 'file' ?
	const EREDEF  =  1;
	const EUNDEF  =  2;
	const ETYPE   =  3;
	const EPARAM  =  4;
	const EVAR    =  5;
	const ENOOP   =  6;
	const EOPTREQ =  7;
	const ESTATIC =  8;
	const EAVAIL  =  9;
	const ETAINT  = 10;
	const ERRS    = [ self::EREDEF  => 'RedefineError',
					  self::EUNDEF  => 'UndefError',
					  self::ETYPE   => 'TypeError',
					  self::EPARAM  => 'ParamError',
					  self::EVAR    => 'VarError',
					  self::ENOOP   => 'NOOPError',
					  self::EOPTREQ => 'ReqAfterOptError',
					  self::ESTATIC => 'StaticCallError',
					  self::EAVAIL  => 'AvailError',
					  self::ETAINT  => 'TaintError'
					];

	const EFATAL = -1;

	public function __construct() {
		$this->msgs = [];
	}

	public static function getInstance():Log {
		if(empty(self::$instance)) {
			self::$instance = new Log();
		}
		return self::$instance;
	}

	public static function setOutputMode(string $mode) {
		$log = self::getInstance();
		$log->output_mode = $mode;
	}

	public static function err(int $etype, string $msg, string $file='', int $lineno=0) {
		$log = self::getInstance();
		if($etype == self::EFATAL) {
			self::display();
			// Something went wrong - abort
			if($file) die("$file:$lineno $msg\n");
			else die($msg."\n");
		}
		$ukey = md5($file.$lineno.$etype.$msg);
		$log->msgs[$ukey] = ['file'=>$file, 'lineno' => $lineno, 'etype'=>$etype, 'msg' => $msg];
	}

	public static function errorHandler($errno, $errstr, $errfile, $errline) {
		echo "$errfile:$errline $errstr\n";
		debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
	}

	public static function display(array $summary=[]) {
		$log = self::getInstance();
		switch($log->output_mode) {
			case 'verbose':
				if(!empty($summary)) {
					$t = round($summary['time'],2);
					echo "Files scanned:	{$summary['total_files']}\n";
					echo "Time:		{$t}s\n";
					echo "Classes:	{$summary['classes']}\n";
					echo "Methods:	{$summary['methods']}\n";
					echo "Functions:	{$summary['functions']}\n";
					echo "Closures:	{$summary['closures']}\n";
					echo "Traits:		{$summary['traits']}\n";
					echo "Conditionals:	{$summary['conditionals']}\n";
					echo "Issues found:	".count($log->msgs)."\n";
					echo "\n";
				}
				// Fall-through
			case 'short':
				foreach($log->msgs as $e) {
					echo "{$e['file']}:{$e['lineno']} ".self::ERRS[$e['etype']]." {$e['msg']}\n";
				}
				break;
			// TODO: json and csv
		}
	}
}

set_error_handler('\\phan\\Log::errorHandler',-1);
/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: sw=4 ts=4 fdm=marker
 * vim<600: sw=4 ts=4
 */
