<?php
# Core https://www.php.net/manual/en/migration84.new-functions.php#migration84.new-functions.core
try { request_parse_body(); } catch (RequestParseBodyException|ValueError|TypeError) {}
try { request_parse_body(['option1' => 'value1', 'option2' => 'value2']); } catch (RequestParseBodyException|ValueError|TypeError) {}
try { request_parse_body('value'); } catch (RequestParseBodyException|ValueError|TypeError) {}
# BCMath https://www.php.net/manual/en/migration84.new-functions.php#migration84.new-functions.bcmath
bcceil('1');
bcceil(1);
bcdivmod('5',  '3');
bcdivmod('5.7', '1.3', 1);
bcfloor('1.3');
bcround('1.6');
bcround('3.6', 1);
bcround('3.6', 1, RoundingMode::HalfEven);
# Date https://www.php.net/manual/en/migration84.new-functions.php#migration84.new-functions.date
DateTime::createFromTimestamp(1);
DateTime::createFromTimestamp(1.0);
DateTime::createFromTimestamp('1');
new DateTime()->getMicrosecond();
new DateTime()->setMicrosecond(123);
DateTimeImmutable::createFromTimestamp(1);
DateTimeImmutable::createFromTimestamp(1.0);
new DateTimeImmutable()->getMicrosecond();
new DateTimeImmutable()->setMicrosecond(123);
# DOM https://www.php.net/manual/en/migration84.new-functions.php#migration84.new-functions.dom
$domElement = new DOMElement('root');
$domElement->compareDocumentPosition(new DOMElement('root'));
$xpath = new DOMXPath(new DOMDocument());
$xpath->registerPHPFunctionNS(
    'urn:my.ns',
    'substring',
    function (string $value): string {
        return $value;
    }
);
DOMXPath::quote("'quoted' name");
# Hash https://www.php.net/manual/en/migration84.new-functions.php#migration84.new-functions.hash
hash_init('sha256')->__debugInfo();
# Intl https://www.php.net/manual/en/migration84.new-functions.php#migration84.new-functions.intl
/**
 * IntlTimeZone::getIanaID is available only for never versions of ICU.
 * For older versions of ICU IntlTimeZone class will be exising, so phan can use its reflection.
 * And phan tries to use maps only when reflection is not available. Which makes it impossible to
 * add IntlTimeZone::getIanaID using maps for older ICU cases.
 */
