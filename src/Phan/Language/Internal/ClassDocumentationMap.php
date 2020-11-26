<?php // phpcs:ignoreFile
namespace Phan\Language\Internal;

/**
 * This contains descriptions used by Phan for hover text of internal classes and interfaces
 *
 * Format
 *
 * '<class_name>' => 'documentation',
 *
 * NOTE: This format will very likely change as information is added and should not be used directly.
 *
 * Sources of function/method summary info:
 *
 * 1. docs.php.net's SVN repo or website, and examples (See internal/internalsignatures.php)
 *
 *    See https://secure.php.net/manual/en/copyright.php
 *
 *    The PHP manual text and comments are covered by the [Creative Commons Attribution 3.0 License](http://creativecommons.org/licenses/by/3.0/legalcode),
 *    copyright (c) the PHP Documentation Group
 * 2. Various websites documenting individual extensions (e.g. php-ast)
 * 3. PHPStorm stubs (for anything missing from the above sources)
 *
 *    Available from https://github.com/JetBrains/phpstorm-stubs under the [Apache 2 license](https://www.apache.org/licenses/LICENSE-2.0)
 *
 *
 * CONTRIBUTING:
 *
 * Running `internal/internalstubs.php` can be used to update signature maps
 *
 * There are no plans for these signatures to diverge from what the above upstream sources contain.
 *
 * - If the descriptions cause Phan to crash, bug reports are welcome
 * - If Phan improperly extracted text from a summary (and this affects multiple signatures), patches fixing the extraction will be accepted.
 * - Otherwise, fixes for typos/grammar/inaccuracies in the summary will only be accepted once they are contributed upstream and can be regenerated (e.g. to the svn repo for docs.php.net).
 *
 *   Note that the summaries are used in a wide variety of contexts (e.g. PHP's online documentation),
 *   and what makes sense for Phan may not make sense for those projects, and vice versa.
 */
return [
'__PhanMissingTestClass' => 'Missing class name used by Phan for unit tests',
'AMQPBasicProperties' => 'stub class representing AMQPBasicProperties from pecl-amqp',
'AMQPChannel' => 'stub class representing AMQPChannel from pecl-amqp',
'AMQPChannelException' => 'stub class representing AMQPChannelException from pecl-amqp',
'AMQPConnection' => 'stub class representing AMQPConnection from pecl-amqp',
'AMQPConnectionException' => 'stub class representing AMQPConnectionException from pecl-amqp',
'AMQPDecimal' => 'stub class representing AMQPDecimal from pecl-amqp',
'AMQPEnvelope' => 'stub class representing AMQPEnvelope from pecl-amqp',
'AMQPEnvelopeException' => 'stub class representing AMQPEnvelopeException from pecl-amqp',
'AMQPException' => 'stub class representing AMQPException from pecl-amqp',
'AMQPExchange' => 'stub class representing AMQPExchange from pecl-amqp',
'AMQPExchangeException' => 'stub class representing AMQPExchangeException from pecl-amqp',
'AMQPExchangeValue' => 'stub class representing AMQPExchangeValue from pecl-amqp',
'AMQPQueue' => 'stub class representing AMQPQueue from pecl-amqp',
'AMQPQueueException' => 'stub class representing AMQPQueueException from pecl-amqp',
'AMQPTimestamp' => 'stub class representing AMQPTimestamp from pecl-amqp',
'AMQPValueException' => 'stub class representing AMQPExchangeValue from pecl-amqp',
'APCIterator' => 'The `APCIterator` class makes it easier to iterate over large APC caches. This is helpful as it allows iterating over large caches in steps, while grabbing a defined number of entries per lock instance, so it frees the cache locks for other activities rather than hold up the entire cache to grab 100 (the default) entries. Also, using regular expression matching is more efficient as it\'s been moved to the C level.',
'APCUIterator' => 'The `APCUIterator` class makes it easier to iterate over large APCu caches. This is helpful as it allows iterating over large caches in steps, while grabbing a defined number of entries per lock instance, so it frees the cache locks for other activities rather than hold up the entire cache to grab 100 (the default) entries. Also, using regular expression matching is more efficient as it\'s been moved to the C level.',
'AppendIterator' => 'An Iterator that iterates over several iterators one after the other.',
'ArgumentCountError' => '`ArgumentCountError` is thrown when too few arguments are passed to a user-defined function or method.',
'ArithmeticError' => '`ArithmeticError` is thrown when an error occurs while performing mathematical operations. In PHP 7.0, these errors include attempting to perform a bitshift by a negative amount, and any call to `intdiv` that would result in a value outside the possible bounds of an `integer`.',
'ArrayIterator' => 'This iterator allows to unset and modify values and keys while iterating over Arrays and Objects.

When you want to iterate over the same array multiple times you need to instantiate ArrayObject and let it create ArrayIterator instances that refer to it either by using `foreach` or by calling its getIterator() method manually.',
'ArrayObject' => 'This class allows objects to work as arrays.',
'AssertionError' => '`AssertionError` is thrown when an assertion made via `assert` fails.',
'ast\Metadata' => 'Metadata entry for a single AST kind, as returned by ast\get_metadata().',
'ast\Node' => 'This class describes a single node in a PHP AST.',
'BadFunctionCallException' => 'Exception thrown if a callback refers to an undefined function or if some arguments are missing.',
'BadMethodCallException' => 'Exception thrown if a callback refers to an undefined method or if some arguments are missing.',
'CachingIterator' => 'This object supports cached iteration over another iterator.',
'Cairo' => 'Simple class with some static helper methods.',
'CairoAntialias' => 'Enum class that specifies the type of antialiasing to do when rendering text or shapes.',
'CairoContent' => '`CairoContent` is used to describe the content that a surface will contain, whether color information, alpha information (translucence vs. opacity), or both.

Note: The large values here are designed to keep `CairoContent` values distinct from `CairoContent` values so that the implementation can detect the error if users confuse the two types.',
'CairoContext' => 'Context is the main object used when drawing with cairo. To draw with cairo, you create a `CairoContext`, set the target `CairoSurface`, and drawing options for the `CairoContext`, create shapes with functions . like `CairoContext::moveTo` and `CairoContext::lineTo`, and then draw shapes with `CairoContext::stroke` or `CairoContext::fill`. Contexts can be pushed to a stack via `CairoContext::save`. They may then safely be changed, without losing the current state. Use `CairoContext::restore` to restore to the saved state.',
'CairoException' => '`Exception` class thrown by `Cairo` functions and methods',
'CairoFillRule' => 'A `CairoFillRule` is used to select how paths are filled. For both fill rules, whether or not a point is included in the fill is determined by taking a ray from that point to infinity and looking at intersections with the path. The ray can be in any direction, as long as it doesn\'t pass through the end point of a segment or have a tricky intersection such as intersecting tangent to the path. (Note that filling is not actually implemented in this way. This is just a description of the rule that is applied.)

The default fill rule is `CairoFillRule::WINDING`.',
'CairoFilter' => 'A `CairoFilter` is used to indicate what filtering should be applied when reading pixel values from patterns. See `CairoPattern::setSource` or `cairo_pattern_set_source` for indicating the desired filter to be used with a particular pattern.',
'CairoFontFace' => 'CairoFontFace abstract class represents a particular font at a particular weight, slant, and other characteristic but no transformation or size.

Note: This class can not be instantiated directly, it is created by `CairoContext::getFontFace` or `cairo_scaled_font_get_font_face`.',
'CairoFontOptions' => 'An opaque structure holding all options that are used when rendering fonts.

Individual features of a cairo_font_options_t can be set or accessed using functions named cairo_font_options_set_feature_name and cairo_font_options_get_feature_name, like cairo_font_options_set_antialias() and cairo_font_options_get_antialias().

New features may be added to `CairoFontOptions` in the future. For this reason `CairoFontOptions::copy`, `CairoFontOptions::equal`, `CairoFontOptions::merge`, `CairoFontOptions::hash` (cairo_font_options_copy(), cairo_font_options_equal(), cairo_font_options_merge(), and cairo_font_options_hash() in procedural way) should be used to copy, check for equality, merge, or compute a hash value of `CairoFontOptions` objects.',
'CairoFontSlant' => 'Specifies variants of a font face based on their slant.',
'CairoFontType' => 'CairoFontType class is an abstract final class that contains constants used to describe the type of a given `CairoFontFace` or `CairoScaledFont`. The font types are also known as "font backends" within cairo.

The type of a CairoFontFace is determined by the how it is created, an example would be the `CairoToyFontFace::__construct`. The `CairoFontFace` type can be queried with `CairoFontFace::getType` or `cairo_font_face_get_type`

The various `CairoFontFace` functions can be used with a font face of any type.

The type of a `CairoScaledFont` is determined by the type of the `CairoFontFace` passed to `CairoScaledFont::__construct` or `cairo_scaled_font_create`. The scaled font type can be queried with `CairoScaledFont::getType` or `cairo_scaled_font_get_type`.',
'CairoFontWeight' => 'Specifies variants of a font face based on their weight.',
'CairoFormat' => 'CairoFormat enums are used to identify the memory format of the image data.',
'CairoGradientPattern' => '`CairoGradientPattern` is an abstract base class from which other Pattern classes derive. It cannot be instantiated directly.',
'CairoHintMetrics' => 'Specifies whether to hint font metrics; hinting font metrics means quantizing them so that they are integer values in device space. Doing this improves the consistency of letter and line spacing, however it also means that text will be laid out differently at different zoom factors.',
'CairoHintStyle' => 'Specifies the type of hinting to do on font outlines. Hinting is the process of fitting outlines to the pixel grid in order to improve the appearance of the result. Since hinting outlines involves distorting them, it also reduces the faithfulness to the original outline shapes. Not all of the outline hinting styles are supported by all font backends.',
'CairoImageSurface' => 'CairoImageSurface provide the ability to render to memory buffers either allocated by cairo or by the calling code. The supported image formats are those defined in `CairoFormat`.',
'CairoLinearGradient' => 'Create a new CairoLinearGradient along the line defined',
'CairoLineCap' => 'Specifies how to render the endpoints of the path when stroking.

The default line cap style is `CairoLineCap::BUTT`.',
'CairoMatrix' => 'Matrices are used throughout cairo to convert between different coordinate spaces.',
'CairoOperator' => 'This is used to set the compositing operator for all cairo drawing operations.

The default operator is `CairoOperator::OVER`

The operators marked as unbounded modify their destination even outside of the mask layer (that is, their effect is not bound by the mask layer). However, their effect can still be limited by way of clipping.

To keep things simple, the operator descriptions here document the behavior for when both source and destination are either fully transparent or fully opaque. The actual implementation works for translucent layers too. For a more detailed explanation of the effects of each operator, including the mathematical definitions, see http://cairographics.org/operators/.',
'CairoPath' => 'Note: CairoPath class cannot be instantiated directly, doing so will result in Fatal Error if used or passed',
'CairoPattern' => '`CairoPattern` is the abstract base class from which all the other pattern classes derive. It cannot be instantiated directly',
'CairoPatternType' => '`CairoPatternType` is used to describe the type of a given pattern.

The type of a pattern is determined by the function used to create it. The `cairo_pattern_create_rgb` and `cairo_pattern_create_rgba` functions create `CairoPatternType::SOLID` patterns. The remaining cairo_pattern_create_* functions map to pattern types in obvious ways.',
'CairoStatus' => '`CairoStatus` is used to indicate errors that can occur when using Cairo. In some cases it is returned directly by functions. but when using `CairoContext`, the last error, if any, is stored in the object and can be retrieved with `CairoContext::status` or `cairo_status`. New entries may be added in future versions.

Use `Cairo::statusToString` or `cairo_status_to_string` to get a human-readable representation of an error message.',
'CairoSurface' => 'This is the base-class for all other Surface types. CairoSurface is the abstract type representing all different drawing targets that cairo can render to. The actual drawings are performed using a CairoContext.',
'CairoSvgSurface' => 'Svg specific surface class, uses the SVG (standard vector graphics) surface backend.',
'CairoToyFontFace' => 'The `CairoToyFontFace` class can be used instead of `CairoContext::selectFontFace` to create a toy font independently of a context.',
'Cassandra' => 'The main entry point to the PHP Driver for Apache Cassandra.

Use Cassandra::cluster() to build a cluster instance.
Use Cassandra::ssl() to build SSL options instance.',
'Cassandra\Aggregate' => 'A PHP representation of an aggregate',
'Cassandra\BatchStatement' => 'Batch statements are used to execute a series of simple or prepared
statements.

There are 3 types of batch statements:
 * `Cassandra::BATCH_LOGGED`   - this is the default batch type. This batch
   guarantees that either all or none of its statements will be executed.
   This behavior is achieved by writing a batch log on the coordinator,
   which slows down the execution somewhat.
 * `Cassandra::BATCH_UNLOGGED` - this batch will not be verified when
   executed, which makes it faster than a `LOGGED` batch, but means that
   some of its statements might fail, while others - succeed.
 * `Cassandra::BATCH_COUNTER`  - this batch is used for counter updates,
   which are, unlike other writes, not idempotent.',
'Cassandra\Bigint' => 'A PHP representation of the CQL `bigint` datatype',
'Cassandra\Blob' => 'A PHP representation of the CQL `blob` datatype',
'Cassandra\Cluster' => 'Cluster object is used to create Sessions.',
'Cassandra\Cluster\Builder' => 'Cluster builder allows fluent configuration of the cluster instance.',
'Cassandra\Collection' => 'A PHP representation of the CQL `list` datatype',
'Cassandra\Column' => 'A PHP representation of a column',
'Cassandra\Custom' => 'A class for representing custom values.',
'Cassandra\Date' => 'A PHP representation of the CQL `date` type.',
'Cassandra\Decimal' => 'A PHP representation of the CQL `decimal` datatype

The actual value of a decimal is `$value * pow(10, $scale * -1)`',
'Cassandra\DefaultAggregate' => 'A PHP representation of an aggregate',
'Cassandra\DefaultCluster' => 'Default cluster implementation.',
'Cassandra\DefaultColumn' => 'A PHP representation of a column',
'Cassandra\DefaultFunction' => 'A PHP representation of a public function',
'Cassandra\DefaultIndex' => 'A PHP representation of an index',
'Cassandra\DefaultKeyspace' => 'A PHP representation of a keyspace',
'Cassandra\DefaultMaterializedView' => 'A PHP representation of a materialized view',
'Cassandra\DefaultSchema' => 'A PHP representation of a schema',
'Cassandra\DefaultSession' => 'A session is used to prepare and execute statements.',
'Cassandra\DefaultTable' => 'A PHP representation of a table',
'Cassandra\Duration' => 'A PHP representation of the CQL `duration` datatype',
'Cassandra\Exception' => 'An interface implemented by all exceptions thrown by the PHP Driver.
Makes it easy to catch all driver-related exceptions using
`catch (Exception $e)`.',
'Cassandra\Exception\AlreadyExistsException' => 'AlreadyExistsException is raised when attempting to re-create existing keyspace.',
'Cassandra\Exception\AuthenticationException' => 'AuthenticationException is raised when client was not configured with valid
authentication credentials.',
'Cassandra\Exception\ConfigurationException' => 'ConfigurationException is raised when query is syntactically correct but
invalid because of some configuration issue.
For example when attempting to drop a non-existent keyspace.',
'Cassandra\Exception\DivideByZeroException' => 'Cassandra domain exception.',
'Cassandra\Exception\DomainException' => 'Cassandra domain exception.',
'Cassandra\Exception\ExecutionException' => 'ExecutionException is raised when something went wrong during request execution.',
'Cassandra\Exception\InvalidArgumentException' => 'Cassandra invalid argument exception.',
'Cassandra\Exception\InvalidQueryException' => 'InvalidQueryException is raised when query is syntactically correct but invalid.
For example when attempting to create a table without specifying a keyspace.',
'Cassandra\Exception\InvalidSyntaxException' => 'InvalidSyntaxException is raised when CQL in the request is syntactically incorrect.',
'Cassandra\Exception\IsBootstrappingException' => 'IsBootstrappingException is raised when a node is bootstrapping.',
'Cassandra\Exception\LogicException' => 'Cassandra logic exception.',
'Cassandra\Exception\OverloadedException' => 'OverloadedException is raised when a node is overloaded.',
'Cassandra\Exception\ProtocolException' => 'ProtocolException is raised when a client did not follow server\'s protocol,
e.g. sending a QUERY message before STARTUP. Seeing this error can be
considered a bug.',
'Cassandra\Exception\RangeException' => 'Cassandra domain exception.',
'Cassandra\Exception\ReadTimeoutException' => 'ReadTimeoutException is raised when a coordinator failed to receive acks
from the required number of replica nodes in time during a read.',
'Cassandra\Exception\RuntimeException' => 'Cassandra runtime exception.',
'Cassandra\Exception\ServerException' => 'ServerException is raised when something unexpected happened on the server.
This exception is most likely due to a server-side bug.
**NOTE** This exception and all its children are generated on the server.',
'Cassandra\Exception\TimeoutException' => 'TimeoutException is generally raised when a future did not resolve
within a given time interval.',
'Cassandra\Exception\TruncateException' => 'TruncateException is raised when something went wrong during table
truncation.',
'Cassandra\Exception\UnauthorizedException' => 'UnauthorizedException is raised when the current user doesn\'t have
sufficient permissions to access data.',
'Cassandra\Exception\UnavailableException' => 'UnavailableException is raised when a coordinator detected that there aren\'t
enough replica nodes available to fulfill the request.

NOTE: Request has not even been forwarded to the replica nodes in this case.',
'Cassandra\Exception\UnpreparedException' => 'UnpreparedException is raised when a given prepared statement id does not
exist on the server. The driver should be automatically re-preparing the
statement in this case. Seeing this error could be considered a bug.',
'Cassandra\Exception\ValidationException' => 'ValidationException is raised on invalid request, before even attempting to
execute it.',
'Cassandra\Exception\WriteTimeoutException' => 'WriteTimeoutException is raised when a coordinator failed to receive acks
from the required number of replica nodes in time during a write.',
'Cassandra\ExecutionOptions' => 'Request execution options.',
'Cassandra\Float_' => 'A PHP representation of the CQL `float` datatype',
'Cassandra\Function_' => 'A PHP representation of a function',
'Cassandra\Future' => 'Futures are returns from asynchronous methods.',
'Cassandra\FutureClose' => 'A future returned from Session::closeAsync().',
'Cassandra\FuturePreparedStatement' => 'A future returned from `Session::prepareAsync()`
This future will resolve with a PreparedStatement or an exception.',
'Cassandra\FutureRows' => 'This future results is resolved with Rows.',
'Cassandra\FutureSession' => 'A future that resolves with Session.',
'Cassandra\FutureValue' => 'A future that always resolves in a value.',
'Cassandra\Index' => 'A PHP representation of an index',
'Cassandra\Inet' => 'A PHP representation of the CQL `inet` datatype',
'Cassandra\Keyspace' => 'A PHP representation of a keyspace',
'Cassandra\Map' => 'A PHP representation of the CQL `map` datatype',
'Cassandra\MaterializedView' => 'A PHP representation of a materialized view',
'Cassandra\Numeric' => 'Common interface implemented by all numeric types, providing basic
arithmetic functions.',
'Cassandra\PreparedStatement' => 'Prepared statements are faster to execute because the server doesn\'t need
to process a statement\'s CQL during the execution.

With token-awareness enabled in the driver, prepared statements are even
faster, because they are sent directly to replica nodes and avoid the extra
network hop.',
'Cassandra\RetryPolicy' => 'Interface for retry policies.',
'Cassandra\RetryPolicy\DefaultPolicy' => 'The default retry policy. This policy retries a query, using the
request\'s original consistency level, in the following cases:

* On a read timeout, if enough replicas replied but the data was not received.
* On a write timeout, if a timeout occurs while writing a distributed batch log.
* On unavailable, it will move to the next host.

In all other cases the error will be returned.',
'Cassandra\RetryPolicy\DowngradingConsistency' => 'A retry policy that will downgrade the consistency of a request in
an attempt to save a request in cases where there is any chance of success. A
write request will succeed if there is at least a single copy persisted and a
read request will succeed if there is some data available even if it increases
the risk of reading stale data. This policy will retry in the same scenarios as
the default policy, and it will also retry in the following case:

* On a read timeout, if some replicas responded but is lower than
  required by the current consistency level then retry with a lower
  consistency level
* On a write timeout, Retry unlogged batches at a lower consistency level
  if at least one replica responded. For single queries and batch if any
  replicas responded then consider the request successful and swallow the
  error.
* On unavailable, retry at a lower consistency if at lease one replica
  responded.

Important: This policy may attempt to retry requests with a lower
consistency level. Using this policy can break consistency guarantees.',
'Cassandra\RetryPolicy\Fallthrough' => 'A retry policy that never retries and allows all errors to fallthrough.',
'Cassandra\RetryPolicy\Logging' => 'A retry policy that logs the decisions of its child policy.',
'Cassandra\Rows' => 'Rows represent a result of statement execution.',
'Cassandra\Schema' => 'A PHP representation of a schema',
'Cassandra\Session' => 'A session is used to prepare and execute statements.',
'Cassandra\Set' => 'A PHP representation of the CQL `set` datatype',
'Cassandra\SimpleStatement' => 'Simple statements can be executed using a Session instance.
They are constructed with a CQL string that can contain positional
argument markers `?`.

NOTE: Positional argument are only valid for native protocol v2+.',
'Cassandra\Smallint' => 'A PHP representation of the CQL `smallint` datatype.',
'Cassandra\SSLOptions' => 'SSL options for Cluster.',
'Cassandra\SSLOptions\Builder' => 'SSLOptions builder allows fluent configuration of ssl options.',
'Cassandra\Statement' => 'All statements implement this common interface.',
'Cassandra\Table' => 'A PHP representation of a table',
'Cassandra\Time' => 'A PHP representation of the CQL `time` type.',
'Cassandra\Timestamp' => 'A PHP representation of the CQL `timestamp` datatype',
'Cassandra\TimestampGenerator' => 'Interface for timestamp generators.',
'Cassandra\TimestampGenerator\Monotonic' => 'A timestamp generator that generates monotonically increasing timestamps
client-side. The timestamps generated have a microsecond granularity with
the sub-millisecond part generated using a counter. The implementation
guarantees that no more than 1000 timestamps will be generated for a given
clock tick even if shared by multiple session objects. If that rate is
exceeded then a warning is logged and timestamps stop incrementing until
the next clock tick.',
'Cassandra\TimestampGenerator\ServerSide' => 'A timestamp generator that allows the server-side to assign timestamps.',
'Cassandra\Timeuuid' => 'A PHP representation of the CQL `timeuuid` datatype',
'Cassandra\Tinyint' => 'A PHP representation of the CQL `tinyint` datatype.',
'Cassandra\Tuple' => 'A PHP representation of the CQL `tuple` datatype',
'Cassandra\Type' => 'Cluster object is used to create Sessions.',
'Cassandra\Type\Collection' => 'A class that represents the list type. The list type contains the type of the
elements contain in the list.',
'Cassandra\Type\Custom' => 'A class that represents a custom type.',
'Cassandra\Type\Map' => 'A class that represents the map type. The map type contains two types that
represents the types of the key and value contained in the map.',
'Cassandra\Type\Scalar' => 'A class that represents a primitive type (e.g. `varchar` or `bigint`)',
'Cassandra\Type\Set' => 'A class that represents the set type. The set type contains the type of the
elements contain in the set.',
'Cassandra\Type\Tuple' => 'A class that represents the tuple type. The tuple type is able to represent
a composite type of one or more types accessed by index.',
'Cassandra\Type\UserType' => 'A class that represents a user type. The user type is able to represent a
composite type of one or more types accessed by name.',
'Cassandra\UserTypeValue' => 'A PHP representation of the CQL UDT datatype',
'Cassandra\Uuid' => 'A PHP representation of the CQL `uuid` datatype',
'Cassandra\UuidInterface' => 'A PHP representation of the CQL `uuid` datatype',
'Cassandra\Value' => 'Common interface implemented by all Cassandra value types.',
'Cassandra\Varint' => 'A PHP representation of the CQL `varint` datatype',
'chdb' => 'Represents a loaded chdb file.',
'classObj' => 'Class Objects can be returned by the `layerObj`_ class, or can be
created using:',
'clusterObj' => 'Instance of clusterObj is always embedded inside the `layerObj`_.',
'Collator' => 'Provides string comparison capability with support for appropriate locale-sensitive sort orderings.',
'Collectable' => 'Represents a garbage-collectable object.',
'colorObj' => 'Instances of colorObj are always embedded inside other classes.',
'COM' => 'The COM class allows you to instantiate an OLE compatible COM object and call its methods and access its properties.',
'com_exception' => 'This extension will throw instances of the class com_exception whenever there is a potentially fatal error reported by COM. All COM exceptions have a well-defined code property that corresponds to the HRESULT return value from the various COM operations. You may use this code to make programmatic decisions on how to handle the exception.',
'CommonMark\CQL' => 'CommonMark Query Language is a DSL for describing how to travel through a CommonMark Node tree implemented as a parser and compiler for a small set of instructions, and a virtual machine for executing those instructions.',
'CommonMark\Node' => 'Represents an Abstract Node, this final abstract is not for direct use by the programmer.',
'CommonMark\Parser' => 'Provides an incremental parser as an alternative to the simple Parsing API function',
'COMPersistHelper' => '`COMPersistHelper` improves the interoperability of COM and PHP with regard to the `php.ini` directive open_basedir, and stream `resource`s.',
'CompileError' => '`CompileError` is thrown for some compilation errors, which formerly issued a fatal error.',
'Componere\Abstract\Definition' => 'This final abstract represents a class entry, and should not be used by the programmer.',
'Componere\Definition' => 'The Definition class allows the programmer to build and register a type at runtime.

Should a Definition replace an existing class, the existing class will be restored when the Definition is destroyed.',
'Componere\Method' => 'A Method represents a function with modifiable accessibility flags',
'Componere\Patch' => 'The Patch class allows the programmer to change the type of an instance at runtime without registering a new Definition

When a Patch is destroyed it is reverted, so that instances that were patched during the lifetime of the Patch are restored to their formal type.',
'Componere\Value' => 'A Value represents a PHP variable of all types, including undefined',
'Cond' => 'The static methods contained in the Cond class provide direct access to Posix Condition Variables.',
'Couchbase\AnalyticsQuery' => 'Represents a Analytics query (currently experimental support).',
'Couchbase\Authenticator' => 'Interface of authentication containers.',
'Couchbase\BooleanFieldSearchQuery' => 'A FTS query that queries fields explicitly indexed as boolean.',
'Couchbase\BooleanSearchQuery' => 'A compound FTS query that allows various combinations of sub-queries.',
'Couchbase\Bucket' => 'Represents connection to the Couchbase Server',
'Couchbase\BucketManager' => 'Provides management capabilities for the Couchbase Bucket',
'Couchbase\ClassicAuthenticator' => 'Authenticator based on login/password credentials.

This authenticator uses separate credentials for Cluster management interface
as well as for each bucket.',
'Couchbase\Cluster' => 'Represents a Couchbase Server Cluster.

It is an entry point to the library, and in charge of opening connections to the Buckets.
In addition it can instantiate \Couchbase\ClusterManager to perform cluster-wide operations.',
'Couchbase\ClusterManager' => 'Provides management capabilities for a Couchbase Server Cluster',
'Couchbase\ConjunctionSearchQuery' => 'A compound FTS query that performs a logical AND between all its sub-queries (conjunction).',
'Couchbase\DateRangeSearchFacet' => 'A facet that categorizes hits inside date ranges (or buckets) provided by the user.',
'Couchbase\DateRangeSearchQuery' => 'A FTS query that matches documents on a range of values. At least one bound is required, and the
inclusiveness of each bound can be configured.',
'Couchbase\DisjunctionSearchQuery' => 'A compound FTS query that performs a logical OR between all its sub-queries (disjunction). It requires that a
minimum of the queries match. The minimum is configurable (default 1).',
'Couchbase\DocIdSearchQuery' => 'A FTS query that matches on Couchbase document IDs. Useful to restrict the search space to a list of keys (by using
this in a compound query).',
'Couchbase\Document' => 'Represents Couchbase Document, which stores metadata and the value.

