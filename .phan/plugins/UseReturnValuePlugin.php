<?php declare(strict_types=1);

use ast\Node;
use Phan\AST\ContextNode;
use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\CodeBaseException;
use Phan\Language\Context;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\PluginV2;
use Phan\PluginV2\FinalizeProcessCapability;
use Phan\PluginV2\PluginAwarePostAnalysisVisitor;
use Phan\PluginV2\PostAnalyzeNodeCapability;


/**
 * A plugin that checks for invocations of functions/methods where the return value should be used.
 * Also, gathers statistics on how often those functions/methods are used.
 *
 * This can be configured by fields of 'plugin_config', or by setting environment variables.
 *
 * Note: When this is using dynamic checks of the whole codebase, the run should be limited to a single process. That check is memory intensive.
 *
 * Configuration options:
 *
 * - ['plugin_config']['use_return_value_verbose'] (or PHAN_USE_RETURN_VALUE_DEBUG=1 as an environment variable)
 *
 *   Print statistics about what percentage of time methods/functions are used
 * - ['plugin_config']['use_return_value_dynamic_checks'] (or PHAN_USE_RETURN_VALUE_DYNAMIC_CHECKS=1 as an environment variable)
 *
 *   Warn about unused return values when the return value is used in 98%+ of the overall statements in the project
 * - ['plugin_config']['use_return_value_warn_threshold_percentage'] (or PHAN_USE_RETURN_VALUE_WARN_THRESHOLD_PERCENTAGE=1 as an environment variable)
 *
 *   For dynamic checks, use this value instead of the default of 98 (should be between 0.01 and 100)
 */
class UseReturnValuePlugin extends PluginV2 implements PostAnalyzeNodeCapability, FinalizeProcessCapability
{
    const UseReturnValue = 'PhanPluginUseReturnValue';
    const UseReturnValueInternal = 'PhanPluginUseReturnValueInternal';
    const UseReturnValueInternalKnown = 'PhanPluginUseReturnValueInternalKnown';

    const DEFAULT_THRESHOLD_PERCENTAGE = 98;

    /**
     * @var array<string,StatsForFQSEN> maps an FQSEN to information about the FQSEN and its uses.
     * @internal
     */
    public static $stats = [];

    /**
     * @var bool should debug information about commonly used FQSENs be used in this project?
     */
    public static $debug = false;

    /**
     * @var bool - If true, this will track the calls in your program to warn if a return value
     * of an internal or user-defined function/method is unused,
     * when over self::$threshold_percentage (e.g. 98%) is used.
     *
     * This option is slow and won't work effectively in the language server mode.
     */
    public static $use_dynamic = false;

    /**
     * @return string - name of PluginAwarePostAnalysisVisitor subclass
     */
    public static function getPostAnalyzeNodeVisitorClassName() : string
    {
        self::$stats = [];
        // NOTE: debug should be used together with dynamic checks.
        self::$debug = Config::getValue('plugin_config')['use_return_value_verbose'] ?? (bool)getenv('PHAN_USE_RETURN_VALUE_DEBUG');
        self::$use_dynamic = Config::getValue('plugin_config')['use_return_value_dynamic_checks'] ??
            (bool)getenv('PHAN_USE_RETURN_VALUE_DYNAMIC_CHECKS');
        return UseReturnValueVisitor::class;
    }