// IntlTimeZone::getIanaID('UTC');
intltz_get_iana_id('UTC');
IntlDateFormatter::create(null, 0, 0)->parseToCalendar('value');
new Spoofchecker()->setAllowedChars('value', 1);
grapheme_str_split('value');
grapheme_str_split(false);
grapheme_str_split('value', 2);
# MBString https://www.php.net/manual/en/migration84.new-functions.php#migration84.new-functions.mbstring
mb_trim(' trim me ');
mb_trim(' trim me ', ' ');
mb_trim(' trim me ', ' ', 'UTF-8');
mb_trim(['array']);
mb_ltrim(' trim me ');
mb_ltrim(' trim me ', ' ');
mb_ltrim(' trim me ', ' ', 'UTF-8');
mb_rtrim(' trim me ');
mb_rtrim(' trim me ', ' ');
mb_rtrim(' trim me ', ' ', 'UTF-8');
mb_ucfirst('value');
mb_ucfirst(['value']);
mb_ucfirst('value', 'UTF-8');
mb_lcfirst('VALUE');
mb_lcfirst('VALUE', 'UTF-8');
# Opcache https://www.php.net/manual/en/migration84.new-functions.php#migration84.new-functions.hash
opcache_jit_blacklist(function (): bool {
    return false;
});
opcache_jit_blacklist('not a closure');
# PCNTL https://www.php.net/manual/en/migration84.new-functions.php#migration84.new-functions.pcntl
pcntl_getcpu();
pcntl_getcpuaffinity();
pcntl_getcpuaffinity(1);
pcntl_getcpuaffinity('invalid');
pcntl_getqos_class();
pcntl_waitid();
pcntl_setns();
pcntl_setns(null, 1);
pcntl_setns(1, 1);
$info = ['test'];
pcntl_waitid(1, 2, $info, 4);
$_pcntl_info = 'not array';
pcntl_waitid(1, 2, $_pcntl_info, 4);
# PDO_PGSQL https://www.php.net/manual/en/migration84.new-functions.php#migration84.new-functions.pdo-pgsql
new Pdo\Pgsql('')->setNoticeCallback(function (string $message): void {
    echo $message;
});
# PGSQL https://www.php.net/manual/en/migration84.new-functions.php#migration84.new-functions.pgsql
$connection = new PgSql\Connection();
pg_change_password($connection, 'user', 'password');
pg_jit();
pg_jit(null);
pg_jit($connection);
pg_put_copy_data($connection, 'cmd');
pg_put_copy_end($connection);
pg_put_copy_end($connection, null);
pg_put_copy_end($connection, 'error');
pg_put_copy_data($connection, 123);
pg_result_memory_size(new PgSql\Result());
pg_set_chunked_rows_size($connection, 10);
pg_socket_poll('socket', 1, 2);
pg_socket_poll('socket', 1, 2, 3);
# Reflection https://www.php.net/manual/en/migration84.new-functions.php#migration84.new-functions.reflection
$reflector = new ReflectionClass(DateTime::class);
$reflector->newLazyGhost(function (DateTime $dt) {
    $dt->__construct('now');
});
$reflector->newLazyProxy(function (DateTime $dt): DateTime {
    return new DateTime($dt->format('c'));
});
$reflector->resetAsLazyGhost(new DateTime('now'), function (DateTime $dt) {
    $dt->__construct('now');
});
$reflector->resetAsLazyProxy(new DateTime('now'), function (DateTime $dt): DateTime {
    return new DateTime($dt->format('c'));
});
$reflector->isUninitializedLazyObject(new DateTime('now'));
$reflector->initializeLazyObject(new DateTime('now'));
$reflector->markLazyObjectAsInitialized(new DateTime('now'));
$reflector->getLazyInitializer(new DateTime('now'));
$reflector = new ReflectionProperty(stdClass::class, 'test');
$reflector->skipLazyInitialization(new stdClass());
$reflector->setRawValueWithoutLazyInitialization(new stdClass(), 'data');
$reflector->isDynamic();
new ReflectionClassConstant(stdClass::class, 'SOME_CONST')->isDeprecated();
function gen(): Generator
{
    yield 'a';
    yield 'a';
}
new ReflectionGenerator(gen())->isClosed();
# Sodium https://www.php.net/manual/en/migration84.new-functions.php#migration84.new-functions.sodium
sodium_crypto_aead_aegis128l_keygen();
sodium_crypto_aead_aegis128l_encrypt('message', 'data', 'nonce', 'key');
sodium_crypto_aead_aegis128l_decrypt('cipher', 'data', 'nonce', 'key');
# SPL https://www.php.net/manual/en/migration84.new-functions.php#migration84.new-functions.sodium
new SplObjectStorage()->seek(0);
# SOAP https://www.php.net/manual/en/migration84.new-functions.php#migration84.new-functions.sodium
new SoapServer('test.wsdl')->__getLastResponse();
# Standard https://www.php.net/manual/en/migration84.new-functions.php#migration84.new-functions.standard
http_get_last_response_headers();
http_clear_last_response_headers();
http_get_last_response_headers('extra');
fpow(2, 4);
fpow(1.2, 5.5);
$array = [
    'a' => 'dog',
    'b' => 'cat',
    'c' => 'cow',
    'd' => 'duck',
    'e' => 'goose',
    'f' => 'elephant'
];
array_all($array, function (string $value): bool {
    return strlen($value) < 12;
});
array_any($array, function (string $value): bool {
    return strlen($value) > 5;
});
array_find($array, function (string $value): bool {
    return strlen($value) > 4;
});
array_find_key($array, function (string $value): bool {
    return strlen($value) > 4;
});
array_all('not array', function (): bool {
    return true;
});
array_any($array, 'not callable');
array_find('not array', function (string $value): bool {
    return strlen($value) > 4;
});
array_find_key($array, 'not callable');
# Tidy https://www.php.net/manual/en/migration84.new-functions.php#migration84.new-functions.tidy
tidy_parse_string('')->body()->getNextSibling();
tidy_parse_string('')->body()->getPreviousSibling();
# XMLReader https://www.php.net/manual/en/migration84.new-functions.php#migration84.new-functions.xmlreader
XMLReader::fromStream(fopen('/tmp', 'r'));
XMLReader::fromStream(fopen('/tmp', 'r'), 'UTF-8');
XMLReader::fromStream(fopen('/tmp', 'r'), 'UTF-8', 8);
XMLReader::fromStream(fopen('/tmp', 'r'), null, 8);
XMLReader::fromUri('uri');
XMLReader::fromUri('uri', 'UTF-8');
XMLReader::fromUri('uri', 'UTF-8', 8);
XMLReader::fromUri('uri', null, 8);
XMLReader::fromString('string');
XMLReader::fromString('string', 'UTF-8');
XMLReader::fromString('string', 'UTF-8', 8);
XMLReader::fromString('string', null, 8);
# XMLWriter https://www.php.net/manual/en/migration84.new-functions.php#migration84.new-functions.xmlwriter
XMLWriter::toStream(fopen('/tmp', 'w'));
XMLWriter::toUri('uri');
XMLWriter::toMemory();
# XSL https://www.php.net/manual/en/migration84.new-functions.php#migration84.new-functions.xsl
new XSLTProcessor()->registerPHPFunctionNS('urn:my.ns', 'uppercase', strtoupper(...));
