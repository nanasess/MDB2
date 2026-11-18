# MDB2

[![Tests](https://github.com/nanasess/MDB2/actions/workflows/tests.yml/badge.svg)](https://github.com/nanasess/MDB2/actions/workflows/tests.yml)
[![Packagist Version](https://img.shields.io/packagist/v/nanasess/mdb2)](https://packagist.org/packages/nanasess/mdb2)
[![License](https://img.shields.io/packagist/l/nanasess/mdb2)](LICENSE)

A maintained fork of [PEAR MDB2](https://pear.php.net/package/MDB2), the database abstraction layer for PHP.

The original [pear/MDB2](https://github.com/pear/MDB2) repository is archived. This fork keeps MDB2 running on current PHP versions and fixes bugs and security issues. Its main user is [EC-CUBE 2](https://github.com/EC-CUBE/ec-cube2). No new features are planned.

## Requirements

- PHP 7.4 to 8.5 are tested in CI. `composer.json` still allows PHP 5.3 and later, and the library code keeps to that syntax.
- The PHP extension for your database (`pgsql`, `mysqli` or `sqlite3`) and `mbstring`.

### Drivers

| Driver | Database | Status |
|---|---|---|
| `pgsql` | PostgreSQL | Tested in CI (PostgreSQL 16) |
| `mysqli` | MySQL | Tested in CI (MySQL 8.0) |
| `sqlite3` | SQLite 3 | Tested in CI |
| `mysql`, `mssql`, `sqlsrv`, `oci8`, `ibase`, `fbsql`, `odbc`, `sqlite`, `querysim` | | Included but untested |

## Installation

```bash
composer require nanasess/mdb2
```

## Usage

```php
<?php
require 'vendor/autoload.php';

$db = MDB2::connect('pgsql://user:password@localhost/dbname', array(
    'persistent' => false,
    'result_buffering' => false,
));
if (MDB2::isError($db)) {
    die($db->getMessage());
}
$db->setCharset('utf8');
$db->setFetchMode(MDB2_FETCHMODE_ASSOC);

// quote() escapes a value for the connection
$affected = $db->exec('INSERT INTO users VALUES (1, '.$db->quote("O'Reilly", 'text').')');
if (MDB2::isError($affected)) {
    die($affected->getUserInfo());
}

// prepared statements
$stmt = $db->prepare('INSERT INTO users VALUES (?, ?)', array('integer', 'text'), MDB2_PREPARE_MANIP);
foreach (array(array(2, 'Alice'), array(3, 'Bob')) as $row) {
    $stmt->execute($row);
}
$stmt->free();

// setLimit() applies to the next query
$db->setLimit(2);
$rows = $db->queryAll('SELECT id, name FROM users ORDER BY id');
if (MDB2::isError($rows)) {
    die($rows->getUserInfo());
}
```

The DSN format is `phptype://username:password@hostspec/database`. Use `mysqli://...` for MySQL and `sqlite3:////absolute/path/to/file.db` (or `sqlite3:///:memory:`) for SQLite.

Errors are returned, not thrown: check every result with `MDB2::isError()`. On PHP 8.1 and later the mysqli driver also returns an `MDB2_Error` instead of letting `mysqli_sql_exception` through.

The [PEAR MDB2 manual](https://pear.php.net/manual/en/package.database.mdb2.php) still describes the API.

## Changes from PEAR MDB2

See the [releases](https://github.com/nanasess/MDB2/releases) for the details.

- Works with PHP 8 (`PgSql\Connection` / `PgSql\Result` objects, mysqli exceptions, and PHP 8 type errors).
- Stricter quoting: a `decimal` or `float` value that is not a number, and a string with a NUL byte on pgsql / sqlite3, return an `MDB2_Error` instead of reaching the query.
- `setLimit()` ignores string literals, identifiers and comments when it rewrites a query, also in multibyte charsets such as Shift_JIS, and works with UPDATE / DELETE on pgsql.
- A `clob` / `blob` value that looks like a URL is only opened when the `lob_allow_url_include` option is enabled.
- Fixes in schema introspection (`tableInfo()`, `listTableFields()`) on PostgreSQL 12+ and SQLite.

## Development

```bash
composer update
vendor/bin/phpunit                    # in-memory SQLite

docker compose up -d --wait           # PostgreSQL and MySQL
MDB2_TEST_DSN=pgsql://postgres:password@127.0.0.1:25432/mdb2_test vendor/bin/phpunit
MDB2_TEST_DSN=mysqli://root:password@127.0.0.1:23306/mdb2_test vendor/bin/phpunit
```

CI runs the tests on every PHP version from 7.4 to 8.5 with each of the three tested drivers.

## Security

Please do not report vulnerabilities in public issues. See [SECURITY.md](SECURITY.md).

## Credits

MDB2 was written by Lukas Smith as a merge of PEAR DB and Metabase, and was developed in PEAR until the repository was archived. Thanks to everyone who worked on it, including:

- Manuel Lemos (Metabase)
- Stig Bakken and Tomas V.V. Cox (PEAR DB)
- Lorenzo Alberton (lead developer, Interbase / Firebird driver)
- Paul Cooper (PostgreSQL driver)
- Frank Kormann (MS SQL / FrontBase driver)
- Christian Dickmann, Brent Cook, Daniel Convissor, Ali Fazelzadeh, David Coallier, Helgi Þormar Þorbjörnsson, Justin Patrin, Nathan Fredrickson and the other PEAR contributors

See [docs/README](docs/README) and [docs/CONTRIBUTORS](docs/CONTRIBUTORS) for the original documentation and credits.

This fork is maintained by [Kentaro Ohkouchi](https://github.com/nanasess), with contributions from [@nobuhiko](https://github.com/nobuhiko) and [@seasoftjapan](https://github.com/seasoftjapan).

## License

[BSD-3-Clause](LICENSE)