    /**
     * @return void
     */
    public function finalizeProcess(CodeBase $code_base)
    {
        if (!self::$use_dynamic) {
            return;
        }
        $threshold_percentage = Config::getValue('plugin_config')['use_return_value_warn_threshold_percentage'] ??
            (getenv('PHAN_USE_RETURN_VALUE_WARN_THRESHOLD_PERCENTAGE') ?: self::DEFAULT_THRESHOLD_PERCENTAGE);

        foreach (self::$stats as $fqsen => $counter) {
            $fqsen_key = ltrim(strtolower($fqsen), "\\");
            $used_count = count($counter->used_locations);
            $unused_count = count($counter->unused_locations);
            $total_count = $used_count + $unused_count;

            $known_must_use_return_value = self::HARDCODED_FQSENS[$fqsen_key] ?? null;
            $used_percentage = $used_count / $total_count * 100;
            if ($total_count >= 5) {
                if (self::$debug) {
                    fprintf(STDERR, "%09.4f %% used: (%4d uses): %s (%s)\n", $used_percentage, $total_count, $fqsen, $counter->is_internal ? 'internal' : 'user-defined');
                }
            }

            if ($known_must_use_return_value === false) {
                continue;
            }

            if ($known_must_use_return_value === null &&  $total_count < 5) {
                continue;
            }
            if ($unused_count > 0 && $used_percentage >= $threshold_percentage) {
                $percentage_string = number_format($used_percentage, 2);
                foreach ($counter->unused_locations as $key => $context) {
                    if (!preg_match('/:(\d+)$/', $key, $matches)) {
                        fprintf(STDERR, "Failed to extract line number from $key\n");
                        continue;
                    }
                    $line = (int)$matches[1];
                    $context = $context->withLineNumberStart($line);
                    if ($known_must_use_return_value) {
                        $this->emitIssue(
                            $code_base,
                            $context,
                            self::UseReturnValueInternalKnown,
                            'Expected to use the return value of the internal function/method {FUNCTION}',
                            [$fqsen]
                        );
                    } elseif ($counter->is_internal) {
                        $this->emitIssue(
                            $code_base,
                            $context,
                            self::UseReturnValueInternal,
                            'Expected to use the return value of the internal function/method {FUNCTION} - {SCALAR}%% of calls use it in the rest of the codebase',
                            [$fqsen, $percentage_string]
                        );
                    } else {
                        $this->emitIssue(
                            $code_base,
                            $context,
                            self::UseReturnValue,
                            'Expected to use the return value of the user-defined function/method {FUNCTION} - {SCALAR}%% of calls use it in the rest of the codebase',
                            [$fqsen, $percentage_string]
                        );
                    }
                }
            }
        }
    }

