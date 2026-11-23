<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal;

use ast\Node;
use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Config;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\ClassConstant;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\GlobalConstant;
use Phan\Language\Element\Method;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\UnionType;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeFunctionCallCapability;
use Phan\PluginV3\HandleLazyLoadInternalFunctionCapability;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

use function count;
use function is_int;
use function ltrim;
use function strtolower;

/**
 * Emits PHP version-specific deprecation diagnostics.
 *
 * This plugin tracks deprecations across different PHP versions and only
 * emits warnings when targeting the version where something was deprecated.
 */
final class PhpDeprecationPlugin extends PluginV3 implements
    AnalyzeFunctionCallCapability,
    HandleLazyLoadInternalFunctionCapability,
    PostAnalyzeNodeCapability
{
    /** @var array<string,array{min_php_version:int,reason:string}> */
    private const DEPRECATED_FUNCTIONS = [
        // PHP 8.1 deprecations
        'date_sunrise' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Use date_sun_info() instead.',
        ],
        'date_sunset' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Use date_sun_info() instead.',
        ],
        'strftime' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Use date() or IntlDateFormatter::format() instead.',
        ],
        'gmstrftime' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Use date() or IntlDateFormatter::format() instead.',
        ],
        'strptime' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Use date_parse_from_format() instead.',
        ],
        'mhash' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Use hash() instead.',
        ],
        'mhash_keygen_s2k' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Use hash_pbkdf2() instead.',
        ],
        'mhash_count' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Use count(hash_algos()) instead.',
        ],
        'mhash_get_block_size' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Use hash() functions instead.',
        ],
        'mhash_get_hash_name' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Use hash() functions instead.',
        ],
        // PHP 8.2 deprecations
        'utf8_encode' => [
            'min_php_version' => 80200,
            'reason' => 'Deprecated in PHP 8.2. Use mb_convert_encoding() instead.',
        ],
        'utf8_decode' => [
            'min_php_version' => 80200,
            'reason' => 'Deprecated in PHP 8.2. Use mb_convert_encoding() instead.',
        ],
        // PHP 8.4 deprecations
        'intlcal_set' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4. Use IntlCalendar::set(), IntlCalendar::setDate(), or IntlCalendar::setDateTime() instead.',
        ],
        'intlgregcal_create_instance' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4. Use IntlGregorianCalendar::__construct(), IntlGregorianCalendar::createFromDate(), or IntlGregorianCalendar::createFromDateTime() instead.',
        ],
        'lcg_value' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4. Use Random\\Randomizer::getFloat() instead.',
        ],
        'mysqli_ping' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4. Reconnect manually instead.',
        ],
        'mysqli_kill' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4. Use mysqli_query() with KILL command instead.',
        ],
        'mysqli_refresh' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4. Use specific FLUSH commands via mysqli_query() instead.',
        ],
        // PHP 8.5 deprecations
        'curl_close' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5. Has no effect since PHP 8.0; handles are freed automatically.',
        ],
        'curl_share_close' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5. Has no effect since PHP 8.0; handles are freed automatically.',
        ],
        'finfo_close' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5. finfo objects are freed automatically.',
        ],
        'imagedestroy' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5. Has no effect since PHP 8.0; images are freed automatically.',
        ],
        'ldap_connect_wallet' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5. Oracle LDAP support is broken since PHP 8.0.',
        ],
        'mysqli_execute' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5. Use mysqli_stmt_execute() instead.',
        ],
        'socket_set_timeout' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5. Use stream_set_timeout() instead.',
        ],
        'xml_parser_free' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5. Has no effect since PHP 8.0; parsers are freed automatically.',
        ],
        // PHP 8.6 deprecations
        'doubleval' => [
            'min_php_version' => 80600,
            'reason' => 'Deprecated in PHP 8.6. Use floatval() instead.',
        ],
        'is_double' => [
            'min_php_version' => 80600,
            'reason' => 'Deprecated in PHP 8.6. Use is_float() instead.',
        ],
        'is_integer' => [
            'min_php_version' => 80600,
            'reason' => 'Deprecated in PHP 8.6. Use is_int() instead.',
        ],
        'is_long' => [
            'min_php_version' => 80600,
            'reason' => 'Deprecated in PHP 8.6. Use is_int() instead.',
        ],
        'mb_ereg' => [
            'min_php_version' => 80600,
            'reason' => 'Deprecated in PHP 8.6. The mbregex functions are deprecated, because Oniguruma is no longer maintained. Use the preg_* functions instead.',
        ],
        'mb_ereg_match' => [
            'min_php_version' => 80600,
            'reason' => 'Deprecated in PHP 8.6. The mbregex functions are deprecated, because Oniguruma is no longer maintained. Use the preg_* functions instead.',
        ],
        'mb_ereg_replace' => [
            'min_php_version' => 80600,
            'reason' => 'Deprecated in PHP 8.6. The mbregex functions are deprecated, because Oniguruma is no longer maintained. Use the preg_* functions instead.',
        ],
        'mb_ereg_replace_callback' => [
            'min_php_version' => 80600,
            'reason' => 'Deprecated in PHP 8.6. The mbregex functions are deprecated, because Oniguruma is no longer maintained. Use the preg_* functions instead.',
        ],
        'mb_ereg_search' => [
            'min_php_version' => 80600,
            'reason' => 'Deprecated in PHP 8.6. The mbregex functions are deprecated, because Oniguruma is no longer maintained. Use the preg_* functions instead.',
        ],
        'mb_ereg_search_getpos' => [
            'min_php_version' => 80600,
            'reason' => 'Deprecated in PHP 8.6. The mbregex functions are deprecated, because Oniguruma is no longer maintained. Use the preg_* functions instead.',
        ],
        'mb_ereg_search_getregs' => [
            'min_php_version' => 80600,
            'reason' => 'Deprecated in PHP 8.6. The mbregex functions are deprecated, because Oniguruma is no longer maintained. Use the preg_* functions instead.',
        ],
        'mb_ereg_search_init' => [
            'min_php_version' => 80600,
            'reason' => 'Deprecated in PHP 8.6. The mbregex functions are deprecated, because Oniguruma is no longer maintained. Use the preg_* functions instead.',
        ],
        'mb_ereg_search_pos' => [
            'min_php_version' => 80600,
            'reason' => 'Deprecated in PHP 8.6. The mbregex functions are deprecated, because Oniguruma is no longer maintained. Use the preg_* functions instead.',
        ],
        'mb_ereg_search_regs' => [
            'min_php_version' => 80600,
            'reason' => 'Deprecated in PHP 8.6. The mbregex functions are deprecated, because Oniguruma is no longer maintained. Use the preg_* functions instead.',
        ],
        'mb_ereg_search_setpos' => [
            'min_php_version' => 80600,
            'reason' => 'Deprecated in PHP 8.6. The mbregex functions are deprecated, because Oniguruma is no longer maintained. Use the preg_* functions instead.',
        ],
        'mb_eregi' => [
            'min_php_version' => 80600,
            'reason' => 'Deprecated in PHP 8.6. The mbregex functions are deprecated, because Oniguruma is no longer maintained. Use the preg_* functions instead.',
        ],
        'mb_eregi_replace' => [
            'min_php_version' => 80600,
            'reason' => 'Deprecated in PHP 8.6. The mbregex functions are deprecated, because Oniguruma is no longer maintained. Use the preg_* functions instead.',
        ],
        'mb_regex_encoding' => [
            'min_php_version' => 80600,
            'reason' => 'Deprecated in PHP 8.6. The mbregex functions are deprecated, because Oniguruma is no longer maintained. Use the preg_* functions instead.',
        ],
        'mb_regex_set_options' => [
            'min_php_version' => 80600,
            'reason' => 'Deprecated in PHP 8.6. The mbregex functions are deprecated, because Oniguruma is no longer maintained. Use the preg_* functions instead.',
        ],
        'mb_split' => [
            'min_php_version' => 80600,
            'reason' => 'Deprecated in PHP 8.6. The mbregex functions are deprecated, because Oniguruma is no longer maintained. Use the preg_* functions instead.',
        ],
        'metaphone' => [
            'min_php_version' => 80600,
            'reason' => 'Deprecated in PHP 8.6. Use a userland phonetic matching library instead.',
        ],
        'spl_classes' => [
            'min_php_version' => 80600,
            'reason' => 'Deprecated in PHP 8.6. Use ReflectionExtension::getClassNames() instead.',
        ],
        'spl_object_hash' => [
            'min_php_version' => 80600,
            'reason' => 'Deprecated in PHP 8.6. Use spl_object_id() instead.',
        ],
        'strcoll' => [
            'min_php_version' => 80600,
            'reason' => 'Deprecated in PHP 8.6. Use Collator::compare() instead.',
        ],
        // The other deprecations of php-src (see internal/check_deprecations_against_php_src.php)
        'enchant_broker_free' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, as EnchantBroker objects are freed automatically.',
        ],
        'enchant_broker_free_dict' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, as EnchantDictionary objects are freed automatically.',
        ],
        'enchant_broker_get_dict_path' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0.',
        ],
        'enchant_broker_set_dict_path' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0.',
        ],
        'enchant_dict_add_to_personal' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use enchant_dict_add() instead.',
        ],
        'enchant_dict_is_in_session' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use enchant_dict_is_added() instead.',
        ],
        'libxml_disable_entity_loader' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, as external entity loading is disabled by default.',
        ],
        'openssl_free_key' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, as OpenSSLAsymmetricKey objects are freed automatically.',
        ],
        'openssl_pkey_free' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, as OpenSSLAsymmetricKey objects are freed automatically.',
        ],
        'openssl_x509_free' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, as OpenSSLCertificate objects are freed automatically.',
        ],
        'pg_clientencoding' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use pg_client_encoding() instead.',
        ],
        'pg_cmdtuples' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use pg_affected_rows() instead.',
        ],
        'pg_errormessage' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use pg_last_error() instead.',
        ],
        'pg_fieldisnull' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use pg_field_is_null() instead.',
        ],
        'pg_fieldname' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use pg_field_name() instead.',
        ],
        'pg_fieldnum' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use pg_field_num() instead.',
        ],
        'pg_fieldprtlen' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use pg_field_prtlen() instead.',
        ],
        'pg_fieldsize' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use pg_field_size() instead.',
        ],
        'pg_fieldtype' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use pg_field_type() instead.',
        ],
        'pg_freeresult' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use pg_free_result() instead.',
        ],
        'pg_getlastoid' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use pg_last_oid() instead.',
        ],
        'pg_loclose' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use pg_lo_close() instead.',
        ],
        'pg_locreate' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use pg_lo_create() instead.',
        ],
        'pg_loexport' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use pg_lo_export() instead.',
        ],
        'pg_loimport' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use pg_lo_import() instead.',
        ],
        'pg_loopen' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use pg_lo_open() instead.',
        ],
        'pg_loread' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use pg_lo_read() instead.',
        ],
        'pg_loreadall' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use pg_lo_read_all() instead.',
        ],
        'pg_lounlink' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use pg_lo_unlink() instead.',
        ],
        'pg_lowrite' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use pg_lo_write() instead.',
        ],
        'pg_numfields' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use pg_num_fields() instead.',
        ],
        'pg_numrows' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use pg_num_rows() instead.',
        ],
        'pg_result' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use pg_fetch_result() instead.',
        ],
        'pg_setclientencoding' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use pg_set_client_encoding() instead.',
        ],
        'shmop_close' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, as Shmop objects are freed automatically.',
        ],
        'zip_close' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use ZipArchive::close() instead.',
        ],
        'zip_entry_close' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0.',
        ],
        'zip_entry_compressedsize' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use ZipArchive::statIndex() instead.',
        ],
        'zip_entry_compressionmethod' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use ZipArchive::statIndex() instead.',
        ],
        'zip_entry_filesize' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use ZipArchive::statIndex() instead.',
        ],
        'zip_entry_name' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use ZipArchive::statIndex() instead.',
        ],
        'zip_entry_open' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0.',
        ],
        'zip_entry_read' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use ZipArchive::getFromIndex() instead.',
        ],
        'zip_open' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use ZipArchive::open() instead.',
        ],
        'zip_read' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, use ZipArchive::statIndex() instead.',
        ],
        'odbc_result_all' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1.',
        ],
        'assert_options' => [
            'min_php_version' => 80300,
            'reason' => 'Deprecated in PHP 8.3.',
        ],
        'xml_set_object' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4, provide a proper method callable to xml_set_*_handler() functions.',
        ],
        'mysqli_get_charset' => [
            'min_php_version' => 80600,
            'reason' => 'Deprecated in PHP 8.6, did you mean mysqli_character_set_name()?.',
        ],
        'mysqli_stmt_init' => [
            'min_php_version' => 80600,
            'reason' => 'Deprecated in PHP 8.6, use mysqli_prepare() instead.',
        ],
    ];

    /**
     * The methods of internal classes that are deprecated, by the lowercase name of the class and of the method.
     *
     * @var array<string,array<string,int>> maps the class name to a map from the method name to the php version id of the deprecation
     */
    private const DEPRECATED_METHODS = [
        // PHP 8.6 deprecations: the methods that ArrayIterator shares with ArrayObject, and the CSV methods of SplFileObject
        'arrayiterator' => [
            'asort' => 80600,
            'getflags' => 80600,
            'ksort' => 80600,
            'natcasesort' => 80600,
            'natsort' => 80600,
            'serialize' => 80600,
            'setflags' => 80600,
            'uasort' => 80600,
            'uksort' => 80600,
            'unserialize' => 80600,
        ],
        'splfileobject' => [
            'fgetcsv' => 80600,
            'fputcsv' => 80600,
            'getcsvcontrol' => 80600,
            'setcsvcontrol' => 80600,
        ],
        // The other deprecations of php-src (see internal/check_deprecations_against_php_src.php)
        'dateinterval' => [
            '__wakeup' => 80500,
        ],
        'dateperiod' => [
            '__wakeup' => 80500,
        ],
        'datetime' => [
            '__wakeup' => 80500,
        ],
        'datetimeimmutable' => [
            '__wakeup' => 80500,
        ],
        'datetimeinterface' => [
            '__wakeup' => 80500,
        ],
        'datetimezone' => [
            '__wakeup' => 80500,
        ],
        'mysqli' => [
            'get_charset' => 80600,
            'get_client_info' => 80100,
            'init' => 80100,
            'kill' => 80400,
            'ping' => 80400,
            'refresh' => 80400,
            'stmt_init' => 80600,
        ],
        'reflectionfunction' => [
            'isdisabled' => 80000,
        ],
        'reflectionmethod' => [
            'setaccessible' => 80500,
        ],
        'reflectionparameter' => [
            'getclass' => 80000,
            'isarray' => 80000,
            'iscallable' => 80000,
        ],
        'reflectionproperty' => [
            'setaccessible' => 80500,
        ],
        'splfileinfo' => [
            '_bad_state_ex' => 80200,
        ],
        'splfixedarray' => [
            '__wakeup' => 80400,
        ],
        'splobjectstorage' => [
            'attach' => 80500,
            'contains' => 80500,
            'detach' => 80500,
        ],
    ];

    /** @var array<string,array{min_php_version:int,reason:string}> */
    private const DEPRECATED_CLASS_CONSTANTS = [
        // PHP 8.3 deprecations
        '\\NumberFormatter::TYPE_CURRENCY' => [
            'min_php_version' => 80300,
            'reason' => 'Deprecated in PHP 8.3. Use NumberFormatter::formatCurrency()/parseCurrency().',
        ],
        // PHP 8.5 deprecations
        '\\DateTimeInterface::RFC7231' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5. Use DateTimeInterface::RFC7231_FORMAT instead.',
        ],
        // The other deprecations of php-src (see internal/check_deprecations_against_php_src.php)
        '\\ZipArchive::FL_RECOMPRESS' => [
            'min_php_version' => 80300,
            'reason' => 'Deprecated in PHP 8.3.',
        ],
        '\\PDO::DBLIB_ATTR_CONNECTION_TIMEOUT' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Dblib::ATTR_CONNECTION_TIMEOUT instead.',
        ],
        '\\PDO::DBLIB_ATTR_DATETIME_CONVERT' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Dblib::ATTR_DATETIME_CONVERT instead.',
        ],
        '\\PDO::DBLIB_ATTR_QUERY_TIMEOUT' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Dblib::ATTR_QUERY_TIMEOUT instead.',
        ],
        '\\PDO::DBLIB_ATTR_SKIP_EMPTY_ROWSETS' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Dblib::ATTR_SKIP_EMPTY_ROWSETS instead.',
        ],
        '\\PDO::DBLIB_ATTR_STRINGIFY_UNIQUEIDENTIFIER' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Dblib::ATTR_STRINGIFY_UNIQUEIDENTIFIER instead.',
        ],
        '\\PDO::DBLIB_ATTR_TDS_VERSION' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Dblib::ATTR_TDS_VERSION instead.',
        ],
        '\\PDO::DBLIB_ATTR_VERSION' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Dblib::ATTR_VERSION instead.',
        ],
        '\\PDO::FB_ATTR_DATE_FORMAT' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Firebird::ATTR_DATE_FORMAT instead.',
        ],
        '\\PDO::FB_ATTR_TIMESTAMP_FORMAT' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Firebird::ATTR_TIMESTAMP_FORMAT instead.',
        ],
        '\\PDO::FB_ATTR_TIME_FORMAT' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Firebird::ATTR_TIME_FORMAT instead.',
        ],
        '\\PDO::MYSQL_ATTR_COMPRESS' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Mysql::ATTR_COMPRESS instead.',
        ],
        '\\PDO::MYSQL_ATTR_DIRECT_QUERY' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Mysql::ATTR_DIRECT_QUERY instead.',
        ],
        '\\PDO::MYSQL_ATTR_FOUND_ROWS' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Mysql::ATTR_FOUND_ROWS instead.',
        ],
        '\\PDO::MYSQL_ATTR_IGNORE_SPACE' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Mysql::ATTR_IGNORE_SPACE instead.',
        ],
        '\\PDO::MYSQL_ATTR_INIT_COMMAND' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Mysql::ATTR_INIT_COMMAND instead.',
        ],
        '\\PDO::MYSQL_ATTR_LOCAL_INFILE' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Mysql::ATTR_LOCAL_INFILE instead.',
        ],
        '\\PDO::MYSQL_ATTR_LOCAL_INFILE_DIRECTORY' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Mysql::ATTR_LOCAL_INFILE_DIRECTORY instead.',
        ],
        '\\PDO::MYSQL_ATTR_MAX_BUFFER_SIZE' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Mysql::ATTR_MAX_BUFFER_SIZE instead.',
        ],
        '\\PDO::MYSQL_ATTR_MULTI_STATEMENTS' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Mysql::ATTR_MULTI_STATEMENTS instead.',
        ],
        '\\PDO::MYSQL_ATTR_READ_DEFAULT_FILE' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Mysql::ATTR_READ_DEFAULT_FILE instead.',
        ],
        '\\PDO::MYSQL_ATTR_READ_DEFAULT_GROUP' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Mysql::ATTR_READ_DEFAULT_GROUP instead.',
        ],
        '\\PDO::MYSQL_ATTR_SERVER_PUBLIC_KEY' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Mysql::ATTR_SERVER_PUBLIC_KEY instead.',
        ],
        '\\PDO::MYSQL_ATTR_SSL_CA' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Mysql::ATTR_SSL_CA instead.',
        ],
        '\\PDO::MYSQL_ATTR_SSL_CAPATH' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Mysql::ATTR_SSL_CAPATH instead.',
        ],
        '\\PDO::MYSQL_ATTR_SSL_CERT' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Mysql::ATTR_SSL_CERT instead.',
        ],
        '\\PDO::MYSQL_ATTR_SSL_CIPHER' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Mysql::ATTR_SSL_CIPHER instead.',
        ],
        '\\PDO::MYSQL_ATTR_SSL_KEY' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Mysql::ATTR_SSL_KEY instead.',
        ],
        '\\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Mysql::ATTR_SSL_VERIFY_SERVER_CERT instead.',
        ],
        '\\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Mysql::ATTR_USE_BUFFERED_QUERY instead.',
        ],
        '\\PDO::ODBC_ATTR_ASSUME_UTF8' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Odbc::ATTR_ASSUME_UTF8 instead.',
        ],
        '\\PDO::ODBC_ATTR_USE_CURSOR_LIBRARY' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Odbc::ATTR_USE_CURSOR_LIBRARY instead.',
        ],
        '\\PDO::ODBC_SQL_USE_DRIVER' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Odbc::SQL_USE_DRIVER instead.',
        ],
        '\\PDO::ODBC_SQL_USE_IF_NEEDED' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Odbc::SQL_USE_IF_NEEDED instead.',
        ],
        '\\PDO::ODBC_SQL_USE_ODBC' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Odbc::SQL_USE_ODBC instead.',
        ],
        '\\PDO::PGSQL_ATTR_DISABLE_PREPARES' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Pgsql::ATTR_DISABLE_PREPARES instead.',
        ],
        '\\PDO::PGSQL_TRANSACTION_ACTIVE' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5.',
        ],
        '\\PDO::PGSQL_TRANSACTION_IDLE' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5.',
        ],
        '\\PDO::PGSQL_TRANSACTION_INERROR' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5.',
        ],
        '\\PDO::PGSQL_TRANSACTION_INTRANS' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5.',
        ],
        '\\PDO::PGSQL_TRANSACTION_UNKNOWN' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5.',
        ],
        '\\PDO::SQLITE_ATTR_EXTENDED_RESULT_CODES' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Sqlite::ATTR_EXTENDED_RESULT_CODES instead.',
        ],
        '\\PDO::SQLITE_ATTR_OPEN_FLAGS' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Sqlite::ATTR_OPEN_FLAGS instead.',
        ],
        '\\PDO::SQLITE_ATTR_READONLY_STATEMENT' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Sqlite::ATTR_READONLY_STATEMENT instead.',
        ],
        '\\PDO::SQLITE_DETERMINISTIC' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Sqlite::DETERMINISTIC instead.',
        ],
        '\\PDO::SQLITE_OPEN_CREATE' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Sqlite::OPEN_CREATE instead.',
        ],
        '\\PDO::SQLITE_OPEN_READONLY' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Sqlite::OPEN_READONLY instead.',
        ],
        '\\PDO::SQLITE_OPEN_READWRITE' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, use Pdo\\Sqlite::OPEN_READWRITE instead.',
        ],
        '\\Pdo\\Pgsql::TRANSACTION_ACTIVE' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, as it has no effect.',
        ],
        '\\Pdo\\Pgsql::TRANSACTION_IDLE' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, as it has no effect.',
        ],
        '\\Pdo\\Pgsql::TRANSACTION_INERROR' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, as it has no effect.',
        ],
        '\\Pdo\\Pgsql::TRANSACTION_INTRANS' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, as it has no effect.',
        ],
        '\\Pdo\\Pgsql::TRANSACTION_UNKNOWN' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5, as it has no effect.',
        ],
    ];

    /** @var array<string,array{min_php_version:int,reason:string}> */
    private const DEPRECATED_GLOBAL_CONSTANTS = [
        // PHP 8.1 deprecations
        'FILTER_SANITIZE_STRING' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Use htmlspecialchars() or FILTER_SANITIZE_FULL_SPECIAL_CHARS instead.',
        ],
        'FILTER_FLAG_SCHEME_REQUIRED' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Has no effect; do not use.',
        ],
        'FILTER_FLAG_HOST_REQUIRED' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Has no effect; do not use.',
        ],
        'FILE_BINARY' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Has no effect; do not use.',
        ],
        'FILE_TEXT' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Has no effect; do not use.',
        ],
        // PHP 8.3 deprecations
        'MT_RAND_PHP' => [
            'min_php_version' => 80300,
            'reason' => 'Deprecated in PHP 8.3. Use Random\\Engine\\Mt19937 to reproduce legacy sequences.',
        ],
        // PHP 8.4 deprecations
        'CURLOPT_BINARYTRANSFER' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4. CURLOPT_BINARYTRANSFER has no effect since PHP 5.1.3.',
        ],
        'E_STRICT' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4. Error level is unused.',
        ],
        // PHP 8.5 deprecations
        'DATE_RFC7231' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5. Use DATE_RFC7231_FORMAT instead.',
        ],
        'GSLC_SSL_NO_AUTH' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5. Oracle LDAP support is broken since PHP 8.0.',
        ],
        'GSLC_SSL_ONEWAY_AUTH' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5. Oracle LDAP support is broken since PHP 8.0.',
        ],
        'GSLC_SSL_TWOWAY_AUTH' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5. Oracle LDAP support is broken since PHP 8.0.',
        ],
        // PHP 8.6 deprecations
        'MB_ONIGURUMA_VERSION' => [
            'min_php_version' => 80600,
            'reason' => 'Deprecated in PHP 8.6. Oniguruma is no longer maintained.',
        ],
        'SORT_LOCALE_STRING' => [
            'min_php_version' => 80600,
            'reason' => 'Deprecated in PHP 8.6. Use one of the Collator::*sort*() methods instead.',
        ],
        // The other deprecations of php-src (see internal/check_deprecations_against_php_src.php)
        'ENCHANT_ISPELL' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, as enchant_broker_get_dict_path() and enchant_broker_set_dict_path() are deprecated.',
        ],
        'ENCHANT_MYSPELL' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, as enchant_broker_get_dict_path() and enchant_broker_set_dict_path() are deprecated.',
        ],
        'PGSQL_LIBPQ_VERSION_STR' => [
            'min_php_version' => 80000,
            'reason' => 'Deprecated in PHP 8.0, as it is the same as PGSQL_LIBPQ_VERSION.',
        ],
        'FILTER_SANITIZE_STRIPPED' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1, use htmlspecialchars() instead.',
        ],
        'MYSQLI_DATA_TRUNCATED' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1, as it was unused.',
        ],
        'MYSQLI_NO_DATA' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1, as it was unused.',
        ],
        'MYSQLI_SERVER_PS_OUT_PARAMS' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1, as it was unused.',
        ],
        'MYSQLI_SERVER_QUERY_NO_GOOD_INDEX_USED' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1, as it was unused.',
        ],
        'MYSQLI_SERVER_QUERY_NO_INDEX_USED' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1, as it was unused.',
        ],
        'MYSQLI_SERVER_QUERY_WAS_SLOW' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1, as it was unused.',
        ],
        'MYSQLI_IS_MARIADB' => [
            'min_php_version' => 80200,
            'reason' => 'Deprecated in PHP 8.2, as it is always false.',
        ],
        'ASSERT_ACTIVE' => [
            'min_php_version' => 80300,
            'reason' => 'Deprecated in PHP 8.3, as assert_options() is deprecated.',
        ],
        'ASSERT_BAIL' => [
            'min_php_version' => 80300,
            'reason' => 'Deprecated in PHP 8.3, as assert_options() is deprecated.',
        ],
        'ASSERT_CALLBACK' => [
            'min_php_version' => 80300,
            'reason' => 'Deprecated in PHP 8.3, as assert_options() is deprecated.',
        ],
        'ASSERT_EXCEPTION' => [
            'min_php_version' => 80300,
            'reason' => 'Deprecated in PHP 8.3, as assert_options() is deprecated.',
        ],
        'ASSERT_WARNING' => [
            'min_php_version' => 80300,
            'reason' => 'Deprecated in PHP 8.3, as assert_options() is deprecated.',
        ],
        'U_MULTIPLE_DECIMAL_SEPERATORS' => [
            'min_php_version' => 80300,
            'reason' => 'Deprecated in PHP 8.3, use U_MULTIPLE_DECIMAL_SEPARATORS instead.',
        ],
        'DOM_PHP_ERR' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4, as it is no longer used.',
        ],
        'MYSQLI_REFRESH_BACKUP_LOG' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4, as mysqli_refresh() is deprecated.',
        ],
        'MYSQLI_REFRESH_GRANT' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4, as mysqli_refresh() is deprecated.',
        ],
        'MYSQLI_REFRESH_HOSTS' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4, as mysqli_refresh() is deprecated.',
        ],
        'MYSQLI_REFRESH_LOG' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4, as mysqli_refresh() is deprecated.',
        ],
        'MYSQLI_REFRESH_MASTER' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4, as mysqli_refresh() is deprecated.',
        ],
        'MYSQLI_REFRESH_REPLICA' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4, as mysqli_refresh() is deprecated.',
        ],
        'MYSQLI_REFRESH_SLAVE' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4, as mysqli_refresh() is deprecated.',
        ],
        'MYSQLI_REFRESH_STATUS' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4, as mysqli_refresh() is deprecated.',
        ],
        'MYSQLI_REFRESH_TABLES' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4, as mysqli_refresh() is deprecated.',
        ],
        'MYSQLI_REFRESH_THREADS' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4, as mysqli_refresh() is deprecated.',
        ],
        'MYSQLI_STORE_RESULT_COPY_DATA' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4, as the mysqli_store_result() parameter is unused since 8.1.',
        ],
        'SOAP_FUNCTIONS_ALL' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4, as enabling all functions is a possible security concern.',
        ],
        'SUNFUNCS_RET_DOUBLE' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4, as date_sunrise() and date_sunset() were deprecated in 8.1.',
        ],
        'SUNFUNCS_RET_STRING' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4, as date_sunrise() and date_sunset() were deprecated in 8.1.',
        ],
        'SUNFUNCS_RET_TIMESTAMP' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4, as date_sunrise() and date_sunset() were deprecated in 8.1.',
        ],
    ];

    /**
     * The properties of internal classes that are deprecated, by the lowercase name of the class.
     * (php-src only marks these with a doc comment; reading them emits a deprecation notice since PHP 8.4)
     *
     * @var array<string,array<string,int>> maps the class name to a map from the property name to the php version id of the deprecation
     */
    private const DEPRECATED_PROPERTIES = [
        'domdocument' => [
            'actualEncoding' => 80400,
            'config' => 80400,
        ],
        'domentity' => [
            'actualEncoding' => 80400,
            'encoding' => 80400,
            'version' => 80400,
        ],
    ];

    /**
     * Returns the tables of deprecated internal elements, for internal/check_deprecations_against_php_src.php.
     *
     * @return array{functions:array<string,array{min_php_version:int,reason:string}>,methods:array<string,array<string,int>>,class_constants:array<string,array{min_php_version:int,reason:string}>,global_constants:array<string,array{min_php_version:int,reason:string}>}
     * @internal
     */
    public static function getDeprecationTables(): array
    {
        return [
            'functions' => self::DEPRECATED_FUNCTIONS,
            'methods' => self::DEPRECATED_METHODS,
            'class_constants' => self::DEPRECATED_CLASS_CONSTANTS,
            'global_constants' => self::DEPRECATED_GLOBAL_CONSTANTS,
        ];
    }

    /**
     * Returns true when the configured target PHP version is at least the given version.
     */
    private static function isTargetingPhpVersionOrNewer(int $min_version): bool
    {
        return Config::get_closest_target_php_version_id() >= $min_version;
    }

    /**
     * Provides call-analyzer closures for version-specific deprecation checks.
     *
     * This handles special cases where specific argument patterns are deprecated,
     * rather than the entire function being deprecated:
     *
     * - mb_strimwidth(): Only deprecated when passing a negative $width (arg 3)
     * - ldap_connect(): Only deprecated when called with 2 arguments (host, port);
     *   single-argument calls with a URI are still valid
     *
     * @unused-param $code_base
     *
     * @return array<string,callable(CodeBase,Context,FunctionInterface,list<Node|int|float|string>,?Node):void>
     */
    public function getAnalyzeFunctionCallClosures(CodeBase $code_base): array
    {
        $closures = [];

        // PHP 8.3: mb_strimwidth() with negative $width
        // See: https://github.com/php/php-src/commit/af3c220abbff2415ccd79aeac1888edfc106ffa6
        // Only the negative width argument usage is deprecated, not the function itself.
        // Phan can detect this when the value is a literal or can be inferred via type analysis.
        if (self::isTargetingPhpVersionOrNewer(80300)) {
            $closures['\\mb_strimwidth'] =
                /** @param list<Node|int|float|string> $args */
                static function (CodeBase $code_base, Context $context, FunctionInterface $function, array $args, ?Node $call_node): void {
                    // mb_strimwidth(string $string, int $start, int $width, ...)
                    if (!isset($args[2])) {
                        return;
                    }
                    $width_node = $args[2];
                    $width_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $width_node);
                    $known_value = $width_type->asSingleScalarValueOrNull();
                    if (is_int($known_value) && $known_value < 0) {
                        $lineno = ($width_node instanceof Node && isset($width_node->lineno))
                            ? $width_node->lineno
                            : ($call_node->lineno ?? $context->getLineNumberStart());
                        Issue::maybeEmit(
                            $code_base,
                            $context,
                            Issue::DeprecatedFunctionArgument,
                            $lineno,
                            $function->getRepresentationForIssue(),
                            'Passing a negative $width is deprecated as of PHP 8.3'
                        );
                    }
                };

            // PHP 8.3: ldap_connect() with separate host and port arguments
            // See: https://github.com/php/php-src/commit/69a8b63ecf4fec1b35ef4da1ac9579321c45f97f
            // Only the two-argument signature ldap_connect($host, $port) is deprecated.
            // Single-argument calls with a URI like ldap_connect('ldap://host:port') are still valid.
            $closures['\\ldap_connect'] =
                /** @param list<Node|int|float|string> $args */
                static function (CodeBase $code_base, Context $context, FunctionInterface $function, array $args, ?Node $call_node): void {
                    if (count($args) === 2) {
                        $lineno = $call_node->lineno ?? $context->getLineNumberStart();
                        Issue::maybeEmit(
                            $code_base,
                            $context,
                            Issue::DeprecatedFunctionArgument,
                            $lineno,
                            $function->getRepresentationForIssue(),
                            'Calling with host and port parameters is deprecated as of PHP 8.3; pass a URI instead'
                        );
                    }
                };
        }

        foreach (self::getDeprecatedCallFormClosures() as $name => $closure) {
            $closures[$name] = $closure;
        }

        // The deprecated functions that Phan's stubs declare (e.g. spl_object_hash() in the SPL stub).
        // A function from a stub replaces the one from Reflection and is not passed to handleLazyLoadInternalFunction(),
        // so it is not marked as deprecated.
        foreach (self::DEPRECATED_FUNCTIONS as $function_name => $deprecation_info) {
            if (!self::isTargetingPhpVersionOrNewer($deprecation_info['min_php_version'])) {
                continue;
            }
            $closures["\\$function_name"] ??=
                /** @param list<Node|int|float|string> $unused_args */
                static function (CodeBase $code_base, Context $context, FunctionInterface $function, array $unused_args, ?Node $call_node): void {
                    if (!$function->isPHPInternal()) {
                        return;
                    }
                    if ($function instanceof Func) {
                        // The analyzed code declares a function with this name, e.g. a polyfill for an extension that is not enabled:
                        // that is the function being called, and it is not deprecated.
                        // (Phan knows the internal function from its signature map, and calls resolve to it)
                        $alternate_id = 0;
                        $fqsen = $function->getFQSEN()->getCanonicalFQSEN();
                        while ($code_base->hasFunctionWithFQSEN($fqsen)) {
                            if (!$code_base->getFunctionByFQSEN($fqsen)->isPHPInternal()) {
                                return;
                            }
                            $fqsen = $fqsen->withAlternateId(++$alternate_id);
                        }
                    }
                    if ($function->isDeprecated()) {
                        // Already reported as PhanDeprecatedFunctionInternal
                        return;
                    }
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        Issue::DeprecatedFunctionInternal,
                        $call_node->lineno ?? $context->getLineNumberStart(),
                        $function->getRepresentationForIssue(),
                        ''
                    );
                };
        }

        // The deprecated methods of internal classes. These classes may be declared by Phan's stubs (e.g. the SPL classes),
        // which replace the information from Reflection, so the methods are not marked as deprecated there.
        foreach (self::DEPRECATED_METHODS as $class_name => $methods) {
            foreach ($methods as $method_name => $min_php_version) {
                if (!self::isTargetingPhpVersionOrNewer($min_php_version)) {
                    continue;
                }
                $closure =
                    /** @param list<Node|int|float|string> $unused_args */
                    static function (CodeBase $code_base, Context $context, FunctionInterface $function, array $unused_args, ?Node $call_node) use ($class_name): void {
                        if (!$function->isPHPInternal() || $function->isDeprecated()) {
                            // Not a method of the internal class, or already reported as PhanDeprecatedFunctionInternal from Reflection
                            return;
                        }
                        if (!($function instanceof Method) || \strtolower(\ltrim((string)$function->getDefiningClassFQSEN(), '\\')) !== $class_name) {
                            // This is an override of the method in a subclass
                            return;
                        }
                        Issue::maybeEmit(
                            $code_base,
                            $context,
                            Issue::DeprecatedFunctionInternal,
                            $call_node->lineno ?? $context->getLineNumberStart(),
                            $function->getRepresentationForIssue(),
                            ''
                        );
                    };
                $closures["\\$class_name::$method_name"] = $closure;
                // An internal subclass inherits the method, e.g. SplTempFileObject::setCsvControl() from SplFileObject.
                foreach (self::getInternalSubclassNames($class_name) as $subclass_name) {
                    $closures["\\$subclass_name::$method_name"] ??= $closure;
                }
            }
        }

        return $closures;
    }

    /**
     * The ways of calling an internal function or method that are deprecated, while other calls of it are not:
     * a number of arguments, or an argument of a certain type or value.
     * Only the calls that are certain to be deprecated are reported.
     *
     * @return array<string,\Closure(CodeBase,Context,FunctionInterface,list<Node|int|float|string>,?Node):void>
     */
    private static function getDeprecatedCallFormClosures(): array
    {
        $closures = [];
        // The calls with a deprecated number of arguments:
        // [the function or method, the php version id of the deprecation, whether the number of arguments is deprecated, the description]
        $deprecated_argument_counts = [
            ['mb_check_encoding', 80100, static fn(int $count): bool => $count === 0, 'Calling mb_check_encoding() without arguments is deprecated as of PHP 8.1'],
            ['imagepolygon', 80100, static fn(int $count): bool => $count === 4, 'Passing $num_points is deprecated as of PHP 8.1; it is the number of elements of $points divided by 2'],
            ['imageopenpolygon', 80100, static fn(int $count): bool => $count === 4, 'Passing $num_points is deprecated as of PHP 8.1; it is the number of elements of $points divided by 2'],
            ['imagefilledpolygon', 80100, static fn(int $count): bool => $count === 4, 'Passing $num_points is deprecated as of PHP 8.1; it is the number of elements of $points divided by 2'],
            ['get_class', 80300, static fn(int $count): bool => $count === 0, 'Calling get_class() without arguments is deprecated as of PHP 8.3; use self::class or static::class instead'],
            ['get_parent_class', 80300, static fn(int $count): bool => $count === 0, 'Calling get_parent_class() without arguments is deprecated as of PHP 8.3; use parent::class instead'],
            ['ReflectionMethod::__construct', 80400, static fn(int $count): bool => $count === 1, 'Calling ReflectionMethod::__construct() with 1 argument is deprecated as of PHP 8.4; use ReflectionMethod::createFromMethodName() instead'],
            ['IntlGregorianCalendar::__construct', 80400, static fn(int $count): bool => $count > 2, 'Calling IntlGregorianCalendar::__construct() with more than 2 arguments is deprecated as of PHP 8.4; use IntlGregorianCalendar::createFromDate() or createFromDateTime() instead'],
            ['stream_context_set_option', 80400, static fn(int $count): bool => $count === 2, 'Calling stream_context_set_option() with 2 arguments is deprecated as of PHP 8.4; use stream_context_set_options() instead'],
            ['session_set_save_handler', 80400, static fn(int $count): bool => $count > 2, 'Providing individual callbacks is deprecated as of PHP 8.4; pass an object implementing SessionHandlerInterface instead'],
            ['openssl_pkey_derive', 80500, static fn(int $count): bool => $count > 2, 'Passing $key_length is deprecated as of PHP 8.5; it is either ignored or truncates the key'],
        ];
        foreach ($deprecated_argument_counts as [$name, $min_php_version, $is_deprecated_count, $details]) {
            if (!self::isTargetingPhpVersionOrNewer($min_php_version)) {
                continue;
            }
            $closures["\\$name"] =
                /** @param list<Node|int|float|string> $args */
                static function (CodeBase $code_base, Context $context, FunctionInterface $function, array $args, ?Node $call_node) use ($is_deprecated_count, $details): void {
                    foreach ($args as $arg) {
                        if ($arg instanceof Node && $arg->kind === \ast\AST_UNPACK) {
                            // The number of arguments is unknown
                            return;
                        }
                    }
                    if ($is_deprecated_count(count($args))) {
                        self::emitDeprecatedCallForm($code_base, $context, $function, $call_node, $details);
                    }
                };
        }

        // The functions for an array that are deprecated for an object:
        // [the function, the php version id of the deprecation, the index of the first argument that is checked, whether the arguments after it are checked, the description]
        $deprecated_object_arguments = [];
        foreach (['reset', 'end', 'key', 'current', 'next', 'prev'] as $name) {
            $deprecated_object_arguments[] = [$name, 80100, 0, false, "Calling $name() on an object is deprecated as of PHP 8.1; use get_object_vars() or ArrayIterator instead"];
        }
        foreach (['array_walk', 'array_walk_recursive', 'http_build_query'] as $name) {
            $deprecated_object_arguments[] = [$name, 80600, 0, false, "Passing an object to $name() is deprecated as of PHP 8.6; call get_object_vars() first instead"];
        }
        $deprecated_object_arguments[] = ['mb_convert_variables', 80600, 2, true, 'Passing an object to mb_convert_variables() is deprecated as of PHP 8.6; call get_object_vars() first instead'];
        foreach ($deprecated_object_arguments as [$name, $min_php_version, $first_index, $is_variadic, $details]) {
            if (!self::isTargetingPhpVersionOrNewer($min_php_version)) {
                continue;
            }
            $closures["\\$name"] =
                /** @param list<Node|int|float|string> $args */
                static function (CodeBase $code_base, Context $context, FunctionInterface $function, array $args, ?Node $call_node) use ($first_index, $is_variadic, $details): void {
                    foreach ($args as $i => $arg) {
                        if ($arg instanceof Node && ($arg->kind === \ast\AST_UNPACK || $arg->kind === \ast\AST_NAMED_ARG)) {
                            return;
                        }
                        if ($i < $first_index || ($i > $first_index && !$is_variadic)) {
                            continue;
                        }
                        if (self::isDefinitelyObject(UnionTypeVisitor::unionTypeFromNode($code_base, $context, $arg))) {
                            self::emitDeprecatedCallForm($code_base, $context, $function, $arg instanceof Node ? $arg : $call_node, $details);
                            return;
                        }
                    }
                };
        }

        if (self::isTargetingPhpVersionOrNewer(80400)) {
            // PHP 8.4: trigger_error() and its alias user_error() with E_USER_ERROR
            foreach (['trigger_error', 'user_error'] as $name) {
                $closures["\\$name"] =
                    /** @param list<Node|int|float|string> $args */
                    static function (CodeBase $code_base, Context $context, FunctionInterface $function, array $args, ?Node $call_node): void {
                        $level = self::getPositionalArgument($args, 1);
                        if ($level === null) {
                            return;
                        }
                        if (UnionTypeVisitor::unionTypeFromNode($code_base, $context, $level)->asSingleScalarValueOrNull() === \E_USER_ERROR) {
                            self::emitDeprecatedCallForm($code_base, $context, $function, $level instanceof Node ? $level : $call_node, 'Passing E_USER_ERROR is deprecated as of PHP 8.4; throw an exception or call exit() with a message instead');
                        }
                    };
            }
            // PHP 8.4: new DatePeriod(string $isostr, int $options = 0)
            $closures['\\DatePeriod::__construct'] =
                /** @param list<Node|int|float|string> $args */
                static function (CodeBase $code_base, Context $context, FunctionInterface $function, array $args, ?Node $call_node): void {
                    $start = self::getPositionalArgument($args, 0);
                    if ($start === null) {
                        return;
                    }
                    $type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $start);
                    if (!$type->isEmpty() && $type->isExclusivelyStringTypes()) {
                        self::emitDeprecatedCallForm($code_base, $context, $function, $call_node, 'Calling DatePeriod::__construct() with an ISO 8601 string is deprecated as of PHP 8.4; use DatePeriod::createFromISO8601String() instead');
                    }
                };
        }
        if (self::isTargetingPhpVersionOrNewer(80500)) {
            // PHP 8.5: array_key_exists(null, $array)
            $closures['\\array_key_exists'] =
                /** @param list<Node|int|float|string> $args */
                static function (CodeBase $code_base, Context $context, FunctionInterface $function, array $args, ?Node $unused_call_node): void {
                    $key = self::getPositionalArgument($args, 0);
                    if ($key instanceof Node && UnionTypeVisitor::unionTypeFromNode($code_base, $context, $key)->isNull()) {
                        self::emitDeprecatedCallForm($code_base, $context, $function, $key, 'Using null as the key is deprecated as of PHP 8.5; use an empty string instead');
                    }
                };
            // PHP 8.5: chr() with an integer that is not a byte
            $closures['\\chr'] =
                /** @param list<Node|int|float|string> $args */
                static function (CodeBase $code_base, Context $context, FunctionInterface $function, array $args, ?Node $call_node): void {
                    $codepoint = self::getPositionalArgument($args, 0);
                    if ($codepoint === null) {
                        return;
                    }
                    $value = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $codepoint)->asSingleScalarValueOrNull();
                    if (is_int($value) && ($value < 0 || $value > 255)) {
                        self::emitDeprecatedCallForm($code_base, $context, $function, $codepoint instanceof Node ? $codepoint : $call_node, 'Providing a value that is not between 0 and 255 is deprecated as of PHP 8.5');
                    }
                };
            // PHP 8.5: ord() with a string that is not one byte long
            $closures['\\ord'] =
                /** @param list<Node|int|float|string> $args */
                static function (CodeBase $code_base, Context $context, FunctionInterface $function, array $args, ?Node $call_node): void {
                    $character = self::getPositionalArgument($args, 0);
                    if ($character === null) {
                        return;
                    }
                    $value = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $character)->asSingleScalarValueOrNull();
                    if (\is_string($value) && \strlen($value) !== 1) {
                        self::emitDeprecatedCallForm($code_base, $context, $function, $character instanceof Node ? $character : $call_node, 'Providing a string that is not one byte long is deprecated as of PHP 8.5; use ord($string[0]) instead');
                    }
                };
            // PHP 8.5: spl_autoload_unregister('spl_autoload_call')
            $closures['\\spl_autoload_unregister'] =
                /** @param list<Node|int|float|string> $args */
                static function (CodeBase $code_base, Context $context, FunctionInterface $function, array $args, ?Node $call_node): void {
                    $callback = self::getPositionalArgument($args, 0);
                    if (\is_string($callback) && \strcasecmp(ltrim($callback, '\\'), 'spl_autoload_call') === 0) {
                        self::emitDeprecatedCallForm($code_base, $context, $function, $call_node, 'Using spl_autoload_call() as the callback is deprecated as of PHP 8.5; call spl_autoload_unregister() for each of spl_autoload_functions() instead');
                    }
                };
        }
        if (self::isTargetingPhpVersionOrNewer(80600)) {
            // PHP 8.6: is_a() with a string and without $allow_string
            $closures['\\is_a'] =
                /** @param list<Node|int|float|string> $args */
                static function (CodeBase $code_base, Context $context, FunctionInterface $function, array $args, ?Node $call_node): void {
                    foreach ($args as $arg) {
                        if ($arg instanceof Node && ($arg->kind === \ast\AST_UNPACK || $arg->kind === \ast\AST_NAMED_ARG)) {
                            return;
                        }
                    }
                    if (!isset($args[0])) {
                        return;
                    }
                    if (isset($args[2]) && UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[2])->asSingleScalarValueOrNull() !== false) {
                        // $allow_string is true, or unknown
                        return;
                    }
                    $type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]);
                    if (!$type->isEmpty() && $type->isExclusivelyStringTypes()) {
                        self::emitDeprecatedCallForm($code_base, $context, $function, $args[0] instanceof Node ? $args[0] : $call_node, 'Calling is_a() with a string when $allow_string is false is deprecated as of PHP 8.6; it always returns false');
                    }
                };
        }
        return $closures;
    }

    /**
     * @param list<Node|int|float|string> $args
     * @return Node|int|float|string|null the argument at $index, or null if there is none or the arguments are not all positional
     */
    private static function getPositionalArgument(array $args, int $index): Node|int|float|string|null
    {
        foreach ($args as $i => $arg) {
            if ($i > $index) {
                break;
            }
            if ($arg instanceof Node && ($arg->kind === \ast\AST_UNPACK || $arg->kind === \ast\AST_NAMED_ARG)) {
                return null;
            }
        }
        return $args[$index] ?? null;
    }

    /**
     * @return bool whether a value of the type is always an object (not an array, null, or an unknown value)
     */
    private static function isDefinitelyObject(UnionType $type): bool
    {
        if ($type->isEmpty()) {
            return false;
        }
        foreach ($type->getTypeSet() as $inner_type) {
            if (!$inner_type->isObject() || $inner_type->isNullable() || $inner_type instanceof \Phan\Language\Type\TemplateType) {
                return false;
            }
        }
        return true;
    }

    private static function emitDeprecatedCallForm(CodeBase $code_base, Context $context, FunctionInterface $function, ?Node $node, string $details): void
    {
        if (!$function->isPHPInternal()) {
            return;
        }
        Issue::maybeEmit(
            $code_base,
            $context,
            Issue::DeprecatedFunctionArgument,
            $node->lineno ?? $context->getLineNumberStart(),
            $function->getRepresentationForIssue(),
            $details
        );
    }

    /**
     * @return list<string> the lowercase names of the internal classes of the running PHP that extend the internal class $class_name
     */
    private static function getInternalSubclassNames(string $class_name): array
    {
        static $internal_class_names = null;
        if ($internal_class_names === null) {
            $internal_class_names = [];
            foreach (\get_declared_classes() as $name) {
                if ((new \ReflectionClass($name))->isInternal()) {
                    $internal_class_names[] = $name;
                }
            }
        }
        if (!\class_exists($class_name, false)) {
            return [];
        }
        $result = [];
        foreach ($internal_class_names as $name) {
            if (\is_subclass_of($name, $class_name)) {
                $result[] = strtolower($name);
            }
        }
        return $result;
    }

    /** @unused-param $code_base */
    public function handleLazyLoadInternalFunction(CodeBase $code_base, Func $function): void
    {
        if (!$function->isPHPInternal()) {
            return;
        }

        $name = strtolower(ltrim($function->getFQSEN()->getNamespacedName(), '\\'));
        $deprecation_info = self::DEPRECATED_FUNCTIONS[$name] ?? null;

        if ($deprecation_info === null) {
            return;
        }

        if (!self::isTargetingPhpVersionOrNewer($deprecation_info['min_php_version'])) {
            return;
        }

        if ($function->isDeprecated()) {
            return;
        }

        // Mark as deprecated but don't add a custom doc comment with the reason.
        // When the extension is loaded, PHP's own deprecation attributes provide the info.
        // When the extension is not loaded, we still want consistent test output.
        // Users can look up the deprecation reason in PHP documentation.
        $function->setIsDeprecated(true);
    }

    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return PhpDeprecationVisitor::class;
    }

    /**
     * Returns the map of deprecated class constants for the current target PHP version.
     *
     * @return array<string,string>
     */
    public static function getDeprecatedClassConstants(): array
    {
        $target_version = Config::get_closest_target_php_version_id();
        $result = [];

        foreach (self::DEPRECATED_CLASS_CONSTANTS as $fqsen => $info) {
            if ($target_version >= $info['min_php_version']) {
                $result[$fqsen] = $info['reason'];
            }
        }

        return $result;
    }

    /**
     * Returns the deprecated properties of internal classes for the current target PHP version.
     *
     * @return array<string,array<string,int>> maps the lowercase class name to a map from the property name to the php version id of the deprecation
     */
    public static function getDeprecatedProperties(): array
    {
        $target_version = Config::get_closest_target_php_version_id();
        $result = [];
        foreach (self::DEPRECATED_PROPERTIES as $class_name => $properties) {
            foreach ($properties as $property_name => $min_php_version) {
                if ($target_version >= $min_php_version) {
                    $result[$class_name][$property_name] = $min_php_version;
                }
            }
        }
        return $result;
    }

    /**
     * Returns the map of deprecated global constants for the current target PHP version.
     *
     * @return array<string,string>
     */
    public static function getDeprecatedGlobalConstants(): array
    {
        $target_version = Config::get_closest_target_php_version_id();
        $result = [];

        foreach (self::DEPRECATED_GLOBAL_CONSTANTS as $name => $info) {
            if ($target_version >= $info['min_php_version']) {
                $result[$name] = $info['reason'];
            }
        }

        return $result;
    }
}

