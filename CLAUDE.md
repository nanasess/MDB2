# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

This is a fork of the archived `pear/MDB2` (`nanasess/MDB2`, Composer package `nanasess/mdb2`). Its main consumer is EC-CUBE 2 (`SC_Query`), and the test suite connects with the same options EC-CUBE 2 uses (`persistent => false`, `result_buffering => false`, `setCharset('utf8')`, `MDB2_FETCHMODE_ASSOC`). Work here is mostly keeping up with newer PHP versions (7.4 to 8.5) and fixing bugs, not adding features.

- Always pass `-R nanasess/MDB2` to `gh`. Without it, `gh` targets the parent repository and fails with `Repository was archived so is read-only`.
- Keep `php: >=5.3.0` in `composer.json` and the PSR-0 + `include-path` loading. Don't bring newer syntax (namespaces, type declarations, short array syntax) into `MDB2.php` or `MDB2/`. The test code uses things like `: void` only because PHPUnit 9.6 requires them.

## Commands

```bash
composer update                      # install dependencies (composer.lock is not committed)
vendor/bin/phpunit                   # defaults to an in-memory sqlite3 database
vendor/bin/phpunit tests/QueryTest.php
vendor/bin/phpunit --filter testQueryAll

# Run against pgsql / mysqli (databases from compose.yaml)
docker compose up -d --wait
MDB2_TEST_DSN=pgsql://postgres:password@127.0.0.1:25432/mdb2_test vendor/bin/phpunit
MDB2_TEST_DSN=mysqli://root:password@127.0.0.1:23306/mdb2_test vendor/bin/phpunit
```

- The `MDB2_TEST_DSN` environment variable selects the database under test. Change the ports with `MDB2_PGSQL_PORT` / `MDB2_MYSQL_PORT`. Avoid 13306 / 15432, which are taken by another project's containers.
- If the local PHP lacks the `pgsql` extension, run the tests in a Docker PHP image. The official PHP 7.4 / 8.0 images (bullseye) return 404 from apt unless `debian-security` is removed from the apt sources.
- There is no lint or static analysis configuration. CI (`.github/workflows/tests.yml`) runs PHPUnit across a matrix of PHP 7.4 to 8.5 × pgsql (postgres:16) / mysqli (mysql:8.0) / sqlite3. It can also be started with `workflow_dispatch`.
- `phpunit.xml.dist` enables `failOnWarning` / `failOnRisky` and disables `convertDeprecationsToExceptions`, so a PHP warning fails the test.

## Architecture

### Core and drivers

- `MDB2.php` (about 4,600 lines) holds the `MDB2` factory class (`factory` / `connect` / `singleton` / `parseDSN` / `loadClass`), the base class of every driver `MDB2_Driver_Common`, plus `MDB2_Result_Common`, `MDB2_Statement_Common`, `MDB2_Module_Common` and `MDB2_Error`.
- The phptype in the DSN selects `MDB2_Driver_<phptype>` in `MDB2/Driver/<phptype>.php`. Each of these files also contains `MDB2_Result_<phptype>`, `MDB2_BufferedResult_<phptype>` and `MDB2_Statement_<phptype>`.
- Only `pgsql`, `mysqli` and `sqlite3` are covered by tests and CI. The other drivers (`mysql`, `mssql`, `oci8`, `ibase`, `fbsql`, `odbc`, `sqlsrv`, `sqlite`, `querysim`) are untested.

### Modules

- Features are loaded lazily by `loadModule()` and attached as properties such as `$db->manager`. When `$phptype_specific` is true it loads `MDB2_Driver_<Module>_<phptype>` (`MDB2/Driver/<Module>/<phptype>.php`, extending `Common.php` in the same directory); when false it loads `MDB2_<Module>` (for example `MDB2/Extended.php`).
- Driver-specific modules: `Datatype` (type conversion and quoting), `Manager` (DDL, listing objects), `Reverse` (schema introspection such as `tableInfo`), `Function` and `Native`. Driver-independent modules: `Extended`, `Date`, `LOB` and `Iterator`.
- `MDB2_Driver_Common::__call()` routes methods by the prefixes in `options['modules']` (`ex` / `dt` / `mg` / `rv` / `na` / `fc`). For example, `$db->mgListTables()` calls `Manager::listTables()`.
- When fixing a bug, check both the shared implementation in `MDB2.php` and the overrides in each driver file and module. The same logic is often duplicated per driver.

### Query rewriting

- `setLimit()` rewrites the query in `_modifyQuery()` (and `_modifyManipQuery()` on pgsql). The clauses are looked for in the result of `_removeLiteralsAndComments()`, which blanks out string literals, delimited identifiers and comments without changing positions.
- That scanner must read the query exactly as the server does, or a quoted value can be taken for SQL. It follows the string quotes, the backslash escapes (`_backslashEscapesInStrings()`), dollar quoting on pgsql and the multibyte charset of the connection (`_multibyteCharPattern()`, where the second byte of a character can be 0x5C).
- The scanner reads the connection with `_scanConnection()` and never connects. The callers of `_modifyQuery()` connect first, and `standaloneQuery()` sets `scan_connection` to its DBA connection.

### Error handling

- In PEAR style, failures return an `MDB2_Error` (`PEAR_Error`) instead of throwing. Callers check them with `MDB2::isError()`. `PEAR.php` comes from the `pear/pear-core-minimal` dependency.
- Fixes must not swallow failures. Return a portable `MDB2_ERROR_*` code through `raiseError()`.

## Tests

- `tests/TestCase.php` is the base class. `setUp()` connects and creates the per-phptype `mdb2_users` table, and `tearDown()` drops it. The schema is in `$schema` and the MDB2 type names are in `$types`.
- `assertNotError()` checks for `MDB2_Error`. Driver-specific tests call `requireDriver('pgsql', ...)` to skip the other drivers.
- An existing bug found by a test and not fixed right away is marked incomplete with `knownBug('description (#issue)', $condition)` and tracked in a GitHub issue. Remove the `knownBug()` call once it is fixed (#9 to #14 were fixed in PR #16).
- `tests/bootstrap.php` sets up globals that MDB2 reads without initialising (`$GLOBALS['_MDB2_dsninfo_default']`, `$GLOBALS['_MDB2_databases']`).

## Releases

- Before a release, check the changes since the previous tag for security regressions: run the same hostile values (quotes, backslashes, comments, NUL bytes, multibyte characters ending in 0x5C, values such as `x LIMIT 1` or `a where true) --`) through `quote()`, `matchPattern()` and `setLimit()` on the previous tag and on `master`, on PHP 7.4 and 8.5 × pgsql / mysqli / sqlite3, and compare the results. Every difference must be stricter, not looser.
- Bump the minor version when a release changes behavior (e.g. a value that was accepted now returns an `MDB2_Error`), and the patch version otherwise.
- Lightweight tags are `X.Y.Z` (no `v`). GitHub Release titles are `vX.Y.Z`.
- Write release notes in English. Translate any Japanese PR titles that `--generate-notes` produces.
- Pass the full SHA to `gh release create --target`. A short SHA returns HTTP 400.