    /**
     * Maps lowercase FQSENs to whether or not this plugin should warn about the return value of a method being unused.
     * This should remain sorted.
     */
    const HARDCODED_FQSENS = [
        'abs' => true,
        'addcslashes' => true,
        'array_chunk' => true,
        'array_column' => true,
        'array_combine' => true,
        'array_diff_key' => true,
        'array_diff' => true,
        'array_fill_keys' => true,
        'array_fill' => true,
        'array_filter' => true,
        'array_flip' => true,
        'array_intersect_key' => true,
        'array_intersect' => true,
        'arrayiterator::current' => true,
        'array_key_exists' => true,
        'array_keys' => true,
        'array_map' => true,
        'array_merge' => true,
        'array_rand' => true,
        'array_reduce' => true,
        'array_replace_recursive' => true,
        'array_replace' => true,
        'array_reverse' => true,
        'array_search' => true,
        'array_slice' => true,
        'array_sum' => true,
        'array_unique' => true,
        'array_values' => true,
        'base64_decode' => true,
        'base64_encode' => true,
        'base_convert' => true,
        'basename' => true,
        'bcadd' => true,  // todo: add the rest of bcadd
        'bccomp' => true,
        'bcdiv' => true,
        'bcmul' => true,
        'bcsub' => true,
        'bin2hex' => true,
        'ceil' => true,
        'chr' => true,
        'class_implements' => true,
        'closure::bind' => true,
        'closure::fromcallable' => true,
        'compact' => true,
        'constant' => true,
        'cos' => true,
        'count' => true,
        'crc32' => true,
        'ctype_alpha' => true,
        'ctype_digit' => true,
        'curl_errno' => true,
        'curl_error' => true,
        'curl_exec' => true,
        'curl_init' => true,
        'current' => true,
        'date_default_timezone_get' => true,
        'datetime::createfromformat' => true,
        'datetime::diff' => true,
        'datetime::format' => true,
        'datetime::gettimestamp' => true,
        'datetimeimmutable::format' => true,
        'datetimeimmutable::settimezone' => true,
        'datetimeinterface::format' => true,
        'datetimezone::getname' => true,
        'date' => true,
        'debug_backtrace' => true,
        'dechex' => true,
        'defined' => true,
        'deg2rad' => true,
        'dirname' => true,
        'domdocument::createelement' => true,
        'domdocument::createtextnode' => true,
        'domdocument::getelementsbytagname' => true,
        'domdocument::importnode' => true,
        'domdocument::savexml' => true,
        'domelement::getattribute' => true,
        'domelement::hasattribute' => true,
        'domnodelist::item' => true,
        'domxpath::query' => true,
        'doubleval' => true,
        'errorexception::getfile' => true,
        'errorexception::getline' => true,
        'errorexception::getseverity' => true,
        'error::getcode' => true,
        'error::getfile' => true,
        'error::getline' => true,
        'error::getmessage' => true,
        'escapeshellarg' => true,
        'exception::getcode' => true,
        'exception::getfile' => true,
        'exception::getline' => true,
        'exception::getmessage' => true,
        'exception::getprevious' => true,
        'exception::gettraceasstring' => true,
        'exception::gettrace' => true,
        'explode' => true,
        'extension_loaded' => true,
        'feof' => true,
        'fgets' => true,
        'file_exists' => true,
        'filemtime' => true,
        'filesize' => true,
        'filter_input_array' => true,
        'filter_input' => true,
        'filteriterator::current' => true,
        'filter_var' => true,
        'floatval' => true,
        'floor' => true,
        'fopen' => true,
        'fread' => true,
        'ftell' => true,
        'func_get_args' => true,
        'func_get_arg' => true,
        'func_num_args' => true,
        'function_exists' => true,
        'get_called_class' => true,
        'get_cfg_var' => true,
        'get_class' => true,
        'getcwd' => true,
        'getdate' => true,
        'get_declared_classes' => true,
        'get_declared_interfaces' => true,
        'get_declared_traits' => true,
        'get_defined_functions' => true,
        'getenv' => true,
        'gethostname' => true,
        'getimagesize' => true,
        'get_include_path' => true,
        'getmypid' => true,
        'get_object_vars' => true,
        'get_parent_class' => true,
        'get_resource_type' => true,
        'gettype' => true,
        'glob' => true,
        'gmdate' => true,
        'gzuncompress' => true,
        'hash_equals' => true,
        'hash_file' => true,
        'hash_hmac' => true,
        'hash' => true,
        'headers_sent' => true,
        'hexdec' => true,
        'htmlentities' => true,
        'html_entity_decode' => true,
        'htmlspecialchars_decode' => true,
        'htmlspecialchars' => true,
        'http_build_query' => true,
        'iconv' => true,
        'implode' => true,
        'in_array' => true,
        'ini_get' => true,
        'interface_exists' => true,
        'internal)' => true,
        'intl_get_error_code' => true,
        'intl_get_error_message' => true,
        'intl_is_failure' => true,
        'intval' => true,
        'invalidargumentexception::getmessage' => true,
        'ip2long' => true,
        'is_array' => true,
        'is_a' => true,
        'is_bool' => true,
        'is_callable' => true,
        'is_dir' => true,
        'is_executable' => true,
        'is_file' => true,
        'is_float' => true,
        'is_int' => true,
        'is_link' => true,
        'is_null' => true,
        'is_numeric' => true,
        'is_object' => true,
        'is_readable' => true,
        'is_resource' => true,
        'is_scalar' => true,
        'is_string' => true,
        'is_subclass_of' => true,
        'is_writable' => true,
        'iterator::current' => true,
        'iterator_to_array' => true,
        'iterator::valid' => true,
        'join' => true,
        'json_decode' => true,
        'json_encode' => true,
        'json_last_error_msg' => true,
        'json_last_error' => true,
        'key' => true,
        'lcfirst' => true,
        'levenshtein' => true,
        'locale::getdefault' => true,
        'log' => true,
        'long2ip' => true,
        'ltrim' => true,
        'max' => true,
        'mb_convert_encoding' => true,
        'mb_detect_encoding' => true,
        'mb_strlen' => true,
        'mb_strtolower' => true,
        'mb_substr' => true,
        'md5' => true,
        'memcached::getoption' => true,
        'memcached::get' => true,
        'memory_get_peak_usage' => true,
        'memory_get_usage' => true,
        'method_exists' => true,
        'microtime' => true,
        'min' => true,
        'mktime' => true,
        'mt_getrandmax' => true,
        'mt_rand' => true,
        'ngettext' => true,
        'numberformatter::getattribute' => true,
        'numberformatter::geterrorcode' => true,
        'numberformatter::geterrormessage' => true,
        'numberformatter::getsymbol' => true,
        'numberformatter::gettextattribute' => true,
        'number_format' => true,
        'ob_get_clean' => true,  // prefer ob_end_clean
        'ob_get_contents' => true,
        'ob_get_level' => true,
        'octdec' => true,
        'opendir' => true,
        'ord' => true,
        'pack' => true,
        'parse_url' => true,
        'pathinfo' => true,
        'pdo::getattribute' => true,
        'pdo::prepare' => true,
        'php_uname' => true,
        'popen' => true,
        'pow' => true,
        'preg_quote' => true,
        'preg_replace_callback' => true,
        'preg_replace' => true,
        'preg_split' => true,
        'proc_open' => true,
        'property_exists' => true,
        'random_bytes' => true,
        'rand' => true,
        'range' => true,
        'rawurldecode' => true,
        'rawurlencode' => true,
        'readdir' => true,
        'realpath' => true,
        'redis::getoption' => true,
        'reflectionclass::getconstructor' => true,
        'reflectionclass::getdoccomment' => true,
        'reflectionclass::getfilename' => true,
        'reflectionclass::getmethods' => true,
        'reflectionclass::getmethod' => true,
        'reflectionclass::getname' => true,
        'reflectionclass::getparentclass' => true,
        'reflectionclass::getproperties' => true,
        'reflectionclass::getproperty' => true,
        'reflectionclass::hasmethod' => true,
        'reflectionclass::hasproperty' => true,
        'reflectionclass::implementsinterface' => true,
        'reflectionclass::isabstract' => true,
        'reflectionclass::isinterface' => true,
        'reflectionclass::issubclassof' => true,
        'reflectionfunction::getclosurescopeclass' => true,
        'reflectionfunction::getfilename' => true,
        'reflectionmethod::getdeclaringclass' => true,
        'reflectionmethod::getname' => true,
        'reflectionmethod::getnumberofrequiredparameters' => true,
        'reflectionmethod::getparameters' => true,
        'reflectionmethod::isconstructor' => true,
        'reflectionmethod::ispublic' => true,
        'reflectionmethod::isstatic' => true,
        'reflectionobject::getfilename' => true,
        'reflectionparameter::allowsnull' => true,
        'reflectionparameter::getclass' => true,
        'reflectionparameter::getdefaultvalue' => true,
        'reflectionparameter::getname' => true,
        'reflectionparameter::gettype' => true,
        'reflectionparameter::isdefaultvalueavailable' => true,
        'reflectionparameter::isvariadic' => true,
        'reflectionproperty::ispublic' => true,
        'reflectionproperty::isstatic' => true,
        'resourcebundle::geterrorcode' => true,
        'round' => true,
        'rtrim' => true,
        'runtimeexception::getcode' => true,
        'runtimeexception::getmessage' => true,
        'seekableiterator::current' => true,
        'seekableiterator::key' => true,
        'seekableiterator::valid' => true,
        'serialize' => true,
        'session_regenerate_id' => true,
        'session_status' => true,
        'sha1' => true,
        'simplexmlelement::asxml' => true,
        'simplexmlelement::xpath' => true,
        'simplexml_import_dom' => true,
        'sin' => true,
        'sizeof' => true,
        'solrresponse::getresponse' => true,
        'solrutils::escapequerychars' => true,
        'splfileinfo::getpathname' => true,
        'spl_object_hash' => true,
        'spl_object_id' => true,
        'splobjectstorage::offsetexists' => true,
        'splobjectstorage::offsetget' => true,
        'sprintf' => true,
        'strcasecmp' => true,
        'strcmp' => true,
        'strcspn' => true,
        'stream_get_contents' => true,
        'stream_is_local' => true,
        'stripcslashes' => true,
        'stripos' => true,
        'strip_tags' => true,
        'stristr' => true,
        'strlen' => true,
        'strncmp' => true,
        'str_pad' => true,
        'strpbrk' => true,
        'strpos' => true,
        'strrchr' => true,
        'str_repeat' => true,
        'str_replace' => true,
        'strrpos' => true,
        'str_split' => true,
        'strspn' => true,
        'strstr' => true,
        'strtolower' => true,
        'strtotime' => true,
        'strtoupper' => true,
        'strtr' => true,
        'strval' => true,
        'substr_compare' => true,
        'substr_count' => true,
        'substr_replace' => true,
        'substr' => true,
        'sys_get_temp_dir' => true,
        'tempnam' => true,
        'throwable::getcode' => true,  // todo: figure out how to inherit that.
        'throwable::getfile' => true,
        'throwable::getline' => true,
        'throwable::getmessage' => true,
        'throwable::gettraceasstring' => true,
        'throwable::gettrace' => true,
        'time' => true,
        'trait_exists' => true,
        'trim' => true,
        'ucfirst' => true,
        'ucwords' => true,
        'umask' => true,
        'uniqid' => true,
        'unpack' => true,
        'unserialize' => true,
        'urldecode' => true,
        'urlencode' => true,
        'version_compare' => true,
        'vsprintf' => true,
        'wordwrap' => true,

        'class_exists' => false,  // Triggers class autoloader to load the class
        'file_get_contents' => false,  // can be used for urls
        'mkdir' => false,  // some code is optimistic
        'preg_match' => false,  // useful if known
        'print_r' => false,  // has mode to return string
        'rename' => false,  // some code is optimistic
        'session_id' => false,  // Triggers regeneration
        'strtok' => false,  // advances a cursor if called with 1 argument
        'var_export' => false,  // can also dump to stdout
    ];
}

