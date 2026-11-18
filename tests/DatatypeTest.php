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

    /**
     * @dataProvider invalidNumberProvider
     */
    public function testQuoteRejectsInvalidNumber($type, $value)
    {
        $this->assertMDB2Error(MDB2_ERROR_INVALID_NUMBER, $this->db->quote($value, $type));
    }

    public function invalidNumberProvider()
    {
        return array(
            'decimal expression' => array('decimal', '1-1'),
            'decimal with comment' => array('decimal', '1--'),
            'decimal without digits' => array('decimal', 'abc'),
            'float expression' => array('float', '1-1'),
            'float mantissa' => array('float', '1-1e+5'),
        );
    }

    /**
     * @dataProvider validNumberProvider
     */
    public function testQuoteValidNumber($type, $value, $expected)
    {
        $this->assertSame($expected, (string) $this->assertNotError($this->db->quote($value, $type)));
    }

    public function validNumberProvider()
    {
        return array(
            'decimal negative' => array('decimal', '-12.5', '-12.5'),
            'decimal leading dot' => array('decimal', '.5', '.5'),
            'decimal comma' => array('decimal', '1000,5', '1000.5'),
            'decimal php float' => array('decimal', 1.5, '1.5'),
            'decimal exponent' => array('decimal', '1.5e3', '1.5e3'),
            'float exponent' => array('float', '1.5e+20', '1.5E+20'),
            'float negative exponent' => array('float', '-2E-5', '-2E-05'),
        );
    }

    public function testMatchPatternWithQuote()
    {
        $this->populateUsers(2);
        $this->db->exec("UPDATE mdb2_users SET name = 'O''Reilly Media' WHERE id = 1");
        $pattern = $this->assertNotError(
            $this->db->datatype->matchPattern(array("O'Reilly", '%'), 'LIKE', 'name')
        );
        $this->assertSame(
            array(1),
            $this->db->queryCol('SELECT id FROM mdb2_users WHERE '.$pattern, 'integer')
        );
    }

    public function testMatchPatternWithBackslash()
    {
        $this->populateUsers(2);
        $this->assertSame(1, $this->db->exec(
            'UPDATE mdb2_users SET name = '.$this->db->quote('C:\\dir\\file').' WHERE id = 1'
        ));
        $pattern = $this->assertNotError(
            $this->db->datatype->matchPattern(array('C:\\dir\\', '%'), 'LIKE', 'name')
        );
        $this->assertSame(
            array(1),
            $this->db->queryCol('SELECT id FROM mdb2_users WHERE '.$pattern, 'integer')
        );
    }

    public function testMatchPatternEscapesWildcards()
    {
        // SQLite has no escape character for LIKE
        $this->requireDriver('pgsql', 'mysqli');
        $this->populateUsers(2);
        $this->db->exec("UPDATE mdb2_users SET name = '100%' WHERE id = 1");
        $this->db->exec("UPDATE mdb2_users SET name = '1000' WHERE id = 2");
        $pattern = $this->assertNotError(
            $this->db->datatype->matchPattern(array('100%'), 'LIKE', 'name')
        );
        $this->assertSame(
            array(1),
            $this->db->queryCol('SELECT id FROM mdb2_users WHERE '.$pattern, 'integer')
        );
    }

    /**
     * @dataProvider lobTypeProvider
     */
    public function testLobUrlIsNotReadByDefault($type)
    {
        $this->assertFalse($this->db->getOption('lob_allow_url_include'));
        $url = 'file://'.$this->createTempFile('file contents');

        $this->populateUsers(1);
        $stmt = $this->assertNotError($this->db->prepare(
            'UPDATE mdb2_users SET note = ? WHERE id = 1', array($type), MDB2_PREPARE_MANIP
        ));
        $this->assertSame(1, $this->assertNotError($stmt->execute(array($url))));
        $stmt->free();
        $this->assertLobNotRead($this->db->queryOne('SELECT note FROM mdb2_users WHERE id = 1'));

        $stmt = $this->assertNotError($this->db->prepare(
            'SELECT ? AS v', array($type), array('text')
        ));
        $result = $this->assertNotError($stmt->execute(array($url)));
        $this->assertLobNotRead($result->fetchOne());
        $result->free();
        $stmt->free();
    }

    public function lobTypeProvider()
    {
        return array(
            'clob' => array('clob'),
            'blob' => array('blob'),
        );
    }

    public function testLobUrlIsReadWhenAllowed()
    {
        $this->db->setOption('lob_allow_url_include', true);
        $url = 'file://'.$this->createTempFile('file contents');

        $this->populateUsers(1);
        $stmt = $this->assertNotError($this->db->prepare(
            'UPDATE mdb2_users SET note = ? WHERE id = 1', array('clob'), MDB2_PREPARE_MANIP
        ));
        $this->assertSame(1, $this->assertNotError($stmt->execute(array($url))));
        $stmt->free();
        $this->assertSame('file contents', $this->db->queryOne('SELECT note FROM mdb2_users WHERE id = 1'));
    }

    public function testLobFromResource()
    {
        $this->populateUsers(1);
        $fp = fopen($this->createTempFile('stream contents'), 'r');
        $stmt = $this->assertNotError($this->db->prepare(
            'UPDATE mdb2_users SET note = ? WHERE id = 1', array('clob'), MDB2_PREPARE_MANIP
        ));
        $this->assertSame(1, $this->assertNotError($stmt->execute(array($fp))));
        $stmt->free();
        fclose($fp);
        $this->assertSame('stream contents', $this->db->queryOne('SELECT note FROM mdb2_users WHERE id = 1'));
    }

    public function testNulByte()
    {
        $value = "before\0after";
        $quoted = $this->db->quote($value, 'text');
        if ($this->db->phptype === 'mysqli') {
            $this->assertSame($value, $this->db->queryOne('SELECT '.$this->assertNotError($quoted)));
        } else {
            // the escaping function would cut the value at the NUL byte
            $this->assertMDB2Error(MDB2_ERROR_INVALID, $quoted);
        }
    }

    /**
     * @param mixed $value
     */
    private function assertLobNotRead($value)
    {
        $value = (string) $this->readClob($value);
        $this->assertStringNotContainsString('file contents', $value);
        $this->assertStringNotContainsString(bin2hex('file contents'), $value);
    }

    /**
     * @var array
     */
    private $tempFiles = array();

    /**
     * @param string $contents
     * @return string path of a file that is removed after the test
     */
    private function createTempFile($contents)
    {
        $file = tempnam(sys_get_temp_dir(), 'mdb2');
        file_put_contents($file, $contents);
        $this->tempFiles[] = $file;

        return $file;
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        $this->tempFiles = array();
        parent::tearDown();
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
