<?php
// Date https://www.php.net/manual/en/migration83.new-functions.php#migration83.new-functions.date
$iso = 'R4/2023-07-01T00:00:00Z/P7D';
DatePeriod::createFromISO8601String($iso);
DatePeriod::createFromISO8601String($iso, DatePeriod::EXCLUDE_START_DATE);
DatePeriod::createFromISO8601String($iso, DatePeriod::INCLUDE_END_DATE);
DatePeriod::createFromISO8601String($iso, false);
// DOM https://www.php.net/manual/en/migration83.new-functions.php#migration83.new-functions.dom
$domElement = new DOMElement('root');
$domElement->getAttributeNames();
$domElement->insertAdjacentElement('beforebegin', new DOMElement('node1'));
$domElement->insertAdjacentText('afterend', 'some data');
$domElement->toggleAttribute('selected');
$domElement->toggleAttribute('selected', true);
$domElement->contains(new DOMElement('node2'));
$domElement->contains(null);
$domElement->contains('some value');
$domElement->getRootNode();
$domElement->getRootNode([1, 2, 3, 'test', []]);
$domElement->isEqualNode(null);
$domElement->isEqualNode(new DOMElement('root'));
$domElement->replaceChildren('foo', new DOMElement('node2'), 'bar');
// Intl https://www.php.net/manual/en/migration83.new-functions.php#migration83.new-functions.intl
$calendar = IntlCalendar::createInstance('UTC');
$calendar->setDate(2012, 1, 29);
$calendar->setDate(2012, '1', 29);
$calendar->setDateTime(2012, 1, 29, 23, 58);
$calendar->setDateTime(2012, 1, 29, 23, 58, 41);
IntlGregorianCalendar::createFromDate(2023, 11, 23);
IntlGregorianCalendar::createFromDateTime(2023, 11, 23, 12, 00);
IntlGregorianCalendar::createFromDateTime(2023, 11, 23, 12, 00, 41);
// JSON https://www.php.net/manual/en/migration83.new-functions.php#migration83.new-functions.json
$json = '{a: {b: 2}}';
json_validate($json);
json_validate($json, 2);
json_validate($json, false);
json_validate($json, 256, 0);
json_validate($json, 2, JSON_INVALID_UTF8_IGNORE);
// LDAP https://www.php.net/manual/en/migration83.new-functions.php#migration83.new-functions.ldap
ldap_connect_wallet('uri', 'wallet', 'password');
ldap_connect_wallet(null, 'wallet', 'password');
ldap_connect_wallet(null, 'wallet', 'password', 'test');
ldap_connect_wallet('uri', 'wallet', 'password', 1);
$ldap = new LDAP\Connection();
$response_data = '';
$response_oid = '';
ldap_exop_sync($ldap, 'request_oid', 'request_data', [], $request_data, $response_oid);
ldap_exop_sync($ldap, 'request_oid', controls: [], response_data: $request_data, response_oid: $response_oid);
ldap_exop_sync($ldap, 'request_oid', response_data: $request_data, response_oid: $response_oid);
ldap_exop_sync($ldap, 'request_oid');
// MBString https://www.php.net/manual/en/migration83.new-functions.php#migration83.new-functions.mbstring
mb_str_pad('test', 32);
mb_str_pad('test', false);
mb_str_pad('test', 32, ' ');
mb_str_pad('test', 32, '-', STR_PAD_LEFT);
mb_str_pad('test', 32, '-', STR_PAD_LEFT, 'UTF-8');
// Posix functions are platform-dependent and not reliably testable across CI environments
// PostgreSQL https://www.php.net/manual/en/migration83.new-functions.php#migration83.new-functions.pgsql
$connection = new PgSql\Connection();
pg_set_error_context_visibility($connection, PGSQL_SHOW_CONTEXT_ALWAYS);
// Random https://www.php.net/manual/en/migration83.new-functions.php#migration83.new-functions.random
$randomizer = new Random\Randomizer();
$randomizer->getBytesFromString('abcdefghijklmnopqrstuvwxyz0123456789', 16);
$randomizer->nextFloat();
$randomizer->getFloat(0, 1);
$randomizer->getFloat(0, '1');
$randomizer->getFloat(-90, 90, Random\IntervalBoundary::OpenOpen);
$randomizer->getFloat(-90, 90, Random\IntervalBoundary::ClosedClosed);
// Reflection https://www.php.net/manual/en/migration83.new-functions.php#migration83.new-functions.reflection
ReflectionMethod::createFromMethodName("SomeCalss::some_method");
// Sockets https://www.php.net/manual/en/migration83.new-functions.php#migration83.new-functions.sockets
$sock = socket_create(AF_INET, TCP_QUICKACK, SOL_TCP);
socket_atmark($sock);
// Standard https://www.php.net/manual/en/migration83.new-functions.php#migration83.new-functions.standard
str_increment('EA');
str_decrement('AA');
str_decrement(12);
stream_context_set_options(stream_context_create(), []);
// Zip https://www.php.net/manual/en/migration83.new-functions.php#migration83.new-functions.zip
$zip = new ZipArchive();
$zip->getArchiveFlag(1);
$zip->getArchiveFlag(1, ZipArchive::FL_UNCHANGED);