/**
 * Information about the function and the locations where the function was called for one FQSEN
 */
class StatsForFQSEN {
    /** @var array<string,Context> the locations where the return value was unused */
    public $unused_locations = [];
    /** @var array<string,Context> the locations where the return value was used */
    public $used_locations = [];
    /** @var bool is this function fqsen internal to PHP */
    public $is_internal;

    public function __construct(FunctionInterface $function)
    {
        $this->is_internal = $function->isPHPInternal();
    }
}

/**
 * Checks for invocations of functions/methods where the return value should be used.
 * Also, gathers statistics on how often those functions/methods are used.
 */
class UseReturnValueVisitor extends PluginAwarePostAnalysisVisitor
{
    /** @var array<int,Node> set by plugin framework */
    protected $parent_node_list;

    /**
     * @param Node $node a node of type AST_CALL
     * @return void
     * @override
     */
    public function visitCall(Node $node) {
        $parent = end($this->parent_node_list);
        if (!$parent) {
            //fwrite(STDERR, "No parent in " . __METHOD__ . "\n");
            return;
        }
        $key = $this->context->getFile() . ':' . $this->context->getLineNumberStart();
        $used = $parent->kind !== ast\AST_STMT_LIST;
        //fwrite(STDERR, "Saw parent of type " . ast\get_kind_name($parent->kind)  . "\n");

        $expression = $node->children['expr'];
        try {
            $function_list_generator = (new ContextNode(
                $this->code_base,
                $this->context,
                $expression
            ))->getFunctionFromNode();

            foreach ($function_list_generator as $function) {
                if ($function instanceof Func && $function->isClosure()) {
                    continue;
                }
                $fqsen = $function->getFQSEN()->__toString();
                if (!UseReturnValuePlugin::$use_dynamic) {
                    $this->quickCheck($fqsen, $node->lineno);
                    continue;
                }
                $counter = UseReturnValuePlugin::$stats[$fqsen] ?? null;
                if (!$counter) {
                    UseReturnValuePlugin::$stats[$fqsen] = $counter = new StatsForFQSEN($function);
                }
                if ($used)  {
                    $counter->used_locations[$key] = $this->context;
                } else {
                    $counter->unused_locations[$key] = $this->context;
                }
            }
        } catch (CodeBaseException $_) {
        }
    }