/**
 * Visitor that emits class/global constant deprecation notices.
 */
final class PhpDeprecationVisitor extends PluginAwarePostAnalysisVisitor
{
    /**
     * @override
     */
    public function visitClassConst(Node $node): void
    {
        $deprecated = PhpDeprecationPlugin::getDeprecatedClassConstants();
        if (empty($deprecated)) {
            return;
        }

        try {
            $constants = (new ContextNode($this->code_base, $this->context, $node))->getClassConstList();
        } catch (\Throwable) {
            return;
        }

        foreach ($constants as $constant) {
            $this->maybeWarnDeprecatedClassConstant($constant, $node, $deprecated);
        }
    }

    /**
     * @override
     */
    public function visitConst(Node $node): void
    {
        $deprecated_map = PhpDeprecationPlugin::getDeprecatedGlobalConstants();
        if (empty($deprecated_map)) {
            return;
        }

        try {
            $constant = (new ContextNode($this->code_base, $this->context, $node))->getConst();
        } catch (\Throwable) {
            return;
        }

        $this->maybeWarnDeprecatedGlobalConstant($constant, $node, $deprecated_map);
    }

    /**
     * @override
     */
    public function visitNullsafeProp(Node $node): void
    {
        // `$document?->actualEncoding` reads the property unless $document is null
        $this->visitProp($node);
    }

