<?php declare(strict_types=1);

namespace Phan\Language\Internal;

// NOTE: PHP allows some code that would be a runtime error to be parsed, e.g. `$x->{2}()`.
// Documenting all of these helps prevent Phan from crashing when analyzing it.
$ordinary_ast_node = 'ast\Node|float|int|string';
$ast_node_shape_inner = \implode(',', [
    "args?:ast\Node",
    "catches?:ast\Node",
    "class?:ast\Node",
    "cond?:$ordinary_ast_node",
    "const?:string",
    "dim?:$ordinary_ast_node",
    "declares?:ast\Node",
    "docComment?:?string",
    "expr?:$ordinary_ast_node",
    "finally?:ast\Node",
    "insteadof?:ast\Node",
    "key?:$ordinary_ast_node",
    "left?:$ordinary_ast_node",
    "method?:$ordinary_ast_node",
    "name?:$ordinary_ast_node",
    "prop?:$ordinary_ast_node",
    "right?:$ordinary_ast_node",
    "stmts?:?ast\Node",
    "try?:ast\Node",
    "type?:?ast\Node",
    "value?:$ordinary_ast_node",
    "var?:ast\Node",
]);

$ast_node_children_types = 'array{' . $ast_node_shape_inner . '}|ast\Node[]|int[]|string[]|float[]|null[]';

$standard_exception_types = [
    'message' => 'string',
    'code' => 'int',
    'file' => 'string',
    'line' => 'int',
];

/**
 * A mapping from class name to property name to property type.
 *
 * # Format
 *
 * This structure is of the form
 * [ class_name => [ property_name => type ] ]
 *
 * # How to Generate
 *
 * ```sh
 * svn checkout https://svn.php.net/repository/phpdoc/en/trunk phpdoc;
 *
 * cd phpdoc;
 *
 * find . -type f -path '*.xml -exec cat {} \; \
 *   | tr "\n" " " \
 *   | grep -o "<type>[^<]*<\/type>\s*<varname linkend=\"[^\.]*\.props.[^\"]*\"" \
 *   | while read l; do T=`echo $l | cut -d ">" -f2 | cut -d "<" -f1`; N=`echo $l | cut -d "\"" -f2 | cut -d "." -f1,3`; printf "$T $N\n"; done \
 *   | tee types
 * ```
 *
 * and then pipe that through
 *
 * ```perl
 * #!/usr/bin/perl
 * my %classes = {};
 * while(<>) {
 *     chomp;
 *     if (/^([^ ]*) ([^\.]*)\.(.*)$/) {
 *         my $type = $1;
 *         my $class = $2;
 *         my $property = $3;
 *         push(@{ $classes{$class} }, "'$property' => '$type'");
 *     } else {
 *         print STDERR "FAIL\n";
 *     }
 * }
 * while (($class, $details) = each %classes) {
 *     print "    '$class' => [\n        ";
 *     print join(",\n        ", @{ $details });
 *     print "\n    ],\n";
 * }
 * ```
 *
 * TODO: Migrate the above scripts to be part of the existing php scripts
 * for working with the phpdoc SVN repo
 */