The instances of this class returned by K/V commands of the \Couchbase\Bucket',
'Couchbase\DocumentFragment' => 'A fragment of a JSON Document returned by the sub-document API.',
'Couchbase\Exception' => 'Exception represeting all errors generated by the extension',
'Couchbase\GeoBoundingBoxSearchQuery' => 'A FTS query which allows to match geo bounding boxes.',
'Couchbase\GeoDistanceSearchQuery' => 'A FTS query that finds all matches from a given location (point) within the given distance.

Both the point and the distance are required.',
'Couchbase\LookupInBuilder' => 'A builder for subdocument lookups. In order to perform the final set of operations, use the
execute() method.

Instances of this builder should be obtained through \Couchbase\Bucket->lookupIn()',
'Couchbase\MatchAllSearchQuery' => 'A FTS query that matches all indexed documents (usually for debugging purposes).',
'Couchbase\MatchNoneSearchQuery' => 'A FTS query that matches 0 document (usually for debugging purposes).',
'Couchbase\MatchPhraseSearchQuery' => 'A FTS query that matches several given terms (a "phrase"), applying further processing
like analyzers to them.',
'Couchbase\MatchSearchQuery' => 'A FTS query that matches a given term, applying further processing to it
like analyzers, stemming and even #fuzziness(int).',
'Couchbase\MutateInBuilder' => 'A builder for subdocument mutations. In order to perform the final set of operations, use the
execute() method.

Instances of this builder should be obtained through \Couchbase\Bucket->mutateIn()',
'Couchbase\MutationState' => 'Container for mutation tokens.',
'Couchbase\MutationToken' => 'An object which contains meta information of the document needed to enforce query consistency.',
'Couchbase\N1qlIndex' => 'Represents N1QL index definition',
'Couchbase\N1qlQuery' => 'Represents a N1QL query',
'Couchbase\NumericRangeSearchFacet' => 'A facet that categorizes hits into numerical ranges (or buckets) provided by the user.',
'Couchbase\NumericRangeSearchQuery' => 'A FTS query that matches documents on a range of values. At least one bound is required, and the
inclusiveness of each bound can be configured.',
'Couchbase\PasswordAuthenticator' => 'Authenticator based on RBAC feature of Couchbase Server 5+.

This authenticator uses single credentials for all operations (data and management).',
'Couchbase\PhraseSearchQuery' => 'A FTS query that matches several terms (a "phrase") as is. The order of the terms matter and no further processing is
applied to them, so they must appear in the index exactly as provided.  Usually for debugging purposes, prefer
MatchPhraseQuery.',
'Couchbase\PrefixSearchQuery' => 'A FTS query that allows for simple matching on a given prefix.',
'Couchbase\QueryStringSearchQuery' => 'A FTS query that performs a search according to the "string query" syntax.',
'Couchbase\RegexpSearchQuery' => 'A FTS query that allows for simple matching of regular expressions.',
'Couchbase\SearchFacet' => 'Common interface for all search facets',
'Couchbase\SearchQuery' => 'Represents full text search query',
'Couchbase\SearchQueryPart' => 'Common interface for all classes, which could be used as a body of SearchQuery',
'Couchbase\SearchSort' => 'Base class for all FTS sort options in querying.',
'Couchbase\SearchSortField' => 'Sort by a field in the hits.',
'Couchbase\SearchSortGeoDistance' => 'Sort by a location and unit in the hits.',
'Couchbase\SearchSortId' => 'Sort by the document identifier.',
'Couchbase\SearchSortScore' => 'Sort by the hit score.',
'Couchbase\SpatialViewQuery' => 'Represents spatial Couchbase Map/Reduce View query',
'Couchbase\TermRangeSearchQuery' => 'A FTS query that matches documents on a range of values. At least one bound is required, and the
inclusiveness of each bound can be configured.',
'Couchbase\TermSearchFacet' => 'A facet that gives the number of occurrences of the most recurring terms in all hits.',
'Couchbase\TermSearchQuery' => 'A facet that gives the number of occurrences of the most recurring terms in all hits.',
'Couchbase\UserSettings' => 'Represents settings for new/updated user.',
'Couchbase\ViewQuery' => 'Represents regular Couchbase Map/Reduce View query',
'Couchbase\ViewQueryEncodable' => 'Common interface for all View queries',
'Couchbase\WildcardSearchQuery' => 'A FTS query that allows for simple matching using wildcard characters (* and ?).',
'Countable' => 'Classes implementing `Countable` can be used with the `count` function.',
'Crypto\Base64' => 'Class for base64 encoding and docoding',
'Crypto\Base64Exception' => 'Exception class for base64 errors',
'Crypto\Cipher' => 'Class providing cipher algorithms',
'Crypto\CipherException' => 'Exception class for cipher errors',
'Crypto\CMAC' => 'Class providing CMAC functionality',
'Crypto\Hash' => 'Class providing hash algorithms',
'Crypto\HashException' => 'Exception class for hash errors',
'Crypto\HMAC' => 'Class providing HMAC functionality',
'Crypto\KDF' => 'Abstract class for KDF subclasses',
'Crypto\KDFException' => 'Exception class for KDF errors',
'Crypto\MAC' => 'Abstract class for MAC subclasses',
'Crypto\MACException' => 'Exception class for MAC errors',
'Crypto\PBKDF2' => 'Class providing PBKDF2 functionality',
'Crypto\PBKDF2Exception' => 'Exception class for PBKDF2 errors',
'Crypto\Rand' => 'Class for generating random numbers',
'Crypto\RandException' => 'Exception class for rand errors',
'CURLFile' => '`CURLFile` should be used to upload a file with `CURLOPT_POSTFIELDS`.',
'DateInterval' => 'Represents a date interval.

A date interval stores either a fixed amount of time (in years, months, days, hours etc) or a relative time string in the format that `DateTime`\'s constructor supports.',
'DatePeriod' => 'Represents a date period.

A date period allows iteration over a set of dates and times, recurring at regular intervals, over a given period.',
'DateTime' => 'Representation of date and time.',
'DateTimeImmutable' => 'This class behaves the same as `DateTime` except it never modifies itself but returns a new object instead.',
'DateTimeInterface' => 'DateTimeInterface is meant so that both DateTime and DateTimeImmutable can be type hinted for. It is not possible to implement this interface with userland classes.',
'DateTimeZone' => 'Representation of time zone.',
'Directory' => 'Instances of `Directory` are created by calling the `dir` function, not by the new operator.',
'DirectoryIterator' => 'The DirectoryIterator class provides a simple interface for viewing the contents of filesystem directories.',
'DivisionByZeroError' => '`DivisionByZeroError` is thrown when an attempt is made to divide a number by zero.',
'DomainException' => 'Exception thrown if a value does not adhere to a defined valid data domain.',
'DOMAttr' => '`DOMAttr` represents an attribute in the `DOMElement` object.',
'DOMCdataSection' => 'The `DOMCdataSection` inherits from `DOMText` for textural representation of CData constructs.',
'DOMCharacterData' => 'Represents nodes with character data. No nodes directly correspond to this class, but other nodes do inherit from it.',
'DOMComment' => 'Represents comment nodes, characters delimited by `&lt;!--` and `--&gt;`.',
'DOMDocument' => 'Represents an entire HTML or XML document; serves as the root of the document tree.',
'DOMDocumentType' => 'Each `DOMDocument` has a `doctype` attribute whose value is either `null` or a `DOMDocumentType` object.',
'DOMEntity' => 'This interface represents a known entity, either parsed or unparsed, in an XML document.',
'DOMException' => 'DOM operations raise exceptions under particular circumstances, i.e., when an operation is impossible to perform for logical reasons.

See also the PHP manual\'s section on language.exceptions.',
'DOMImplementation' => 'The `DOMImplementation` interface provides a number of methods for performing operations that are independent of any particular instance of the document object model.',
'DOMText' => 'The `DOMText` class inherits from `DOMCharacterData` and represents the textual content of a `DOMElement` or `DOMAttr`.',
'DOMXPath' => 'Supports XPath 1.0',
'DOTNET' => 'The DOTNET class allows you to instantiate a class from a .Net assembly and call its methods and access its properties.',
'Ds\Collection' => '`Collection` is the base interface which covers functionality common to all the data structures in this library. It guarantees that all structures are traversable, countable, and can be converted to json using `json_encode`.',
'Ds\Deque' => 'A Deque (pronounced “deck”) is a sequence of values in a contiguous buffer that grows and shrinks automatically. The name is a common abbreviation of “double-ended queue” and is used internally by `Ds\Queue`.

Two pointers are used to keep track of a head and a tail. The pointers can “wrap around” the end of the buffer, which avoids the need to move other values around to make room. This makes shift and unshift very fast —  something a `Ds\Vector` can’t compete with.

Accessing a value by index requires a translation between the index and its corresponding position in the buffer: `((head + position) % capacity)`.',
'Ds\Hashable' => 'Hashable is an interface which allows objects to be used as keys. It’s an alternative to `spl_object_hash`, which determines an object’s hash based on its handle: this means that two objects that are considered equal by an implicit definition would not treated as equal because they are not the same instance.