    /**
     * @param Node $node a node of type AST_METHOD_CALL
     * @return void
     * @override
     */
    public function visitMethodCall(Node $node) {
        $parent = end($this->parent_node_list);
        if (!$parent) {
            //fwrite(STDERR, "No parent in " . __METHOD__ . "\n");
            return;
        }
        $key = $this->context->getFile() . ':' . $this->context->getLineNumberStart();
        $used = $parent->kind !== ast\AST_STMT_LIST;
        //fwrite(STDERR, "Saw parent of type " . ast\get_kind_name($parent->kind)  . "\n");

        $method_name = $node->children['method'];

        if (!\is_string($method_name)) {
            return;
        }
        try {
            $method = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getMethod($method_name, false);
        } catch (Exception $_) {
            return;
        }
        $fqsen = $method->getDefiningFQSEN()->__toString();
        if (!UseReturnValuePlugin::$use_dynamic) {
            $this->quickCheck($fqsen, $node->lineno);
            return;
        }
        $counter = UseReturnValuePlugin::$stats[$fqsen] ?? null;
        if (!$counter) {
            UseReturnValuePlugin::$stats[$fqsen] = $counter = new StatsForFQSEN($method);
        }
        if ($used)  {
            $counter->used_locations[$key] = $this->context;
        } else {
            $counter->unused_locations[$key] = $this->context;
        }
    }

