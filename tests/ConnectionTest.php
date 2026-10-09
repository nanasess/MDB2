<?php

namespace MDB2\Tests;

use MDB2;

class ConnectionTest extends TestCase
{
    public function testConnect()
    {
        $this->assertInstanceOf('MDB2_Driver_Common', $this->db);
        $this->assertNotError($this->db->connect());
        $this->assertNotEmpty($this->db->getConnection());
    }

    public function testConnectionIsReused()
    {
        $connection = $this->assertNotError($this->db->getConnection());
        $this->assertNotError($this->db->connect());
        $this->assertSame($connection, $this->db->getConnection());
    }

    public function testDisconnect()
    {
        $this->assertNotError($this->db->getConnection());
        $this->assertTrue($this->db->disconnect());
        $this->assertEmpty($this->db->connection);

        // reconnects on demand
        $this->assertSame(1, (int) $this->assertNotError($this->db->queryOne('SELECT 1')));
    }

    public function testFactoryConnectsLazily()
    {
        $dsn = MDB2::parseDSN(self::getDsn());
        $dsn['new_link'] = true;
        $db = MDB2::factory($dsn);
        $this->assertNotError($db);
        $this->assertEmpty($db->connection);
        $this->assertSame(1, (int) $this->assertNotError($db->queryOne('SELECT 1')));
        $this->assertNotEmpty($db->connection);
        $db->disconnect();
    }

    public function testConnectFailure()
    {
        $this->requireDriver('pgsql', 'mysqli');
        $this->knownMysqliExceptionBug();
        $dsn = MDB2::parseDSN(self::getDsn());
        $dsn['password'] = 'invalid-password';
        $db = MDB2::connect($dsn);
        $this->assertTrue(MDB2::isError($db));
        $this->assertContains($db->getCode(), array(MDB2_ERROR_CONNECT_FAILED, MDB2_ERROR_ACCESS_VIOLATION));
    }

    public function testSetCharset()
    {
        $this->requireDriver('pgsql', 'mysqli');
        $this->assertNotError($this->db->setCharset('utf8'));
        $this->populateUsers(1);
        $this->assertNotError($this->db->exec(
            "UPDATE mdb2_users SET name = 'テスト' WHERE id = 1"
        ));
        $this->assertSame('テスト', $this->db->queryOne('SELECT name FROM mdb2_users WHERE id = 1'));
    }

    public function testGetServerVersion()
    {
        $this->requireDriver('pgsql', 'mysqli');
        $version = $this->assertNotError($this->db->getServerVersion());
        $this->assertIsArray($version);
        $this->assertGreaterThan(0, (int) $version['major']);
    }
}
