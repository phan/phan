<?php
declare(strict_types=1);
namespace Phan;

/**
 * Log
 *
 */
class Log {
	protected static $instance;
	protected $output_mode  = 'verbose'; // 'json', 'csv', ?
	protected $output_order = 'chrono';  // 'type', 'file' ?
	protected $output_filename = '';
	protected $output_mask = -1;

	const EREDEF  =  1<<0;
	const EUNDEF  =  1<<1;
	const ETYPE   =  1<<2;
	const EPARAM  =  1<<3;
	const EVAR    =  1<<4;
	const ENOOP   =  1<<5;
	const EOPTREQ =  1<<6;
	const ESTATIC =  1<<6;
	const EAVAIL  =  1<<8;
	const ETAINT  =  1<<9;
	const ECOMPAT = 1<<10;
	const EACCESS = 1<<11;
	const EDEP    = 1<<12;

	const ERRS    = [ self::EREDEF  => 'RedefineError',
					  self::EUNDEF  => 'UndefError',
					  self::ETYPE   => 'TypeError',
					  self::EPARAM  => 'ParamError',
					  self::EVAR    => 'VarError',
					  self::ENOOP   => 'NOOPError',
					  self::EOPTREQ => 'ReqAfterOptError',
					  self::ESTATIC => 'StaticCallError',
					  self::EAVAIL  => 'AvailError',
					  self::ETAINT  => 'TaintError',
					  self::ECOMPAT => 'CompatError',
					  self::EACCESS => 'AccessError',
					  self::EDEP    => 'DeprecatedError'
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

	public static function setFilename(string $filename) {
		$log = self::getInstance();
		$log->output_filename = $filename;
	}

	public static function getOutputMask():int {
		$log = self::getInstance();
		return $log->output_mask;
	}

	public static function setOutputMask(int $mask) {
		$log = self::getInstance();
		$log->output_mask = $mask;
	}

    public static function err(
        int $etype,
        string $msg,
        string $file = '',
        int $lineno = 0
    ) {
		$log = self::getInstance();

		if($etype == self::EFATAL) {
			self::display();
			// Something went wrong - abort
            if($file) {
                throw new \Exception("$file:$lineno $msg");
            }
            else {
                throw new \Exception($msg);
            }
		}

		if($etype & $log->output_mask) {
			$ukey = md5($file.$lineno.$etype.$msg);
            $log->msgs[$ukey] = [
                'file' => $file,
                'lineno' => $lineno,
                'etype' => $etype,
                'msg' => $msg
            ];
		}
	}

	public static function errorHandler($errno, $errstr, $errfile, $errline) {
		echo "$errfile:$errline $errstr\n";
		debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
	}

	public static function display(array $summary=[]) {
		$log = self::getInstance();
		$out = '';

        $print_closure = function($message) {
            print $message;
        };

        $fp = null;
        if(!empty($log->output_filename)) {
            $fp = fopen($log->output_filename, "w");
            $print_closure = function($message) use ($fp) {
                fputs($fp, $message);
            };
        }

		switch($log->output_mode) {
			case 'verbose':
				if(!empty($summary)) {
					$t = round($summary['time'],2);
					$out .= "Files scanned: {$summary['total_files']}\n";
					$out .= "Time:		{$t}s\n";
					$out .= "Classes:	{$summary['classes']}\n";
					$out .= "Methods:	{$summary['methods']}\n";
					$out .= "Functions:	{$summary['functions']}\n";
					$out .= "Closures:	{$summary['closures']}\n";
					$out .= "Traits:		{$summary['traits']}\n";
					$out .= "Conditionals:	{$summary['conditionals']}\n";
					$out .= "Issues found:	".count($log->msgs)."\n\n";
                    $print_closure($out);
				}
				// Fall-through
			case 'short':
				foreach($log->msgs as $e) {
					$print_closure("{$e['file']}:{$e['lineno']} ".self::ERRS[$e['etype']]." {$e['msg']}\n");
				}
				break;
			// TODO: json and csv
		}

        $log->msgs = [];

        if ($fp) {
            fclose($fp);
        }
	}
}

set_error_handler('\\phan\\Log::errorHandler', -1);
/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: sw=4 ts=4 fdm=marker
 * vim<600: sw=4 ts=4
 */
