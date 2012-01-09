<?php

if (!empty($_ENV['MYSQL_TEST_USER']) && extension_loaded('mysqli')) {
    $dsn = array(
        'phptype' => 'mysqli',
        'username' => $_ENV['MYSQL_TEST_USER'],
        'password' => $_ENV['MYSQL_TEST_PASSWD'],
        'database' => $_ENV['MYSQL_TEST_DB'],

        'hostspec' => empty($_ENV['MYSQL_TEST_HOST'])
                ? null : $_ENV['MYSQL_TEST_HOST'],

        'port' => empty($_ENV['MYSQL_TEST_PORT'])
                ? null : $_ENV['MYSQL_TEST_PORT'],

        'socket' => empty($_ENV['MYSQL_TEST_SOCKET'])
                ? null : $_ENV['MYSQL_TEST_SOCKET'],
    );
} elseif (!empty($_ENV['PGSQL_TEST_USER']) && extension_loaded('pgsql')) {
    $dsn = array(
        'phptype' => 'pgsql',
        'username' => $_ENV['PGSQL_TEST_USER'],
        'password' => $_ENV['PGSQL_TEST_PASSWD'],
        'database' => $_ENV['PGSQL_TEST_DB'],

        'hostspec' => empty($_ENV['PGSQL_TEST_HOST'])
                ? null : $_ENV['PGSQL_TEST_HOST'],

        'port' => empty($_ENV['PGSQL_TEST_PORT'])
                ? null : $_ENV['PGSQL_TEST_PORT'],

        'socket' => empty($_ENV['PGSQL_TEST_SOCKET'])
                ? null : $_ENV['PGSQL_TEST_SOCKET'],

        'protocol' => empty($_ENV['PGSQL_TEST_PROTOCOL'])
                ? null : $_ENV['PGSQL_TEST_PROTOCOL'],

        'option' => empty($_ENV['PGSQL_TEST_OPTIONS'])
                ? null : $_ENV['PGSQL_TEST_OPTIONS'],

        'tty' => empty($_ENV['PGSQL_TEST_TTY'])
                ? null : $_ENV['PGSQL_TEST_TTY'],

        'connect_timeout' => empty($_ENV['PGSQL_TEST_CONNECT_TIMEOUT'])
                ? null : $_ENV['PGSQL_TEST_CONNECT_TIMEOUT'],

        'sslmode' => empty($_ENV['PGSQL_TEST_SSL_MODE'])
                ? null : $_ENV['PGSQL_TEST_SSL_MODE'],

        'service' => empty($_ENV['PGSQL_TEST_SERVICE'])
                ? null : $_ENV['PGSQL_TEST_SERVICE'],
    );
} else {
    $dsn = array();
}

define('DB_NESTEDSET_TEST_DSN', serialize($dsn));
define('DB_NESTEDSET_TEST_DRIVER', 'DB');


/*
 * Create the tables if necessary.
 */

if (!$fp = @fopen('DB.php', 'r', true)) {
    die("skip DB is not installed.\n");
}
fclose($fp);

require_once 'DB.php';
$db = DB::connect($dsn);
if (PEAR::isError($db)) {
    die($db->getMessage(). "\n");
}

$fh = fopen(dirname(__FILE__) . '/testdb.sql', 'r');
$contents = '';
while (!feof($fh)) {
    $line = fgets($fh, 5000);
    if (substr($line, 0, 2) != '--') {
        $contents .= $line;
    }
}
fclose($fh);

$queries = preg_split('/;\s*$/m', $contents);
foreach ($queries as $query) {
    if (trim($query) == '') {
        continue;
    }
    $result = $db->query($query);
    if (DB::isError($result)) {
        switch ($result->getCode()) {
            case DB_ERROR_ALREADY_EXISTS:
                break;
            default:
                die('TEST TABLE CREATION ERROR: '
                        . $result->getDebugInfo() . "\n");
        }
    }
}
