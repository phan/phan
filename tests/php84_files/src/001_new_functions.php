<?php
# Core https://www.php.net/manual/en/migration84.new-functions.php#migration84.new-functions.core
request_parse_body();
request_parse_body(['option1' => 'value1', 'option2' => 'value2']);
request_parse_body('value');
# BCMath https://www.php.net/manual/en/migration84.new-functions.php#migration84.new-functions.bcmath
bcceil('1');
bcceil(1);
bcdivmod('5',  '3');
bcdivmod('5.7', '1.3', 1);
bcfloor('1.3');
bcround('1.6');
bcround('3.6', 1);
bcround('3.6', 1, RoundingMode::HalfEven);
# DOM, https://www.php.net/manual/en/migration84.new-functions.php#migration84.new-functions.dom
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
# Intl https://www.php.net/manual/en/migration84.new-functions.php#migration84.new-functions.intl
grapheme_str_split('value');
grapheme_str_split(false);
grapheme_str_split('value', 2);
# MBString https://www.php.net/manual/en/migration84.new-functions.php#migration84.new-functions.mbstring
mb_trim(' trim me ');
mb_trim(' trim me ', ' ');
mb_trim(' trim me ', ' ', 'UTF-8');
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
# PCNTL https://www.php.net/manual/en/migration84.new-functions.php#migration84.new-functions.pcntl
pcntl_getcpuaffinity();
pcntl_getcpuaffinity(1);
pcntl_waitid();
$info = ['test'];
pcntl_waitid(1, 2, $info, 4);
# PDO_PGSQL https://www.php.net/manual/en/migration84.new-functions.php#migration84.new-functions.pdo-pgsql
new Pdo\Pgsql('')->setNoticeCallback(function (string $message): void {
    echo $message;
});
# PGSQL https://www.php.net/manual/en/migration84.new-functions.php#migration84.new-functions.pgsql
pg_result_memory_size(new PgSql\Result());
pg_set_chunked_rows_size(new PgSql\Connection(), 10);
