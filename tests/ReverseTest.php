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
        $this->knownPgsqlConsrcBug();
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

    public function testSqliteInlineUniqueColumn()
    {
        $this->requireDriver('sqlite3');
        $this->knownBug('sqlite3 reverse module drops columns declared with an inline UNIQUE constraint (#12)');
        $this->assertNotError($this->db->exec('CREATE TABLE mdb2_inline_test (id INTEGER NOT NULL, code VARCHAR(10) UNIQUE)'));
        $this->assertSame(array('id', 'code'), $this->db->manager->listTableFields('mdb2_inline_test'));
    }

    public function testSqliteTableInfoWithInlinePrimaryKey()
    {
        $this->requireDriver('sqlite3');
        $this->knownBug('sqlite3 tableInfo() fails on tables with an inline PRIMARY KEY (#13)');
        $this->assertNotError($this->db->exec('CREATE TABLE mdb2_inline_test (id INTEGER NOT NULL PRIMARY KEY, name TEXT)'));
        $this->assertNotError($this->db->reverse->tableInfo('mdb2_inline_test'));
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
        $this->knownPgsqlConsrcBug();
        $constraints = $this->assertNotError($this->db->manager->listTableConstraints('mdb2_users'));
        $primary = $this->db->phptype === 'mysqli' ? 'primary' : 'mdb2_users_pkey';
        $this->assertContains($primary, $constraints);
        $definition = $this->assertNotError($this->db->reverse->getTableConstraintDefinition('mdb2_users', $primary));
        $this->assertTrue($definition['primary']);
        $this->assertSame(array('id'), array_keys($definition['fields']));
    }

    /**
     * pg_constraint.consrc was removed in PostgreSQL 12.
     */
    private function knownPgsqlConsrcBug()
    {
        if ($this->db->phptype !== 'pgsql') {
            return;
        }
        $version = $this->assertNotError($this->db->getServerVersion());
        $this->knownBug(
            'pgsql getTableConstraintDefinition() refers to pg_constraint.consrc removed in PostgreSQL 12 (#11)',
            (int) $version['major'] >= 12
        );
    }
}