`hash` is used to return a scalar value to be used as the object\'s hash value, which determines where it goes in the hash table. While this value does not have to be unique, objects which are equal must have the same hash value.

`equals` is used to determine if two objects are equal. It\'s guaranteed that the comparing object will be an instance of the same class as the subject.',
'Ds\Map' => 'A Map is a sequential collection of key-value pairs, almost identical to an `array` used in a similar context. Keys can be any type, but must be unique. Values are replaced if added to the map using the same key.',
'Ds\Pair' => 'A pair is used by `Ds\Map` to pair keys with values.',
'Ds\PriorityQueue' => 'A PriorityQueue is very similar to a Queue. Values are pushed into the queue with an assigned priority, and the value with the highest priority will always be at the front of the queue.

Implemented using a max heap.',
'Ds\Queue' => 'A Queue is a “first in, first out” or “FIFO” collection that only allows access to the value at the front of the queue and iterates in that order, destructively.',
'Ds\Sequence' => 'A Sequence describes the behaviour of values arranged in a single, linear dimension. Some languages refer to this as a "List". It’s similar to an array that uses incremental integer keys, with the exception of a few characteristics: Values will always be indexed as [0, 1, 2, …, size - 1]. Only allowed to access values by index in the range [0, size - 1].

Use cases: Wherever you would use an array as a list (not concerned with keys). A more efficient alternative to `SplDoublyLinkedList` and `SplFixedArray`.',
'Ds\Set' => 'A Set is a sequence of unique values. This implementation uses the same hash table as `Ds\Map`, where values are used as keys and the mapped value is ignored.',
'Ds\Stack' => 'A Stack is a “last in, first out” or “LIFO” collection that only allows access to the value at the top of the structure and iterates in that order, destructively.

Uses a `Ds\Vector` internally.',
'Ds\Vector' => 'A Vector is a sequence of values in a contiguous buffer that grows and shrinks automatically. It’s the most efficient sequential structure because a value’s index is a direct mapping to its index in the buffer, and the growth factor isn\'t bound to a specific multiple or exponent.',
'EmptyIterator' => 'The EmptyIterator class for an empty iterator.',
'Ev' => 'Ev is a static class providing access to the default loop and to some common operations.',
'EvCheck' => '`EvPrepare` and `EvCheck` watchers are usually used in pairs. `EvPrepare` watchers get invoked before the process blocks, `EvCheck` afterwards.

It is not allowed to call `EvLoop::run` or similar methods or functions that enter the current event loop from either `EvPrepare` or `EvCheck` watchers. Other loops than the current one are fine, however. The rationale behind this is that one don\'t need to check for recursion in those watchers, i.e. the sequence will always be: `EvPrepare` -&gt; blocking -&gt; `EvCheck` , so having a watcher of each kind they will always be called in pairs bracketing the blocking call.

The main purpose is to integrate other event mechanisms into *libev* and their use is somewhat advanced. They could be used, for example, to track variable changes, implement custom watchers, integrate net-snmp or a coroutine library and lots more. They are also occasionally useful to cache some data and want to flush it before blocking.

It is recommended to give `EvCheck` watchers highest( `Ev::MAXPRI` ) priority, to ensure that they are being run before any other watchers after the poll (this doesn’t matter for `EvPrepare` watchers).

Also, `EvCheck` watchers should not activate/feed events. While *libev* fully supports this, they might get executed before other `EvCheck` watchers did their job.',
'EvChild' => '`EvChild` watchers trigger when the process receives a `SIGCHLD` in response to some child status changes (most typically when a child dies or exits). It is permissible to install an `EvChild` watcher after the child has been forked(which implies it might have already exited), as long as the event loop isn\'t entered(or is continued from a watcher), i.e. forking and then immediately registering a watcher for the child is fine, but forking and registering a watcher a few event loop iterations later or in the next callback invocation is not.

It is allowed to register `EvChild` watchers in the *default loop* only.',
'EvEmbed' => 'Used to embed one event loop into another.',
'Event' => '`Event` class represents and event firing on a file descriptor being ready to read from or write to; a file descriptor becoming ready to read from or write to(edge-triggered I/O only); a timeout expiring; a signal occurring; a user-triggered event.

Every event is associated with `EventBase` . However, event will never fire until it is *added* (via `Event::add` ). An added event remains in *pending* state until the registered event occurs, thus turning it to *active* state. To handle events user may register a callback which is called when event becomes active. If event is configured *persistent* , it remains pending. If it is not persistent, it stops being pending when it\'s callback runs. `Event::del` method *deletes* event, thus making it non-pending. By means of `Event::add` method it could be added again.',
'EventBase' => '`EventBase` class represents libevent\'s event base structure. It holds a set of events and can poll to determine which events are active.

Each event base has a *method* , or a *backend* that it uses to determine which events are ready. The recognized methods are: `select` , `poll` , `epoll` , `kqueue` , `devpoll` , `evport` and `win32` .

To configure event base to use, or avoid specific backend `EventConfig` class can be used.',
'EventBuffer' => '`EventBuffer` represents Libevent\'s "evbuffer", an utility functionality for buffered I/O.

Event buffers are meant to be generally useful for doing the "buffer" part of buffered network I/O.',
'EventBufferEvent' => 'Represents Libevent\'s buffer event.

Usually an application wants to perform some amount of data buffering in addition to just responding to events. When we want to write data, for example, the usual pattern looks like:

This buffered I/O pattern is common enough that Libevent provides a generic mechanism for it. A "buffer event" consists of an underlying transport (like a socket), a read buffer, and a write buffer. Instead of regular events, which give callbacks when the underlying transport is ready to be read or written, a buffer event invokes its user-supplied callbacks when it has read or written enough data.',
'EventConfig' => 'Represents configuration structure which could be used in construction of the `EventBase` .',
'EventDnsBase' => 'Represents Libevent\'s DNS base structure. Used to resolve DNS asynchronously, parse configuration files like resolv.conf etc.',
'EventHttp' => 'Represents HTTP server.',
'EventHttpConnection' => 'Represents an HTTP connection.',
'EventHttpRequest' => 'Represents an HTTP request.',
'EventListener' => 'Represents a connection listener.',
'EventSslContext' => 'Represents `SSL_CTX` structure. Provides methods and properties to configure the SSL context.',
'EventUtil' => '`EventUtil` is a singleton with supplimentary methods and constants.',
'EvFork' => 'Fork watchers are called when a `fork()` was detected (usually because whoever signalled *libev* about it by calling `EvLoop::fork` ). The invocation is done before the event loop blocks next and before `EvCheck` watchers are being called, and only in the child after the fork. Note, that if whoever calling `EvLoop::fork` calls it in the wrong process, the fork handlers will be invoked, too.',
'EvIdle' => '`EvIdle` watchers trigger events when no other events of the same or higher priority are pending ( `EvPrepare` , `EvCheck` and other `EvIdle` watchers do not count as receiving *events* ).

Thus, as long as the process is busy handling sockets or timeouts(or even signals) of the same or higher priority it will not be triggered. But when the process is in idle(or only lower-priority watchers are pending), the `EvIdle` watchers are being called once per event loop iteration - until stopped, that is, or the process receives more events and becomes busy again with higher priority stuff.

Apart from keeping the process non-blocking(which is a useful on its own sometimes), `EvIdle` watchers are a good place to do *"pseudo-background processing"* , or delay processing stuff to after the event loop has handled all outstanding events.

The most noticeable effect is that as long as any *idle* watchers are active, the process will *not* block when waiting for new events.',
'EvIo' => '`EvIo` watchers check whether a file descriptor(or socket, or a stream castable to numeric file descriptor) is readable or writable in each iteration of the event loop, or, more precisely, when reading would not block the process and writing would at least be able to write some data. This behaviour is called *level-triggering* because events are kept receiving as long as the condition persists. To stop receiving events just stop the watcher.

The number of read and/or write event watchers per fd is unlimited. Setting all file descriptors to non-blocking mode is also usually a good idea(but not required).

Another thing to watch out for is that it is quite easy to receive false readiness notifications, i.e. the callback might be called with `Ev::READ` but a subsequent *read()* will actually block because there is no data. It is very easy to get into this situation. Thus it is best to always use non-blocking I/O: An extra *read()* returning `EAGAIN` (or similar) is far preferable to a program hanging until some data arrives.

If for some reason it is impossible to run the fd in non-blocking mode, then separately re-test whether a file descriptor is really ready. Some people additionally use `SIGALRM` and an interval timer, just to be sure thry won\'t block infinitely.

Always consider using non-blocking mode.',
'EvLoop' => 'Represents an event loop that is always distinct from the *default loop* . Unlike the *default loop* , it cannot handle `EvChild` watchers.

Having threads we have to create a loop per thread, and use the *default loop* in the parent thread.

The *default event loop* is initialized automatically by *Ev* . It is accessible via methods of the `Ev` class, or via `EvLoop::defaultLoop` method.',
'EvPeriodic' => 'Periodic watchers are also timers of a kind, but they are very versatile.

Unlike `EvTimer` , `EvPeriodic` watchers are not based on real time(or relative time, the physical time that passes) but on wall clock time(absolute time, calendar or clock). The difference is that wall clock time can run faster or slower than real time, and time jumps are not uncommon(e.g. when adjusting it).

`EvPeriodic` watcher can be configured to trigger after some specific point in time. For example, if an `EvPeriodic` watcher is configured to trigger *"in 10 seconds"* (e.g. `EvLoop::now` + `10.0` , i.e. an absolute time, not a delay), and the system clock is reset to *January of the previous year* , then it will take a year or more to trigger the event (unlike an `EvTimer` , which would still trigger roughly `10` seconds after starting it as it uses a relative timeout).

As with timers, the callback is guaranteed to be invoked only when the point in time where it is supposed to trigger has passed. If multiple timers become ready during the same loop iteration then the ones with earlier time-out values are invoked before ones with later time-out values (but this is no longer true when a callback calls `EvLoop::run` recursively).',
'EvPrepare' => 'Class EvPrepare

EvPrepare and EvCheck watchers are usually used in pairs. EvPrepare watchers get invoked before the process blocks,
EvCheck afterwards.

It is not allowed to call EvLoop::run() or similar methods or functions that enter the current event loop from either
EvPrepare or EvCheck watchers. Other loops than the current one are fine, however. The rationale behind this is that
one don\'t need to check for recursion in those watchers, i.e. the sequence will always be: EvPrepare -> blocking ->
EvCheck, so having a watcher of each kind they will always be called in pairs bracketing the blocking call.

The main purpose is to integrate other event mechanisms into libev and their use is somewhat advanced. They could be
used, for example, to track variable changes, implement custom watchers, integrate net-snmp or a coroutine library
and lots more. They are also occasionally useful to cache some data and want to flush it before blocking.

It is recommended to give EvCheck watchers highest (Ev::MAXPRI) priority, to ensure that they are being run before
any other watchers after the poll (this doesn’t matter for EvPrepare watchers).

Also, EvCheck watchers should not activate/feed events. While libev fully supports this, they might get executed
before other EvCheck watchers did their job.',
'EvSignal' => '`EvSignal` watchers will trigger an event when the process receives a specific signal one or more times. Even though signals are very asynchronous, *libev* will try its best to deliver signals synchronously, i.e. as part of the normal event processing, like any other event.

There is no limit for the number of watchers for the same signal, but only within the same loop, i.e. one can watch for `SIGINT` in the default loop and for `SIGIO` in another loop, but it is not allowed to watch for `SIGINT` in both the default loop and another loop at the same time. At the moment, `SIGCHLD` is permanently tied to the default loop.

If possible and supported, *libev* will install its handlers with `SA_RESTART` (or equivalent) behaviour enabled, so system calls should not be unduly interrupted. In case of a problem with system calls getting interrupted by signals, all the signals can be blocked in an `EvCheck` watcher and unblocked in a `EvPrepare` watcher.',
'EvStat' => '`EvStat` monitors a file system path for attribute changes. It calls *stat()* on that path in regular intervals(or when the OS signals it changed) and sees if it changed compared to the last time, invoking the callback if it did.

The path does not need to exist: changing from "path exists" to "path does not exist" is a status change like any other. The condition "path does not exist" is signified by the `\'nlink\'` item being 0(returned by `EvStat::attr` method).

The path must not end in a slash or contain special components such as `\'.\'` or `..` . The path should be absolute: if it is relative and the working directory changes, then the behaviour is undefined.

Since there is no portable change notification interface available, the portable implementation simply calls *stat()* regularly on the path to see if it changed somehow. For this case a recommended polling interval can be specified. If one specifies a polling interval of `0.0 ` (highly recommended) then a suitable, unspecified default value will be used(which could be expected to be around 5 seconds, although this might change dynamically). *libev* will also impose a minimum interval which is currently around `0.1` , but that’s usually overkill.

This watcher type is not meant for massive numbers of `EvStat` watchers, as even with OS-supported change notifications, this can be resource-intensive.',
'EvTimer' => '`EvTimer` watchers are simple relative timers that generate an event after a given time, and optionally repeating in regular intervals after that.

The timers are based on real time, that is, if one registers an event that times out after an hour and resets the system clock to *January last year* , it will still time out after(roughly) one hour. "Roughly" because detecting time jumps is hard, and some inaccuracies are unavoidable.

The callback is guaranteed to be invoked only after its timeout has passed (not at, so on systems with very low-resolution clocks this might introduce a small delay). If multiple timers become ready during the same loop iteration then the ones with earlier time-out values are invoked before ones of the same priority with later time-out values (but this is no longer true when a callback calls `EvLoop::run` recursively).

