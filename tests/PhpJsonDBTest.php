<?php

namespace Kevinsillo\PhpJsonDB\Tests;

use Kevinsillo\PhpJsonDB\PhpJsonDB;
use PHPUnit\Framework\TestCase;

class PhpJsonDBTest extends TestCase
{
    public function testCreateTable()
    {
        $database = new PhpJsonDB('./files/');
        $database->dropTable('users');
        $this->assertInstanceOf(PhpJsonDB::class, $database);
    }

    /**
     * Demostrar cómo crear una tabla en la base de datos
     */
    public function testCanCreateTable()
    {
        $database = new PhpJsonDB('./files/');
        $database->createTable('users', [
            'name' => 'string',
            'email' => 'string'
        ]);

        $this->assertTrue($database->tableExists('users'));
    }
}
