<?php

namespace MDB2\Tests;

class PreparedStatementTest extends TestCase
{
    public function testPositionalPlaceholders()
    {
        $data = $this->populateUsers();
        $stmt = $this->assertNotError($this->db->prepare(
            'SELECT name FROM mdb2_users WHERE id = ? AND name = ?',
            array('integer', 'text')
        ));
        $result = $this->assertNotError($stmt->execute(array(2, 'user2')));
        $this->assertSame(array('name' => $data[2]['name']), $result->fetchRow());
        $result->free();
        $stmt->free();
    }

    public function testNamedPlaceholders()
    {
        $this->populateUsers();
        $stmt = $this->assertNotError($this->db->prepare(
            'SELECT id FROM mdb2_users WHERE name = :name',
            array('name' => 'text'),
            array('id' => 'integer')
        ));
        $result = $this->assertNotError($stmt->execute(array('name' => 'user3')));
        $this->assertSame(array('id' => 3), $result->fetchRow());
        $result->free();
        $stmt->free();
    }

    public function testManipReturnsAffectedRows()
    {
        $this->populateUsers();
        $stmt = $this->assertNotError($this->db->prepare(
            'UPDATE mdb2_users SET note = ? WHERE id <= ?',
            array('text', 'integer'),
            MDB2_PREPARE_MANIP
        ));
        $this->assertSame(2, $stmt->execute(array('updated', 2)));
        $this->assertSame(0, $stmt->execute(array('updated', 0)));
        $stmt->free();
    }

    public function testExecuteMultipleTimes()
    {
        $stmt = $this->assertNotError($this->db->prepare(
            'INSERT INTO mdb2_users (id, name) VALUES (?, ?)',
            array('integer', 'text'),
            MDB2_PREPARE_MANIP
        ));
        for ($i = 1; $i <= 5; ++$i) {
            $this->assertSame(1, $stmt->execute(array($i, 'name'.$i)));
        }
        $stmt->free();
        $this->assertSame(5, $this->db->queryOne('SELECT COUNT(*) FROM mdb2_users', 'integer'));
    }

    public function testNullValues()
    {
        $stmt = $this->assertNotError($this->db->prepare(
            'INSERT INTO mdb2_users (id, name, email, price, rate, is_active, birthday, updated_at, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array('integer', 'text', 'text', 'decimal', 'float', 'boolean', 'date', 'timestamp', 'clob'),
            MDB2_PREPARE_MANIP
        ));
        $this->assertSame(1, $stmt->execute(array(1, 'null', null, null, null, null, null, null, null)));
        $stmt->free();

        $row = $this->db->queryRow('SELECT email, price, rate, is_active, birthday, updated_at, note FROM mdb2_users WHERE id = 1');
        foreach ($row as $column => $value) {
            $this->assertNull($value, $column);
        }
    }

    public function testQuestionMarkInsideLiteralIsNotPlaceholder()
    {
        $this->populateUsers(1);
        $stmt = $this->assertNotError($this->db->prepare(
            "UPDATE mdb2_users SET note = 'what?' WHERE id = ?",
            array('integer'),
            MDB2_PREPARE_MANIP
        ));
        $this->assertSame(1, $stmt->execute(array(1)));
        $stmt->free();
        $this->assertSame('what?', $this->db->queryOne('SELECT note FROM mdb2_users WHERE id = 1'));
    }

    public function testPlaceholderCountMismatch()
    {
        $stmt = $this->assertNotError($this->db->prepare(
            'SELECT name FROM mdb2_users WHERE id = ? AND name = ?',
            array('integer', 'text')
        ));
        $this->assertTrue(\MDB2::isError($stmt->execute(array(1))));
        $stmt->free();
    }
}
