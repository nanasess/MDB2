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
     * @dataProvider existingLimitProvider
     */
    public function testSetLimitKeepsExistingLimitClause($where)
    {
        $this->populateUsers(5);
        $this->assertNotError($this->db->setLimit(2));
        $this->assertCount(3, $this->assertNotError($this->db->queryCol(
            'SELECT id FROM mdb2_users'.$this->buildWhere($where).' ORDER BY id LIMIT 3'
        )));
    }

    public function existingLimitProvider()
    {
        return array(
            'no literal' => array(null),
            'quote in literal' => array(array('quote', "it's")),
            'backslash in literal' => array(array('quote', 'C:\\')),
        );
    }

    public function testSetLimitKeepsExistingLimitClauseAfterDoubleQuotedString()
    {
        // MySQL accepts double quoted strings unless ANSI_QUOTES is set
        $this->requireDriver('mysqli');
        $this->populateUsers(5);
        $this->assertNotError($this->db->setLimit(2));
        $this->assertCount(3, $this->assertNotError($this->db->queryCol(
            'SELECT id FROM mdb2_users WHERE name <> "it\'s" ORDER BY id LIMIT 3'
        )));
    }

    /**
     * @dataProvider sqliteIdentifierProvider
     */
    public function testSetLimitKeepsExistingLimitClauseAfterSqliteIdentifier($alias)
    {
        $this->requireDriver('sqlite3');
        $this->populateUsers(5);
        $this->assertNotError($this->db->setLimit(2));
        $this->assertCount(3, $this->assertNotError($this->db->queryCol(
            'SELECT id FROM mdb2_users AS '.$alias.' ORDER BY id LIMIT 3'
        )));
    }

    public function sqliteIdentifierProvider()
    {
        return array(
            'brackets' => array("[it's]"),
            'backticks' => array("`it's`"),
        );
    }

    /**
     * @dataProvider dollarQuotedStringProvider
     */
    public function testSetLimitKeepsExistingLimitClauseAfterDollarQuotedString($literal)
    {
        $this->requireDriver('pgsql');
        $this->populateUsers(5);
        $this->assertNotError($this->db->setLimit(2));
        $this->assertCount(3, $this->assertNotError($this->db->queryCol(
            'SELECT id FROM mdb2_users WHERE name <> '.$literal.' ORDER BY id LIMIT 3'
        )));
    }

    public function dollarQuotedStringProvider()
    {
        return array(
            'without tag' => array("\$\$it's\$\$"),
            'with tag' => array("\$t\$it's \$\$ LIMIT 1\$t\$"),
        );
    }

    public function testSetLimitKeepsExistingLimitClauseAfterIdentifierWithDollarSign()
    {
        $this->requireDriver('pgsql');
        $this->populateUsers(5);
        $this->assertNotError($this->db->setLimit(2));
        $this->assertCount(3, $this->assertNotError($this->db->queryCol(
            'SELECT id FROM mdb2_users AS foo$tag$ ORDER BY id LIMIT 3'
        )));
    }

    /**
     * @dataProvider trailingClauseInLiteralProvider
     */
    public function testSetLimitWithTrailingClauseInLiteral($value)
    {
        $this->requireDriver('mysqli');
        $this->populateUsers(5);
        $this->assertSame(1, $this->db->exec(
            'UPDATE mdb2_users SET name = '.$this->db->quote($value, 'text').' WHERE id = 1'
        ));
        $this->assertNotError($this->db->setLimit(2));
        // the literal ends the query, so the clause in it is at the end
        $ids = $this->assertNotError($this->db->queryCol(
            'SELECT id FROM mdb2_users WHERE name <> "'.$value.'"', 'integer'
        ));
        $this->assertCount(2, $ids);
        $this->assertNotContains(1, $ids);
    }

    public function trailingClauseInLiteralProvider()
    {
        return array(
            'into outfile' => array(' INTO OUTFILE LIMIT 1'),
            'for update' => array('x FOR UPDATE'),
            'lock in share mode' => array('x LOCK IN SHARE MODE'),
        );
    }

    public function testSetLimitWithTrailingClause()
    {
        $this->requireDriver('mysqli');
        $this->populateUsers(5);
        $this->assertNotError($this->db->beginTransaction());
        $this->assertNotError($this->db->setLimit(2));
        $this->assertSame(array(1, 2), $this->assertNotError($this->db->queryCol(
            'SELECT id FROM mdb2_users ORDER BY id FOR UPDATE', 'integer'
        )));
        $this->assertNotError($this->db->rollback());
    }

    /**
     * setLimit() with UPDATE / DELETE failed on pgsql (#19).
     *
     * @dataProvider manipLimitProvider
     */
    public function testSetLimitWithManipQuery($query, $remaining, $updated, $keeps_first)
    {
        $this->requireDriver('pgsql');
        $this->populateUsers(5);
        $this->assertNotError($this->db->setLimit(2));
        $this->assertSame(2, $this->assertNotError($this->db->exec($query)));
        $this->assertSame($remaining, (int) $this->db->queryOne('SELECT COUNT(*) FROM mdb2_users'));
        $this->assertSame($updated, (int) $this->db->queryOne("SELECT COUNT(*) FROM mdb2_users WHERE note = 'x'"));
        if ($keeps_first) {
            // the row outside of the condition is neither deleted nor updated
            $this->assertSame(
                $this->getSampleUser(1)['note'],
                $this->db->queryOne('SELECT note FROM mdb2_users WHERE id = 1')
            );
        }
    }

    public function manipLimitProvider()
    {
        return array(
            'delete without where' => array('DELETE FROM mdb2_users', 3, 0, false),
            'delete with where' => array('DELETE FROM mdb2_users WHERE id >= 2', 3, 0, true),
            'update without where' => array("UPDATE mdb2_users SET note = 'x'", 5, 2, false),
            'update with where' => array("UPDATE mdb2_users SET note = 'x' WHERE id >= 2", 5, 2, true),
            'multiple lines' => array("UPDATE mdb2_users\nSET note = 'x'\nWHERE id >= 2", 5, 2, true),
            'where in subquery' => array("UPDATE mdb2_users SET note = (SELECT 'x' WHERE true) WHERE id >= 2", 5, 2, true),
            'line comment at the end' => array('DELETE FROM mdb2_users WHERE id >= 2 -- comment', 3, 0, true),
            'line comment without where' => array('DELETE FROM mdb2_users -- comment', 3, 0, false),
            'update with line comment without where' => array("UPDATE mdb2_users SET note = 'x' -- comment", 5, 2, false),
        );
    }

    /**
     * A ctid is only unique within one table, so rows of another child table
     * with the same ctid were also deleted (#19).
     */
    public function testSetLimitWithManipQueryOnInheritedTables()
    {
        $this->requireDriver('pgsql');
        $this->assertNotError($this->db->exec('DROP TABLE IF EXISTS mdb2_parent_test CASCADE'));
        $this->assertNotError($this->db->exec('CREATE TABLE mdb2_parent_test (id INTEGER NOT NULL, kind TEXT)'));
        try {
            foreach (array('a', 'b') as $kind) {
                $this->assertNotError($this->db->exec(
                    'CREATE TABLE mdb2_child_'.$kind.'_test () INHERITS (mdb2_parent_test)'
                ));
            }
            $this->assertNotError($this->db->exec("INSERT INTO mdb2_child_a_test VALUES (1, 'a'), (2, 'a'), (3, 'a')"));
            $this->assertNotError($this->db->exec("INSERT INTO mdb2_child_b_test VALUES (4, 'b'), (5, 'b'), (6, 'b')"));

            $this->assertNotError($this->db->setLimit(2));
            $this->assertSame(2, $this->assertNotError($this->db->exec(
                "DELETE FROM mdb2_parent_test WHERE kind = 'a'"
            )));
            $this->assertSame(
                array(4, 5, 6),
                $this->db->queryCol("SELECT id FROM mdb2_parent_test WHERE kind = 'b' ORDER BY id", 'integer')
            );
            $this->assertSame(1, (int) $this->db->queryOne("SELECT COUNT(*) FROM mdb2_parent_test WHERE kind = 'a'"));
        } finally {
            $this->assertNotError($this->db->exec('DROP TABLE mdb2_parent_test CASCADE'));
        }
    }

    /**
     * A WHERE in a value or a comment was taken as the WHERE clause, so the
     * rest of the value became part of the condition (#19).
     *
     * @dataProvider whereInLiteralProvider
     */
    public function testSetLimitWithManipQueryIgnoresWhereInLiteral($note, $comment)
    {
        $this->requireDriver('pgsql');
        $this->populateUsers(5);
        $this->assertNotError($this->db->setLimit(2));
        $this->assertSame(1, $this->assertNotError($this->db->exec(
            'UPDATE mdb2_users SET note = '.$this->db->quote($note, 'text').$comment.' WHERE id = 1'
        )));
        $this->assertSame(array(1), $this->db->queryCol(
            "SELECT id FROM mdb2_users WHERE note NOT LIKE 'note%' ORDER BY id", 'integer'
        ));
    }

    public function whereInLiteralProvider()
    {
        return array(
            'string literal' => array('x where true) --', ''),
            'string literal with quote' => array("x' WHERE true) --", ''),
            'block comment' => array('x', ' /* where true) -- */'),
        );
    }

    public function testSetLimitIgnoresLimitInDollarQuotedString()
    {
        $this->requireDriver('pgsql');
        $this->populateUsers(5);
        $this->assertNotError($this->db->setLimit(2));
        $this->assertCount(2, $this->assertNotError($this->db->queryCol(
            'SELECT id FROM mdb2_users WHERE name <> $$LIMIT 1$$'
        )));
    }

    public function testSetLimitKeepsExistingLimitClauseWithNoBackslashEscapes()
    {
        $this->requireDriver('mysqli');
        $this->populateUsers(5);
        $this->assertNotError($this->db->exec("SET SESSION sql_mode = CONCAT(@@sql_mode, ',NO_BACKSLASH_ESCAPES')"));
        $this->assertNotError($this->db->setLimit(2));
        // without backslash escapes 'C:\' is a complete literal
        $this->assertCount(3, $this->assertNotError($this->db->queryCol(
            "SELECT id FROM mdb2_users WHERE name <> 'C:\\' ORDER BY id LIMIT 3"
        )));
    }

    /**
     * The second byte of a multibyte character can be 0x5C (a backslash) in
     * these character sets, and it does not escape the quote after it.
     *
     * @dataProvider multibyteCharsetProvider
     */
    public function testSetLimitWithMultibyteCharset($phptype, $charset, $char)
    {
        $this->requireDriver($phptype);
        $this->populateUsers(5);
        $this->assertNotError($this->db->setCharset($charset));
        $char = hex2bin($char);
        if ($this->db->escape($char) !== $char) {
            // e.g. mysqlnd of PHP 7.4 escapes the second byte of a Big5
            // character, which the server then takes for an escaped quote
            $this->markTestSkipped('The client library does not take '.bin2hex($char).' for one character in '.$charset);
        }

        // a clause in the second literal must not be taken for the clause
        // of the query
        foreach (array('x FOR UPDATE', 'x LOCK IN SHARE MODE', 'x LIMIT 1') as $value) {
            $this->assertNotError($this->db->setLimit(2));
            $this->assertCount(2, $this->assertNotError($this->db->queryCol(
                'SELECT id FROM mdb2_users WHERE name <> '.$this->db->quote($char, 'text')
                .' AND name <> '.$this->db->quote($value, 'text')
            )), $value);
        }

        // nor a WHERE in it
        $this->assertNotError($this->db->setLimit(2));
        $this->assertSame(1, $this->assertNotError($this->db->exec(
            'UPDATE mdb2_users SET note = '.$this->db->quote($char, 'text')
            .', name = '.$this->db->quote(' WHERE true) --', 'text').' WHERE id = 1'
        )));
        $this->assertSame(array(1), $this->db->queryCol(
            "SELECT id FROM mdb2_users WHERE note NOT LIKE 'note%' ORDER BY id", 'integer'
        ));
        $this->assertSame($char, $this->db->queryOne('SELECT note FROM mdb2_users WHERE id = 1'));
    }

    public function multibyteCharsetProvider()
    {
        // characters whose second byte is 0x5C
        return array(
            'mysqli sjis' => array('mysqli', 'sjis', '955c'),
            'mysqli cp932' => array('mysqli', 'cp932', '955c'),
            'mysqli gbk' => array('mysqli', 'gbk', '815c'),
            'mysqli big5' => array('mysqli', 'big5', 'a15c'),
            'mysqli gb18030' => array('mysqli', 'gb18030', '815c'),
            'pgsql SJIS' => array('pgsql', 'SJIS', '955c'),
            'pgsql GBK' => array('pgsql', 'GBK', '815c'),
            'pgsql BIG5' => array('pgsql', 'BIG5', 'a15c'),
            'pgsql GB18030' => array('pgsql', 'GB18030', '815c'),
        );
    }

    public function testSetLimitIgnoresLimitInCommentAndIdentifier()
    {
        $this->populateUsers(5);

        $this->assertNotError($this->db->setLimit(2));
        $this->assertCount(2, $this->assertNotError($this->db->queryCol(
            'SELECT id FROM mdb2_users /* LIMIT 1 */'
        )));

        $this->assertNotError($this->db->setLimit(2));
        $this->assertCount(2, $this->assertNotError($this->db->queryCol(
            'SELECT id FROM mdb2_users '.$this->db->quoteIdentifier('LIMIT 1')
        )));
    }

    /**
     * @param array|null $where array('quote', value) for name <> value
     * @return string
     */
    private function buildWhere($where)
    {
        if (null === $where) {
            return '';
        }

        return ' WHERE name <> '.$this->db->quote($where[1], 'text');
    }

    /**
     * @dataProvider limitLikeValueProvider
     */
    public function testSetLimitWithLimitLikeValue($value)
    {
        $this->populateUsers(5);

        $this->assertNotError($this->db->setLimit(2));
        $this->assertCount(2, $this->assertNotError($this->db->queryCol(
            'SELECT id FROM mdb2_users WHERE name <> '.$this->db->quote($value, 'text')
        )));

        $this->assertNotError($this->db->setLimit(2));
        $stmt = $this->assertNotError($this->db->prepare('SELECT id FROM mdb2_users WHERE name <> ?'));
        $result = $this->assertNotError($stmt->execute(array($value)));
        $this->assertCount(2, $result->fetchCol());
        $result->free();
        $stmt->free();

        $this->assertNotError($this->db->setOption('emulate_prepared', true));
        $this->assertNotError($this->db->setLimit(2));
        $stmt = $this->assertNotError($this->db->prepare('SELECT id FROM mdb2_users WHERE name <> ?'));
        $result = $this->assertNotError($stmt->execute(array($value)));
        $this->assertCount(2, $result->fetchCol());
        $result->free();
        $stmt->free();
    }

    public function limitLikeValueProvider()
    {
        return array(
            'limit' => array('LIMIT 1'),
            'quote before limit' => array("it's LIMIT 1"),
            'backslash before limit' => array('C:\\ LIMIT 1'),
        );
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
