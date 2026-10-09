<?php

namespace MDB2\Tests;

class DatatypeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertNotError($this->db->loadModule('Datatype', null, true));
    }

    public function testRoundTrip()
    {
        $data = $this->populateUsers();
        $rows = $this->db->queryAll(
            'SELECT '.implode(', ', array_keys(self::$types)).' FROM mdb2_users ORDER BY id',
            self::$types
        );
        $this->assertNotError($rows);
        $this->assertCount(count($data), $rows);
        foreach ($rows as $row) {
            $expected = $data[$row['id']];
            $this->assertSame($expected['id'], $row['id']);
            $this->assertSame($expected['name'], $row['name']);
            $this->assertSame($expected['email'], $row['email']);
            // SQLite returns NUMERIC values as float
            $this->assertSame($expected['price'], (string) $row['price']);
            $this->assertEqualsWithDelta($expected['rate'], $row['rate'], 0.0001);
            $this->assertSame($expected['is_active'], $row['is_active']);
            $this->assertSame($expected['birthday'], $row['birthday']);
            $this->assertSame($expected['login_time'], $row['login_time']);
            $this->assertSame($expected['updated_at'], $row['updated_at']);
            $this->assertSame($expected['note'], $this->readClob($row['note']));
        }
    }

    public function testPortabilityRtrim()
    {
        $this->populateUsers(1);
        $this->db->exec("UPDATE mdb2_users SET name = 'trailing   ' WHERE id = 1");
        $this->assertSame('trailing', $this->db->queryOne('SELECT name FROM mdb2_users WHERE id = 1', 'text'));

        $this->db->setOption('portability', MDB2_PORTABILITY_ALL ^ MDB2_PORTABILITY_RTRIM);
        $this->assertSame('trailing   ', $this->db->queryOne('SELECT name FROM mdb2_users WHERE id = 1', 'text'));
    }

    /**
     * @dataProvider quoteProvider
     */
    public function testQuote($type, $value, $expected)
    {
        $this->assertSame($expected, (string) $this->db->quote($value, $type));
    }

    public function quoteProvider()
    {
        return array(
            'integer' => array('integer', '12abc', '12'),
            'integer null' => array('integer', null, 'NULL'),
            'decimal' => array('decimal', '1,234.5678', '1234.5678'),
            'float' => array('float', '1.5', '1.5'),
            'date' => array('date', '2026-10-09', "'2026-10-09'"),
            'timestamp' => array('timestamp', '2026-10-09 12:34:56', "'2026-10-09 12:34:56'"),
        );
    }

    public function testQuoteBoolean()
    {
        $true = (string) $this->db->quote(true, 'boolean');
        $false = (string) $this->db->quote(false, 'boolean');
        $this->assertNotSame($true, $false);
        $this->assertSame('NULL', $this->db->quote(null, 'boolean'));
    }

    public function testConvertResult()
    {
        $this->assertSame(10, $this->db->datatype->convertResult('10', 'integer'));
        $this->assertSame(1.5, $this->db->datatype->convertResult('1.5', 'float'));
        $this->assertTrue($this->db->datatype->convertResult('1', 'boolean'));
        $this->assertFalse($this->db->datatype->convertResult('0', 'boolean'));
        $this->assertNull($this->db->datatype->convertResult(null, 'integer'));
    }

    public function testGetDeclaration()
    {
        $declaration = $this->db->datatype->getDeclaration('text', 'name', array('type' => 'text', 'length' => 20, 'notnull' => true));
        $this->assertNotError($declaration);
        $this->assertMatchesRegularExpression('/^name\b.*\b(VARCHAR|CHAR|TEXT)\b.*NOT NULL/i', $declaration);
    }

    /**
     * @param mixed $value a string or an MDB2 LOB resource
     * @return string
     */
    private function readClob($value)
    {
        if (!is_resource($value)) {
            return $value;
        }
        $contents = '';
        while (!feof($value)) {
            $contents .= fread($value, 8192);
        }
        $this->db->datatype->destroyLOB($value);

        return $contents;
    }
}
