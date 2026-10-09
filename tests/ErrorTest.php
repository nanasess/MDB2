<?php

namespace MDB2\Tests;

use MDB2;

class ErrorTest extends TestCase
{
    public function testNoSuchTable()
    {
        $this->knownSqliteErrorMappingBug();
        $this->knownMysqliExceptionBug();
        $this->assertMDB2Error(MDB2_ERROR_NOSUCHTABLE, $this->db->query('SELECT * FROM mdb2_no_such_table'));
    }

    public function testNoSuchField()
    {
        $this->knownSqliteErrorMappingBug();
        $this->knownMysqliExceptionBug();
        $this->assertMDB2Error(MDB2_ERROR_NOSUCHFIELD, $this->db->query('SELECT no_such_field FROM mdb2_users'));
    }

    public function testSyntaxError()
    {
        $this->knownSqliteErrorMappingBug();
        $this->knownMysqliExceptionBug();
        $this->assertMDB2Error(MDB2_ERROR_SYNTAX, $this->db->query('SELEC * FROM mdb2_users'));
    }

    public function testUniqueConstraintViolation()
    {
        $this->knownSqliteErrorMappingBug();
        $this->knownMysqliExceptionBug();
        $this->populateUsers(1);
        $result = $this->db->exec("INSERT INTO mdb2_users (id, name) VALUES (1, 'duplicate')");
        $this->assertTrue(MDB2::isError($result));
        $this->assertContains($result->getCode(), array(MDB2_ERROR_CONSTRAINT, MDB2_ERROR_ALREADY_EXISTS));
    }

    public function testNotNullConstraintViolation()
    {
        $this->knownSqliteErrorMappingBug();
        $this->knownMysqliExceptionBug();
        $result = $this->db->exec('INSERT INTO mdb2_users (id, name) VALUES (1, NULL)');
        $this->assertTrue(MDB2::isError($result));
        $this->assertContains($result->getCode(), array(MDB2_ERROR_CONSTRAINT_NOT_NULL, MDB2_ERROR_CONSTRAINT));
    }

    public function testErrorContainsNativeMessage()
    {
        $this->knownMysqliExceptionBug();
        $error = $this->db->query('SELECT * FROM mdb2_no_such_table');
        $this->assertTrue(MDB2::isError($error));
        $this->assertStringContainsString('mdb2_no_such_table', $error->getUserInfo());
    }

    public function testErrorInfo()
    {
        $this->knownSqliteErrorMappingBug();
        $this->knownMysqliExceptionBug();
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
        $this->knownMysqliExceptionBug();
        $this->db->query('SELECT * FROM mdb2_no_such_table');
        $this->assertSame(1, (int) $this->db->queryOne('SELECT 1'));
    }
}
