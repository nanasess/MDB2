<?php

namespace MDB2\Tests;

use MDB2;

class ErrorTest extends TestCase
{
    public function testNoSuchTable()
    {
        $this->assertMDB2Error(MDB2_ERROR_NOSUCHTABLE, $this->db->query('SELECT * FROM mdb2_no_such_table'));
    }

    public function testNoSuchField()
    {
        $this->assertMDB2Error(MDB2_ERROR_NOSUCHFIELD, $this->db->query('SELECT no_such_field FROM mdb2_users'));
    }

    public function testSyntaxError()
    {
        $this->assertMDB2Error(MDB2_ERROR_SYNTAX, $this->db->query('SELEC * FROM mdb2_users'));
    }

    public function testUniqueConstraintViolation()
    {
        $this->populateUsers(1);
        $result = $this->db->exec("INSERT INTO mdb2_users (id, name) VALUES (1, 'duplicate')");
        $this->assertTrue(MDB2::isError($result));
        $this->assertContains($result->getCode(), array(MDB2_ERROR_CONSTRAINT, MDB2_ERROR_ALREADY_EXISTS));
    }

    public function testNotNullConstraintViolation()
    {
        $result = $this->db->exec('INSERT INTO mdb2_users (id, name) VALUES (1, NULL)');
        $this->assertTrue(MDB2::isError($result));
        $this->assertContains($result->getCode(), array(MDB2_ERROR_CONSTRAINT_NOT_NULL, MDB2_ERROR_CONSTRAINT));
    }

    public function testErrorContainsNativeMessage()
    {
        $error = $this->db->query('SELECT * FROM mdb2_no_such_table');
        $this->assertTrue(MDB2::isError($error));
        $this->assertStringContainsString('mdb2_no_such_table', $error->getUserInfo());
    }

    public function testErrorInfo()
    {
        $this->db->query('SELECT * FROM mdb2_no_such_table');
        $info = $this->db->errorInfo();
        $this->assertSame(MDB2_ERROR_NOSUCHTABLE, $info[0]);
        $this->assertNotEmpty($info[2]);
    }

    /**
     * PHP 8.1+ returns PgSql\Result objects instead of resources (#8).
     */
    public function testPgsqlErrorInfoWithResult()
    {
        $this->requireDriver('pgsql');
        $connection = $this->db->getConnection();
        pg_send_query($connection, 'SELECT * FROM mdb2_no_such_table');
        $result = pg_get_result($connection);
        $info = $this->db->errorInfo($result);
        $this->assertSame(MDB2_ERROR_NOSUCHTABLE, $info[0]);
        $this->assertStringContainsString('mdb2_no_such_table', $info[2]);
    }

    public function testConnectionStillUsableAfterError()
    {
        $this->db->query('SELECT * FROM mdb2_no_such_table');
        $this->assertSame(1, (int) $this->db->queryOne('SELECT 1'));
    }

    public function testPreparedStatementError()
    {
        $this->populateUsers(1);
        $stmt = $this->assertNotError($this->db->prepare(
            'INSERT INTO mdb2_users (id, name) VALUES (?, ?)',
            array('integer', 'text'),
            MDB2_PREPARE_MANIP
        ));
        $result = $stmt->execute(array(1, 'duplicate'));
        $stmt->free();
        $this->assertTrue(MDB2::isError($result));
        $this->assertContains($result->getCode(), array(MDB2_ERROR_CONSTRAINT, MDB2_ERROR_ALREADY_EXISTS));
    }

    /**
     * mysqli throws mysqli_sql_exception by default since PHP 8.1 (#10).
     */
    public function testMysqliReportModeIsPreserved()
    {
        $this->requireDriver('mysqli');
        $driver = new \mysqli_driver();
        $mode = $driver->report_mode;
        $this->assertTrue(MDB2::isError($this->db->query('SELECT * FROM mdb2_no_such_table')));
        $this->assertSame($mode, $driver->report_mode);
    }
}
