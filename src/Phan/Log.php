<?php
declare(strict_types=1);
namespace Phan;

use \Phan\Config;

class Log {
	protected static $instance;
	protected $output_mode  = 'text'; // 'text, 'codeclimate'
	protected $output_order = 'chrono';  // 'type', 'file' ?
	protected $output_filename = '';
	protected $output_mask = -1;

    /**
     * @var string[]
     */
    protected $msgs = [];

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

    /**
     * @param int $etype
     * The error type such as Log::EUNDEF.
     *
     * @param string $msg
     * The error message
     *
     * @param string $file
     * The name of the file with the issue
     *
     * @param int|null $lineno
     * The line number where the issue occurs
     */
    public static function err(
        int $etype,
        string $msg,
        string $file = null,
        $lineno = 0
    ) {
		$log = self::getInstance();

        $lineno = (int)$lineno;

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

        // Don't report anything for excluded files
        if(Phan::isExcludedAnalysisFile($file)) {
            return;
        }

        // If configured to do so, prepend the message
        // with a trace ID which indicates where the issue
        // came from allowing us to group on unique classes
        // of issues
        if (Config::get()->emit_trace_id) {
            $msg = self::traceId(debug_backtrace()[1])
                . ' ' . $msg;
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

    /**
     * Get an identifier for where the error is being thrown
     * from. This helps us find counts of all unique errors
     * being thrown.
     */
    public static function traceId($trace) {
        $id = $trace['class']
            . $trace['type']
            . $trace['function'];

        return substr(md5($id), 0, 6);
    }

	public static function errorHandler($errno, $errstr, $errfile, $errline) {
		echo "$errfile:$errline $errstr\n";
		debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
	}

	public static function display(array $summary=[]) {
		$log = self::getInstance();
		$out = '';

        $print_closure = function(string $message) {
            print $message;
        };

        $fp = null;
        if(!empty($log->output_filename)) {
            $fp = fopen($log->output_filename, "w");
            $print_closure = function($message) use ($fp) {
                fputs($fp, $message);
            };
        } else {
            if(Config::get()->progress_bar) {
                fwrite(STDERR, "\n");
            }
        }

		switch($log->output_mode) {
			case 'text':
				foreach($log->msgs as $e) {
                    $print_closure(
                        "{$e['file']}:{$e['lineno']} "
                        . self::ERRS[$e['etype']]
                        . " {$e['msg']}\n"
                    );
				}
				break;
            case 'codeclimate':
                foreach($log->msgs as $e) {
                    $print_closure(
                        json_encode([
                            'type' => 'issue',
                            'check_name' => self::ERRS[$e['etype']],
                            'description' => self::ERRS[$e['etype']] . ' ' . $e['msg'],
                            'categories' => ['Bug Risk'],
                            'location' => [
                                'path' => preg_replace('/^\/code\//', '', $e['file']),
                                'lines' => [
                                    'begin' => $e['lineno'],
                                    'end' => $e['lineno'],
                                ],
                            ],
                        ], JSON_UNESCAPED_SLASHES, JSON_UNESCAPED_UNICODE) . chr(0)
                    );
                }
                break;
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
