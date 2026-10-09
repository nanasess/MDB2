<?php

namespace MDB2\Tests;

class ManagerTest extends TestCase
{
    const TABLE = 'mdb2_manager_test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropTestTable();
    }

    protected function tearDown(): void
    {
        if ($this->db) {
            $this->dropTestTable();
        }
        parent::tearDown();
    }

    public function testListTables()
    {
        $tables = $this->assertNotError($this->db->manager->listTables());
        $this->assertContains('mdb2_users', $tables);
    }

    public function testListTableFields()
    {
        $fields = $this->assertNotError($this->db->manager->listTableFields('mdb2_users'));
        $this->assertSame(array_keys(self::$types), $fields);
    }

    public function testCreateAndDropTable()
    {
        $this->createTestTable();
        $this->assertContains(self::TABLE, $this->db->manager->listTables());
        $this->assertSame(
            array('id', 'name', 'amount', 'created_at'),
            $this->db->manager->listTableFields(self::TABLE)
        );

        $this->assertNotError($this->db->manager->dropTable(self::TABLE));
        $this->assertNotContains(self::TABLE, $this->db->manager->listTables());
    }

    public function testCreateAndListIndexes()
    {
        $this->createTestTable();
        $this->assertNotError($this->db->manager->createIndex(self::TABLE, 'name_idx', array(
            'fields' => array('name' => array()),
        )));
        $indexes = $this->assertNotError($this->db->manager->listTableIndexes(self::TABLE));
        $this->assertContains('name_idx', $indexes);

        $this->assertNotError($this->db->manager->dropIndex(self::TABLE, 'name_idx'));
        $this->assertNotContains('name_idx', $this->db->manager->listTableIndexes(self::TABLE));
    }

    public function testCreateConstraint()
    {
        $this->createTestTable();
        $this->assertNotError($this->db->manager->createConstraint(self::TABLE, 'name_unique', array(
            'unique' => true,
            'fields' => array('name' => array()),
        )));
        $this->assertContains('name_unique', $this->db->manager->listTableConstraints(self::TABLE));
    }

    /**
     * sqlite3 re-creates the table from the reversed definition, which
     * reports DECIMAL(p,s) as length "p,s" (#14).
     */
    public function testAlterTableAddColumn()
    {
        $this->createTestTable();
        $this->assertNotError($this->db->manager->alterTable(self::TABLE, array(
            'add' => array('memo' => array('type' => 'text', 'length' => 50)),
        ), false));
        $this->assertContains('memo', $this->db->manager->listTableFields(self::TABLE));

        $this->assertNotError($this->db->loadModule('Reverse', null, true));
        $definition = $this->assertNotError($this->db->reverse->getTableFieldDefinition(self::TABLE, 'amount'));
        $this->assertSame('10,2', (string) $definition[0]['length']);
    }

    /**
     * Field definitions returned by the reverse module can be passed back
     * to createTable() (#14).
     */
    public function testCreateTableFromReversedDefinition()
    {
        $this->assertNotError($this->db->loadModule('Reverse', null, true));
        $definition = $this->assertNotError($this->db->reverse->getTableFieldDefinition('mdb2_users', 'price'));
        $this->assertNotError($this->db->manager->createTable(self::TABLE, array(
            'price' => $definition[0],
        )));
        $definition = $this->assertNotError($this->db->reverse->getTableFieldDefinition(self::TABLE, 'price'));
        $this->assertSame('10,2', (string) $definition[0]['length']);
    }

    private function createTestTable()
    {
        $this->assertNotError($this->db->manager->createTable(self::TABLE, array(
            'id' => array('type' => 'integer', 'notnull' => true),
            'name' => array('type' => 'text', 'length' => 100),
            'amount' => array('type' => 'decimal', 'length' => 10, 'scale' => 2),
            'created_at' => array('type' => 'timestamp'),
        ), array('primary' => array('id' => array()))));
    }

    private function dropTestTable()
    {
        if (in_array(self::TABLE, $this->db->manager->listTables(), true)) {
            $this->assertNotError($this->db->manager->dropTable(self::TABLE));
        }
    }
}