    /**
     * @param Node $node a node of type AST_METHOD_CALL
     * @return void
     * @override
     */
    public function visitStaticCall(Node $node) {
        $parent = end($this->parent_node_list);
        if (!$parent) {
            //fwrite(STDERR, "No parent in " . __METHOD__ . "\n");
            return;
        }
        $key = $this->context->getFile() . ':' . $this->context->getLineNumberStart();
        $used = $parent->kind !== ast\AST_STMT_LIST;
        //fwrite(STDERR, "Saw parent of type " . ast\get_kind_name($parent->kind)  . "\n");

        $method_name = $node->children['method'];

        if (!\is_string($method_name)) {
            return;
        }
        try {
            $method = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getMethod($method_name, true, true);
        } catch (Exception $_) {
            return;
        }
        $fqsen = $method->getDefiningFQSEN()->__toString();
        if (!UseReturnValuePlugin::$use_dynamic) {
            $this->quickCheck($fqsen, $node->lineno);
            return;
        }
        $counter = UseReturnValuePlugin::$stats[$fqsen] ?? null;
        if (!$counter) {
            UseReturnValuePlugin::$stats[$fqsen] = $counter = new StatsForFQSEN($method);
        }
        if ($used)  {
            $counter->used_locations[$key] = $this->context;
        } else {
            $counter->unused_locations[$key] = $this->context;
        }
    }

    private function quickCheck(string $fqsen, int $lineno)
    {
        if ((end($this->parent_node_list)->kind ?? null) !== ast\AST_STMT_LIST) {
            return;
        }
        $fqsen_key = strtolower(ltrim($fqsen, "\\"));
        if (UseReturnValuePlugin::HARDCODED_FQSENS[$fqsen_key] ?? false) {
            $this->emitPluginIssue(
                $this->code_base,
                clone($this->context)->withLineNumberStart($lineno),
                UseReturnValuePlugin::UseReturnValueInternalKnown,
                'Expected to use the return value of the internal function/method {FUNCTION}',
                [$fqsen]
            );
        }
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new UseReturnValuePlugin();
