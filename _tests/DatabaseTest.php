<?php
/**
 * Class DatabaseTest
 */

namespace ChristopherL;

class DatabaseTest extends \PHPUnit\Framework\TestCase
{
    protected $database_server = '';
    protected $database_host = '';
    protected $database_user = '';
    protected $database_pass = '';
    protected $database_port = 12345;
    protected $database_prefix = '';
    protected $database_name = 'cl_database_test';
    protected $database_table = 'unit_tests_table';
    protected $db;

    public function testCreateTestTables()
    {
        include_once 'cl_database.php';

        $this->db = new \ChristopherL\Database([
            'server' => $this->database_server,
            'host' => $this->database_host,
            'username' => $this->database_user,
            'password' => $this->database_pass,
            'database' => $this->database_name,
            'port' => $this->database_port,
            'prefix' => $this->database_prefix
        ]);

        $result = $this->db->execute("CREATE TABLE " . $this->database_table . " (number_column float, string_column varchar(50), boolean_column smallint, date_column date, time_column time, fish_column varchar(10));");
        $this->assertEquals(true, $result['successful'], 'Create ' . $this->database_table . ' table failed');

        $result = $this->db->execute("CREATE TABLE " . $this->database_table . "_2 (number_column float, string_column varchar(50), boolean_column smallint, date_column date, time_column time, fish_column varchar(10));");
        $this->assertEquals(true, $result['successful'], 'Create ' . $this->database_table . ' table failed');
    }

    /**
     * @depends testCreateTestTables
     */
    public function testInsertRecords()
    {
        include_once 'cl_database.php';

        $this->db = new \ChristopherL\Database([
            'server' => $this->database_server,
            'host' => $this->database_host,
            'username' => $this->database_user,
            'password' => $this->database_pass,
            'database' => $this->database_name,
            'port' => $this->database_port,
            'prefix' => $this->database_prefix
        ]);

        $result = $this->db->insert(
            $this->database_table,
            [
                [
                    'number_column' => mt_rand(0, 1000),
                    'string_column' => str_shuffle('abcdefghijklmnopqrstuvwxyz1234567890'),
                    'boolean_column' => 1,
                    'date_column' => date('Y-m-d'),
                    'time_column' => date('H:i:s'),
                    'fish_column' => 'one fish',
                ],
                [
                    'number_column' => mt_rand(0, 1000),
                    'string_column' => str_shuffle('abcdefghijklmnopqrstuvwxyz1234567890'),
                    'boolean_column' => 1,
                    'date_column' => date('Y-m-d'),
                    'time_column' => date('H:i:s'),
                    'fish_column' => 'two fish',
                ],
                [
                    'number_column' => mt_rand(0, 1000),
                    'string_column' => str_shuffle('abcdefghijklmnopqrstuvwxyz1234567890'),
                    'boolean_column' => 1,
                    'date_column' => date('Y-m-d'),
                    'time_column' => date('H:i:s'),
                    'fish_column' => 'red fish',
                ],
                [
                    'number_column' => mt_rand(0, 1000),
                    'string_column' => str_shuffle('abcdefghijklmnopqrstuvwxyz1234567890'),
                    'boolean_column' => 1,
                    'date_column' => date('Y-m-d'),
                    'time_column' => date('H:i:s'),
                    'fish_column' => 'blue fish',
                ]
            ]
        );

        $this->assertEquals(4, $result);
    }

    /**
     * @depends testCreateTestTables
     */
    public function testUpdateRecords()
    {
        include_once 'cl_database.php';

        $this->db = new \ChristopherL\Database([
            'server' => $this->database_server,
            'host' => $this->database_host,
            'username' => $this->database_user,
            'password' => $this->database_pass,
            'database' => $this->database_name,
            'port' => $this->database_port,
            'prefix' => $this->database_prefix
        ]);

        $original = $this->db->select(
            $this->database_table,
            [
                $this->database_table => ['*']
            ],
            [
                'LIMIT' => 1
            ]
        );

        $this->assertEquals(1, count($original));

        $update = $this->db->update(
            $this->database_table,
            [
                'fish_column' => 'new fish',
            ],
            [
                'AND' => [
                    $this->database_table => [
                        'string_column[=]' => $original[0]['string_column'],
                        'number_column[=]' => $original[0]['number_column'],
                    ]
                ]
            ]
        );

        $this->assertEquals(1, $update);

        $updated = $this->db->select(
            $this->database_table,
            [
                $this->database_table => ['*']
            ],
            [
                'AND' => [
                    $this->database_table => [
                        'fish_column[=]' => 'new fish'
                    ]
                ]
            ]
        );

        $this->assertEquals(1, count($updated));
    }

    /**
     * @depends testCreateTestTables
     */
    public function testDeleteRecords()
    {
        include_once 'cl_database.php';

        $this->db = new \ChristopherL\Database([
            'server' => $this->database_server,
            'host' => $this->database_host,
            'username' => $this->database_user,
            'password' => $this->database_pass,
            'database' => $this->database_name,
            'port' => $this->database_port,
            'prefix' => $this->database_prefix
        ]);

        $result = $this->db->delete(
            $this->database_table,
            [
                'AND' => [
                    $this->database_table => [
                        'date_column[>]' => date('Y-m-d'),
                        'boolean_column[=]' => 0
                    ]
                ]
            ]
        );

        $this->assertEquals(0, $result);

        $result = $this->db->delete(
            $this->database_table,
            [
                'AND' => [
                    $this->database_table => [
                        'date_column[>=]' => date('Y-m-d'),
                        'boolean_column[=]' => 1
                    ]
                ]
            ]
        );

        $this->assertEquals(4, $result);
    }

