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
        'slaveokay' => 'boolean',
        'timeout' => 'integer'
    ],
    'domxpath' => [
        'document' => 'DOMDocument'
    ],
    'domentity' => [
        'publicid' => 'string',
        'systemid' => 'string',
        'notationname' => 'string',
        'actualencoding' => 'string',
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
        'sourceline' => 'integer',
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
        'y' => 'integer',
        'm' => 'integer',
        'd' => 'integer',
        'h' => 'integer',
        'i' => 'integer',
        's' => 'integer',
        'invert' => 'integer',
        'days' => 'mixed'
    ],
    'tokyotyrantexception' => [
        'code' => 'int'
    ],
    'tidy' => [
        'errorbuffer' => 'string'
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
        'publicid' => 'string',
        'systemid' => 'string'
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
        'w' => 'integer',
        'wtimeout' => 'integer'
    ],
    'splpriorityqueue' => [
        'name' => 'string'
    ],
    'mongoclient' => [
        'connected' => 'boolean',
        'status' => 'string'
    ],
    'domdocument' => [
        'actualencoding' => 'string',
        'config' => 'DOMConfiguration',
        'doctype' => 'DOMDocumentType',
        'documentelement' => 'DOMElement',
        'documenturi' => 'string',
        'encoding' => 'string',
        'formatoutput' => 'bool',
        'implementation' => 'DOMImplementation',
        'preservewhitespace' => 'bool',
        'recover' => 'bool',
        'resolveexternals' => 'bool',
        'standalone' => 'bool',
        'stricterrorchecking' => 'bool',
        'substituteentities' => 'bool',
        'validateonparse' => 'bool',
        'version' => 'string',
        'xmlencoding' => 'string',
        'xmlstandalone' => 'bool',
        'xmlversion' => 'string'
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
        'publicid' => 'string',
        'systemid' => 'string',
        'name' => 'string',
        'entities' => 'DOMNamedNodeMap',
        'notations' => 'DOMNamedNodeMap',
        'internalsubset' => 'string'
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
        'w' => 'integer',
        'wtimeout' => 'integer'
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
        'schematypeinfo' => 'bool',
        'tagname' => 'string'
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
        'http-status' => 'integer',
        'parser-mode' => 'integer',
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
        'nodename' => 'string',
        'nodevalue' => 'string',
        'nodetype' => 'int',
        'parentnode' => 'DOMNode',
        'childnodes' => 'DOMNodeList',
        'firstchild' => 'DOMNode',
        'lastchild' => 'DOMNode',
        'previoussibling' => 'DOMNode',
        'nextsibling' => 'DOMNode',
        'attributes' => 'DOMNamedNodeMap',
        'ownerdocument' => 'DOMDocument',
        'namespaceuri' => 'string',
        'prefix' => 'string',
        'localname' => 'string',
        'baseuri' => 'string',
        'textcontent' => 'string'
    ],
    'domattr' => [
        'name' => 'string',
        'ownerelement' => 'DOMElement',
        'schematypeinfo' => 'bool',
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
        'fd' => 'integer',
        'priority' => 'integer',
        'input' => 'EventBuffer',
        'output' => 'EventBuffer'
    ],
    'domtext' => [
        'wholetext' => 'string'
    ],
    'exception' => [
        'message' => 'string',
        'code' => 'int',
        'file' => 'string',
        'line' => 'int'
    ],
];