The timer itself will do a best-effort at avoiding drift, that is, if a timer is configured to trigger every `10` seconds, then it will normally trigger at exactly `10` second intervals. If, however, the script cannot keep up with the timer because it takes longer than those `10` seconds to do) the timer will not fire more than once per event loop iteration.',
'EvWatcher' => '`EvWatcher` is a base class for all watchers( `EvCheck` , `EvChild` etc.). Since `EvWatcher` \'s constructor is abstract , one can\'t(and don\'t need to) create EvWatcher objects directly.',
'FANNConnection' => '`FANNConnection` is used for the neural network connection. The objects of this class are used in `fann_get_connection_array` and `fann_set_weight_array`.',
'FFI' => 'FFI class provides access to a simple way to call native functions,
access native variables and create/access data structures defined
in C language.',
'FFI\CData' => 'Proxy object that provides access to compiled structures.',
'FFI\CType' => 'Class containing C type information.',
'FFI\Exception' => 'Class Exception',
'FFI\ParserException' => 'Class ParserException',
'FilesystemIterator' => 'The Filesystem iterator',
'FilterIterator' => 'This abstract iterator filters out unwanted values. This class should be extended to implement custom iterator filters. The `FilterIterator::accept` must be implemented in the subclass.',
'finfo' => 'This class provides an object oriented interface into the fileinfo functions.',
'GearmanClient' => 'Represents a class for connecting to a Gearman job server and making requests to perform some function on provided data. The function performed must be one registered by a Gearman worker and the data passed is opaque to the job server.',
'GearmanException' => 'Class: GearmanException',
'GearmanJob' => 'Class: GearmanJob',
'GearmanTask' => 'Class: GearmanTask',
'GearmanWorker' => 'Class: GearmanWorker',
'GEOSGeometry' => 'Class GEOSGeometry',
'GEOSWKBReader' => 'Class GEOSWKBReader',
'GEOSWKBWriter' => 'Class GEOSWKBWriter',
'GEOSWKTReader' => 'Class GEOSWKTReader',
'GEOSWKTWriter' => 'Class GEOSWKTWriter',
'GlobIterator' => 'Iterates through a file system in a similar fashion to `glob`.',
'GmagickException' => 'GmagickException class',
'GMP' => 'A GMP number. These objects support overloaded arithmetic, bitwise and comparison operators.',
'gnupg' => 'GNUPG Encryption Class',
'gridObj' => 'The grid is always embedded inside a layer object defined as
a grid (layer->connectiontype = MS_GRATICULE)
(for more docs : https://github.com/mapserver/mapserver/wiki/MapServerGrid)
A layer can become a grid layer by adding a grid object to it using :
ms_newGridObj(layerObj layer)
$oLayer = ms_newlayerobj($oMap);
$oLayer->set("name", "GRID");
ms_newgridobj($oLayer);
$oLayer->grid->set("labelformat", "DDMMSS");',
'Grpc\Call' => 'Class Call',
'Grpc\CallCredentials' => 'Class CallCredentials',
'Grpc\Channel' => 'Class Channel',
'Grpc\ChannelCredentials' => 'Class ChannelCredentials',
'Grpc\Server' => 'Class Server',
'Grpc\ServerCredentials' => 'Class ServerCredentials',
'Grpc\Timeval' => 'Class Timeval',
'HaruAnnotation' => 'Haru PDF Annotation Class.',
'HaruDestination' => 'Haru PDF Destination Class.',
'HaruDoc' => 'Haru PDF Document Class.',
'HaruEncoder' => 'Haru PDF Encoder Class.',
'HaruException' => 'Haru PDF Exception Class.',
'HaruFont' => 'Haru PDF Font Class.',
'HaruImage' => 'Haru PDF Image Class.',
'HaruOutline' => 'Haru PDF Outline Class.',
'HaruPage' => 'Haru PDF Page Class.',
'hashTableObj' => 'Instance of hashTableObj is always embedded inside the `classObj`_,
`layerObj`_, `mapObj`_ and `webObj`_. It is uses a read only.
$hashTable = $oLayer->metadata;
$key = null;
while ($key = $hashTable->nextkey($key))
echo "Key: ".$key." value: ".$hashTable->get($key)."<br/>";',
'http\Client' => 'The HTTP client. See http\Client\Curl’s options which is the only driver currently supported.',
'http\Client\Curl\User' => 'Interface to an user event loop implementation for http\Client::configure()\'s $use_eventloop option.',
'http\Client\Request' => 'The http\Client\Request class provides an HTTP message implementation tailored to represent a request message to be sent by the client.

See http\Client::enqueue().',
'http\Client\Response' => 'The http\Client\Response class represents an HTTP message the client returns as answer from a server to an http\Client\Request.',
'http\Cookie' => 'A class representing a list of cookies with specific attributes.',
'http\Encoding\Stream' => 'Base class for encoding stream implementations.',
'http\Encoding\Stream\Debrotli' => 'A [brotli](https://brotli.org) decoding stream.',
'http\Encoding\Stream\Dechunk' => 'A stream decoding data encoded with chunked transfer encoding.',
'http\Encoding\Stream\Deflate' => 'A deflate stream supporting deflate, zlib and gzip encodings.',
'http\Encoding\Stream\Enbrotli' => 'A [brotli](https://brotli.org) encoding stream.',
'http\Encoding\Stream\Inflate' => 'A inflate stream supporting deflate, zlib and gzip encodings.',
'http\Env' => 'The http\Env class provides static methods to manipulate and inspect the server’s current request’s HTTP environment',
'http\Env\Request' => 'The http\Env\Request class\' instances represent the server’s current HTTP request.

See http\Message for inherited members.',
'http\Env\Response' => 'Class Response

The http\Env\Response class\' instances represent the server’s current HTTP response.

See http\Message for inherited members.',
'http\Env\Url' => 'URL class using the HTTP environment by default.

Always adds http\Url::FROM_ENV to the $flags constructor argument. See also http\Url.',
'http\Exception' => 'The http extension\'s Exception interface.

Use it to catch any Exception thrown by pecl/http.

The individual exception classes extend their equally named native PHP extensions, if such exist, and implement this empty interface. For example the http\Exception\BadMethodCallException extends SPL\'s BadMethodCallException.',
'http\Exception\BadConversionException' => 'A bad conversion (e.g. character conversion) was encountered.',
'http\Exception\BadHeaderException' => 'A bad HTTP header was encountered.',
'http\Exception\BadMessageException' => 'A bad HTTP message was encountered.',
'http\Exception\BadMethodCallException' => 'A method was called on an object, which was in an invalid or unexpected state.',
'http\Exception\BadQueryStringException' => 'A bad querystring was encountered.',
'http\Exception\BadUrlException' => 'A bad HTTP URL was encountered.',
'http\Exception\InvalidArgumentException' => 'One or more invalid arguments were passed to a method.',
'http\Exception\RuntimeException' => 'A generic runtime exception.',
'http\Exception\UnexpectedValueException' => 'An unexpected value was encountered.',
'http\Header' => 'The http\Header class provides methods to manipulate, match, negotiate and serialize HTTP headers.',
'http\Header\Parser' => 'The parser which is underlying http\Header and http\Message.',
'http\Message' => 'The message class builds the foundation for any request and response message.

See http\Client\Request and http\Client\Response, as well as http\Env\Request and http\Env\Response.',
'http\Message\Body' => 'The message body, represented as a PHP (temporary) stream.',
'http\Message\Parser' => 'The parser which is underlying http\Message.',
'http\Params' => 'Parse, interpret and compose HTTP (header) parameters.',
'http\QueryString' => 'The http\QueryString class provides versatile facilities to retrieve, use and manipulate query strings and form data.',
'http\Url' => 'The http\Url class provides versatile means to parse, construct and manipulate URLs.',
'imageObj' => 'Instances of imageObj are always created by the `mapObj`_ class methods.',
'InfiniteIterator' => 'The `InfiniteIterator` allows one to infinitely iterate over an iterator without having to manually rewind the iterator upon reaching its end.',
'IntlBreakIterator' => 'A “break iterator” is an ICU object that exposes methods for locating boundaries in text (e.g. word or sentence boundaries). The PHP `IntlBreakIterator` serves as the base class for all types of ICU break iterators. Where extra functionality is available, the intl extension may expose the ICU break iterator with suitable subclasses, such as `IntlRuleBasedBreakIterator` or `IntlCodePointBreakIterator`.

This class implements `Traversable`. Traversing an `IntlBreakIterator` yields non-negative integer values representing the successive locations of the text boundaries, expressed as UTF-8 code units (byte) counts, taken from the beginning of the text (which has the location `0`). The keys yielded by the iterator simply form the sequence of natural numbers `{0, 1, 2, …}`.',
'IntlChar' => '`IntlChar` provides access to a number of utility methods that can be used to access information about Unicode characters.

The methods and constants adhere closely to the names and behavior used by the underlying ICU library.',
'IntlCodePointBreakIterator' => 'This break iterator identifies the boundaries between UTF-8 code points.',
'IntlException' => 'This class is used for generating exceptions when errors occur inside intl functions. Such exceptions are only generated when intl.use_exceptions is enabled.',
'IntlIterator' => 'This class represents iterator objects throughout the intl extension whenever the iterator cannot be identified with any other object provided by the extension. The distinct iterator object used internally by the `foreach` construct can only be obtained (in the relevant part here) from objects, so objects of this class serve the purpose of providing the hook through which this internal object can be obtained. As a convenience, this class also implements the `Iterator` interface, allowing the collection of values to be navigated using the methods defined in that interface. Both these methods and the internal iterator objects provided to `foreach` are backed by the same state (e.g. the position of the iterator and its current value).

Subclasses may provide richer functionality.',
'IntlPartsIterator' => 'Objects of this class can be obtained from `IntlBreakIterator` objects. While the break iterators provide a sequence of boundary positions when iterated, `IntlPartsIterator` objects provide, as a convenience, the text fragments comprehended between two successive boundaries.

The keys may represent the offset of the left boundary, right boundary, or they may just the sequence of non-negative integers. See `IntlBreakIterator::getPartsIterator`.',
'IntlRuleBasedBreakIterator' => 'A subclass of `IntlBreakIterator` that encapsulates ICU break iterators whose behavior is specified using a set of rules. This is the most common kind of break iterators.

These rules are described in the ICU Boundary Analysis User Guide.',
'InvalidArgumentException' => 'Exception thrown if an argument is not of the expected type.',
'IteratorIterator' => 'This iterator wrapper allows the conversion of anything that is Traversable into an Iterator. It is important to understand that most classes that do not implement Iterators have reasons as most likely they do not allow the full Iterator feature set. If so, techniques should be provided to prevent misuse, otherwise expect exceptions or fatal errors.',
'JsonException' => 'Exception thrown if `JSON_THROW_ON_ERROR` option is set for `json_encode` or `json_decode`.',
'JsonSerializable' => 'Objects implementing `JsonSerializable` can customize their JSON representation when encoded with `json_encode`.',
'Judy' => 'The Judy class implements the ArrayAccess interface and the Iterator interface. This class, once instantiated, can be accessed like a PHP array.

A PHP Judy object (or Judy Array) can be one of the following type : Judy::BITSET Judy::INT_TO_INT Judy::INT_TO_MIXED Judy::STRING_TO_INT Judy::STRING_TO_MIXED

Judy array example ]]>',
'KTaglib_ID3v2_AttachedPictureFrame' => 'Represents an ID3v2 frame that can hold a picture.',
'KTaglib_ID3v2_Frame' => 'The base class for ID3v2 frames. ID3v2 tags are separated in various specialized frames. Some frames can exists multiple times.',
'KTaglib_ID3v2_Tag' => 'Represents and ID3v2 tag. It provides a list of ID3v2 frames and can be used to add and remove additional frames.',
'KTaglib_MPEG_Audioproperties' => 'Represents the audio properties of a MPEG file, like length, bitrate or samplerate.',
'KTaglib_MPEG_File' => 'Represents an MPEG file. MPEG files can have ID3v1, ID3v2 tags and audio properties.',
'KTaglib_Tag' => 'Base class for ID3v1 or ID3v2 tags',
'labelObj' => 'labelObj are always embedded inside other classes.',
'Lapack' => 'LAPACK is written in Fortran 90 and provides routines for solving systems of simultaneous linear equations, least-squares solutions of linear systems of equations, eigenvalue problems, and singular value problems. This extension wraps the LAPACKE C bindings to allow access to several processes exposed by the library. Most functions work with arrays of arrays, representing rectangular matrices in row major order - so a two by two matrix [1 2; 3 4] would be array(array(1, 2), array(3, 4)).

All of the functions are called statically, for example $eig = Lapack::eigenvalues($a);',
'lapackexception' => 'Exception thrown when an error is caught in the LAPACK functions',
'layerObj' => 'Layer Objects can be returned by the `mapObj`_ class, or can be
created using:
A second optional argument can be given to ms_newLayerObj() to create
the new layer as a copy of an existing layer. If a layer is given as
argument then all members of a this layer will be copied in the new
layer created.',
'legendObj' => 'Instances of legendObj are always are always embedded inside the `mapObj`_.',
'LengthException' => 'Exception thrown if a length is invalid.',
'libXMLError' => 'Contains various information about errors thrown by libxml. The error codes are described within the official xmlError API documentation.',
'LimitIterator' => 'The `LimitIterator` class allows iteration over a limited subset of items in an `Iterator`.',
'Locale' => 'Examples of identifiers include: en-US (English, United States) zh-Hant-TW (Chinese, Traditional Script, Taiwan) fr-CA, fr-FR (French for Canada and France respectively)',
'LogicException' => 'Exception that represents error in the program logic. This kind of exception should lead directly to a fix in your code.',
'LuaClosure' => 'LuaClosure is a wrapper class for LUA_TFUNCTION which could be return from calling to Lua function.',
'LuaSandbox' => 'The LuaSandbox class creates a Lua environment and allows for execution of Lua code.',
'LuaSandboxError' => 'Base class for LuaSandbox exceptions',
'LuaSandboxErrorError' => 'Exception thrown when Lua encounters an error inside an error handler.',
'LuaSandboxFatalError' => 'Uncatchable LuaSandbox exceptions.

These may not be caught inside Lua using `pcall()` or `xpcall()`.',
'LuaSandboxFunction' => 'Represents a Lua function, allowing it to be called from PHP.

A LuaSandboxFunction may be obtained as a return value from Lua, as a parameter passed to a callback from Lua, or by using `LuaSandbox::wrapPhpFunction`, `LuaSandbox::loadString`, or `LuaSandbox::loadBinary`.',
'LuaSandboxMemoryError' => 'Exception thrown when Lua cannot allocate memory.',
'LuaSandboxRuntimeError' => 'Catchable LuaSandbox runtime exceptions.

These may be caught inside Lua using `pcall()` or `xpcall()`.',
'LuaSandboxSyntaxError' => 'Exception thrown when Lua code cannot be parsed.',
'LuaSandboxTimeoutError' => 'Exception thrown when the configured CPU time limit is exceeded.',
'Memcache' => 'Represents a connection to a set of memcache servers.',
'Memcached' => 'Represents a connection to a set of memcached servers.',
'Mongo' => 'A connection between PHP and MongoDB.

This class extends `MongoClient` and provides access to several deprecated methods.

For backwards compatibility, it also defaults the `"w"` option of its constructor argument to `0`, which does not require write operations to be acknowledged by the server. See `MongoClient::__construct` for more information.',
'MongoBinData' => 'An object that can be used to store or retrieve binary data from the database.

The maximum size of a single object that can be inserted into the database is 16MB. For data that is larger than this (movies, music, Henry Kissinger\'s autobiography), use `MongoGridFS`. For data that is smaller than 16MB, you may find it easier to embed it within the document using `MongoBinData`.

For example, to embed an image in a document, one could write:

This class contains a type field, which currently gives no additional functionality in the PHP driver or the database. There are seven predefined types, which are defined as class constants below. For backwards compatibility, the PHP driver uses `MongoBinData::BYTE_ARRAY` as the default; however, this may change to `MongoBinData::GENERIC` in the future. Users are encouraged to specify a type in `MongoBinData::__construct`.',
'MongoClient' => 'A connection manager for PHP and MongoDB.

This class is used to create and manage connections. A typical use is: `MongoClient` basic usage foo; // get the database named "foo" ?> ]]>

See `MongoClient::__construct` and the section on connecting for more information about creating connections.',
'MongoCode' => 'Represents JavaScript code for the database.

MongoCode objects are composed of two parts: a string of code and an optional scope. The string of code must be valid JavaScript. The scope is a associative array of variable name/value pairs.',
'MongoCollection' => 'Represents a MongoDB collection.

Collection names can use any character in the ASCII set. Some valid collection names are "", "...", "my collection", and "*&amp;#@".

User-defined collection names cannot contain the $ symbol. There are certain system collections which use a $ in their names (e.g., local.oplog.$main), but it is a reserved character. If you attempt to create and use a collection with a $ in the name, MongoDB will assert.',
'MongoCommandCursor' => 'A command cursor is similar to a `MongoCursor` except that you use it for iterating through the results of a database command instead of a normal query. Command cursors are useful for iterating over large result sets that might exceed the document size limit (currently 16MB) of a single `MongoDB::command` response.

While you can create command cursors using `MongoCommandCursor::__construct` or the `MongoCommandCursor::createFromDocument` factory method, you will generally want to use command-specific helpers such as `MongoCollection::aggregateCursor`.

Note that the cursor does not "contain" the database command\'s results; it just manages iteration through them. Thus, if you print a cursor (f.e. with `var_dump` or `print_r`), you will see the cursor object but not the result documents.',
'MongoConnectionException' => 'Thrown when the driver fails to connect to the database.

There are a number of possible error messages to help you diagnose the connection problem. These are:

If the error message is not listed above, it is probably an error from the C socket, and you can search the web for its usual cause.',
'MongoCursor' => 'A cursor is used to iterate through the results of a database query. For example, to query the database and see all results, you could do: `MongoCursor` basic usage find(); var_dump(iterator_to_array($cursor)); ?> ]]>

You don\'t generally create cursors using the `MongoCursor` constructor, you get a new cursor by calling `MongoCollection::find` (as shown above).

Suppose that, in the example above, `$collection` was a 50GB collection. We certainly wouldn\'t want to load that into memory all at once, which is what a cursor is for: allowing the client to access the collection in dribs and drabs.

If we have a large result set, we can iterate through it, loading a few megabytes of results into memory at a time. For example, we could do: Iterating over `MongoCursor` find(); foreach ($cursor as $doc) { // do something to each document } ?> ]]> This will go through each document in the collection, loading and garbage collecting documents as needed.

Note that this means that a cursor does not "contain" the database results, it just manages them. Thus, if you print a cursor (with, say, `var_dump` or `print_r`), you\'ll just get the cursor object, not your documents. To get the documents themselves, you can use one of the methods shown above.',
'MongoCursorException' => 'Caused by accessing a cursor incorrectly or a error receiving a reply. Note that this can be thrown by any database request that receives a reply, not just queries. Writes, commands, and any other operation that sends information to the database and waits for a response can throw a `MongoCursorException`. The only exception is `new MongoClient()` (creating a new connection), which will only throw `MongoConnectionException`s.

This returns a specific error message to help diagnose the problem and a numeric error code associated with the cause of the exception.

For example, suppose you tried to insert two documents with the same _id: insert(array("_id" => 1), array("w" => 1)); $collection->insert(array("_id" => 1), array("w" => 1)); } catch (MongoCursorException $e) { echo "error message: ".$e->getMessage()."\n"; echo "error code: ".$e->getCode()."\n"; } ?> ]]> This would produce output like: Note that the MongoDB error code (11000) is used for the PHP error code. The PHP driver uses the "native" error code wherever possible.

The following is a list of common errors, codes, and causes. Exact errors are in italics, errors where the message can vary are described in obliques.',
'MongoCursorInterface' => 'Interface for cursors, which can be used to iterate through results of a database query or command. This interface is implemented by the `MongoCursor` and `MongoCommandCursor` classes.',
'MongoCursorTimeoutException' => 'Caused by a query timing out. You can set the length of time to wait before this exception is thrown by calling `MongoCursor::timeout` on the cursor or setting `MongoCursor::$timeout`. The static variable is useful for queries such as database commands and `MongoCollection::findOne`, both of which implicitly use cursors.',
'MongoDate' => 'Represent date objects for the database. This class should be used to save dates to the database and to query for dates. For example:

MongoDB stores dates as milliseconds past the epoch. This means that dates *do not* contain timezone information. Timezones must be stored in a separate field if needed. Second, this means that any precision beyond milliseconds will be lost when the document is sent to/from the database.',
'MongoDB' => 'Instances of this class are used to interact with a database. To get a database: Selecting a database selectDB("example"); ?> ]]> Database names can use almost any character in the ASCII range. However, they cannot contain " ", "." or be the empty string. The name "system" is also reserved.

A few unusual, but valid, database names: "null", "[x,y]", "3", "\"", "/".

Unlike collection names, database names may contain "$".',
'MongoDB\BSON\Binary' => 'BSON type for binary data (i.e. array of bytes). Binary values also have a subtype, which is used to indicate what kind of data is in the byte array. Subtypes from zero to 127 are predefined or reserved. Subtypes from 128-255 are user-defined.',
'MongoDB\BSON\BinaryInterface' => 'This interface is implemented by `MongoDB\BSON\Binary` but may also be used for type-hinting and userland classes.',
'MongoDB\BSON\DBPointer' => 'BSON type for the "DBPointer" type. This BSON type is deprecated, and this class can not be instantiated. It will be created from a BSON DBPointer type while converting BSON to PHP, and can also be converted back into BSON while storing documents in the database.',
'MongoDB\BSON\Decimal128' => 'BSON type for the Decimal128 floating-point format, which supports numbers with up to 34 decimal digits (i.e. significant digits) and an exponent range of −6143 to +6144.

Unlike the double BSON type (i.e. `float` in PHP), which only stores an approximation of the decimal values, the decimal data type stores the exact value. For example, `MongoDB\BSON\Decimal128(\'9.99\')` has a precise value of 9.99 where as a double 9.99 would have an approximate value of 9.9900000000000002131628….',
'MongoDB\BSON\Decimal128Interface' => 'This interface is implemented by `MongoDB\BSON\Decimal128` but may also be used for type-hinting and userland classes.',
'MongoDB\BSON\Int64' => 'BSON type for a 64-bit integer. This class cannot be instantiated and is only created during BSON decoding when a 64-bit integer cannot be represented as a PHP integer on a 32-bit platform. Versions of the driver before 1.5.0 would throw an exception when attempting to decode a 64-bit integer on a 32-bit platform.

During BSON encoding, objects of this class will convert back to a 64-bit integer type. This allows 64-bit integers to be roundtripped through a 32-bit PHP environment without any loss of precision. The __toString() method allows the 64-bit integer value to be accessed as a string.',
'MongoDB\BSON\Javascript' => 'BSON type for Javascript code. An optional scope document may be specified that maps identifiers to values and defines the scope in which the code should be evaluated by the server.',
'MongoDB\BSON\JavascriptInterface' => 'This interface is implemented by `MongoDB\BSON\Javascript` but may also be used for type-hinting and userland classes.',
'MongoDB\BSON\MaxKey' => 'Special BSON type which compares higher than all other possible BSON element values.',
'MongoDB\BSON\MaxKeyInterface' => 'This interface is implemented by `MongoDB\BSON\MaxKey` but may also be used for type-hinting and userland classes.',
'MongoDB\BSON\MinKey' => 'Special BSON type which compares lower than all other possible BSON element values.',
'MongoDB\BSON\MinKeyInterface' => 'This interface is implemented by `MongoDB\BSON\MinKey` but may also be used for type-hinting and userland classes.',
'MongoDB\BSON\ObjectId' => 'BSON type for an ObjectId. The value consists of 12 bytes, where the first four bytes are a timestamp that reflect the ObjectId\'s creation. Specifically, the value consists of:

