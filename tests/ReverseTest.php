<?php

namespace MDB2\Tests;

class ReverseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertNotError($this->db->loadModule('Reverse', null, true));
    }

    public function testTableInfoByTableName()
    {
        $info = $this->assertNotError($this->db->reverse->tableInfo('mdb2_users'));
        $this->assertSame(array_keys(self::$types), array_column($info, 'name'));
        foreach ($info as $column) {
            $this->assertSame('mdb2_users', $column['table']);
        }
    }

    /**
     * PHP 8.1+ returns PgSql\Result objects instead of resources (#8).
     */
    public function testTableInfoByResult()
    {
        $this->populateUsers(1);
        $result = $this->assertNotError($this->db->query('SELECT id, name FROM mdb2_users'));
        $info = $this->db->reverse->tableInfo($result);
        $result->free();

        if ($this->db->phptype === 'sqlite3') {
            $this->assertMDB2Error(MDB2_ERROR_NOT_CAPABLE, $info);

            return;
        }
        $this->assertNotError($info);
        $this->assertSame(array('id', 'name'), array_column($info, 'name'));
    }

    public function testGetTableFieldDefinition()
    {
        $expected = array(
            'id' => 'integer',
            'name' => 'text',
            'price' => 'decimal',
            'rate' => 'float',
            'birthday' => 'date',
            'login_time' => 'time',
            'updated_at' => 'timestamp',
            'note' => 'clob',
        );
        foreach ($expected as $field => $type) {
            $definition = $this->assertNotError($this->db->reverse->getTableFieldDefinition('mdb2_users', $field), $field);
            $this->assertSame($type, $definition[0]['mdb2type'], $field);
        }

        $definition = $this->db->reverse->getTableFieldDefinition('mdb2_users', 'name');
        $this->assertSame(100, (int) $definition[0]['length']);
        $this->assertTrue($definition[0]['notnull']);
    }

    /**
     * Columns declared as REAL were dropped by the SQLite reverse module (#6).
     */
    public function testSqliteRealColumn()
    {
        $this->requireDriver('sqlite3');
        $this->assertContains('rate', $this->db->manager->listTableFields('mdb2_users'));
        $definition = $this->assertNotError($this->db->reverse->getTableFieldDefinition('mdb2_users', 'rate'));
        $this->assertSame('float', $definition[0]['mdb2type']);
    }

    public function testDecimalLength()
    {
        // the reverse module reports DECIMAL(p,s) as length "p,s"
        $definition = $this->assertNotError($this->db->reverse->getTableFieldDefinition('mdb2_users', 'price'));
        $this->assertSame('10,2', (string) $definition[0]['length']);
    }

    /**
     * Columns with an inline UNIQUE constraint were dropped (#12).
     */
    public function testSqliteInlineUniqueColumn()
    {
        $this->requireDriver('sqlite3');
        $this->assertNotError($this->db->exec(
            "CREATE TABLE mdb2_inline_test (id INTEGER NOT NULL, code VARCHAR(10) UNIQUE, a TEXT NOT NULL UNIQUE, b TEXT UNIQUE NOT NULL, c VARCHAR(10) DEFAULT 'x' UNIQUE)"
        ));
        $this->assertSame(
            array('id', 'code', 'a', 'b', 'c'),
            $this->db->manager->listTableFields('mdb2_inline_test')
        );
        $definition = $this->assertNotError($this->db->reverse->getTableFieldDefinition('mdb2_inline_test', 'b'));
        $this->assertTrue($definition[0]['notnull']);
        $definition = $this->assertNotError($this->db->reverse->getTableFieldDefinition('mdb2_inline_test', 'c'));
        $this->assertSame('x', $definition[0]['default']);
    }

    /**
     * tableInfo() failed on tables with an inline PRIMARY KEY (#13).
     *
     * @dataProvider inlinePrimaryKeyProvider
     */
    public function testSqliteInlinePrimaryKey($sql, $column)
    {
        $this->requireDriver('sqlite3');
        $this->assertNotError($this->db->exec($sql));
        $this->assertNotError($this->db->reverse->tableInfo('mdb2_inline_test'));
        $definition = $this->assertNotError(
            $this->db->reverse->getTableConstraintDefinition('mdb2_inline_test', 'primary')
        );
        $this->assertTrue($definition['primary']);
        $this->assertSame(array($column), array_keys($definition['fields']));
    }

    public function inlinePrimaryKeyProvider()
    {
        return array(
            'first column' => array('CREATE TABLE mdb2_inline_test (id INTEGER NOT NULL PRIMARY KEY, name TEXT)', 'id'),
            'second column' => array('CREATE TABLE mdb2_inline_test (price DECIMAL(10,2), code VARCHAR(10) PRIMARY KEY)', 'code'),
            'quoted' => array('CREATE TABLE mdb2_inline_test ("id" INTEGER PRIMARY KEY, "name" TEXT)', 'id'),
        );
    }

    public function testGetTableIndexDefinition()
    {
        $this->assertNotError($this->db->manager->createIndex('mdb2_users', 'name_idx', array(
            'fields' => array('name' => array()),
        )));
        $definition = $this->assertNotError($this->db->reverse->getTableIndexDefinition('mdb2_users', 'name_idx'));
        $this->assertSame(array('name'), array_keys($definition['fields']));
    }

    public function testGetTableConstraintDefinitionPrimary()
    {
        $this->requireDriver('pgsql', 'mysqli');
        $constraints = $this->assertNotError($this->db->manager->listTableConstraints('mdb2_users'));
        $primary = $this->db->phptype === 'mysqli' ? 'primary' : 'mdb2_users_pkey';
        $this->assertContains($primary, $constraints);
        $definition = $this->assertNotError($this->db->reverse->getTableConstraintDefinition('mdb2_users', $primary));
        $this->assertTrue($definition['primary']);
        $this->assertSame(array('id'), array_keys($definition['fields']));
    }
}
