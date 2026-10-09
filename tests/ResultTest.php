<?php

namespace MDB2\Tests;

class ResultTest extends TestCase
{
    public function testNumCols()
    {
        $this->populateUsers();
        $result = $this->assertNotError($this->db->query('SELECT id, name FROM mdb2_users'));
        $this->assertSame(2, $result->numCols());
        $this->assertSame(array('id' => 0, 'name' => 1), $result->getColumnNames());
        $result->free();
    }

    public function testNumRowsWithBuffering()
    {
        $this->populateUsers();
        $this->db->setOption('result_buffering', true);
        $result = $this->assertNotError($this->db->query('SELECT id FROM mdb2_users'));
        $this->assertSame(3, $result->numRows());
        $result->free();
    }

    public function testFetchOne()
    {
        $this->populateUsers();
        $result = $this->assertNotError($this->db->query('SELECT id, name FROM mdb2_users ORDER BY id', array('id' => 'integer', 'name' => 'text')));
        $this->assertSame('user1', $result->fetchOne(1));
        $result->free();
    }

    public function testFetchCol()
    {
        $this->populateUsers();
        // fetchCol() fetches in MDB2_FETCHMODE_ORDERED, so types are positional
        $result = $this->assertNotError($this->db->query('SELECT id FROM mdb2_users ORDER BY id', array('integer')));
        $this->assertSame(array(1, 2, 3), $result->fetchCol());
        $result->free();
    }

    public function testFetchAll()
    {
        $this->populateUsers(2);
        $result = $this->assertNotError($this->db->query('SELECT id, name FROM mdb2_users ORDER BY id', array('id' => 'integer', 'name' => 'text')));
        $this->assertSame(array(
            array('id' => 1, 'name' => 'user1'),
            array('id' => 2, 'name' => 'user2'),
        ), $result->fetchAll());
        $result->free();
    }

    public function testEmptyResult()
    {
        $result = $this->assertNotError($this->db->query('SELECT id FROM mdb2_users'));
        $this->assertNull($result->fetchRow());
        $this->assertSame(array(), $result->fetchAll());
        $result->free();
    }

    /**
     * PHP 8.1+ returns PgSql\Result objects instead of resources (#8).
     */
    public function testFreeReleasesResult()
    {
        $this->populateUsers();
        $result = $this->assertNotError($this->db->query('SELECT id FROM mdb2_users'));
        $resource = $result->getResource();
        $this->assertNotEmpty($resource);
        $this->assertSame(MDB2_OK, $result->free());
        $this->assertFalse($result->getResource());

        if ($this->db->phptype !== 'pgsql') {
            return;
        }
        if (!is_object($resource)) {
            $this->assertSame('resource (closed)', gettype($resource));
        } else {
            // PgSql\Result (PHP 8.1+)
            try {
                pg_num_rows($resource);
                $this->fail('The result has not been freed');
            } catch (\Error $e) {
                $this->assertStringContainsString('already been', $e->getMessage());
            }
        }
    }

    public function testMultipleOpenResults()
    {
        $this->populateUsers();
        $outer = $this->assertNotError($this->db->query('SELECT id FROM mdb2_users ORDER BY id', array('id' => 'integer')));
        $pairs = array();
        while ($row = $outer->fetchRow()) {
            $pairs[$row['id']] = $this->db->queryOne('SELECT name FROM mdb2_users WHERE id = '.$row['id']);
        }
        $outer->free();
        $this->assertSame(array(1 => 'user1', 2 => 'user2', 3 => 'user3'), $pairs);
    }
}