In MongoDB, each document stored in a collection requires a unique `_id` field that acts as a primary key. If an inserted document omits the `_id` field, the driver automatically generates an ObjectId for the `_id` field.

Using ObjectIds for the `_id` field provides the following additional benefits:',
'MongoDB\BSON\ObjectIdInterface' => 'This interface is implemented by `MongoDB\BSON\ObjectId` but may also be used for type-hinting and userland classes.',
'MongoDB\BSON\Persistable' => 'Classes may implement this interface to take advantage of automatic ODM (object document mapping) behavior in the driver. During serialization, the driver will inject a `__pclass` property containing the PHP class name into the data returned by `MongoDB\BSON\Serializable::bsonSerialize`. During unserialization, the same `__pclass` property will then be used to infer the PHP class (independent of any type map configuration) to be constructed before `MongoDB\BSON\Unserializable::bsonUnserialize` is invoked. See the PHP manual\'s section on mongodb.persistence for additional information.',
'MongoDB\BSON\Regex' => 'BSON type for a regular expression pattern and optional flags.',
'MongoDB\BSON\RegexInterface' => 'This interface is implemented by `MongoDB\BSON\Regex` but may also be used for type-hinting and userland classes.',
'MongoDB\BSON\Serializable' => 'Classes that implement this interface may return data to be serialized as a BSON array or document in lieu of the object\'s public properties.',
'MongoDB\BSON\Symbol' => 'BSON type for the "Symbol" type. This BSON type is deprecated, and this class can not be instantiated. It will be created from a BSON symbol type while converting BSON to PHP, and can also be converted back into BSON while storing documents in the database.',
'MongoDB\BSON\Timestamp' => 'Represents a BSON timestamp, The value consists of a 4-byte timestamp (i.e. seconds since the epoch) and a 4-byte increment.',
'MongoDB\BSON\TimestampInterface' => 'This interface is implemented by `MongoDB\BSON\Timestamp` but may also be used for type-hinting and userland classes.',
'MongoDB\BSON\Type' => 'Abstract base interface that should not be implemented directly.',
'MongoDB\BSON\Undefined' => 'BSON type for the "Undefined" type. This BSON type is deprecated, and this class can not be instantiated. It will be created from a BSON undefined type while converting BSON to PHP, and can also be converted back into BSON while storing documents in the database.',
'MongoDB\BSON\Unserializable' => 'Classes that implement this interface may be specified in a type map for unserializing BSON arrays and documents (both root and embedded).',
'MongoDB\BSON\UTCDateTime' => 'Represents a BSON date. The value is a 64-bit integer that represents the number of milliseconds since the Unix epoch (Jan 1, 1970). Negative values represent dates before 1970.',
'MongoDB\BSON\UTCDateTimeInterface' => 'This interface is implemented by `MongoDB\BSON\UTCDateTime` but may also be used for type-hinting and userland classes.',
'MongoDB\Driver\BulkWrite' => 'The `MongoDB\Driver\BulkWrite` collects one or more write operations that should be sent to the server. After adding any number of insert, update, and delete operations, the collection may be executed via `MongoDB\Driver\Manager::executeBulkWrite`.

Write operations may either be ordered (default) or unordered. Ordered write operations are sent to the server, in the order provided, for serial execution. If a write fails, any remaining operations will be aborted. Unordered operations are sent to the server in an arbitrary order where they may be executed in parallel. Any errors that occur are reported after all operations have been attempted.',
'MongoDB\Driver\ClientEncryption' => 'The `MongoDB\Driver\ClientEncryption` class handles creation of data keys for client-side encryption, as well as manually encrypting and decrypting values.',
'MongoDB\Driver\Command' => 'The `MongoDB\Driver\Command` class is a value object that represents a database command.

To provide Command Helpers the `MongoDB\Driver\Command` object should be composed.',
'MongoDB\Driver\Cursor' => 'The `MongoDB\Driver\Cursor` class encapsulates the results of a MongoDB command or query and may be returned by `MongoDB\Driver\Manager::executeCommand` or `MongoDB\Driver\Manager::executeQuery`, respectively.',
'MongoDB\Driver\CursorId' => 'The `MongoDB\Driver\CursorID` class is a value object that represents a cursor ID. Instances of this class are returned by `MongoDB\Driver\Cursor::getId`.',
'MongoDB\Driver\CursorInterface' => 'This interface is implemented by `MongoDB\Driver\Cursor` but may also be used for type-hinting and userland classes.',
'MongoDB\Driver\Exception\AuthenticationException' => 'Thrown when the driver fails to authenticate with the server.',
'MongoDB\Driver\Exception\BulkWriteException' => 'Thrown when a bulk write operation fails.',
'MongoDB\Driver\Exception\CommandException' => 'Thrown when a command fails.',
'MongoDB\Driver\Exception\ConnectionException' => 'Base class for exceptions thrown when the driver fails to establish a database connection.',
'MongoDB\Driver\Exception\ConnectionTimeoutException' => 'Thrown when the driver fails to establish a database connection within a specified time limit (connectTimeoutMS) or server selection fails (serverSelectionTimeoutMS).',
'MongoDB\Driver\Exception\EncryptionException' => 'Base class for exceptions thrown during client-side encryption.',
'MongoDB\Driver\Exception\Exception' => 'Common interface for all driver exceptions. This may be used to catch only exceptions originating from the driver itself.',
'MongoDB\Driver\Exception\ExecutionTimeoutException' => 'Thrown when a query or command fails to complete within a specified time limit (e.g. maxTimeMS).',
'MongoDB\Driver\Exception\InvalidArgumentException' => 'Thrown when a driver method is given invalid arguments (e.g. invalid option types).',
'MongoDB\Driver\Exception\LogicException' => 'Thrown when the driver is incorrectly used (e.g. rewinding a cursor).',
'MongoDB\Driver\Exception\RuntimeException' => 'Thrown when the driver encounters a runtime error (e.g. internal error from libmongoc).',
'MongoDB\Driver\Exception\ServerException' => 'Base class for exceptions thrown by the server. The code of this exception and its subclasses will correspond to the original error code from the server.',
'MongoDB\Driver\Exception\SSLConnectionException' => 'Thrown when the driver fails to establish an SSL connection with the server.',
'MongoDB\Driver\Exception\UnexpectedValueException' => 'Thrown when the driver encounters an unexpected value (e.g. during BSON serialization or deserialization).',
'MongoDB\Driver\Exception\WriteException' => 'Base class for exceptions thrown by a failed write operation. The exception encapsulates a `MongoDB\Driver\WriteResult` object.',
'MongoDB\Driver\Manager' => 'The `MongoDB\Driver\Manager` is the main entry point to the extension. It is responsible for maintaining connections to MongoDB (be it standalone server, replica set, or sharded cluster).

No connection to MongoDB is made upon instantiating the Manager. This means the `MongoDB\Driver\Manager` can always be constructed, even though one or more MongoDB servers are down.

Any write or query can throw connection exceptions as connections are created lazily. A MongoDB server may also become unavailable during the life time of the script. It is therefore important that all actions on the Manager to be wrapped in try/catch statements.',
'MongoDB\Driver\Monitoring\CommandFailedEvent' => 'The `MongoDB\Driver\Monitoring\CommandFailedEvent` class encapsulates information about a failed command.',
'MongoDB\Driver\Monitoring\CommandStartedEvent' => 'The `MongoDB\Driver\Monitoring\CommandStartedEvent` class encapsulates information about a started command.',
'MongoDB\Driver\Monitoring\CommandSubscriber' => 'Classes may implement this interface to register an event subscriber that is notified for each started, successful, and failed command event. See the PHP manual\'s section on mongodb.tutorial.apm for additional information.',
'MongoDB\Driver\Monitoring\CommandSucceededEvent' => 'The `MongoDB\Driver\Monitoring\CommandSucceededEvent` class encapsulates information about a successful command.',
'MongoDB\Driver\Monitoring\Subscriber' => 'Base interface for event subscribers. This is used for type-hinting `MongoDB\Driver\Monitoring\addSubscriber` and `MongoDB\Driver\Monitoring\removeSubscriber` and should not be implemented directly.',
'MongoDB\Driver\Query' => 'The `MongoDB\Driver\Query` class is a value object that represents a database query.',
'MongoDB\Driver\ReadConcern' => '`MongoDB\Driver\ReadConcern` controls the level of isolation for read operations for replica sets and replica set shards. This option requires MongoDB 3.2 or later.',
'MongoDB\Driver\ReadPreference' => 'Class ReadPreference',
'MongoDB\Driver\Session' => 'The `MongoDB\Driver\Session` class represents a client session and is returned by `MongoDB\Driver\Manager::startSession`. Commands, queries, and write operations may then be associated the session.',
'MongoDB\Driver\WriteConcern' => '`MongoDB\Driver\WriteConcern` describes the level of acknowledgement requested from MongoDB for write operations to a standalone `mongod` or to replica sets or to sharded clusters. In sharded clusters, `mongos` instances will pass the write concern on to the shards.',
'MongoDB\Driver\WriteConcernError' => 'The `MongoDB\Driver\WriteConcernError` class encapsulates information about a write concern error and may be returned by `MongoDB\Driver\WriteResult::getWriteConcernError`.',
'MongoDB\Driver\WriteError' => 'The `MongoDB\Driver\WriteError` class encapsulates information about a write error and may be returned as an array element from `MongoDB\Driver\WriteResult::getWriteErrors`.',
'MongoDB\Driver\WriteResult' => 'The `MongoDB\Driver\WriteResult` class encapsulates information about an executed `MongoDB\Driver\BulkWrite` and may be returned by `MongoDB\Driver\Manager::executeBulkWrite`.',
'MongoDBRef' => 'This class can be used to create lightweight links between objects in different collections.

*Motivation*: Suppose we need to refer to a document in another collection. The easiest way is to create a field in the current document. For example, if we had a "people" collection and an "addresses" collection, we might want to create a link between each person document and an address document: Linking documents people; $addresses = $db->addresses; $myAddress = array("line 1" => "123 Main Street", "line 2" => null, "city" => "Springfield", "state" => "Vermont", "country" => "USA"); // save the address $addresses->insert($myAddress); // save a person with a reference to the address $me = array("name" => "Fred", "address" => $myAddress[\'_id\']); $people->insert($me); ?> ]]>

Then, later on, we can find the person\'s address by querying the "addresses" collection with the `MongoId` we saved in the "people" collection.

Suppose now that we have a more general case, where we don\'t know which collection (or even which database) contains the referenced document. `MongoDBRef` is a good choice for this case, as it is a common format that all of the drivers and the database understand.

If each person had a list of things they liked which could come from multiple collections, such as "hobbies", "sports", "books", etc., we could use `MongoDBRef`s to keep track of what "like" went with what collection: Creating MongoDBRef links selectCollection("people"); // model trains are in the "hobbies" collection $trainRef = MongoDBRef::create("hobbies", $modelTrains[\'_id\']); // soccer is in the "sports" collection $soccerRef = MongoDBRef::create("sports", $soccer[\'_id\']); // now we\'ll know what collections the items in the "likes" array came from when // we retrieve this document $people->insert(array("name" => "Fred", "likes" => array($trainRef, $soccerRef))); ?> ]]>

Database references can be thought of as hyperlinks: they give the unique address of another document, but they do not load it or automatically follow the link/reference.

A database reference is just a normal associative array, not an instance of `MongoDBRef`, so this class is a little different than the other data type classes. This class contains exclusively static methods for manipulating database references.',
'MongoDeleteBatch' => 'Constructs a batch of DELETE operations. See `MongoWriteBatch`.',
'MongoDuplicateKeyException' => 'Thrown when attempting to insert a document into a collection which already contains the same values for the unique keys.',
'MongoException' => 'Default Mongo exception.

This covers a bunch of different error conditions that may eventually be moved to more specific exceptions, but will always extend `MongoException`.',
'MongoExecutionTimeoutException' => 'Thrown when a operation times out server side (i.e. in MongoDB).

To configure the operation timeout threshold, use `MongoCursor::maxTimeMS` or the `"maxTimeMS"` command option.',
'MongoGridFS' => 'Utilities for storing and retrieving files from the database.

GridFS is a storage specification all supported drivers implement. Basically, it defines two collections: `files`, for file metadata, and `chunks`, for file content. If the file is large, it will automatically be split into smaller chunks and each chunk will be saved as a document in the chunks collection.

Each document in the files collection contains the filename, upload date, and md5 hash. It also contains a unique `_id` field, which can be used to query the chunks collection for the file\'s content. Each document in the chunks collection contains a chunk of binary data, a `files_id` field that matches its file\'s `_id`, and the position of this chunk in the overall file.

For example, the files document is something like: 123456789, "filename" => "foo.txt", "chunkSize" => 3, "length" => 12); ?> ]]> and the chunks documents look like: 123456789, "n" => 0, "data" => new MongoBinData("abc")); array("files_id" => 123456789, "n" => 1, "data" => new MongoBinData("def")); array("files_id" => 123456789, "n" => 2, "data" => new MongoBinData("ghi")); array("files_id" => 123456789, "n" => 3, "data" => new MongoBinData("jkl")); ?> ]]> Of course, the default chunk size is thousands of bytes, but that makes an unwieldy example.',
'MongoGridFSCursor' => 'Cursor for database file results.',
'MongoGridFSException' => 'Thrown when there are errors reading or writing files to or from the database.',
'MongoGridFSFile' => 'A database file object.',
'MongoId' => 'A unique identifier created for database objects. If an object is inserted into the database without an _id field, an _id field will be added to it with a `MongoId` instance as its value. If the data has a naturally occurring unique field (e.g. username or timestamp) it is fine to use this as the _id field instead, and it will not be replaced with a `MongoId`.

Instances of the `MongoId` class fulfill the role that autoincrementing does in a relational database: to provide a unique key if the data does not naturally have one. Autoincrementing does not work well with a sharded database, as it is difficult to determine the next number in the sequence. This class fulfills the constraints of quickly generating a value that is unique across shards.

Each MongoId is 12 bytes (making its string form 24 hexadecimal characters). The first four bytes are a timestamp, the next three are a hash of the client machine\'s hostname, the next two are the two least significant bytes of the process id running the script, and the last three bytes are an incrementing value.

`MongoId`s are serializable/unserializable. Their serialized form is similar to their string form:',
'MongoInsertBatch' => 'Constructs a batch of INSERT operations. See `MongoWriteBatch`.',
'MongoInt32' => 'The class can be used to save 32-bit integers to the database on a 64-bit system.',
'MongoInt64' => 'The class can be used to save 64-bit integers to the database on a 32-bit system.',
'MongoLog' => 'Logging can be used to get detailed information about what the driver is doing. Logging is disabled by default, but this class allows you to activate specific levels of logging for various parts of the driver. Some examples:',
'MongoMaxKey' => '`MongoMaxKey` is an special type used by the database that compares greater than all other possible BSON values. Thus, if a query is sorted by a given field in ascending order, any document with a `MongoMaxKey` as its value will be returned last.

`MongoMaxKey` has no associated fields, methods, or constants. It is merely the "greatest" value that can be represented in the database.',
'MongoMinKey' => '`MongoMinKey` is an special type used by the database that compares less than all other possible BSON values. Thus, if a query is sorted by a given field in ascending order, any document with a `MongoMinKey` as its value will be returned first.

`MongoMinKey` has no associated fields, methods, or constants. It is merely the "smallest" value that can be represented in the database.',
'MongoProtocolException' => 'When talking to MongoDB 2.6.0, and later, certain operations (such as writes) may throw MongoProtocolException when the response from the server did not make sense - for example during network failure (we could read the entire response) or data corruption.

This exception is also thrown when attempting to talk newer protocols then the server supports, for example using the `MongoWriteBatch` when talking to a MongoDB server prior to 2.6.0.',
'MongoRegex' => 'This class can be used to create regular expressions. Typically, these expressions will be used to query the database and find matching strings. More unusually, they can be saved to the database and retrieved.

Regular expressions consist of four parts. First a `/` as starting delimiter, then the pattern, another `/` and finally a string containing flags.

Regular expression pattern

MongoDB recognizes six regular expression flags:',
'MongoResultException' => 'The MongoResultException is thrown by several command helpers (such as `MongoCollection::findAndModify`) in the event of failure. The original result document is available through `MongoResultException::getDocument`.',
'MongoTimestamp' => '`MongoTimestamp` is an internal type used by MongoDB for replication and sharding. It consists of a 4-byte timestamp (i.e. seconds since the epoch) and a 4-byte increment. This type is not intended for storing time or date values (e.g. a "createdAt" field on a document).',
'MongoUpdateBatch' => 'Constructs a batch of UPDATE operations. See `MongoWriteBatch`.',
'MongoWriteBatch' => 'MongoWriteBatch is the base class for the `MongoInsertBatch`, `MongoUpdateBatch` and `MongoDeleteBatch` classes.

MongoWriteBatch allows you to "batch up" multiple operations (of same type) and shipping them all to MongoDB at the same time. This can be especially useful when operating on many documents at the same time to reduce roundtrips.

Prior to version 1.5.0 of the driver it was possible to use `MongoCollection::batchInsert`, however, as of 1.5.0 that method is now discouraged.

Note: This class is only available when talking to MongoDB 2.6.0 (and later) servers. It will throw `MongoProtocolException` if attempting to use it on older MongoDB servers.',
'MongoWriteConcernException' => 'MongoWriteConcernException is thrown when a write fails. See the PHP manual\'s section on mongo.writeconcerns for how to set failure thresholds.

Prior to MongoDB 2.6.0, the getLastError command would determine whether a write failed.',
'MultipleIterator' => 'An Iterator that sequentially iterates over all attached iterators',
'Mutex' => 'The static methods contained in the Mutex class provide direct access to Posix Mutex functionality.',
'mysql_xdevapi\Client' => 'Provides access to the connection pool.',
'mysql_xdevapi\Table' => 'Provides access to the table through INSERT/SELECT/UPDATE/DELETE statements.',
'mysql_xdevapi\TableDelete' => 'A statement for delete operations on Table.',
'mysql_xdevapi\TableInsert' => 'A statement for insert operations on Table.',
'mysql_xdevapi\TableSelect' => 'A statement for record retrieval operations on a Table.',
'mysql_xdevapi\TableUpdate' => 'A statement for record update operations on a Table.',
'mysqli' => 'Represents a connection between PHP and a MySQL database.',
'mysqli_driver' => 'MySQLi Driver.',
'mysqli_result' => 'Represents the result set obtained from a query against the database.

