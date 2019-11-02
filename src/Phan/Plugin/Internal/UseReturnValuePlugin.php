<?php declare(strict_types=1);

namespace Phan\Plugin\Internal;

use Phan\CLI;
use Phan\CodeBase;
use Phan\Config;
use Phan\Plugin\Internal\UseReturnValuePlugin\PureMethodInferrer;
use Phan\Plugin\Internal\UseReturnValuePlugin\StatsForFQSEN;
use Phan\Plugin\Internal\UseReturnValuePlugin\UseReturnValueVisitor;
use Phan\PluginV3;
use Phan\PluginV3\BeforeAnalyzePhaseCapability;
use Phan\PluginV3\FinalizeProcessCapability;
use Phan\PluginV3\PostAnalyzeNodeCapability;

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
 *
 * - ['plugin_config']['use_return_value_dynamic_checks'] (or PHAN_USE_RETURN_VALUE_DYNAMIC_CHECKS=1 as an environment variable)
 *
 *   Warn about unused return values when the return value is used in 98%+ of the overall statements in the project
 *
 *   Note that this currently breaks the hardcoded checks of whether return values should be used.
 *
 * - ['plugin_config']['use_return_value_warn_threshold_percentage'] (or PHAN_USE_RETURN_VALUE_WARN_THRESHOLD_PERCENTAGE=1 as an environment variable)
 *
 *   For dynamic checks, use this value instead of the default of 98 (should be between 0.01 and 100)
 *
 * - ['plugin_config']['infer_pure_methods'] to automatically infer which methods are pure.
 *   This is a best effort attempt, and it is not done for methods that override or are overridden by other methods.
 *
 *   This setting is ignored in the language server or daemon mode,
 *   due to being extremely slow and memory intensive.
 */