return [
    'arrayiterator' => ['name' => 'string'],
    'arrayobject' => ['name' => 'string'],
    'ast\metadata' => [
        'flags' => 'list<string>',
        'flagsCombinable' => 'bool',
        'kind' => 'int',
        'name' => 'string',
    ],
    'ast\node' => [
        'children' => $ast_node_children_types,  // NOTE: in the latest version, this is consistently an array, even for edge cases like statement lists of single statements.
        'endLineno' => 'int',
        'flags' => 'int',
        'kind' => 'int',
        'lineno' => 'int',
    ],
    'ast\node\decl' => [
        'children' => $ast_node_children_types,  // NOTE: in the latest version, this is consistently an array, even for edge cases like statement lists of single statements.
        'docComment' => '?string',
        'endLineno' => 'int',
        'flags' => 'int',
        'kind' => 'int',
        'lineno' => 'int',
        'name' => '?string',
    ],
    'collator' => ['name' => 'string'],
    'curlfile' => [
        'mime' => 'string',
        'name' => 'string',
        'postname' => 'string',
    ],
    'dateinterval' => [
        'd' => 'int',
        'days' => 'mixed',
        'h' => 'int',
        'i' => 'int',
        'invert' => 'int',
        'm' => 'int',
        's' => 'int',
        'y' => 'int',
    ],
    'directory' => ['handle' => 'resource', 'path' => 'string'],
    'directoryiterator' => ['name' => 'string'],
    'domattr' => [
        'name' => 'string',
        'ownerElement' => 'DOMElement',
        'schemaTypeInfo' => 'bool',
        'specified' => 'bool',
        'value' => 'string'
    ],
    'domcharacterdata' => [
        'data' => 'string',
        'length' => 'int'
    ],
    'domdocument' => [
        'actualEncoding' => 'string',
        'config' => 'DOMConfiguration',
        'doctype' => 'DOMDocumentType',
        'documentElement' => 'DOMElement',
        'documentURI' => 'string',
        'encoding' => 'string',
        'formatOutput' => 'bool',
        'implementation' => 'DOMImplementation',
        'preserveWhiteSpace' => 'bool',
        'recover' => 'bool',
        'resolveExternals' => 'bool',
        'standalone' => 'bool',
        'strictErrorChecking' => 'bool',
        'substituteEntities' => 'bool',
        'validateOnParse' => 'bool',
        'version' => 'string',
        'xmlEncoding' => 'string',
        'xmlStandalone' => 'bool',
        'xmlVersion' => 'string'
    ],
    'domdocumentfragment' => ['name' => 'string'],
    'domdocumenttype' => [
        'entities' => 'DOMNamedNodeMap',
        'internalSubset' => 'string',
        'name' => 'string',
        'notations' => 'DOMNamedNodeMap',
        'publicId' => 'string',
        'systemId' => 'string',
    ],
    'domelement' => [
        'schemaTypeInfo' => 'bool',
        'tagName' => 'string',
    ],
    'domentity' => [
        'actualEncoding' => 'string',
        'encoding' => 'string',
        'notationName' => 'string',
        'publicId' => 'string',
        'systemId' => 'string',
        'version' => 'string',
    ],
    'domentityreference' => ['name' => 'string'],
    'domexception' => ['code' => 'int'],
    'domimplementation' => ['name' => 'string'],
    'domnamednodemap' => ['length' => 'int'],
    'domnode' => [
        'attributes' => 'DOMNamedNodeMap',
        'baseURI' => 'string',
        'childNodes' => 'DOMNodeList',
        'firstChild' => 'DOMNode',
        'lastChild' => 'DOMNode',
        'localName' => 'string',
        'namespaceURI' => 'string',
        'nextSibling' => 'DOMNode',
        'nodeName' => 'string',
        'nodeType' => 'int',
        'nodeValue' => 'string',
        'ownerDocument' => 'DOMDocument',
        'parentNode' => 'DOMNode',
        'prefix' => 'string',
        'previousSibling' => 'DOMNode',
        'textContent' => 'string',
    ],
    'domnodelist' => ['length' => 'int'],
    'domnotation' => ['publicId' => 'string', 'systemId' => 'string'],
    'domprocessinginstruction' => ['data' => 'string', 'target' => 'string'],
    'domtext' => ['wholeText' => 'string'],
    'domxpath' => ['document' => 'DOMDocument'],
    'error' => ['code' => 'int', 'file' => 'string', 'line' => 'int', 'message' => 'string'],
    'errorexception' => ['severity' => 'int'],
    'event' => ['pending' => 'bool'],
    'eventbuffer' => [
        'contiguous_space' => 'int',
        'length' => 'int',
    ],
    'eventbufferevent' => [
        'fd' => 'int',
        'input' => 'EventBuffer',
        'output' => 'EventBuffer',
        'priority' => 'int',
    ],
    'eventlistener' => ['fd' => 'int'],
    'eventsslcontext' => [
        'local_cert' => 'string',
        'local_pk' => 'string',
    ],
    'exception' => [
        'code' => 'int',
        'file' => 'string',
        'line' => 'int',
        'message' => 'string',
    ],
    'filteriterator' => ['name' => 'string'],
    'libxmlerror' => [
        'code' => 'int',
        'column' => 'int',
        'file' => 'string',
        'level' => 'int',
        'line' => 'int',
        'message' => 'string',
    ],
    'limititerator' => ['name' => 'string'],
    'locale' => ['name' => 'string'],
    'mongoclient' => [
        'connected' => 'bool',
        'status' => 'string'
    ],
    'mongocollection' => [
        'db' => 'MongoDB',
        'w' => 'int',
        'wtimeout' => 'int'
    ],
    'mongocursor' => [
        'slaveokay' => 'bool',
        'timeout' => 'int'
    ],
    'mongodb' => ['w' => 'int', 'wtimeout' => 'int'],
    'mongodb\driver\exception\writeexception' => [
        'writeresult' => 'MongoDBDriverWriteResult'
    ],
    'mongoid' => ['id' => 'string'],
    'mongoint32' => ['value' => 'string'],
    'mongoint64' => ['value' => 'string'],
    'mysqli' => [
        'affected_rows' => 'int',
        'client_info' => 'string',
        'client_version' => 'int',
        'connect_errno' => 'int',
        'connect_error' => 'string',
        'errno' => 'int',
        'error' => 'string',
        'error_list' => 'array',
        'field_count' => 'int',
        'host_info' => 'string',
        'info' => 'string',
        'insert_id' => 'mixed',
        'protocol_version' => 'string',
        'server_info' => 'string',
        'server_version' => 'int',
        'sqlstate' => 'string',
        'stat' => 'mixed',
        'thread_id' => 'int',
        'warning_count' => 'int',
    ],
    'mysqli_driver' => [
        'client_info' => 'string',
        'client_version' => 'string',
        'driver_version' => 'string',
        'embedded' => 'string',
        'reconnect' => 'bool',
        'report_mode' => 'int'
    ],
    'mysqli_result' => [
        'current_field'  => 'int',
        'field_count' => 'int',
        'lengths' => 'array',
        'num_rows' => 'int',
        'type' => 'mixed',
    ],
    'mysqli_sql_exception' => [
        'sqlstate' => 'string'
    ],
    'mysqli_stmt' => [
        'affected_rows' => 'int',
        'errno' => 'int',
        'error' => 'string',
        'error_list' => 'array',
        'field_count' => 'int',
        'id' => 'mixed',
        'insert_id' => 'int',
        'num_rows' => 'int',
        'param_count' => 'int',
        'sqlstate' => 'string',
    ],
    'mysqli_warning' => [
        'errno' => 'int',
        'message' => 'string',
        'sqlstate' => 'mixed',
    ],
    'norewinditerator' => ['name' => 'string'],
    'normalizer' => ['name' => 'string'],
    'numberformatter' => ['name' => 'string'],
    'parentiterator' => ['name' => 'string'],
    'pdoexception' => ['code' => 'string', 'errorInfo' => 'array'],
    'pdostatement' => ['queryString' => 'string'],
    'php_user_filter' => ['filtername' => 'string', 'params' => 'mixed'],
    'recursivearrayiterator' => ['name' => 'string'],
    'recursivecachingiterator' => ['name' => 'string'],
    'recursivedirectoryiterator' => ['name' => 'string'],
    'recursiveregexiterator' => ['name' => 'string', 'replacement' => 'mixed'], // TODO: is 'replacement' documented
    'reflectionclass' => ['name' => 'string'],
    'reflectionclassconstant' => ['class' => 'string', 'name' => 'string'],
    'reflectionextension' => ['name' => 'string'],
    'reflectionfunction' => ['name' => 'string'],
    'reflectionfunctionabstract' => ['name' => 'string'],
    'reflectionmethod' => ['class' => 'string', 'name' => 'string'],
    'reflectionobject' => ['name' => 'string'],
    'reflectionparameter' => ['name' => 'string'],
    'reflectionproperty' => ['class' => 'string', 'name' => 'string'],
    'reflectionzendextension' => ['name' => 'string'],
    'regexiterator' => ['name' => 'string', 'replacement' => 'mixed'],
    'simplexmliterator' => [
        'name' => 'string',
    ],
    'snmp' => [
        'enum_print' => 'bool',
        'exceptions_enabled' => 'int',
        'info' => 'array',
        'max_oids' => 'int',
        'oid_increasing_check' => 'bool',
        'oid_output_format' => 'int',
        'quick_print' => 'bool',
        'valueretrieval' => 'int',
    ],
    'snmpexception' => ['code' => 'string'],
    'solrdocumentfield' => [
        'boost' => 'float',
        'name' => 'string',
        'values' => 'array',
    ],
    'solrexception' => [
        'sourcefile' => 'string',
        'sourceline' => 'int',
        'zif_name' => 'string'
    ],
    'solrresponse' => [
        'http_digested_response' => 'string',
        'http_raw_request' => 'string',
        'http_raw_request_headers' => 'string',
        'http_raw_response' => 'string',
        'http_raw_response_headers' => 'string',
        'http_request_url' => 'string',
        'http_status' => 'int',
        'http_status_message' => 'string',
        'parser_mode' => 'int',
        'success' => 'bool',
    ],
    'spldoublylinkedlist' => ['name' => 'string'],
    'splheap' => ['name' => 'string'],
    'splmaxheap' => ['name' => 'string'],
    'splminheap' => ['name' => 'string'],
    'splpriorityqueue' => ['name' => 'string'],
    'splqueue' => ['name' => 'string'],
    'splstack' => ['name' => 'string'],
    'streamwrapper' => ['context' => 'resource'],
    'tidy' => ['errorBuffer' => 'string'],
    'tidynode' => [
        'attribute' => 'array',
        'child' => 'array',
        'column' => 'int',
        'id' => 'int',
        'line' => 'int',
        'name' => 'string',
        'proprietary' => 'bool',
        'type' => 'int',
        'value' => 'string',
    ],
    'tokyotyrantexception' => ['code' => 'int'],
    'transliterator' => ['id' => 'string'],
    'xmlreader' => [
        'attributeCount' => 'int',
        'baseURI' => 'string',
        'depth' => 'int',
        'hasAttributes' => 'bool',
        'hasValue' => 'bool',
        'isDefault' => 'bool',
        'isEmptyElement' => 'bool',
        'localName' => 'string',
        'name' => 'string',
        'namespaceURI' => 'string',
        'nodeType' => 'int',
        'prefix' => 'string',
        'value' => 'string',
        'xmlLang' => 'string',
    ],
    'ziparchive' => [
        'comment' => 'string',
        'filename' => 'string',
        'numFiles' => 'int',
        'status' => 'int',
        'statusSys' => 'int',
    ],
];