`Changelog`*',
'mysqli_sql_exception' => 'The mysqli exception handling class.',
'mysqli_stmt' => 'Represents a prepared statement.',
'mysqli_warning' => 'Represents a MySQL warning.',
'NoRewindIterator' => 'This iterator cannot be rewound.',
'Normalizer' => 'The Unicode Consortium has defined a number of normalization forms reflecting the various needs of applications: Normalization Form D (NFD) - Canonical Decomposition Normalization Form C (NFC) - Canonical Decomposition followed by Canonical Composition Normalization Form KD (NFKD) - Compatibility Decomposition Normalization Form KC (NFKC) - Compatibility Decomposition followed by Canonical Composition The different forms are defined in terms of a set of transformations on the text, transformations that are expressed by both an algorithm and a set of data files.',
'NumberFormatter' => 'For currencies you can use currency format type to create a formatter that returns a string with the formatted number and the appropriate currency sign. Of course, the NumberFormatter class is unaware of exchange rates so, the number output is the same regardless of the specified currency. This means that the same number has different monetary values depending on the currency locale. If the number is 9988776.65 the results will be: 9 988 776,65 € in France 9.988.776,65 € in Germany $9,988,776.65 in the United States',
'OAuth' => 'The OAuth extension provides a simple interface to interact with data providers using the OAuth HTTP specification to protect private resources.',
'OAuthException' => 'This exception is thrown when exceptional errors occur while using the OAuth extension and contains useful debugging information.',
'OAuthProvider' => 'Manages an OAuth provider class.

See also an external in-depth tutorial titled Writing an OAuth Provider Service, which takes a hands-on approach to providing this service. There are also OAuth provider examples within the OAuth extensions sources.',
'OCI-Collection' => 'OCI8 Collection functionality.',
'OCI-Lob' => 'OCI8 LOB functionality for large binary (BLOB) and character (CLOB) objects.',
'OCICollection' => 'OCI8 Collection functionality.',
'OCILob' => 'OCI8 LOB functionality for large binary (BLOB) and character (CLOB) objects.',
'OuterIterator' => 'Classes implementing `OuterIterator` can be used to iterate over iterators.',
'OutOfBoundsException' => 'Exception thrown if a value is not a valid key. This represents errors that cannot be detected at compile time.',
'OutOfRangeException' => 'Exception thrown when an illegal index was requested. This represents errors that should be detected at compile time.',
'outputformatObj' => 'Instance of outputformatObj is always embedded inside the `mapObj`_.
It is uses a read only.
No constructor available (coming soon, see ticket 979)',
'OverflowException' => 'Exception thrown when adding an element to a full container.',
'parallel\Channel' => 'An unbuffered channel will block on calls to `parallel\Channel::send` until there is a receiver, and block on calls to `parallel\Channel::recv` until there is a sender. This means an unbuffered channel is not only a way to share data among tasks but also a simple method of synchronization.

An unbuffered channel is the fastest way to share data among tasks, requiring the least copying.',
'parallel\Events' => 'The Event loop monitors the state of sets of futures and or channels (targets) in order to perform read (`parallel\Future::value`, `parallel\Channel::recv`) and write (`parallel\Channel::send`) operations as the targets become available and the operations may be performed without blocking the event loop.',
'parallel\Events\Event' => 'When an Event is returned, Event::$object shall be removed from the loop that returned it, should the event be a write event the `Input` for Event::$source shall also be removed.',
'parallel\Events\Input' => 'An Input object is a container for data that the `parallel\Events` object will write to `parallel\Channel` objects as they become available. Multiple event loops may share an Input container - parallel does not verify the contents of the container when it is set as the input for a `parallel\Events` object.',
'parallel\Future' => 'A Future represents the return value or uncaught exception from a task, and exposes an API for cancellation.

The behaviour of a future also allows it to be used as a simple synchronization point even where the task does not return a value explicitly.',
'parallel\Runtime' => 'Each runtime represents a single PHP thread, the thread is created (and bootstrapped) upon construction. The thread then waits for tasks to be scheduled: Scheduled tasks will be executed FIFO and then the thread will resume waiting until more tasks are scheduled, or it\'s closed, killed, or destroyed by the normal scoping rules of PHP objects.',
'parallel\Sync' => 'The `parallel\Sync` class provides access to low level synchronization primitives, mutex, condition variables, and allows the implementation of semaphores.

Synchronization for most applications is much better implemented using channels, however, in some cases authors of low level code may find it useful to be able to access these lower level mechanisms.',
'ParentIterator' => 'This extended `FilterIterator` allows a recursive iteration using `RecursiveIteratorIterator` that only shows those elements which have children.',
'Parle\ErrorInfo' => 'The class represents detailed error information as supplied by `Parle\Parser::errorInfo`',
'Parle\Lexer' => 'Single state lexer class. Lexemes can be defined on the fly. If the particular lexer instance is meant to be used with `Parle\Parser`, the token IDs need to be taken from there. Otherwise, arbitrary token IDs can be supplied. This lexer can give a certain performance advantage over `Parle\RLexer`, if no multiple states are required. Note, that `Parle\RParser` is not compatible with this lexer.',
'Parle\Parser' => 'Parser class. Rules can be defined on the fly. Once finalized, a `Parle\Lexer` instance is required to deliver the token stream.',
'Parle\RLexer' => 'Multistate lexer class. Lexemes can be defined on the fly. If the particular lexer instance is meant to be used with `Parle\RParser`, the token IDs need to be taken from there. Otherwise, arbitrary token IDs can be supplied. Note, that `Parle\Parser` is not compatible with this lexer.',
'Parle\RParser' => 'Parser class. Rules can be defined on the fly. Once finalized, a `Parle\RLexer` instance is required to deliver the token stream.',
'Parle\Stack' => '`Parle\Stack` is a LIFO stack. The elements are inserted and and removed only from one end.',
'Parle\Token' => 'This class represents a token. Lexer returns instances of this class.',
'ParseError' => '`ParseError` is thrown when an error occurs while parsing PHP code, such as when `eval` is called.',
'PDO' => 'Represents a connection between PHP and a database server.',
'PDOException' => 'Represents an error raised by PDO. You should not throw a `PDOException` from your own code. See Exceptions for more information about Exceptions in PHP.',
'PDOStatement' => 'Represents a prepared statement and, after the statement is executed, an associated result set.',
'Phar' => 'The Phar class provides a high-level interface to accessing and creating phar archives.',
'PharData' => 'The PharData class provides a high-level interface to accessing and creating non-executable tar and zip archives. Because these archives do not contain a stub and cannot be executed by the phar extension, it is possible to create and manipulate regular zip and tar files using the PharData class even if `phar.readonly` php.ini setting is `1`.',
'PharException' => 'The PharException class provides a phar-specific exception class for try/catch blocks.',
'PharFileInfo' => 'The PharFileInfo class provides a high-level interface to the contents and attributes of a single file within a phar archive.',
'php_user_filter' => 'Children of this class are passed to `stream_filter_register`.',
'pht\AtomicInteger' => 'The `pht\AtomicInteger` class is currently the only supported atomic value. It allows for an integer to be safely passed around between, and manipulated, by multiple threads. The methods exposed by this class do not need mutex locking, since they will acquire the internal mutex lock implicitly. `pht\AtomicInteger::lock` and `pht\AtomicInteger::unlock` are still exposed, however, for when multiple operations involving the same `pht\AtomicInteger` object need to be grouped together.

The mutex locks of the atomic values are reentrant safe.',
'pht\HashTable' => 'The `pht\HashTable` class is one of the Inter-Thread Communication (ITC) data structures exposed by pht. It can be safely passed around between threads, and manipulated by multiple threads using the mutex locks that have been packed in with the data structure. It is reference-counted across threads, and so it does not need to be explicitly destroyed.

The `pht\HashTable` class enables for array access upon its objects (along with the `isset` and `unset` functions). The `ArrayAccess` interface is not explicitly implemented, however, because it is only needed for such abilities by userland classes.',
'pht\Queue' => 'The `pht\Queue` class is one of the Inter-Thread Communication (ITC) data structures exposed by pht. It can be safely passed around between threads, and manipulated by multiple threads using the mutex locks that have been packed in with the data structure. It is reference-counted across threads, and so it does not need to be explicitly destroyed.',
'pht\Runnable' => 'The `pht\Runnable` interface enforces the implementation of a run() method on classes that should be threaded. This method acts as the entry point of the threaded class.',
'pht\Thread' => 'The `pht\Thread` class abstracts away a native thread. It has an internal task queue, where the methods `pht\Thread::addClassTask`, `pht\Thread::addFunctionTask`, and `pht\Thread::addFileTask` push new tasks onto this queue. Invoking the `pht\Thread::start` method will cause the new thread to be spawned, where it will then begin working through the task queue. A thread may be reused for any number of tasks.',
'pht\Threaded' => 'The `pht\Threaded` interface is an internal interface used by the Inter-Thread Communication (ITC) data structures (`pht\HashTable`, `pht\Queue`, and `pht\Vector`). It allows those data structures to be threaded and ensures that the mutex locking API (`pht\Threaded::lock` and `pht\Threaded::unlock`) is implemented by each of the ITC data structures. It is not implementable by userland classes (since standalone mutex locks are not exposed).',
'pht\Vector' => 'The `pht\Vector` class is one of the Inter-Thread Communication (ITC) data structures exposed by pht. It can be safely passed around between threads, and manipulated by multiple threads using the mutex locks that have been packed in with the data structure. It is reference-counted across threads, and so is does not need to be explicitly destroyed.

The `pht\Vector` class enables for array access upon its objects (along with the `isset` and `unset` functions). The `ArrayAccess` interface is not explicitly implemented, however, because it is only needed for such abilities by userland classes.',
'Pool' => 'A Pool is a container for, and controller of, an adjustable number of Workers.

Pooling provides a higher level abstraction of the Worker functionality, including the management of references in the way required by pthreads.',
'querymapObj' => 'Instances of querymapObj are always are always embedded inside the
`mapObj`_.',
'QuickHashIntHash' => 'This class wraps around a hash containing integer numbers, where the values are also integer numbers. Hashes are also available as implementation of the ArrayAccess interface.

Hashes can also be iterated over with foreach as the Iterator interface is implemented as well. The order of which elements are returned in is not guaranteed.',
'QuickHashIntSet' => 'This class wraps around a set containing integer numbers.

Sets can also be iterated over with foreach as the Iterator interface is implemented as well. The order of which elements are returned in is not guaranteed.',
'QuickHashIntStringHash' => 'This class wraps around a hash containing integer numbers, where the values are strings. Hashes are also available as implementation of the ArrayAccess interface.

Hashes can also be iterated over with foreach as the Iterator interface is implemented as well. The order of which elements are returned in is not guaranteed.',
'QuickHashStringIntHash' => 'This class wraps around a hash containing strings, where the values are integer numbers. Hashes are also available as implementation of the ArrayAccess interface.

Hashes can also be iterated over with foreach as the Iterator interface is implemented as well. The order of which elements are returned in is not guaranteed.',
'RangeException' => 'Exception thrown to indicate range errors during program execution. Normally this means there was an arithmetic error other than under/overflow. This is the runtime version of `DomainException`.',
'RarArchive' => 'This class represents a RAR archive, which may be formed by several volumes (parts) and which contains a number of RAR entries (i.e., files, directories and other special objects such as symbolic links).

Objects of this class can be traversed, yielding the entries stored in the respective RAR archive. Those entries can also be obtained through `RarArchive::getEntry` and `RarArchive::getEntries`.',
'RarEntry' => 'A RAR entry, representing a directory or a compressed file inside a RAR archive.',
'RarException' => 'This class serves two purposes: it is the type of the exceptions thrown by the RAR extension functions and methods and it allows, through static methods to query and define the error behaviour of the extension, i.e., whether exceptions are thrown or only warnings are emitted.

The following error codes are used:',
'RdKafka\Conf' => 'Configuration reference: https://github.com/edenhill/librdkafka/blob/master/CONFIGURATION.md',
'RdKafka\TopicConf' => 'Configuration reference: https://github.com/edenhill/librdkafka/blob/master/CONFIGURATION.md',
'rectObj' => 'rectObj are sometimes embedded inside other objects.  New ones can
also be created with:',
'RecursiveArrayIterator' => 'This iterator allows to unset and modify values and keys while iterating over Arrays and Objects in the same way as the `ArrayIterator`. Additionally it is possible to iterate over the current iterator entry.',
'RecursiveCachingIterator' => '...',
'RecursiveDirectoryIterator' => 'The `RecursiveDirectoryIterator` provides an interface for iterating recursively over filesystem directories.',
'RecursiveFilterIterator' => 'This abstract iterator filters out unwanted values for a `RecursiveIterator`. This class should be extended to implement custom filters. The `RecursiveFilterIterator::accept` must be implemented in the subclass.',
'RecursiveIterator' => 'Classes implementing `RecursiveIterator` can be used to iterate over iterators recursively.',
'RecursiveIteratorIterator' => 'Can be used to iterate through recursive iterators.',
'RecursiveRegexIterator' => 'This recursive iterator can filter another recursive iterator via a regular expression.',
'RecursiveTreeIterator' => 'Allows iterating over a `RecursiveIterator` to generate an ASCII graphic tree.',
'referenceMapObj' => 'Instances of referenceMapObj are always embedded inside the `mapObj`_.',
'Reflection' => 'The reflection class.',
'ReflectionClass' => 'The `ReflectionClass` class reports information about a class.',
'ReflectionClassConstant' => 'The `ReflectionClassConstant` class reports information about a class constant.',
'ReflectionException' => 'The ReflectionException class.',
'ReflectionExtension' => 'The `ReflectionExtension` class reports information about an extension.',
'ReflectionFunction' => 'The `ReflectionFunction` class reports information about a function.',
'ReflectionFunctionAbstract' => 'A parent class to `ReflectionFunction`, read its description for details.',
'ReflectionGenerator' => 'The `ReflectionGenerator` class reports information about a generator.',
'ReflectionMethod' => 'The `ReflectionMethod` class reports information about a method.',
'ReflectionObject' => 'The `ReflectionObject` class reports information about an `object`.',
'ReflectionParameter' => 'The `ReflectionParameter` class retrieves information about function\'s or method\'s parameters.

To introspect function parameters, first create an instance of the `ReflectionFunction` or `ReflectionMethod` classes and then use their `ReflectionFunctionAbstract::getParameters` method to retrieve an array of parameters.',
'ReflectionProperty' => 'The `ReflectionProperty` class reports information about classes properties.',
'ReflectionType' => 'The `ReflectionType` class reports information about a function\'s return type.',
'Reflector' => '`Reflector` is an interface implemented by all exportable Reflection classes.',
'RegexIterator' => 'This iterator can be used to filter another iterator based on a regular expression.',
'RRDCreator' => 'Class for creation of RRD database file.',
'RRDGraph' => 'Class for exporting data from RRD database to image file.',
'RRDUpdater' => 'Class for updating RDD database file.',
'RuntimeException' => 'Exception thrown if an error which can only be found on runtime occurs.',
'scalebarObj' => 'Instances of scalebarObj are always embedded inside the `mapObj`_.',
'SeekableIterator' => 'The Seekable iterator.',
'SessionHandler' => '`SessionHandler` is a special class that can be used to expose the current internal PHP session save handler by inheritance. There are seven methods which wrap the seven internal session save handler callbacks (open, close, read, write, destroy, gc and create_sid). By default, this class will wrap whatever internal save handler is set as defined by the session.save_handler configuration directive which is usually files by default. Other internal session save handlers are provided by PHP extensions such as SQLite (as sqlite), Memcache (as memcache), and Memcached (as memcached).

When a plain instance of `SessionHandler` is set as the save handler using `session_set_save_handler` it will wrap the current save handlers. A class extending from `SessionHandler` allows you to override the methods or intercept or filter them by calls the parent class methods which ultimately wrap the internal PHP session handlers.

This allows you, for example, to intercept the read and write methods to encrypt/decrypt the session data and then pass the result to and from the parent class. Alternatively one might chose to entirely override a method like the garbage collection callback gc.

Because the `SessionHandler` wraps the current internal save handler methods, the above example of encryption can be applied to any internal save handler without having to know the internals of the handlers.

To use this class, first set the save handler you wish to expose using session.save_handler and then pass an instance of `SessionHandler` or one extending it to `session_set_save_handler`.

Please note the callback methods of this class are designed to be called internally by PHP and are not meant to be called from user-space code. The return values are equally processed internally by PHP. For more information on the session workflow, please refer `session_set_save_handler`.',
'SessionHandlerInterface' => '`SessionHandlerInterface` is an interface which defines a prototype for creating a custom session handler. In order to pass a custom session handler to `session_set_save_handler` using its `OOP` invocation, the class must implement this interface.

Please note the callback methods of this class are designed to be called internally by PHP and are not meant to be called from user-space code.',
'SimpleXMLElement' => 'Represents an element in an XML document.',
'SimpleXMLIterator' => 'The SimpleXMLIterator provides recursive iteration over all nodes of a `SimpleXMLElement` object.',
'SNMP' => 'Represents SNMP session.',
'SNMPException' => 'Represents an error raised by SNMP. You should not throw a `SNMPException` from your own code. See Exceptions for more information about Exceptions in PHP.',
'SoapClient' => 'The SoapClient class provides a client for SOAP 1.1, SOAP 1.2 servers. It can be used in WSDL or non-WSDL mode.',
'SoapFault' => 'Represents a SOAP fault.',
'SoapHeader' => 'Represents a SOAP header.',
'SoapParam' => 'Represents parameter to a SOAP call.',
'SoapServer' => 'The SoapServer class provides a server for the SOAP 1.1 and SOAP 1.2 protocols. It can be used with or without a WSDL service description.',
'SoapVar' => 'A class representing a variable or object for use with SOAP services.',
'SolrClient' => 'Used to send requests to a Solr server. Currently, cloning and serialization of SolrClient instances is not supported.',
'SolrClientException' => 'An exception thrown when there is an error while making a request to the server from the client.',
'SolrCollapseFunction' => 'Class SolrCollapseFunction',
'SolrDisMaxQuery' => 'Version not present on php.net documentation, determined here by using PECL solr changelog:
https://pecl.php.net/package-changelog.php?package=solr&release=2.1.0 <br/>
Class SolrDisMaxQuery<br/>',
'SolrDocument' => 'Represents a Solr document retrieved from a query response.',
'SolrDocumentField' => 'This represents a field in a Solr document. All its properties are read-only.',
'SolrException' => 'This is the base class for all exception thrown by the Solr extension classes.',
'SolrGenericResponse' => 'Represents a response from the solr server.',
'SolrIllegalArgumentException' => 'This object is thrown when an illegal or invalid argument is passed to a method.',
'SolrIllegalOperationException' => 'This object is thrown when an illegal or unsupported operation is performed on an object.',
'SolrInputDocument' => 'This class represents a Solr document that is about to be submitted to the Solr index.',
'SolrMissingMandatoryParameterException' => 'Class SolrMissingMandatoryParameterException<br/>',
'SolrModifiableParams' => 'Represents a collection of name-value pairs sent to the Solr server during a request.',
'SolrObject' => 'This is an object whose properties can also by accessed using the array syntax. All its properties are read-only.',
'SolrParams' => 'Represents a collection of name-value pairs sent to the Solr server during a request.',
'SolrPingResponse' => 'Represents a response to a ping request to the server',
'SolrQuery' => 'Represents a collection of name-value pairs sent to the Solr server during a request.',
'SolrQueryResponse' => 'Represents a response to a query request.',
'SolrResponse' => 'Represents a response from the Solr server.',
'SolrServerException' => 'An exception thrown when there is an error produced by the Solr Server itself.',
'SolrUpdateResponse' => 'Represents a response to an update request.',
'SolrUtils' => 'Contains utility methods for retrieving the current extension version and preparing query phrases.

