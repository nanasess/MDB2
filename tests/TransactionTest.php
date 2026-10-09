<?php

namespace MDB2\Tests;

class TransactionTest extends TestCase
{
    public function testCommit()
    {
        $this->assertFalse($this->db->inTransaction());
        $this->assertNotError($this->db->beginTransaction());
        $this->assertTrue($this->db->inTransaction());
        $this->populateUsers(2);
        $this->assertNotError($this->db->commit());
        $this->assertFalse($this->db->inTransaction());

        $this->assertSame(2, $this->countUsersFromAnotherConnection());
    }

    public function testRollback()
    {
        $this->populateUsers(1);
        $this->assertNotError($this->db->beginTransaction());
        $this->assertNotError($this->db->exec('DELETE FROM mdb2_users'));
        $this->assertSame(0, $this->db->queryOne('SELECT COUNT(*) FROM mdb2_users', 'integer'));
        $this->assertNotError($this->db->rollback());
        $this->assertFalse($this->db->inTransaction());
        $this->assertSame(1, $this->db->queryOne('SELECT COUNT(*) FROM mdb2_users', 'integer'));
    }

    public function testUncommittedChangesAreInvisibleToOtherConnections()
    {
        $this->requireDriver('pgsql', 'mysqli');
        $this->assertNotError($this->db->beginTransaction());
        $this->populateUsers(1);
        $this->assertSame(0, $this->countUsersFromAnotherConnection());
        $this->assertNotError($this->db->rollback());
    }

    public function testDisconnectRollsBackTransaction()
    {
        $this->requireDriver('pgsql', 'mysqli');
        $this->assertNotError($this->db->beginTransaction());
        $this->populateUsers(1);
        $this->assertTrue($this->db->disconnect());
        $this->assertEmpty($this->db->inTransaction());
        $this->assertSame(0, $this->countUsersFromAnotherConnection());
    }

    public function testCommitWithoutTransactionFails()
    {
        $this->assertMDB2Error(MDB2_ERROR_INVALID, $this->db->commit());
    }

    public function testSavepoint()
    {
        $this->requireDriver('pgsql', 'mysqli');
        $this->populateUsers(1);
        $this->assertNotError($this->db->beginTransaction());
        $this->assertNotError($this->db->beginTransaction('sp1'));
        $this->assertNotError($this->db->exec('DELETE FROM mdb2_users'));
        $this->assertNotError($this->db->rollback('sp1'));
        $this->assertNotError($this->db->commit());
        $this->assertSame(1, $this->db->queryOne('SELECT COUNT(*) FROM mdb2_users', 'integer'));
    }

    public function testSqliteSavepointIsNotSupported()
    {
        $this->requireDriver('sqlite3');
        $this->assertNotError($this->db->beginTransaction());
        $this->assertMDB2Error(MDB2_ERROR_UNSUPPORTED, $this->db->beginTransaction('sp1'));
        $this->assertNotError($this->db->rollback());
    }

    /**
     * @return int
     */
    private function countUsersFromAnotherConnection()
    {
        if ($this->db->phptype === 'sqlite3') {
            // an in-memory database cannot be shared with another connection
            return $this->db->queryOne('SELECT COUNT(*) FROM mdb2_users', 'integer');
        }
        $db = $this->connect(array(), true);
        $count = $this->assertNotError($db->queryOne('SELECT COUNT(*) FROM mdb2_users', 'integer'));
        $db->disconnect();

        return $count;
    }
}