    /**
     * @override
     */
    public function visitProp(Node $node): void
    {
        $property_name = $node->children['prop'];
        if (!\is_string($property_name)) {
            return;
        }
        $deprecated = PhpDeprecationPlugin::getDeprecatedProperties();
        $has_name = false;
        foreach ($deprecated as $properties) {
            if (isset($properties[$property_name])) {
                $has_name = true;
                break;
            }
        }
        if (!$has_name) {
            return;
        }
        try {
            $union_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $node->children['expr']);
        } catch (\Throwable) {
            return;
        }
        foreach ($union_type->getTypeSet() as $type) {
            if (!$type->isObjectWithKnownFQSEN()) {
                continue;
            }
            $class_fqsen = FullyQualifiedClassName::fromType($type);
            if (!$this->code_base->hasClassWithFQSEN($class_fqsen)) {
                continue;
            }
            $class = $this->code_base->getClassByFQSEN($class_fqsen);
            if (!$class->hasPropertyWithName($this->code_base, $property_name)) {
                continue;
            }
            $property = $class->getPropertyByName($this->code_base, $property_name);
            if (!$property->isPHPInternal() || $property->isDeprecated()) {
                continue;
            }
            // The property may be inherited from the class that declares it
            $declaring_class = strtolower(ltrim((string)$property->getRealDefiningFQSEN()->getFullyQualifiedClassName(), '\\'));
            $min_php_version = $deprecated[$declaring_class][$property_name] ?? null;
            if ($min_php_version === null) {
                continue;
            }
            $this->emitIssue(
                Issue::DeprecatedProperty,
                $node->lineno,
                $property->getRepresentationForIssue(),
                $property->getFileRef()->getFile(),
                $property->getFileRef()->getLineNumberStart(),
                \sprintf(' (Deprecated because: Deprecated in PHP %d.%d.)', \intdiv($min_php_version, 10000), \intdiv($min_php_version % 10000, 100))
            );
            return;
        }
    }

    /**
     * @param ClassConstant $constant
     * @param array<string,string> $deprecated_map
     */
    private function maybeWarnDeprecatedClassConstant(ClassConstant $constant, Node $node, array $deprecated_map): void
    {
        $fqsen = $constant->getFQSEN()->__toString();
        $reason = $deprecated_map[$fqsen] ?? null;
        if ($reason === null) {
            return;
        }
        $this->emitIssue(
            Issue::DeprecatedClassConstant,
            $node->lineno,
            $fqsen,
            $constant->getFileRef()->getFile(),
            $constant->getFileRef()->getLineNumberStart(),
            ' (Deprecated because: ' . $reason . ')'
        );
    }

    /**
     * @param array<string,string> $deprecated_map
     */
    private function maybeWarnDeprecatedGlobalConstant(GlobalConstant $constant, Node $node, array $deprecated_map): void
    {
        $name = ltrim($constant->getFQSEN()->__toString(), '\\');
        $reason = $deprecated_map[$name] ?? null;
        if ($reason === null) {
            return;
        }
        $this->emitIssue(
            Issue::DeprecatedGlobalConstant,
            $node->lineno,
            $constant->getFQSEN()->__toString(),
            ' (Deprecated because: ' . $reason . ')'
        );
    }
}