Also contains method for escaping query strings and parsing XML responses.',
'SphinxClient' => 'The SphinxClient class provides object-oriented interface to Sphinx.',
'SplBool' => 'The SplBool class is used to enforce strong typing of the bool type.',
'SplDoublyLinkedList' => 'The SplDoublyLinkedList class provides the main functionalities of a doubly linked list.',
'SplEnum' => 'SplEnum gives the ability to emulate and create enumeration objects natively in PHP.',
'SplFileInfo' => 'The SplFileInfo class offers a high-level object oriented interface to information for an individual file.',
'SplFileObject' => 'The SplFileObject class offers an object oriented interface for a file.',
'SplFixedArray' => 'The SplFixedArray class provides the main functionalities of array. The main differences between a SplFixedArray and a normal PHP array is that the SplFixedArray is of fixed length and allows only integers within the range as indexes. The advantage is that it allows a faster array implementation.',
'SplFloat' => 'The SplFloat class is used to enforce strong typing of the float type.',
'SplHeap' => 'The SplHeap class provides the main functionalities of a Heap.',
'SplInt' => 'The SplInt class is used to enforce strong typing of the integer type.',
'SplMaxHeap' => 'The SplMaxHeap class provides the main functionalities of a heap, keeping the maximum on the top.',
'SplMinHeap' => 'The SplMinHeap class provides the main functionalities of a heap, keeping the minimum on the top.',
'SplObjectStorage' => 'The SplObjectStorage class provides a map from objects to data or, by ignoring data, an object set. This dual purpose can be useful in many cases involving the need to uniquely identify objects.',
'SplObserver' => 'The `SplObserver` interface is used alongside `SplSubject` to implement the Observer Design Pattern.',
'SplPriorityQueue' => 'The SplPriorityQueue class provides the main functionalities of a prioritized queue, implemented using a max heap.',
'SplQueue' => 'The SplQueue class provides the main functionalities of a queue implemented using a doubly linked list.',
'SplStack' => 'The SplStack class provides the main functionalities of a stack implemented using a doubly linked list.',
'SplString' => 'The SplString class is used to enforce strong typing of the string type.',
'SplSubject' => 'The `SplSubject` interface is used alongside `SplObserver` to implement the Observer Design Pattern.',
'SplTempFileObject' => 'The SplTempFileObject class offers an object oriented interface for a temporary file.',
'SplType' => 'Parent class for all SPL types.',
'Spoofchecker' => 'This class is provided because Unicode contains large number of characters and incorporates the varied writing systems of the world and their incorrect usage can expose programs or systems to possible security attacks using characters similarity.

Provided methods allow to check whether an individual string is likely an attempt at confusing the reader (`spoof detection`), such as "pаypаl" spelled with Cyrillic \'а\' characters.',
'SQLite3' => 'A class that interfaces SQLite 3 databases.',
'SQLite3Result' => 'A class that handles result sets for the SQLite 3 extension.',
'SQLite3Stmt' => 'A class that handles prepared statements for the SQLite 3 extension.',
'SQLiteUnbuffered' => 'Represents an unbuffered SQLite result set. Unbuffered results sets are sequential, forward-seeking only.',
'Stackable' => 'Stackable is an alias of Threaded. This class name was used in pthreads until
version 2.0.0',
'Stomp' => 'Represents a connection between PHP and a Stomp compliant Message Broker.',
'StompException' => 'Represents an error raised by the stomp extension. See Exceptions for more information about Exceptions in PHP.',
'StompFrame' => 'Represents a message which was sent or received from a Stomp compliant Message Broker.',
'streamWrapper' => 'Allows you to implement your own protocol handlers and streams for use with all the other filesystem functions (such as `fopen`, `fread` etc.).

An instance of this class is initialized as soon as a stream function tries to access the protocol it is associated with.',
'StubTests\Parsers\Visitors\ParentConnector' => 'The visitor is required to provide "parent" attribute to nodes',
'styleObj' => 'Instances of styleObj are always embedded inside a `classObj`_ or `labelObj`_.',
'SVMException' => 'The exception object thrown on errors from the SVM and SVMModel classes.',
'SVMModel' => 'The SVMModel is the end result of the training process. It can be used to classify previously unseen data.',
'SWFAction' => 'SWFAction.',
'SWFBitmap' => 'SWFBitmap.',
'SWFButton' => 'SWFButton.',
'SWFDisplayItem' => 'SWFDisplayItem.',
'SWFFill' => 'The `SWFFill` object allows you to transform (scale, skew, rotate) bitmap and gradient fills.

`swffill` objects are created by the `SWFShape::addFill` method.',
'SWFFont' => 'The `SWFFont` object represent a reference to the font definition, for us with `SWFText::setFont` and `SWFTextField::setFont`.',
'SWFFontChar' => 'SWFFontChar.',
'SWFGradient' => 'SWFGradient.',
'SWFMorph' => 'The methods here are sort of weird. It would make more sense to just have newSWFMorph(shape1, shape2);, but as things are now, shape2 needs to know that it\'s the second part of a morph. (This, because it starts writing its output as soon as it gets drawing commands- if it kept its own description of its shapes and wrote on completion this and some other things would be much easier.)',
'SWFMovie' => '`SWFMovie` is a movie object representing an SWF movie.',
'SWFPrebuiltClip' => 'SWFPrebuiltClip.',
'SWFShape' => 'SWFShape.',
'SWFSound' => 'SWFSound.',
'SWFSoundInstance' => '`SWFSoundInstance` objects are returned by the `SWFSprite::startSound` and `SWFMovie::startSound` methods.',
'SWFSprite' => 'An `SWFSprite` is also known as a "movie clip", this allows one to create objects which are animated in their own timelines. Hence, the sprite has most of the same methods as the movie.',
'SWFText' => 'SWFText.',
'SWFTextField' => 'SWFTextField.',
'SWFVideoStream' => 'SWFVideoStream.',
'SyncEvent' => 'A cross-platform, native implementation of named and unnamed event objects. Both automatic and manual event objects are supported.

An event object waits, without polling, for the object to be fired/set. One instance waits on the event object while another instance fires/sets the event. Event objects are useful wherever a long-running process would otherwise poll a resource (e.g. checking to see if uploaded data needs to be processed).',
'SyncMutex' => 'A cross-platform, native implementation of named and unnamed countable mutex objects.

A mutex is a mutual exclusion object that restricts access to a shared resource (e.g. a file) to a single instance. Countable mutexes acquire the mutex a single time and internally track the number of times the mutex is locked. The mutex is unlocked as soon as it goes out of scope or is unlocked the same number of times that it was locked.',
'SyncReaderWriter' => 'A cross-platform, native implementation of named and unnamed reader-writer objects.

A reader-writer object allows many readers or one writer to access a resource. This is an efficient solution for managing resources where access will primarily be read-only but exclusive write access is occasionally necessary.',
'SyncSemaphore' => 'A cross-platform, native implementation of named and unnamed semaphore objects.

A semaphore restricts access to a limited resource to a limited number of instances. Semaphores differ from mutexes in that they can allow more than one instance to access a resource at one time while a mutex only allows one instance at a time.',
'SyncSharedMemory' => 'A cross-platform, native, consistent implementation of named shared memory objects.

Shared memory lets two separate processes communicate without the need for complex pipes or sockets. There are several integer-based shared memory implementations for PHP. Named shared memory is an alternative.

Synchronization objects (e.g. SyncMutex) are still required to protect most uses of shared memory.',
'Thread' => 'When the start method of a Thread is invoked, the run method code will be executed in separate Thread, in parallel.

After the run method is executed the Thread will exit immediately, it will be joined with the creating Thread at the appropriate time.',
'Threaded' => 'Threaded objects form the basis of pthreads ability to execute user code in parallel; they expose synchronization methods and various useful interfaces.

Threaded objects, most importantly, provide implicit safety for the programmer; all operations on the object scope are safe.',
'tidy' => 'An HTML node in an HTML file, as detected by tidy.',
'tidyNode' => 'An HTML node in an HTML file, as detected by tidy.',
'TokyoTyrant' => 'The main Tokyo Tyrant class',
'tokyotyrantexception' => 'TokyoTyrantException',
'TokyoTyrantIterator' => 'Provides an iterator for TokyoTyrant and TokyoTyrantTable objects. The iterator iterates over all keys and values in the database. TokyoTyrantIterator was added in version 0.2.0.',
'TokyoTyrantQuery' => 'This class is used to query the table databases',
'TokyoTyrantTable' => 'Provides an API to the table databases. A table database can be create using the following command: `ttserver -port 1979 /tmp/tt_table.tct`. In Tokyo Tyrant the table API is a schemaless database which can store arbitrary amount of key-value pairs under a single primary key.',
'Traversable' => 'Interface to detect if a class is traversable using `foreach`.

Abstract base interface that cannot be implemented alone. Instead it must be implemented by either `IteratorAggregate` or `Iterator`.',
'TypeError' => 'There are three scenarios where a `TypeError` may be thrown. The first is where the argument type being passed to a function does not match its corresponding declared parameter type. The second is where a value being returned from a function does not match the declared function return type. The third is where an invalid number of arguments are passed to a built-in PHP function (strict mode only).',
'UI\Area' => 'An Area represents a canvas which can be used to draw, and respond to mouse and key events.',
'UI\Control' => 'This is the closed base class for all UI Controls.',
'UI\Controls\Box' => 'A Box allows the arrangement of other controls',
'UI\Controls\Button' => 'Represents a labelled clickable button',
'UI\Controls\Check' => 'A Check is a labelled checkable box',
'UI\Controls\ColorButton' => 'A Color Button is a button which displays a color picker when clicked',
'UI\Controls\Combo' => 'A Combo control represents a list of options, like the familiar select HTML element.',
'UI\Controls\EditableCombo' => 'An Editable Combo is a Combo which allows the user to enter custom options',
'UI\Controls\Entry' => 'An Entry is a text entry control, suitable for entering plain text, passwords, or search terms.',
'UI\Controls\Form' => 'A Form is a control which allows the arrangement of other controls into a familiar layout (the form).',
'UI\Controls\Grid' => 'A Grid is a control which is allows the arrangement of children into a grid',
'UI\Controls\Group' => 'A Group is a titled container for child controls',
'UI\Controls\Label' => 'A Label is a single line of text, meant to identify, for the user, some element of the interface.',
'UI\Controls\MultilineEntry' => 'A Multiline Entry is a text entry control able to hold multiple lines of text, with or without wrapping.',
'UI\Controls\Picker' => 'A Picker represents a button which when clicked presents a native Date/Time/DateTime Picker interface to the user.',
'UI\Controls\Progress' => 'A Progress control is a familiar Progress bar: It represents progress as a percentage, with a possible range of 0 to 100 (inclusive).',
'UI\Controls\Radio' => 'A Radio is similar to the radio input type familiar from HTML',
'UI\Controls\Separator' => 'A Separator represents a control separator, it has no other function.',
'UI\Controls\Slider' => 'A Slider is a control which represents a range, and a current value in the range. The sliding element of the control (sometimes called the "thumb") reflects the value, and can be adjusted within the range.',
'UI\Controls\Spin' => 'A Spin box is a text box with an up-down control which changes the integer value in the box, within a defined range',
'UI\Controls\Tab' => 'A Tab can contain many pages of Controls, each with a title, each selectable by the user.',
'UI\Draw\Brush' => 'Represents a solid color brush',
'UI\Draw\Brush\Gradient' => 'Abstract for gradient brushes',
'UI\Draw\Color' => 'Represents RGBA colours, individual channels are accessible via public properties.',
'UI\Draw\Path' => 'A Draw Path guides a Draw Pen, telling the Pen where to draw on an Area.',
'UI\Draw\Pen' => 'The Pen is passed to the Area Draw event handler, it is used for clipping, filling, stroking, and writing to Draw Paths.',
'UI\Draw\Stroke' => 'Holds the configuration for the Pen to perform a stroke',
'UI\Draw\Text\Font' => 'Loads a described font',
'UI\Draw\Text\Font\Descriptor' => 'Describes a font',
'UI\Draw\Text\Layout' => 'A Text Layout represents the layout of text which will be drawn by the Pen',
'UI\Executor' => 'This facility schedules repetitive execution of a callback, useful for animations and other such activities.',
'UI\Menu' => 'Menus must be constructed before the first Window, and can be shown on any Window',
'UI\MenuItem' => 'Menu Items should only be created by the Menu',
'UI\Point' => 'Points are used throughout UI to represent co-ordinates on a screen, control, or area.',
'UI\Size' => 'Sizes are used throughout UI to represent the size of a screen, control, or area.',
'UI\Window' => 'Represents a UI Window',
'UnderflowException' => 'Exception thrown when performing an invalid operation on an empty container, such as removing an element.',
'UnexpectedValueException' => 'Exception thrown if a value does not match with a set of values. Typically this happens when a function calls another function and expects the return value to be of a certain type or value not including arithmetic or buffer related errors.',
'V8Js' => 'This is the core class for V8Js extension. Each instance created from this class has own context in which all JavaScript is compiled and executed.

See `V8Js::__construct` for more information.',
'VARIANT' => 'The VARIANT is COM\'s equivalent of the PHP zval; it is a structure that can contain a value with a range of different possible types. The VARIANT class provided by the COM extension allows you to have more control over the way that PHP passes values to and from COM.',
'Volatile' => 'The `Volatile` class is new to pthreads v3. Its introduction is a consequence of the new immutability semantics of `Threaded` members of `Threaded` classes. The `Volatile` class enables for mutability of its `Threaded` members, and is also used to store PHP arrays in `Threaded` contexts.',
'Vtiful\Kernel\Excel' => 'Create xlsx files and set cells and output xlsx files',
'Vtiful\Kernel\Format' => 'Create a cell format object',
'WeakRef' => 'The WeakRef class provides a gateway to objects without preventing the garbage collector from freeing those objects. It also provides a way to turn a weak reference into a strong one.',
'WeakReference' => 'Weak references allow the programmer to retain a reference to an
object which does not prevent the object from being destroyed.
They are useful for implementing cache like structures.',
'webObj' => 'Instances of webObj are always are always embedded inside the `mapObj`_.',
'wkhtmltox\Image\Converter' => 'Converts an HTML input into various image formats',
'wkhtmltox\PDF\Converter' => 'Converts an HTML input, or set of HTML inputs, into PDF output',
'wkhtmltox\PDF\Object' => 'Represents an HTML document, input to PDF converter',
'Worker' => 'Worker Threads have a persistent context, as such should be used over Threads in most cases.

When a Worker is started, the run method will be executed, but the Thread will not leave until one of the following conditions are met:

