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
        'contiguous-space' => 'int'
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
    'mongodb-driver-exception-writeexception' => [
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
        'zif-name' => 'string'
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
    'mysqli-driver' => [
        'client-info' => 'string',
        'client-version' => 'string',
        'driver-version' => 'string',
        'embedded' => 'string',
        'reconnect' => 'bool',
        'report-mode' => 'int'
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
        'http-status' => 'int',
        'parser-mode' => 'int',
        'success' => 'bool',
        'http-status-message' => 'string',
        'http-request-url' => 'string',
        'http-raw-request-headers' => 'string',
        'http-raw-request' => 'string',
        'http-raw-response-headers' => 'string',
        'http-raw-response' => 'string',
        'http-digested-response' => 'string'
    ],
    'domnamednodemap' => [
        'length' => 'int'
    ],
    'mysqli-sql-exception' => [
        'sqlstate' => 'string'
    ],
    'splstack' => [
        'name' => 'string'
    ],
    'numberformatter' => [
        'name' => 'string'
    ],
    'eventsslcontext' => [
        'local-cert' => 'string',
        'local-pk' => 'string'
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
        'max-oids' => 'int',
        'valueretrieval' => 'int',
        'quick-print' => 'bool',
        'enum-print' => 'bool',
        'oid-output-format' => 'int',
        'oid-increasing-check' => 'bool',
        'exceptions-enabled' => 'int',
        'info' => 'array'
    ],
    'mongoint32' => [
        'value' => 'string'
    ],
    'xmlreader' => [
        'attributecount' => 'int',
        'baseuri' => 'string',
        'depth' => 'int',
        'hasattributes' => 'bool',
        'hasvalue' => 'bool',
        'isdefault' => 'bool',
        'isemptyelement' => 'bool',
        'localname' => 'string',
        'name' => 'string',
        'namespaceuri' => 'string',
        'nodetype' => 'int',
        'prefix' => 'string',
        'value' => 'string',
        'xmllang' => 'string'
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
        'children' => 'array|null',
    ],
    'ast\node\decl' => [
        'kind' => 'int',
        'flags' => 'int',
        'lineno' => 'int',
        'children' => 'array|null',
        'endLineno' => 'int',
        'name' => 'string',
        'docComment' => 'string|null',
    ],
];
