<?php

namespace MDB2\Tests;

use MDB2;
use MDB2_Driver_Common;

/**
 * Base class for the MDB2 test suite.
 *
 * The database to test is selected with the MDB2_TEST_DSN environment
 * variable (e.g. pgsql://user:pass@127.0.0.1/mdb2_test). An in-memory
 * SQLite3 database is used when it is not set.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    const DEFAULT_DSN = 'sqlite3:///:memory:';

    /**
     * @var MDB2_Driver_Common
     */
    protected $db;

    /**
     * Tables created by setUp(), keyed by phptype.
     *
     * @var array
     */
    protected static $schema = array(
        'pgsql' => array(
            'mdb2_users' => 'CREATE TABLE mdb2_users (
                id integer NOT NULL,
                name varchar(100) NOT NULL,
                email varchar(255),
                price numeric(10,2),
                rate double precision,
                is_active boolean,
                birthday date,
                login_time time,
                updated_at timestamp,
                note text,
                PRIMARY KEY (id),
                UNIQUE (email)
            )',
        ),
        'mysqli' => array(
            'mdb2_users' => 'CREATE TABLE mdb2_users (
                id int NOT NULL,
                name varchar(100) NOT NULL,
                email varchar(255),
                price decimal(10,2),
                rate double,
                is_active tinyint(1),
                birthday date,
                login_time time,
                updated_at datetime,
                note text,
                PRIMARY KEY (id),
                UNIQUE (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        ),
        'sqlite3' => array(
            'mdb2_users' => 'CREATE TABLE mdb2_users (
                id INTEGER NOT NULL,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(255),
                price DECIMAL(10,2),
                rate REAL,
                is_active BOOLEAN,
                birthday DATE,
                login_time TIME,
                updated_at DATETIME,
                note TEXT,
                PRIMARY KEY (id),
                UNIQUE (email)
            )',
        ),
    );

    /**
     * Column types of mdb2_users for MDB2's type conversion.
     *
     * @var array
     */
    protected static $types = array(
        'id' => 'integer',
        'name' => 'text',
        'email' => 'text',
        'price' => 'decimal',
        'rate' => 'float',
        'is_active' => 'boolean',
        'birthday' => 'date',
        'login_time' => 'time',
        'updated_at' => 'timestamp',
        'note' => 'clob',
    );

    protected function setUp(): void
    {
        $this->db = $this->connect();
        $this->dropTables();
        foreach ($this->getSchema() as $sql) {
            $this->assertNotError($this->db->exec($sql));
        }
    }

    protected function tearDown(): void
    {
        if ($this->db instanceof MDB2_Driver_Common) {
            $this->dropTables();
            $this->db->disconnect();
        }
        $this->db = null;
    }

    /**
     * @return string
     */
    protected static function getDsn()
    {
        $dsn = getenv('MDB2_TEST_DSN');

        return $dsn === false || $dsn === '' ? self::DEFAULT_DSN : $dsn;
    }

    /**
     * Connect with the same options as EC-CUBE 2 (SC_Query).
     *
     * @param array $options
     * @param bool $new_link open a new connection instead of reusing an
     *                       existing one (pg_connect() shares connections
     *                       with the same connection string)
     * @return MDB2_Driver_Common
     */
    protected function connect(array $options = array(), $new_link = false)
    {
        $dsn = MDB2::parseDSN(self::getDsn());
        if ($new_link) {
            $dsn['new_link'] = true;
        }
        $options = array_merge(array(
            'persistent' => false,
            'debug' => 0,
            'result_buffering' => false,
        ), $options);
        $db = MDB2::connect($dsn, $options);
        $this->assertNotError($db, 'connect');
        $charset = $db->setCharset('utf8');
        if (!MDB2::isError($charset, MDB2_ERROR_UNSUPPORTED)) {
            $this->assertNotError($charset, 'setCharset');
        }
        $db->setFetchMode(MDB2_FETCHMODE_ASSOC);

        return $db;
    }

    /**
     * @return array
     */
    protected function getSchema()
    {
        if (!isset(self::$schema[$this->db->phptype])) {
            $this->markTestSkipped('No test schema for '.$this->db->phptype);
        }

        return self::$schema[$this->db->phptype];
    }

    protected function dropTables()
    {
        $this->db->loadModule('Manager', null, true);
        $tables = $this->db->manager->listTables();
        $this->assertNotError($tables, 'listTables');
        foreach (array_keys($this->getSchema()) as $table) {
            if (in_array($table, $tables, true)) {
                $this->assertNotError($this->db->exec('DROP TABLE '.$table));
            }
        }
    }

    /**
     * Mark the test as incomplete because of a known bug in MDB2.
     *
     * @param string $description
     * @param bool $condition whether the bug affects the current environment
     */
    protected function knownBug($description, $condition = true)
    {
        if ($condition) {
            $this->markTestIncomplete('Known bug: '.$description);
        }
    }

    /**
     * The sqlite3 driver does not map native errors to MDB2 error codes:
     * the error message is read from $php_errormsg, which was removed in
     * PHP 8.0, and the "SQLite3::query(): ..." prefix is not stripped on
     * older versions.
     *
     * @param bool $php8_only whether only PHP 8+ is affected
     */
    protected function knownSqliteErrorMappingBug($php8_only = false)
    {
        $this->knownBug(
            'sqlite3 driver does not map native errors to MDB2 error codes (#9)',
            $this->db->phptype === 'sqlite3' && (!$php8_only || PHP_VERSION_ID >= 80000)
        );
    }

    /**
     * PHP 8.1 changed the default mysqli error mode to MYSQLI_REPORT_ERROR |
     * MYSQLI_REPORT_STRICT, so failed queries throw mysqli_sql_exception
     * instead of returning an MDB2 error.
     */
    protected function knownMysqliExceptionBug()
    {
        $this->knownBug(
            'mysqli driver throws mysqli_sql_exception on PHP 8.1+ instead of returning an MDB2 error (#10)',
            $this->db->phptype === 'mysqli' && PHP_VERSION_ID >= 80100
        );
    }

    /**
     * Skip the test unless the driver under test is one of $phptypes.
     */
    protected function requireDriver()
    {
        $phptypes = func_get_args();
        if (!in_array($this->db->phptype, $phptypes, true)) {
            $this->markTestSkipped('Only for '.implode(', ', $phptypes));
        }
    }

    /**
     * Insert sample rows into mdb2_users.
     *
     * @param int $rows
     * @return array the inserted rows keyed by id
     */
    protected function populateUsers($rows = 3)
    {
        $data = array();
        for ($i = 1; $i <= $rows; ++$i) {
            $data[$i] = $this->getSampleUser($i);
        }
        $columns = array_keys(self::$types);
        $stmt = $this->db->prepare(
            'INSERT INTO mdb2_users ('.implode(', ', $columns).') VALUES ('
                .implode(', ', array_fill(0, count($columns), '?')).')',
            array_values(self::$types),
            MDB2_PREPARE_MANIP
        );
        $this->assertNotError($stmt, 'prepare');
        foreach ($data as $row) {
            $this->assertNotError($stmt->execute(array_values($row)), 'execute');
        }
        $stmt->free();

        return $data;
    }

    /**
     * @param int $i
     * @return array
     */
    protected function getSampleUser($i)
    {
        return array(
            'id' => $i,
            'name' => 'user'.$i,
            'email' => 'user'.$i.'@example.com',
            'price' => sprintf('%d.%02d', $i * 100, $i),
            'rate' => $i + 0.5,
            'is_active' => $i % 2 === 1,
            'birthday' => sprintf('2000-01-%02d', $i),
            'login_time' => sprintf('12:34:%02d', $i),
            'updated_at' => sprintf('2026-10-%02d 12:34:56', $i),
            'note' => implode(' ', array_fill(0, 10, 'note'.$i)),
        );
    }

    /**
     * Fail if $value is a PEAR_Error.
     *
     * @param mixed $value
     * @param string $message
     * @return mixed $value
     */
    protected function assertNotError($value, $message = '')
    {
        if (MDB2::isError($value)) {
            $this->fail(trim($message.' '.$value->getMessage().' '.$value->getUserInfo()));
        }
        $this->addToAssertionCount(1);

        return $value;
    }

    /**
     * Assert that $value is a PEAR_Error with the given MDB2 error code.
     *
     * @param int $code
     * @param mixed $value
     */
    protected function assertMDB2Error($code, $value)
    {
        $this->assertTrue(MDB2::isError($value), 'Expected an MDB2 error');
        $this->assertSame(
            $code,
            $value->getCode(),
            sprintf('Expected "%s" but got "%s" (%s)',
                MDB2::errorMessage($code), $value->getMessage(), $value->getUserInfo())
        );
    }
}