This means the programmer can reuse the context throughout execution; placing objects on the stack of the Worker will cause the Worker to execute the stacked objects run method.',
'XMLDiff\Base' => 'Base abstract class for all the comparison classes in the extension.',
'XMLReader' => 'The XMLReader extension is an XML Pull parser. The reader acts as a cursor going forward on the document stream and stopping at each node on the way.',
'Yaconf' => 'Yaconf is a configurations container, it parses INIT files, stores the result in PHP when PHP is started, the result lives with the whole PHP lifecycle.',
'Yaf\Action_Abstract' => '<p>A action can be defined in a separate file in Yaf(see \Yaf\Controller_Abstract). that is a action method can also be a <b>\Yaf\Action_Abstract</b> class.</P>
<br/>
<p>Since there should be a entry point which can be called by Yaf (as of PHP 5.3, there is a new magic method __invoke, but Yaf is not only works with PHP 5.3+, Yaf choose another magic method execute), you must implement the abstract method \Yaf\Action_Abstract::execute() in your custom action class.</p>',
'Yaf\Application' => '\Yaf\Application provides a bootstrapping facility for applications which provides reusable resources, common- and module-based bootstrap classes and dependency checking.
<br/>
<b>Note:</b>
<p>
\Yaf\Application implements the singleton pattern, and \Yaf\Application can not be serialized or un-serialized which will cause problem when you try to use PHPUnit to write some test case for Yaf.<br/>
You may use &#64;backupGlobals annotation of PHPUnit to control the backup and restore operations for global variables. thus can solve this problem.
</p>',
'Yaf\Bootstrap_Abstract' => '<p>Bootstrap is a mechanism used to do some initial config before a Application run.<br/><br/></p>
<p>User may define their own Bootstrap class by inheriting <b>\Yaf\Bootstrap_Abstract</b><br/><br/></p>
<p>Any method declared in Bootstrap class with leading "_init", will be called by \Yaf\Application::bootstrap() one by one according to their defined order<br/><br/></p>',
'Yaf\Config\Ini' => '<p>\Yaf\Config\Ini enables developers to store configuration data in a familiar INI format and read them in the application by using nested object property syntax. The INI format is specialized to provide both the ability to have a hierarchy of configuration data keys and inheritance between configuration data sections. Configuration data hierarchies are supported by separating the keys with the dot or period character ("."). A section may extend or inherit from another section by following the section name with a colon character (":") and the name of the section from which data are to be inherited.</p><br/>
<b>Note:</b>
<p>\Yaf\Config\Ini utilizes the » parse_ini_file() PHP function. Please review this documentation to be aware of its specific behaviors, which propagate to \Yaf\Config\Ini, such as how the special values of "TRUE", "FALSE", "yes", "no", and "NULL" are handled.</p>',
'Yaf\Controller_Abstract' => '<p><b>\Yaf\Controller_Abstract</b> is the heart of Yaf\'s system. MVC stands for Model-View-Controller and is a design pattern targeted at separating application logic from display logic.</p>
<br/>
<p>Every custom controller shall inherit <b>\Yaf\Controller_Abstract</b>.</p>
<br/>
<p>You will find that you can not define __construct function for your custom controller, thus, <b>\Yaf\Controller_Abstract</b> provides a magic method: \Yaf\Controller_Abstract::init().</p>
<br/>
<p>If you have defined a init() method in your custom controller, it will be called as long as the controller was instantiated.</p>
<br/>
<p>Action may have arguments, when a request coming, if there are the same name variable in the request parameters(see \Yaf\Request_Abstract::getParam()) after routed, Yaf will pass them to the action method (see \Yaf\Action_Abstract::execute()).</p>
<br/>
<b>Note:</b>
<p>These arguments are directly fetched without filtering, it should be carefully processed before use them.</p>',
'Yaf\Dispatcher' => '<p><b>\Yaf\Dispatcher</b> purpose is to initialize the request environment, route the incoming request, and then dispatch any discovered actions; it aggregates any responses and returns them when the process is complete.</p><br/>
<p><b>\Yaf\Dispatcher</b> also implements the Singleton pattern, meaning only a single instance of it may be available at any given time. This allows it to also act as a registry on which the other objects in the dispatch process may draw.</p>',
'Yaf\Loader' => '<p><b>\Yaf\Loader</b> introduces a comprehensive autoloading solution for Yaf.</p>
<br/>
<p>The first time an instance of \Yaf\Application is retrieved, <b>\Yaf\Loader</b> will instance a singleton, and registers itself with spl_autoload. You retrieve an instance using the \Yaf\Loader::getInstance()</p>
<br/>
<p><b>\Yaf\Loader</b> attempt to load a class only one shot, if failed, depend on yaf.use_spl_autoload, if this config is On \Yaf\Loader::autoload() will return FALSE, thus give the chance to other autoload function. if it is Off (by default), \Yaf\Loader::autoload() will return TRUE, and more important is that a very useful warning will be triggered (very useful to find out why a class could not be loaded).</p>
<br/>
<b>Note:</b>
<p>Please keep yaf.use_spl_autoload Off unless there is some library have their own autoload mechanism and impossible to rewrite it.</p>
<br/>
<p>If you want <b>\Yaf\Loader</b> search some classes(libraries) in the local class directory(which is defined in application.ini, and by default, it is application.directory . "/library"), you should register the class prefix using the \Yaf\Loader::registerLocalNameSpace()</p>',
'Yaf\Plugin_Abstract' => '<p>Plugins allow for easy extensibility and customization of the framework.</p>
<br/>
<p>Plugins are classes. The actual class definition will vary based on the component -- you may need to implement this interface, but the fact remains that the plugin is itself a class.</p>
<br/>
<p>A plugin could be loaded into Yaf by using \Yaf\Dispatcher::registerPlugin(), after registered, All the methods which the plugin implemented according to this interface, will be called at the proper time.</p>',
'Yaf\Registry' => '<p>All methods of <b>\Yaf\Registry</b> declared as static, making it universally accessible. This provides the ability to get or set any custom data from anyway in your code as necessary.</p>',
'Yaf\Request\Simple' => '<b>\Yaf\Request\Simple</b> is particularly used for test purpose. ie. simulate a spacial request under CLI mode.',
'Yaf\Route\Map' => '<p><b>\Yaf\Route\Map</b> is a built-in route, it simply convert a URI endpoint (that part of the URI which comes after the base URI: see \Yaf\Request_Abstract::setBaseUri()) to a controller name or action name(depends on the parameter passed to \Yaf\Route\Map::__construct()) in following rule: A => controller A. A/B/C => controller A_B_C. A/B/C/D/E => controller A_B_C_D_E.</p>
<br/>
<p>If the second parameter of \Yaf\Route\Map::__construct() is specified, then only the part before delimiter of URI will used to routing, the part after it is used to routing request parameters (see the example section of \Yaf\Route\Map::__construct()).</p>',
'Yaf\Route\Regex' => '<p><b>\Yaf\Route\Regex</b> is the most flexible route among the Yaf built-in routes.</p>',
'Yaf\Route\Rewrite' => '<p>For usage, please see the example section of \Yaf\Route\Rewrite::__construct()</p>',
'Yaf\Route\Simple' => '<p><b>\Yaf\Route\Simple</b> will match the query string, and find the route info.</p>
<br/>
<p>all you need to do is tell <b>\Yaf\Route\Simple</b> what key in the $_GET is module, what key is controller, and what key is action.</p>
<br/>
<p>\Yaf\Route\Simple::route() will always return TRUE, so it is important put <b>\Yaf\Route\Simple</b> in the front of the Route stack, otherwise all the other routes will not be called</p>',
'Yaf\Route_Interface' => '<b>\Yaf\Route_Interface</b> used for developer defined their custom route.',
'Yaf\Route_Static' => '<p>by default, \Yaf\Router only have a <b>\Yaf\Route_Static</b> as its default route.</p>
<br/>
<p><b>\Yaf\Route_Static</b> is designed to handle 80% of normal requirements.</p>
<br/>
<b>Note:</b>
<p> it is unnecessary to instance a <b>\Yaf\Route_Static</b>, also unnecessary to add it into \Yaf\Router\'s routes stack, since there is always be one in \Yaf\Router\'s routes stack, and always be called at the last time.</p>',
'Yaf\Router' => '<p><b>\Yaf\Router</b> is the standard framework router. Routing is the process of taking a URI endpoint (that part of the URI which comes after the base URI: see \Yaf\Request_Abstract::setBaseUri()) and decomposing it into parameters to determine which module, controller, and action of that controller should receive the request. This values of the module, controller, action and other parameters are packaged into a \Yaf\Request_Abstract object which is then processed by \Yaf\Dispatcher. Routing occurs only once: when the request is initially received and before the first controller is dispatched. \Yaf\Router is designed to allow for mod_rewrite-like functionality using pure PHP structures. It is very loosely based on Ruby on Rails routing and does not require any prior knowledge of webserver URL rewriting</p>
<br/>
<b>Default Route</b>
<br/>
<p><b>\Yaf\Router</b> comes pre-configured with a default route \Yaf\Route_Static, which will match URIs in the shape of controller/action. Additionally, a module name may be specified as the first path element, allowing URIs of the form module/controller/action. Finally, it will also match any additional parameters appended to the URI by default - controller/action/var1/value1/var2/value2.</p>
<br/>
<b>Note:</b>
<p>Module name must be defined in config, considering application.module="Index,Foo,Bar", in this case, only index, foo and bar can be considered as a module name. if doesn\'t config, there is only one module named "Index".</p>
<br/>
<p>** See examples by opening the external documentation</p>',
'Yaf\View\Simple' => '<b>\Yaf\View\Simple</b> is the built-in template engine in Yaf, it is a simple but fast template engine, and only support PHP script template.',
'Yaf\View_Interface' => 'Yaf provides a ability for developers to use custom view engine instead of built-in engine which is \Yaf\View\Simple. There is a example to explain how to do this, please see \Yaf\Dispatcher::setView()',
'Yaf_Action_Abstract' => 'A action can be defined in a separate file in Yaf(see `Yaf_Controller_Abstract`). that is a action method can also be a `Yaf_Action_Abstract` class.

Since there should be a entry point which can be called by Yaf (as of PHP 5.3, there is a new magic method __invoke, but Yaf is not only works with PHP 5.3+, Yaf choose another magic method execute), you must implement the abstract method `Yaf_Action_Abstract::execute` in your custom action class.',
'Yaf_Application' => '`Yaf_Application` provides a bootstrapping facility for applications which provides reusable resources, common- and module-based bootstrap classes and dependency checking.

`Yaf_Application` implements the singleton pattern, and `Yaf_Application` can not be serialized or unserialized which will cause problem when you try to use PHPUnit to write some test case for Yaf. You may use @backupGlobals annotation of PHPUnit to control the backup and restore operations for global variables. thus can solve this problem.',
'Yaf_Bootstrap_Abstract' => 'Bootstrap is a mechanism used to do some initial config before a Application run.

User may define their own Bootstrap class by inheriting `Yaf_Bootstrap_Abstract`

Any method declared in Bootstrap class with leading "_init", will be called by `Yaf_Application::bootstrap` one by one according to their defined order.',
'Yaf_Config_Ini' => 'Yaf_Config_Ini enables developers to store configuration data in a familiar INI format and read them in the application by using nested object property syntax. The INI format is specialized to provide both the ability to have a hierarchy of configuration data keys and inheritance between configuration data sections. Configuration data hierarchies are supported by separating the keys with the dot or period character ("."). A section may extend or inherit from another section by following the section name with a colon character (":") and the name of the section from which data are to be inherited. Yaf_Config_Ini utilizes the » parse_ini_file() PHP function. Please review this documentation to be aware of its specific behaviors, which propagate to Yaf_Config_Ini, such as how the special values of "`true`", "`false`", "yes", "no", and "`null`" are handled.',
'Yaf_Controller_Abstract' => '`Yaf_Controller_Abstract` is the heart of Yaf\'s system. MVC stands for Model-View-Controller and is a design pattern targeted at separating application logic from display logic.

Every custom controller shall inherit `Yaf_Controller_Abstract`.

You will find that you can not define __construct function for your custom controller, thus, `Yaf_Controller_Abstract` provides a magic method: `Yaf_Controller_Abstract::init`.

If you have defined a init() method in your custom controller, it will be called as long as the controller was instantiated.

Action may have arguments, when a request coming, if there are the same name variable in the request parameters(see `Yaf_Request_Abstract::getParam`) after routed, Yaf will pass them to the action method (see `Yaf_Action_Abstract::execute`). These arguments are directly fetched without filtering, it should be carefully processed before use them.',
'Yaf_Dispatcher' => '`Yaf_Dispatcher` purpose is to initialize the request environment, route the incoming request, and then dispatch any discovered actions; it aggregates any responses and returns them when the process is complete.

`Yaf_Dispatcher` also implements the Singleton pattern, meaning only a single instance of it may be available at any given time. This allows it to also act as a registry on which the other objects in the dispatch process may draw.',
'Yaf_Loader' => '`Yaf_Loader` introduces a comprehensive autoloading solution for Yaf.

The first time an instance of `Yaf_Application` is retrieved, `Yaf_Loader` will instance a singleton, and registers itself with spl_autoload. You retrieve an instance using the `Yaf_Loader::getInstance`

`Yaf_Loader` attempt to load a class only one shot, if failed, depend on yaf.use_spl_auload, if this config is On `Yaf_Loader::autoload` will return `false`, thus give the chance to other autoload function. if it is Off (by default), `Yaf_Loader::autoload` will return `true`, and more important is that a very useful warning will be triggered (very useful to find out why a class could not be loaded). Please keep yaf.use_spl_autoload Off unless there is some library have their own autoload mechanism and impossible to rewrite it.

By default, `Yaf_Loader` assume all library (class defined script) store in the global library directory, which is defined in the php.ini(yaf.library).

If you want `Yaf_Loader` search some classes(libraries) in the local class directory(which is defined in application.ini, and by default, it is application.directory . "/library"), you should register the class prefix using the `Yaf_Loader::registerLocalNameSpace`

Let\'s see some examples(assuming APPLICATION_PATH is application.directory): Config example Assuming the following local name space registered: Register localnamespace registerLocalNameSpace(array("Foo", "Bar")); } ?> ]]> Then the autoload examples: Load class example // APPLICATION_PATH/library/Foo/Bar/Test.php class GLO_Name => // /global_dir/Glo/Name.php class BarNon_Test // /global_dir/Barnon/Test.php ]]> As of PHP 5.3, you can use namespace: Load namespace class example // APPLICATION_PATH/library/Foo/Bar/Dummy.php class \FooBar\Bar\Dummy => // /global_dir/FooBar/Bar/Dummy.php ]]>

You may noticed that all the folder with the first letter capitalized, you can make them lowercase by set yaf.lowcase_path = On in php.ini

`Yaf_Loader` is also designed to load the MVC classes, and the rule is: MVC class loading example // APPLICATION_PATH/controllers/ Model Classes => // APPLICATION_PATH/models/ Plugin Classes => // APPLICATION_PATH/plugins/ ]]> Yaf identify a class\'s suffix(this is by default, you can also change to the prefix by change the configure yaf.name_suffix) to decide whether it is a MVC class: MVC class distinctions // ***Controller Model Classes => // ***Model Plugin Classes => // ***Plugin ]]> some examples: MVC loading example // APPLICATION_PATH/models/Data.php class DummyPlugin => // APPLICATION_PATH/plugins/Dummy.php class A_B_TestModel => // APPLICATION_PATH/models/A/B/Test.php ]]> As of 2.1.18, Yaf supports Controllers autoloading for user script side, (which means the autoloading triggered by user php script, eg: access a controller static property in Bootstrap or Plugins), but autoloader only try to locate controller class script under the default module folder, which is "APPLICATION_PATH/controllers/". also, the directory will be affected by yaf.lowcase_path.',
'Yaf_Plugin_Abstract' => 'Plugins allow for easy extensibility and customization of the framework.

Plugins are classes. The actual class definition will vary based on the component -- you may need to implement this interface, but the fact remains that the plugin is itself a class.

A plugin could be loaded into Yaf by using `Yaf_Dispatcher::registerPlugin`, after registering, All the methods which the plugin implemented according to this interface, will be called at the proper time.',
'Yaf_Registry' => 'All methods of `Yaf_Registry` declared as static, making it unversally accessible. This provides the ability to get or set any custom data from anyway in your code as necessary.',
'Yaf_Request_Http' => 'Any request from client is initialized as a `Yaf_Request_Http`. you can get the request information like, uri query and post parameters via methods of this class. For security, $_GET/$_POST are readonly in Yaf, which means if you set a value to these global variables, you can not get it from `Yaf_Request_Http::getQuery` or `Yaf_Request_Http::getPost`. But there do is some usage need such feature, like unit testing. thus Yaf can be built with --enable-yaf-debug, which will allow Yaf read the value user set via script. in such case, Yaf will throw a E_STRICT warning to remind you about that: Strict Standards: you are running yaf in debug mode',
'Yaf_Request_Simple' => '`Yaf_Request_Simple` is particularlly used for test puporse. ie. simulate some espacial request under CLI mode.',
'Yaf_Route_Interface' => '`Yaf_Route_Interface` used for developer defined their custom route.',
'Yaf_Route_Map' => '`Yaf_Route_Map` is a built-in route, it simply convert a URI endpoint (that part of the URI which comes after the base URI: see `Yaf_Request_Abstract::setBaseUri`) to a controller name or action name(depends on the parameter passed to `Yaf_Route_Map::__construct`) in following rule: A =&gt; controller A. A/B/C =&gt; controller A_B_C. A/B/C/D/E =&gt; controller A_B_C_D_E.

If the second parameter of `Yaf_Route_Map::__construct` is specified, then only the part before delimiter of URI will used to routing, the part after it is used to routing request parameters (see the example section of `Yaf_Route_Map::__construct`).',
'Yaf_Route_Regex' => '`Yaf_Route_Regex` is the most flexible route among the Yaf built-in routes.',
'Yaf_Route_Rewrite' => 'For usage, please see the example section of `Yaf_Route_Rewrite::__construct`',
'Yaf_Route_Simple' => '`Yaf_Route_Simple` will match the query string, and find the route info.

all you need to do is tell `Yaf_Route_Simple` what key in the $_GET is module, what key is controller, and what key is action.

`Yaf_Route_Simple::route` will always return `true`, so it is important put `Yaf_Route_Simple` in the front of the Route stack, otherwise all the other routes will not be called.',
'Yaf_Route_Static' => 'Defaultly, `Yaf_Router` only have a `Yaf_Route_Static` as its default route.

And `Yaf_Route_Static` is designed to handle the 80% requirement.

please *NOTE* that it is unnecessary to instance a `Yaf_Route_Static`, also unecesary to add it into `Yaf_Router`\'s routes stack, since there is always be one in `Yaf_Router`\'s routes stack, and always be called at the last time.',
'Yaf_Router' => '`Yaf_Router` is the standard framework router. Routing is the process of taking a URI endpoint (that part of the URI which comes after the base URI: see `Yaf_Request_Abstract::setBaseUri`) and decomposing it into parameters to determine which module, controller, and action of that controller should receive the request. This values of the module, controller, action and other parameters are packaged into a `Yaf_Request_Abstract` object which is then processed by `Yaf_Dispatcher`. Routing occurs only once: when the request is initially received and before the first controller is dispatched. `Yaf_Router` is designed to allow for mod_rewrite-like functionality using pure PHP structures. It is very loosely based on Ruby on Rails routing and does not require any prior knowledge of webserver URL rewriting. It is designed to work with a single Apache mod_rewrite rule (one of): Rewrite rule for Apache or (preferred): Rewrite rule for Apache If using Lighttpd, the following rewrite rule is valid: Rewrite rule for Lighttpd "/index.php?$1", ".*\.(js|ico|gif|jpg|png|css|html)$" => "$0", "" => "/index.php" ) ]]> If using Nginx, use the following rewrite rule: Rewrite rule for Nginx',
'Yaf_View_Interface' => 'Yaf provides a ability for developers to use coustom view engine instead of built-in engine which is `Yaf_View_Simple`. There is a example to explain how to do this, please see `Yaf_Dispatcher::setView`.',
'Yaf_View_Simple' => '`Yaf_View_Simple` is the built-in template engine in Yaf, it is a simple but fast template engine, and only support PHP script template.',
'Yar_Client_Exception' => 'Class Yar_Client_Exception
Date 2018/6/9 下午3:05',
'Yar_Server' => 'Class Yar_Server
Date 2018/6/9 下午3:02',
'Yar_Server_Exception' => 'If service threw exceptions, A Yar_Server_Exception will be threw in client side.',
'ZendAPI_Job' => 'Describing a job in a queue
In order to add/modify a job in the queue, a Job class must be created/retrieved and than saved in a queue

For simplicity, a job can be added directly to a queue and without creating an instant of a Queue object',
'ZipArchive' => 'A file archive, compressed with Zip.',
'ZMQ' => 'Class ZMQ',
'ZMQContext' => 'Class ZMQContext',
'ZMQDevice' => 'Class ZMQDevice',
'ZMQPoll' => 'Class ZMQPoll',
'ZMQSocket' => 'Class ZMQSocket',
'Zookeeper' => 'Represents ZooKeeper session.',
'ZookeeperAuthenticationException' => 'The ZooKeeper authentication exception handling class.',
'ZookeeperConfig' => 'The ZooKeeper Config handling class.',
'ZookeeperConnectionException' => 'The ZooKeeper connection exception handling class.',
'ZookeeperException' => 'The ZooKeeper exception handling class.',
'ZookeeperMarshallingException' => 'The ZooKeeper exception (while marshalling or unmarshalling data) handling class.',
'ZookeeperNoNodeException' => 'The ZooKeeper exception (when node does not exist) handling class.',
'ZookeeperOperationTimeoutException' => 'The ZooKeeper operation timeout exception handling class.',
'ZookeeperSessionException' => 'The ZooKeeper session exception handling class.',
];