class UseReturnValuePlugin extends PluginV3 implements PostAnalyzeNodeCapability, FinalizeProcessCapability, BeforeAnalyzePhaseCapability
{
    // phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
    // this is deliberate for issue names
    const UseReturnValue = 'PhanPluginUseReturnValue';
    const UseReturnValueKnown = 'PhanPluginUseReturnValueKnown';
    const UseReturnValueInternal = 'PhanPluginUseReturnValueInternal';
    const UseReturnValueInternalKnown = 'PhanPluginUseReturnValueInternalKnown';
    const UseReturnValueNoopVoid = 'PhanPluginUseReturnValueNoopVoid';
    // phpcs:enable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase

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
        self::$debug = Config::getValue('plugin_config')['use_return_value_verbose'] ?? (bool)\getenv('PHAN_USE_RETURN_VALUE_DEBUG');
        self::$use_dynamic = Config::getValue('plugin_config')['use_return_value_dynamic_checks'] ??
        (bool)\getenv('PHAN_USE_RETURN_VALUE_DYNAMIC_CHECKS');
        return UseReturnValueVisitor::class;
    }

    /** @override */
    public function beforeAnalyzePhase(CodeBase $code_base) : void
    {
        if (!(Config::getValue('plugin_config')['infer_pure_methods'] ?? false)) {
            return;
        }
        if (CLI::isDaemonOrLanguageServer()) {
            // This is horribly slow and causes out of memory errors in language server mode
            return;
        }
        PureMethodInferrer::identifyPureMethods($code_base);
    }

    /**
     * @override
     */
    public function finalizeProcess(CodeBase $code_base) : void
    {
        if (!self::$use_dynamic) {
            return;
        }
        $threshold_percentage = Config::getValue('plugin_config')['use_return_value_warn_threshold_percentage'] ??
        (\getenv('PHAN_USE_RETURN_VALUE_WARN_THRESHOLD_PERCENTAGE') ?: self::DEFAULT_THRESHOLD_PERCENTAGE);

        foreach (self::$stats as $fqsen => $counter) {
            $fqsen_key = \ltrim(\strtolower($fqsen), "\\");
            $used_count = \count($counter->used_locations);
            $unused_count = \count($counter->unused_locations);
            $total_count = $used_count + $unused_count;

            $known_must_use_return_value = self::HARDCODED_FQSENS[$fqsen_key] ?? null;
            $used_percentage = $used_count / $total_count * 100;
            if ($total_count >= 5) {
                if (self::$debug) {
                    \fprintf(\STDERR, "%09.4f %% used: (%4d uses): %s (%s)\n", $used_percentage, $total_count, $fqsen, $counter->is_internal ? 'internal' : 'user-defined');
                }
            }

            if ($known_must_use_return_value === false) {
                continue;
            }

            if ($unused_count > 0 && $used_percentage >= $threshold_percentage) {
                $percentage_string = \number_format($used_percentage, 2);
                foreach ($counter->unused_locations as $key => $context) {
                    if (!\preg_match('/:(\d+)$/', $key, $matches)) {
                        \fprintf(\STDERR, "Failed to extract line number from %s\n", $key);
                        continue;
                    }
                    $line = (int)$matches[1];
                    $context = (clone($context))->withLineNumberStart($line);
                    if ($known_must_use_return_value) {
                        self::emitIssue(
                            $code_base,
                            $context,
                            self::UseReturnValueInternalKnown,
                            'Expected to use the return value of the internal function/method {FUNCTION}',
                            [$fqsen]
                        );
                    } elseif ($counter->is_internal) {
                        self::emitIssue(
                            $code_base,
                            $context,
                            self::UseReturnValueInternal,
                            'Expected to use the return value of the internal function/method {FUNCTION} - {SCALAR}%% of calls use it in the rest of the codebase',
                            [$fqsen, $percentage_string]
                        );
                    } else {
                        self::emitIssue(
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
    '_' => true,
    'abs' => true,
    'acosh' => true,
    'acos' => true,
    'addcslashes' => true,
    'addslashes' => true,
    'apcu_fetch' => true,
    'arrayaccess::offsetexists' => true,
    'arrayaccess::offsetget' => true,
    'array_change_key_case' => true,
    'array_chunk' => true,
    'array_column' => true,
    'array_combine' => true,
    'array_count_values' => true,
    'array_diff_assoc' => true,
    'array_diff_key' => true,
    'array_diff' => true,
    'array_fill_keys' => true,
    'array_fill' => true,
    'array_filter' => true,
    'array_flip' => true,
    'array_intersect_assoc' => true,
    'array_intersect_key' => true,
    'array_intersect' => true,
    'arrayiterator::current' => true,
    'arrayiterator::key' => true,
    'arrayiterator::valid' => true,
    'array_key_exists' => true,
    'array_key_first' => true,
    'array_key_last' => true,
    'array_keys' => true,
    'array_map' => true,
    'array_merge_recursive' => true,
    'array_merge' => true,
    'array_pad' => true,
    'array_product' => true,
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
    'asinh' => true,
    'asin' => true,
    'atan2' => true,
    'atanh' => true,
    'atan' => true,
    'base64_decode' => true,
    'base64_encode' => true,
    'base_convert' => true,
    'basename' => true,
    'bcadd' => true,
    'bccomp' => true,
    'bcdiv' => true,
    'bcmod' => true,
    'bcmul' => true,
    'bcpowmod' => true,
    'bcpow' => true,
    'bcscale' => true,
    'bcsqrt' => true,
    'bcsub' => true,
    'bin2hex' => true,
    'bindec' => true,
    'boolval' => true,
    'bzcompress' => true,
    'bzdecompress' => true,
    'ceil' => true,
    'checkdate' => true,
    'checkdnsrr' => true,
    'chop' => true,
    'chr' => true,
    'chunk_split' => true,
    'class_implements' => true,
    'class_parents' => true,
    'closure::bindto' => true,
    'closure::bind' => true,
    'closure::fromcallable' => true,
    'compact' => true,
    'constant' => true,
    'convert_cyr_string' => true,
    'convert_uudecode' => true,
    'convert_uuencode' => true,
    'cosh' => true,
    'cos' => true,
    'countable::count' => true,
    'count_chars' => true,
    'count' => true,
    'crc32' => true,
    'ctype_alnum' => true,
    'ctype_alpha' => true,
    'ctype_digit' => true,
    'ctype_lower' => true,
    'ctype_space' => true,
    'ctype_upper' => true,
    'ctype_xdigit' => true,
    'curl_errno' => true,
    'curl_error' => true,
    'curl_exec' => true,
    'curl_getinfo' => true,
    'curl_init' => true,
    'curl_version' => true,
    'current' => true,
    'date_create' => true,
    'date_default_timezone_get' => true,
    'dateinterval::format' => true,
    'datetime::createfromformat' => true,
    'datetime::createfromimmutable' => true,
    'datetime::diff' => true,
    'datetime::format' => true,
    'datetime::gettimestamp' => true,
    'datetime::gettimezone' => true,
    'datetimeimmutable::createfromformat' => true,
    'datetimeimmutable::diff' => true,
    'datetimeimmutable::format' => true,
    'datetimeimmutable::gettimestamp' => true,
    'datetimeimmutable::gettimezone' => true,
    'datetimeimmutable::settimezone' => true,
    'datetimeinterface::format' => true,
    'datetimeinterface::gettimestamp' => true,
    'datetimeinterface::gettimezone' => true,
    'datetimezone::getname' => true,
    'date' => true,
    'debug_backtrace' => true,
    'decbin' => true,
    'dechex' => true,
    'decoct' => true,
    'defined' => true,
    'deg2rad' => true,
    'dirname' => true,
    'domdocument::createcdatasection' => true,
    'domdocument::createcomment' => true,
    'domdocument::createelementns' => true,
    'domdocument::createelement' => true,
    'domdocument::createtextnode' => true,
    'domdocument::getelementsbytagnamens' => true,
    'domdocument::getelementsbytagname' => true,
    'domdocument::importnode' => true,
    'domdocument::savexml' => true,
    'domelement::getattribute' => true,
    'domelement::getelementsbytagnamens' => true,
    'domelement::hasattribute' => true,
    'domelement::haschildnodes' => true,
    'domelement::issamenode' => true,
    'domnodelist::item' => true,
    'domxpath::query' => true,
    'doubleval' => true,
    'each' => true,
    'error::getcode' => true,
    'error::getfile' => true,
    'error::getline' => true,
    'error::getmessage' => true,
    'error::getprevious' => true,
    'error::gettraceasstring' => true,
    'error::gettrace' => true,
    'error::__tostring' => true,
    'error_get_last' => true,
    'escapeshellarg' => true,
    'exception::getcode' => true,
    'exception::getfile' => true,
    'exception::getline' => true,
    'exception::getmessage' => true,
    'exception::getprevious' => true,
    'exception::gettraceasstring' => true,
    'exception::gettrace' => true,
    'exception::__tostring' => true,
    'explode' => true,
    'expm1' => true,
    'exp' => true,
    'extension_loaded' => true,
    'feof' => true,
    'ffi::addr' => true,
    'ffi::alignof' => true,
    'ffi::arraytype' => true,
    'ffi::cast' => true,
    'ffi::cdef' => true,
    'ffi::isnull' => true,
    'ffi::load' => true,
    'ffi::memcmp' => true,
    'ffi::new' => true,
    'ffi::scope' => true,
    'ffi::sizeof' => true,
    'ffi::string' => true,
    'ffi::type' => true,
    'ffi::typeof' => true,
    'fgetcsv' => true,
    'fgets' => true,
    'file_exists' => true,
    'filemtime' => true,
    'fileperms' => true,
    'filesize' => true,
    'file' => true,
    'filter_input_array' => true,
    'filter_input' => true,
    'filteriterator::current' => true,
    'filteriterator::getinneriterator' => true,
    'filter_var' => true,
    'floatval' => true,
    'floor' => true,
    'fmod' => true,
    'fopen' => self::MUST_USE_WITH_SIDE_EFFECTS,
    'fread' => self::MUST_USE_WITH_SIDE_EFFECTS,
    'fsockopen' => self::MUST_USE_WITH_SIDE_EFFECTS,
    'fstat' => true,
    'ftell' => true,
    'ftp_chdir' => self::MUST_USE_WITH_SIDE_EFFECTS,
    'func_get_args' => true,
    'func_get_arg' => true,
    'func_num_args' => true,
    'function_exists' => true,
    'gc_status' => true,
    'get_called_class' => true,
    'get_cfg_var' => true,
    'get_class_methods' => true,
    'get_class' => true,
    'getcwd' => true,
    'getdate' => true,
    'get_declared_classes' => true,
    'get_declared_interfaces' => true,
    'get_declared_traits' => true,
    'get_defined_constants' => true,
    'get_defined_functions' => true,
    'get_defined_vars' => true,
    'getenv' => true,
    'gethostname' => true,
    'getimagesize' => true,
    'get_include_path' => true,
    'get_magic_quotes_gpc' => true,
    'get_magic_quotes_gpc_runtime' => true,
    'getmypid' => true,
    'get_object_vars' => true,
    'get_parent_class' => true,
    'getrandmax' => true,
    'get_resource_type' => true,
    'gettext' => true,
    'gettype' => true,
    'glob' => self::MUST_USE_WITH_SIDE_EFFECTS,
    'gmdate' => true,
    'gmmktime' => true,
    'gzcompress' => true,
    'gzdecode' => true,
    'gzdeflate' => true,
    'gzencode' => true,
    'gzinflate' => true,
    'gzopen' => self::MUST_USE_WITH_SIDE_EFFECTS,
    'gzuncompress' => true,
    'hash_algos' => true,
    'hash_equals' => true,
    'hash_file' => self::MUST_USE_WITH_SIDE_EFFECTS,
    'hash_final' => true,
    'hash_hmac' => true,
    'hash_init' => true,
    'hash_pbkdf2' => true,
    'hash' => true,
    'headers_sent' => true,  // TODO: handle output references
    'hex2bin' => true,
    'hexdec' => true,
    'hrtime' => true,
    'htmlentities' => true,
    'html_entity_decode' => true,
    'htmlspecialchars_decode' => true,
    'htmlspecialchars' => true,
    'http_build_query' => true,
    'hypot' => true,
    'iconv_strlen' => true,
    'iconv' => true,
    'imagecreatetruecolor' => self::MUST_USE_WITH_SIDE_EFFECTS,
    'imagetypes' => true,
    'implode' => true,
    'in_array' => true,
    'inet_ntop' => true,
    'inet_pton' => true,
    'ini_get' => true,
    'intdiv' => true,
    'intlchar::charage' => true,
    'intlchar::chardigitvalue' => true,
    'intlchar::chardirection' => true,
    'intlchar::charfromname' => true,
    'intlchar::charmirror' => true,
    'intlchar::charname' => true,
    'intlchar::chartype' => true,
    'intlchar::chr' => true,
    'intlchar::digit' => true,
    'intlchar::enumcharnames' => true,
    'intlchar::enumchartypes' => true,
    'intlchar::foldcase' => true,
    'intlchar::fordigit' => true,
    'intlchar::getbidipairedbracket' => true,
    'intlchar::getblockcode' => true,
    'intlchar::getcombiningclass' => true,
    'intlchar::getfc_nfkc_closure' => true,
    'intlchar::getintpropertymaxvalue' => true,
    'intlchar::getintpropertyminvalue' => true,
    'intlchar::getintpropertyvalue' => true,
    'intlchar::getnumericvalue' => true,
    'intlchar::getpropertyenum' => true,
    'intlchar::getpropertyname' => true,
    'intlchar::getpropertyvalueenum' => true,
    'intlchar::getpropertyvaluename' => true,
    'intlchar::getunicodeversion' => true,
    'intlchar::hasbinaryproperty' => true,
    'intlchar::isalnum' => true,
    'intlchar::isalpha' => true,
    'intlchar::isbase' => true,
    'intlchar::isblank' => true,
    'intlchar::iscntrl' => true,
    'intlchar::isdefined' => true,
    'intlchar::isdigit' => true,
    'intlchar::isgraph' => true,
    'intlchar::isidignorable' => true,
    'intlchar::isidpart' => true,
    'intlchar::isidstart' => true,
    'intlchar::isisocontrol' => true,
    'intlchar::isjavaidpart' => true,
    'intlchar::isjavaidstart' => true,
    'intlchar::isjavaspacechar' => true,
    'intlchar::islower' => true,
    'intlchar::ismirrored' => true,
    'intlchar::isprint' => true,
    'intlchar::ispunct' => true,
    'intlchar::isspace' => true,
    'intlchar::istitle' => true,
    'intlchar::isualphabetic' => true,
    'intlchar::isulowercase' => true,
    'intlchar::isupper' => true,
    'intlchar::isuuppercase' => true,
    'intlchar::isuwhitespace' => true,
    'intlchar::iswhitespace' => true,
    'intlchar::isxdigit' => true,
    'intlchar::ord' => true,
    'intlchar::tolower' => true,
    'intlchar::totitle' => true,
    'intlchar::toupper' => true,
    'intl_get_error_code' => true,
    'intl_get_error_message' => true,
    'intl_is_failure' => true,
    'intval' => true,
    'ip2long' => true,
    'is_array' => true,
    'is_a' => true,
    'is_bool' => true,
    'is_callable' => true,  // todo: handle w_callable_name=
    'is_countable' => true,
    'is_dir' => true,
    'is_double' => true,
    'is_executable' => true,
    'is_file' => true,
    'is_finite' => true,
    'is_float' => true,
    'is_infinite' => true,
    'is_integer' => true,
    'is_int' => true,
    'is_iterable' => true,
    'is_link' => true,
    'is_long' => true,
    'is_nan' => true,
    'is_null' => true,
    'is_numeric' => true,
    'is_object' => true,
    'is_readable' => true,
    'is_real' => true,
    'is_resource' => true,
    'is_scalar' => true,
    'is_string' => true,
    'is_subclass_of' => true,
    'is_writable' => true,
    'is_writeable' => true,
    'iteratoraggregate::getiterator' => true,
    'iterator_count' => true,
    'iterator::current' => true,
    'iteratoriterator::current' => true,
    'iterator_to_array' => true,
    'iterator::valid' => true,
    'join' => true,
    'json_decode' => true,
    'json_encode' => true,
    'json_last_error_msg' => true,
    'json_last_error' => true,
    'key_exists' => true,
    'key' => true,
    'lcfirst' => true,
    'levenshtein' => true,
    'libxml_get_errors' => true,
    'localeconv' => true,
    'locale::getdefault' => true,
    'log10' => true,
    'log1p' => true,
    'log' => true,
    'long2ip' => true,
    'ltrim' => true,
    'max' => true,
    'mb_convert_case' => true,
    'mb_convert_encoding' => true,
    'mb_detect_encoding' => true,
    'mb_strlen' => true,
    'mb_strpos' => true,
    'mb_strtolower' => true,
    'mb_strwidth' => true,
    'mb_substr' => true,
    'md5_file' => true,
    'md5' => true,
    'memcached::getoption' => true,
    'memcached::getresultcode' => true,
    'memcached::get' => true,
    'memory_get_peak_usage' => true,
    'memory_get_usage' => true,
    'metaphone' => true,
    'method_exists' => true,
    'microtime' => true,
    'min' => true,
    'mktime' => true,
    'mt_getrandmax' => true,
    'mt_rand' => true,
    'ngettext' => true,
    'nl2br' => true,
    'numberformatter::format' => true,
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
    'openssl_encrypt' => true,
    'openssl_error_string' => true,
    'openssl_random_pseudo_bytes' => true,
    'ord' => true,
    'pack' => true,
    'parse_ini_file' => true,
    'parse_url' => true,
    'pathinfo' => true,
    'pdo::getattribute' => true,
    'pdo::prepare' => true,
    'pdo::quote' => true,
    'pdostatement::execute' => true,
    'pdostatement::fetchall' => true,
    'pdostatement::fetchcolumn' => true,
    'pdostatement::fetch' => true,
    'pdostatement::rowcount' => true,
    'php_sapi_name' => true,
    'php_uname' => true,
    'phpversion' => true,
    'pi' => true,
    'popen' => true,
    'posix_isatty' => true,
    'pow' => true,
    'preg_filter' => true,
    'preg_grep' => true,
    'preg_last_error' => true,
    'preg_quote' => true,
    'preg_replace_callback' => true,  // TODO: Handle w_count for preg_replace*, preg_filter
    'preg_replace_callback_array' => true,
    'preg_replace' => true,
    'preg_split' => true,
    'proc_open' => self::MUST_USE_WITH_SIDE_EFFECTS,
    'property_exists' => true,
    'quoted_printable_decode' => true,
    'quoted_printable_encode' => true,
    'quotemeta' => true,
    'rad2deg' => true,
    'random_bytes' => true,
    'random_int' => true,
    'rand' => true,
    'range' => true,
    'rawurldecode' => true,
    'rawurlencode' => true,
    'readdir' => true,
    'readlink' => true,
    'realpath' => true,
    'redis::getoption' => true,
    'reflectionclass::getconstructor' => true,
    'reflectionclass::getdoccomment' => true,
    'reflectionclass::getfilename' => true,
    'reflectionclass::getinterfaces' => true,
    'reflectionclass::getmethods' => true,
    'reflectionclass::getmethod' => true,
    'reflectionclass::getnamespacename' => true,
    'reflectionclass::getname' => true,
    'reflectionclass::getparentclass' => true,
    'reflectionclass::getproperties' => true,
    'reflectionclass::getproperty' => true,
    'reflectionclass::getshortname' => true,
    'reflectionclass::gettraits' => true,
    'reflectionclass::hasmethod' => true,
    'reflectionclass::hasproperty' => true,
    'reflectionclass::implementsinterface' => true,
    'reflectionclass::isabstract' => true,
    'reflectionclass::isfinal' => true,
    'reflectionclass::isinstantiable' => true,
    'reflectionclass::isinterface' => true,
    'reflectionclass::isinternal' => true,
    'reflectionclass::issubclassof' => true,
    'reflectionclass::istrait' => true,
    'reflectionclass::isuserdefined' => true,
    'reflectionclass::newinstanceargs' => self::MUST_USE_WITH_SIDE_EFFECTS,
    'reflectionclass::newinstance' => true,
    'reflectionfunction::getclosurescopeclass' => true,
    'reflectionfunction::getfilename' => true,
    'reflectionfunction::getparameters' => true,
    'reflectionmethod::getdeclaringclass' => true,
    'reflectionmethod::getdoccomment' => true,
    'reflectionmethod::getfilename' => true,
    'reflectionmethod::getname' => true,
    'reflectionmethod::getnumberofparameters' => true,
    'reflectionmethod::getnumberofrequiredparameters' => true,
    'reflectionmethod::getparameters' => true,
    'reflectionmethod::getreturntype' => true,
    'reflectionmethod::getstartline' => true,
    'reflectionmethod::hasreturntype' => true,
    'reflectionmethod::isabstract' => true,
    'reflectionmethod::isconstructor' => true,
    'reflectionmethod::isfinal' => true,
    'reflectionmethod::ispublic' => true,
    'reflectionmethod::isstatic' => true,
    'reflectionmethod::returnsreference' => true,
    'reflectionnamedtype::getname' => true,
    'reflectionobject::getfilename' => true,
    'reflectionobject::getmethod' => true,
    'reflectionobject::getproperties' => true,
    'reflectionobject::getproperty' => true,
    'reflectionobject::hasmethod' => true,
    'reflectionparameter::allowsnull' => true,
    'reflectionparameter::getclass' => true,
    'reflectionparameter::getdefaultvalue' => true,
    'reflectionparameter::getname' => true,
    'reflectionparameter::gettype' => true,
    'reflectionparameter::hastype' => true,
    'reflectionparameter::isarray' => true,
    'reflectionparameter::isdefaultvalueavailable' => true,
    'reflectionparameter::isoptional' => true,
    'reflectionparameter::ispassedbyreference' => true,
    'reflectionparameter::isvariadic' => true,
    'reflectionproperty::getdeclaringclass' => true,
    'reflectionproperty::getname' => true,
    'reflectionproperty::getvalue' => true,
    'reflectionproperty::ispublic' => true,
    'reflectionproperty::isstatic' => true,
    'reflectiontype::__tostring' => true,
    'resourcebundle::geterrorcode' => true,
    'round' => true,
    'rtrim' => true,
    'scandir' => self::MUST_USE_WITH_SIDE_EFFECTS,
    'seekableiterator::current' => true,
    'seekableiterator::key' => true,
    'seekableiterator::valid' => true,
    'serialize' => true,
    'session_regenerate_id' => true,
    'session_status' => true,
    'sha1' => true,
    'similar_text' => true,
    'simplexmlelement::asxml' => true,
    'simplexmlelement::attributes' => true,
    'simplexmlelement::children' => true,
    'simplexmlelement::getnamespaces' => true,
    'simplexmlelement::xpath' => true,
    'simplexml_import_dom' => true,
    'simplexml_load_file' => true,
    'simplexml_load_string' => true,
    'sinh' => true,
    'sin' => true,
    'sizeof' => true,
    'socket_last_error' => true,
    'socket_strerror' => true,
    'solrresponse::getresponse' => true,
    'solrutils::escapequerychars' => true,
    'soundex' => true,
    'spl_autoload_functions' => true,
    'splfileinfo::getbasename' => true,
    'splfileinfo::getfilename' => true,
    'splfileinfo::getpathname' => true,
    'splfileinfo::getrealpath' => true,
    'splfileinfo::getsize' => true,
    'splfixedarray::count' => true,
    'splfixedarray::getsize' => true,
    'splfixedarray::offsetexists' => true,
    'spl_object_hash' => true,
    'spl_object_id' => true,
    'splobjectstorage::contains' => true,
    'splobjectstorage::offsetexists' => true,
    'splobjectstorage::offsetget' => true,
    'splstack::top' => true,
    'sprintf' => true,
    'sqrt' => true,
    'stat' => true,
    'strcasecmp' => true,
    'strchr' => true,
    'strcmp' => true,
    'strcoll' => true,
    'strcspn' => true,
    'stream_context_create' => true,
    'stream_get_contents' => self::MUST_USE_WITH_SIDE_EFFECTS,
    'stream_get_meta_data' => true,
    'stream_isatty' => true,
    'stream_is_local' => true,
    'stream_resolve_include_path' => true,
    'stream_socket_client' => self::MUST_USE_WITH_SIDE_EFFECTS,
    'strftime' => true,
    'stripcslashes' => true,
    'stripos' => true,
    'stripslashes' => true,
    'strip_tags' => true,
    'str_ireplace' => true,
    'stristr' => true,
    'strlen' => true,
    'strnatcasecmp' => true,
    'strnatcmp' => true,
    'strncasecmp' => true,
    'strncmp' => true,
    'str_pad' => true,
    'strpbrk' => true,
    'strpos' => true,
    'strrchr' => true,
    'str_repeat' => true,
    'str_replace' => true,
    'strrev' => true,
    'strripos' => true,
    'str_rot13' => true,
    'strrpos' => true,
    'str_split' => true,
    'strspn' => true,
    'strstr' => true,
    'strtolower' => true,
    'strtotime' => true,
    'strtoupper' => true,
    'strtr' => true,
    'strval' => true,
    'str_word_count' => true,
    'substr_compare' => true,
    'substr_count' => true,
    'substr_replace' => true,
    'substr' => true,
    'sys_get_temp_dir' => true,
    'tanh' => true,
    'tan' => true,
    'tempnam' => true,
    'throwable::getcode' => true,  // todo: make these apply to subclasses automatically
    'throwable::getfile' => true,
    'throwable::getline' => true,
    'throwable::getmessage' => true,
    'throwable::getprevious' => true,
    'throwable::gettraceasstring' => true,
    'throwable::gettrace' => true,
    'throwable::__tostring' => true,
    'time' => true,
    'token_get_all' => true,
    'token_name' => true,
    'trim' => true,
    'ucfirst' => true,
    'ucwords' => true,
    'uniqid' => true,
    'unpack' => true,
    'unserialize' => self::MUST_USE_WITH_SIDE_EFFECTS,
    'urldecode' => true,
    'urlencode' => true,
    'utf8_decode' => true,
    'utf8_encode' => true,
    'version_compare' => true,
    'vsprintf' => true,
    'wordwrap' => true,
    'xml_get_error_code' => true,
    'xml_parser_create' => true,
    'xmlreader::getattribute' => true,
    'ziparchive::getfromname' => true,
    'ziparchive::locatename' => true,
    'zlib_decode' => true,
    'zlib_encode' => true,

    'call_user_func' => self::SPECIAL_CASE,  // dynamic
    'call_user_func_array' => self::SPECIAL_CASE,
    'chmod' => false,  // some code is optimistic
    'class_exists' => self::SPECIAL_CASE,  // triggers class autoloader to load the class
    'copy' => false,  // some code is optimistic
    'define' => false,
    'end' => false,  // move array cursor
    'file_get_contents' => false,  // can be used for urls
    'interface_exists' => self::SPECIAL_CASE,  // triggers class autoloader to load the interface
    'mkdir' => false,  // some code is optimistic
    'next' => false,  // move array cursor
    'preg_match' => self::SPECIAL_CASE,  // useful if known
    'preg_match_all' => self::SPECIAL_CASE,  // useful if known
    'prev' => false,  // move array cursor
    'print_r' => self::SPECIAL_CASE,  // returns a string if second arg is true
    'reflectionmethod::invokeargs' => false,  // may be a void
    'rename' => false,  // some code is optimistic
    'reset' => false,  // move array cursor
    'session_id' => false,  // Triggers regeneration
    'strtok' => false,  // advances a cursor if called with 1 argument - Any argument position can be ignored.
    'trait_exists' => self::SPECIAL_CASE,  // triggers class autoloader to load the trait
    'var_export' => self::SPECIAL_CASE,  // returns a string if second arg is true
    ];

    const SPECIAL_CASE = 'specialcase';
    const MUST_USE_WITH_SIDE_EFFECTS = 'sideeffects';
}
