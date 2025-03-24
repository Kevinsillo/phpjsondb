<?php

namespace Kevinsillo\PhpJsonDB\Tests;

use Kevinsillo\PhpJsonDB\PhpJsonDB;
use PHPUnit\Framework\TestCase;

class PhpJsonDBTest extends TestCase
{
    private string $database_path;

    public function setUp(): void
    {
        $this->database_path = './files/.database';
    }

    public function testCreateTable()
    {
        $database = new PhpJsonDB($this->database_path);
        $database->dropTable('users');
        $this->assertInstanceOf(PhpJsonDB::class, $database);
    }

    /**
     * Demostrar cómo crear una tabla en la base de datos
     */
    public function testCanCreateTable()
    {
        $database = new PhpJsonDB($this->database_path);
        $database->createTable('users', [
            'name' => 'string',
            'email' => 'string'
        ]);

        $this->assertTrue($database->tableExists('users'));
    }

    /**
     * Test getTablePath
     */
    public function testGetTables()
    {
        $database = new PhpJsonDB($this->database_path);
        $tablesExpected = ['users'];
        $this->assertEquals($tablesExpected, $database->getTables());
    }

    /**
     * Test getTableInfo
     */
    public function testGetTableInfo()
    {
        $database = new PhpJsonDB($this->database_path);
        $tableInfoExpected = [
            'users' => [
                'auto_increment' => 1,
                'records_count' => 0,
                'structure' => [
                    'name' => 'string',
                    'email' => 'string'
                ],
                'created_at' => '2025-03-24 22:54:34',
                'updated_at' => '2025-03-24 22:54:34'
            ]
        ];
        $this->assertEquals(
            $this->getAllKeys($tableInfoExpected),
            $this->getAllKeys($database->getTablesInfo())
        );
    }

    /**
     * Test tableRecords
     */
    public function testTableRecords()
    {
        $database = new PhpJsonDB($this->database_path);
        $database->selectTable('users');
        $records = $database->tableRecordsCount();
        $this->assertEquals(0, $records);
    }

    /**
     * Iterate over the control file and get all the keys
     * 
     * @param array $controlFile 
     * @return array 
     */
    private function getAllKeys(array $controlFile)
    {
        $keys = [];
        foreach ($controlFile as $table => $info) {
            $keys[] = $table;
            if (gettype($info) === 'array') {
                $keys = array_merge($keys, $this->getAllKeys($info));
            }
        }
        return $keys;
    }
}
