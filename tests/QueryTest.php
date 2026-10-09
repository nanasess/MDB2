<?php

namespace MDB2\Tests;

class QueryTest extends TestCase
{
    public function testQueryOne()
    {
        $this->populateUsers();
        $this->assertSame(3, $this->db->queryOne('SELECT COUNT(*) FROM mdb2_users', 'integer'));
        $this->assertSame(2, $this->db->queryOne('SELECT id FROM mdb2_users WHERE id = 2', 'integer'));
        $this->assertSame('user2', $this->db->queryOne('SELECT name FROM mdb2_users WHERE id = 2'));
        $this->assertNull($this->db->queryOne('SELECT name FROM mdb2_users WHERE id = 99'));
    }

    public function testQueryRow()
    {
        $this->populateUsers();
        $row = $this->db->queryRow('SELECT id, name FROM mdb2_users WHERE id = 1', array('id' => 'integer', 'name' => 'text'));
        $this->assertSame(array('id' => 1, 'name' => 'user1'), $row);

        // types are positional in MDB2_FETCHMODE_ORDERED
        $row = $this->db->queryRow('SELECT id, name FROM mdb2_users WHERE id = 1', array('integer', 'text'), MDB2_FETCHMODE_ORDERED);
        $this->assertSame(array(1, 'user1'), $row);

        $this->assertNull($this->db->queryRow('SELECT id FROM mdb2_users WHERE id = 99'));
    }

    public function testQueryCol()
    {
        $this->populateUsers();
        $this->assertSame(
            array('user1', 'user2', 'user3'),
            $this->db->queryCol('SELECT name FROM mdb2_users ORDER BY id')
        );
        $this->assertSame(
            array(1, 2, 3),
            $this->db->queryCol('SELECT id FROM mdb2_users ORDER BY id', 'integer')
        );
        $this->assertEquals(
            array(1, 2, 3),
            $this->db->queryCol('SELECT name, id FROM mdb2_users ORDER BY id', null, 1)
        );
        $this->assertSame(array(), $this->db->queryCol('SELECT name FROM mdb2_users WHERE id = 99'));
    }

    public function testQueryAll()
    {
        $this->populateUsers();
        $rows = $this->db->queryAll('SELECT id, name FROM mdb2_users ORDER BY id', array('id' => 'integer', 'name' => 'text'));
        $this->assertSame(array(
            array('id' => 1, 'name' => 'user1'),
            array('id' => 2, 'name' => 'user2'),
            array('id' => 3, 'name' => 'user3'),
        ), $rows);

        $rows = $this->db->queryAll('SELECT id, name FROM mdb2_users ORDER BY id', array('id' => 'integer', 'name' => 'text'), MDB2_FETCHMODE_ASSOC, true);
        $this->assertSame(array(1 => 'user1', 2 => 'user2', 3 => 'user3'), $rows);
    }

    public function testFetchModeObject()
    {
        $this->populateUsers(1);
        $row = $this->db->queryRow('SELECT id, name FROM mdb2_users', array('id' => 'integer', 'name' => 'text'), MDB2_FETCHMODE_OBJECT);
        $this->assertInstanceOf('stdClass', $row);
        $this->assertSame(1, $row->id);
        $this->assertSame('user1', $row->name);
    }

    public function testFetchRowLoop()
    {
        $data = $this->populateUsers();
        $result = $this->assertNotError($this->db->query('SELECT id, name FROM mdb2_users ORDER BY id', array('id' => 'integer', 'name' => 'text')));
        $ids = array();
        while ($row = $result->fetchRow()) {
            $this->assertNotError($row);
            $this->assertSame($data[$row['id']]['name'], $row['name']);
            $ids[] = $row['id'];
        }
        $result->free();
        $this->assertSame(array(1, 2, 3), $ids);
    }

    public function testExecReturnsAffectedRows()
    {
        $this->populateUsers();
        $this->assertSame(2, $this->db->exec("UPDATE mdb2_users SET note = 'x' WHERE id >= 2"));
        $this->assertSame(0, $this->db->exec('UPDATE mdb2_users SET note = NULL WHERE id = 99'));
        $this->assertSame(3, $this->db->exec('DELETE FROM mdb2_users'));
    }

    public function testSetLimit()
    {
        $this->populateUsers(5);
        $this->assertNotError($this->db->setLimit(2, 1));
        $this->assertSame(
            array(2, 3),
            $this->db->queryCol('SELECT id FROM mdb2_users ORDER BY id', 'integer')
        );
        // the limit is reset after each query
        $this->assertCount(5, $this->db->queryCol('SELECT id FROM mdb2_users'));
    }

    /**
     * @dataProvider quoteProvider
     */
    public function testQuoteRoundTrip($value)
    {
        $this->populateUsers(1);
        $this->assertSame(1, $this->db->exec(
            'UPDATE mdb2_users SET note = '.$this->db->quote($value, 'text').' WHERE id = 1'
        ));
        $this->assertSame($value, $this->db->queryOne('SELECT note FROM mdb2_users WHERE id = 1'));
    }

    public function quoteProvider()
    {
        return array(
            'single quote' => array("O'Reilly"),
            'double quote' => array('say "hello"'),
            'backslash' => array('C:\\path\\to\\file'),
            'backslash before quote' => array("\\' OR 1=1 --"),
            'multibyte' => array('日本語のテキスト'),
            'newline' => array("line1\nline2"),
        );
    }

    public function testQuote()
    {
        $this->assertSame('NULL', $this->db->quote(null, 'text'));
        $this->assertSame('1', (string) $this->db->quote(1, 'integer'));
        $this->assertSame('NULL', $this->db->quote('', 'integer'));
        $this->assertSame("'abc'", $this->db->quote('abc', 'text'));
    }

    public function testQuoteIdentifier()
    {
        $quoted = $this->db->quoteIdentifier('mdb2_users');
        $this->assertNotSame('mdb2_users', $quoted);
        $this->assertSame(0, (int) $this->db->queryOne('SELECT COUNT(*) FROM '.$quoted));
    }

    public function testEscapePattern()
    {
        $this->populateUsers(1);
        $this->db->exec("UPDATE mdb2_users SET note = '100%' WHERE id = 1");
        $pattern = $this->db->quote('100%', 'text', true, true);
        $this->assertSame(1, (int) $this->db->queryOne(
            'SELECT COUNT(*) FROM mdb2_users WHERE note LIKE '.$pattern
        ));
    }
}
