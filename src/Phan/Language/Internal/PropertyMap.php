<?php declare(strict_types=1);
namespace Phan\Language\Internal;

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
 * cd phpdoc;
 * find . -type f -path '*.xml -exec cat {} \; | tr "\n" " " | grep -o "<type>[^<]*<\/type>\s*<varname linkend=\"[^\.]*\.props.[^\"]*\"" | while read l; do T=`echo $l | cut -d ">" -f2 | cut -d "<" -f1`; N=`echo $l | cut -d "\"" -f2 | cut -d "." -f1,3`; printf "$T $N\n"; done | tee types
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
 */
return [
    'arrayobject' => [
        'name' => 'string'
    ],
    'limititerator' => [
        'name' => 'string'
    ],
    'solrdocumentfield' => [
        'name' => 'string',
        'boost' => 'float',
        'values' => 'array'
    ],
    'domprocessinginstruction' => [
        'target' => 'string',
        'data' => 'string'
    ],
    'recursivearrayiterator' => [
        'name' => 'string'
    ],
    'eventbuffer' => [
        'length' => 'int',
        'contiguous_space' => 'int'
    ],
    'mongocursor' => [
        'slaveokay' => 'bool',
        'timeout' => 'int'
    ],
    'domxpath' => [
        'document' => 'DOMDocument'
    ],
    'domentity' => [
        'publicId' => 'string',
        'systemId' => 'string',
        'notationName' => 'string',
        'actualEncoding' => 'string',
        'encoding' => 'string',
        'version' => 'string'
    ],
    'splminheap' => [
        'name' => 'string'
    ],
    'mongodb\driver\exception\writeexception' => [
        'writeresult' => 'MongoDBDriverWriteResult'
    ],
    'ziparchive' => [
        'status' => 'int',
        'statussys' => 'int',
        'numfiles' => 'int',
        'filename' => 'string',
        'comment' => 'string'
    ],
    'solrexception' => [
        'sourceline' => 'int',
        'sourcefile' => 'string',
        'zif_name' => 'string'
    ],
    'arrayiterator' => [
        'name' => 'string'
    ],
    'mongoid' => [
        'id' => 'string'
    ],
    'dateinterval' => [
        'y' => 'int',
        'm' => 'int',
        'd' => 'int',
        'h' => 'int',
        'i' => 'int',
        's' => 'int',
        'invert' => 'int',
        'days' => 'mixed'
    ],
    'tokyotyrantexception' => [
        'code' => 'int'
    ],
    'tidy' => [
        'errorBuffer' => 'string'
    ],
    'filteriterator' => [
        'name' => 'string'
    ],
    'parentiterator' => [
        'name' => 'string'
    ],
    'recursiveregexiterator' => [
        'name' => 'string'
    ],
    'error' => [
        'message' => 'string',
        'code' => 'int',
        'file' => 'string',
        'line' => 'int'
    ],
    'domexception' => [
        'code' => 'int'
    ],
    'domentityreference' => [
        'name' => 'string'
    ],
    'spldoublylinkedlist' => [
        'name' => 'string'
    ],
    'domdocumentfragment' => [
        'name' => 'string'
    ],
    'collator' => [
        'name' => 'string'
    ],
    'streamwrapper' => [
        'context' => 'resource'
    ],
    'pdostatement' => [
        'querystring' => 'string'
    ],
    'domnotation' => [
        'publicId' => 'string',
        'systemId' => 'string'
    ],
    'snmpexception' => [
        'code' => 'string'
    ],
    'directoryiterator' => [
        'name' => 'string'
    ],
    'splqueue' => [
        'name' => 'string'
    ],
    'locale' => [
        'name' => 'string'
    ],
    'directory' => [
        'path' => 'string',
        'handle' => 'resource'
    ],
    'splheap' => [
        'name' => 'string'
    ],
    'domnodelist' => [
        'length' => 'int'
    ],
    'mongodb' => [
        'w' => 'int',
        'wtimeout' => 'int'
    ],
    'splpriorityqueue' => [
        'name' => 'string'
    ],
    'mongoclient' => [
        'connected' => 'bool',
        'status' => 'string'
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
    'libxmlerror' => [
        'level' => 'int',
        'code' => 'int',
        'column' => 'int',
        'message' => 'string',
        'file' => 'string',
        'line' => 'int'
    ],
    'domimplementation' => [
        'name' => 'string'
    ],
    'normalizer' => [
        'name' => 'string'
    ],
    'mysqli_driver' => [
        'client_info' => 'string',
        'client_version' => 'string',
        'driver_version' => 'string',
        'embedded' => 'string',
        'reconnect' => 'bool',
        'report_mode' => 'int'
    ],
    'norewinditerator' => [
        'name' => 'string'
    ],
    'event' => [
        'pending' => 'bool'
    ],
    'domdocumenttype' => [
        'publicId' => 'string',
        'systemId' => 'string',
        'name' => 'string',
        'entities' => 'DOMNamedNodeMap',
        'notations' => 'DOMNamedNodeMap',
        'internalSubset' => 'string'
    ],
    'errorexception' => [
        'severity' => 'int'
    ],
    'recursivedirectoryiterator' => [
        'name' => 'string'
    ],
    'domcharacterdata' => [
        'data' => 'string',
        'length' => 'int'
    ],
    'mongocollection' => [
        'db' => 'MongoDB',
        'w' => 'int',
        'wtimeout' => 'int'
    ],
    'mongoint64' => [
        'value' => 'string'
    ],
    'eventlistener' => [
        'fd' => 'int'
    ],
    'splmaxheap' => [
        'name' => 'string'
    ],
    'regexiterator' => [
        'name' => 'string'
    ],
    'domelement' => [
        'schemaTypeInfo' => 'bool',
        'tagName' => 'string'
    ],
    'tidynode' => [
        'value' => 'string',
        'name' => 'string',
        'type' => 'int',
        'line' => 'int',
        'column' => 'int',
        'proprietary' => 'bool',
        'id' => 'int',
        'attribute' => 'array',
        'child' => 'array'
    ],
    'recursivecachingiterator' => [
        'name' => 'string'
    ],
    'solrresponse' => [
        'http_status' => 'int',
        'parser_mode' => 'int',
        'success' => 'bool',
        'http_status_message' => 'string',
        'http_request_url' => 'string',
        'http_raw_request_headers' => 'string',
        'http_raw_request' => 'string',
        'http_raw_response_headers' => 'string',
        'http_raw_response' => 'string',
        'http_digested_response' => 'string'
    ],
    'domnamednodemap' => [
        'length' => 'int'
    ],
    'mysqli_sql_exception' => [
        'sqlstate' => 'string'
    ],
    'splstack' => [
        'name' => 'string'
    ],
    'numberformatter' => [
        'name' => 'string'
    ],
    'eventsslcontext' => [
        'local_cert' => 'string',
        'local_pk' => 'string'
    ],
    'pdoexception' => [
        'errorinfo' => 'array',
        'code' => 'string'
    ],
    'domnode' => [
        'nodeName' => 'string',
        'nodeValue' => 'string',
        'nodeType' => 'int',
        'parentNode' => 'DOMNode',
        'childNodes' => 'DOMNodeList',
        'firstChild' => 'DOMNode',
        'lastChild' => 'DOMNode',
        'previousSibling' => 'DOMNode',
        'nextSibling' => 'DOMNode',
        'attributes' => 'DOMNamedNodeMap',
        'ownerDocument' => 'DOMDocument',
        'namespaceURI' => 'string',
        'prefix' => 'string',
        'localName' => 'string',
        'baseURI' => 'string',
        'textContent' => 'string'
    ],
    'domattr' => [
        'name' => 'string',
        'ownerElement' => 'DOMElement',
        'schemaTypeInfo' => 'bool',
        'specified' => 'bool',
        'value' => 'string'
    ],
    'simplexmliterator' => [
        'name' => 'string'
    ],
    'snmp' => [
        'max_oids' => 'int',
        'valueretrieval' => 'int',
        'quick_print' => 'bool',
        'enum_print' => 'bool',
        'oid_output_format' => 'int',
        'oid_increasing_check' => 'bool',
        'exceptions_enabled' => 'int',
        'info' => 'array'
    ],
    'mongoint32' => [
        'value' => 'string'
    ],
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
        'xmlLang' => 'string'
    ],
    'eventbufferevent' => [
        'fd' => 'int',
        'priority' => 'int',
        'input' => 'EventBuffer',
        'output' => 'EventBuffer'
    ],
    'domtext' => [
        'wholeText' => 'string'
    ],
    'exception' => [
        'message' => 'string',
        'code' => 'int',
        'file' => 'string',
        'line' => 'int'
    ],
    'ast\node' => [
        'kind' => 'int',
        'flags' => 'int',
        'lineno' => 'int',
        'children' => 'array',  // NOTE: in the latest version, this is consistently an array, even for edge cases like statement lists of single statements.
        'endLineno' => 'int',
    ],
    'ast\node\decl' => [
        'kind' => 'int',
        'flags' => 'int',
        'lineno' => 'int',
        'children' => 'array',  // NOTE: in the latest version, this is consistently an array, even for edge cases like statement lists of single statements.
        'endLineno' => 'int',
        'name' => '?string',
        'docComment' => '?string',
    ],
    'ast\metadata' => [
        'kind' => 'int',
        'name' => 'string',
        'flags' => 'string[]',
        'flagsCombinable' => 'bool',
    ],
];