    public function testSelectRecords()
    {
        include_once 'cl_database.php';

        $this->db = new \ChristopherL\Database([
            'server' => $this->database_server,
            'host' => $this->database_host,
            'username' => $this->database_user,
            'password' => $this->database_pass,
            'database' => $this->database_name,
            'port' => $this->database_port,
            'prefix' => $this->database_prefix
        ]);

        $result = $this->db->insert(
            $this->database_table,
            [
                [
                    'number_column' => mt_rand(0, 1000),
                    'string_column' => str_shuffle('abcdefghijklmnopqrstuvwxyz1234567890'),
                    'boolean_column' => 1,
                    'date_column' => date('Y-m-d'),
                    'time_column' => date('H:i:s'),
                    'fish_column' => 'one fish',
                ],
                [
                    'number_column' => mt_rand(0, 1000),
                    'string_column' => str_shuffle('abcdefghijklmnopqrstuvwxyz1234567890'),
                    'boolean_column' => 1,
                    'date_column' => date('Y-m-d'),
                    'time_column' => date('H:i:s'),
                    'fish_column' => 'two fish',
                ],
                [
                    'number_column' => mt_rand(0, 1000),
                    'string_column' => str_shuffle('abcdefghijklmnopqrstuvwxyz1234567890'),
                    'boolean_column' => 1,
                    'date_column' => date('Y-m-d'),
                    'time_column' => date('H:i:s'),
                    'fish_column' => 'red fish',
                ],
                [
                    'number_column' => mt_rand(0, 1000),
                    'string_column' => str_shuffle('abcdefghijklmnopqrstuvwxyz1234567890'),
                    'boolean_column' => 1,
                    'date_column' => date('Y-m-d'),
                    'time_column' => date('H:i:s'),
                    'fish_column' => 'blue fish',
                ]
            ]
        );
        $this->assertEquals(4, $result);

        $result = $this->db->insert(
            $this->database_table . '_2',
            [
                [
                    'number_column' => mt_rand(0, 1000),
                    'string_column' => str_shuffle('abcdefghijklmnopqrstuvwxyz1234567890'),
                    'boolean_column' => 1,
                    'date_column' => date('Y-m-d'),
                    'time_column' => date('H:i:s'),
                    'fish_column' => 'one fish',
                ],
                [
                    'number_column' => mt_rand(0, 1000),
                    'string_column' => str_shuffle('abcdefghijklmnopqrstuvwxyz1234567890'),
                    'boolean_column' => 1,
                    'date_column' => date('Y-m-d'),
                    'time_column' => date('H:i:s'),
                    'fish_column' => 'two fish',
                ],
                [
                    'number_column' => mt_rand(0, 1000),
                    'string_column' => str_shuffle('abcdefghijklmnopqrstuvwxyz1234567890'),
                    'boolean_column' => 1,
                    'date_column' => date('Y-m-d'),
                    'time_column' => date('H:i:s'),
                    'fish_column' => 'red fish',
                ],
                [
                    'number_column' => mt_rand(0, 1000),
                    'string_column' => str_shuffle('abcdefghijklmnopqrstuvwxyz1234567890'),
                    'boolean_column' => 1,
                    'date_column' => date('Y-m-d'),
                    'time_column' => date('H:i:s'),
                    'fish_column' => 'blue fish',
                ]
            ]
        );
        $this->assertEquals(4, $result);

        $result = $this->db->select(
            $this->database_table,
            [
                $this->database_table => ['string_column'],
                $this->database_table . '_2' => ['string_column{string_column_two}']
            ],
            [
                'AND' => [
                    $this->database_table => [
                        'date_column[>=]' => date('Y-m-d'),
                    ],
                    $this->database_table . '_2' => [
                        'date_column[>=]' => date('Y-m-d')
                    ]
                ]
            ],
            [
                '[>]' . $this->database_table . '_2' => ['fish_column', 'fish_column']
            ]
        );

        $this->assertEquals(4, count($result));

        foreach ($result as $record) {
            $this->assertNotEquals($record['string_column'], $record['string_column_two']);
            $this->assertEquals(36, strlen($record['string_column']));
            $this->assertEquals(36, strlen($record['string_column_two']));
        }
    }

    public function testDeleteTestTables()
    {
        include_once 'cl_database.php';

        $this->db = new \ChristopherL\Database([
            'server' => $this->database_server,
            'host' => $this->database_host,
            'username' => $this->database_user,
            'password' => $this->database_pass,
            'database' => $this->database_name,
            'port' => $this->database_port,
            'prefix' => $this->database_prefix
        ]);

        $result = $this->db->execute("DROP TABLE " . $this->database_table . ";");
        $this->assertEquals(true, $result['successful'], 'Drop ' . $this->database_table . ' table failed');

        $result = $this->db->execute("DROP TABLE " . $this->database_table . "_2;");
        $this->assertEquals(true, $result['successful'], 'Drop ' . $this->database_table . ' table failed');
    }
}
