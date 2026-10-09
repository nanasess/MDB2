<?php

namespace MDB2\Tests;

class SequenceTest extends TestCase
{
    const SEQUENCE = 'mdb2_test_seq';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropSequence();
    }

    protected function tearDown(): void
    {
        if ($this->db) {
            $this->dropSequence();
        }
        parent::tearDown();
    }

    public function testNextIdCreatesSequenceOnDemand()
    {
        // on-demand creation relies on the "no such table" error message
        $this->knownSqliteErrorMappingBug(true);
        $this->knownMysqliExceptionBug();
        $this->assertSame(1, (int) $this->assertNotError($this->db->nextID(self::SEQUENCE)));
        $this->assertSame(2, (int) $this->db->nextID(self::SEQUENCE));
        $this->assertSame(2, (int) $this->assertNotError($this->db->currID(self::SEQUENCE)));
    }

    public function testNextIdWithoutOnDemand()
    {
        $this->knownMysqliExceptionBug();
        $this->db->expectError('*');
        $result = $this->db->nextID(self::SEQUENCE, false);
        $this->db->popExpect();
        $this->assertTrue(\MDB2::isError($result));
    }

    public function testCreateAndListSequences()
    {
        $this->assertNotError($this->db->manager->createSequence(self::SEQUENCE, 10));
        $sequences = $this->assertNotError($this->db->manager->listSequences());
        $this->assertContains(self::SEQUENCE, $sequences);
        $this->assertSame(10, (int) $this->db->nextID(self::SEQUENCE));

        $this->assertNotError($this->db->manager->dropSequence(self::SEQUENCE));
        $this->assertNotContains(self::SEQUENCE, $this->db->manager->listSequences());
    }

    private function dropSequence()
    {
        $sequences = $this->assertNotError($this->db->manager->listSequences());
        if (in_array(self::SEQUENCE, $sequences, true)) {
            $this->assertNotError($this->db->manager->dropSequence(self::SEQUENCE));
        }
    }
}
