<?php

namespace Test\Phinx\Db\Adapter;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use Phinx\Db\Adapter\SQLiteAdapter;

class SQLiteAdapterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Phinx\Db\Adapter\SQLiteAdapter
     */
    private $adapter;

    public function setUp()
    {
        if (!TESTS_PHINX_DB_ADAPTER_SQLITE_ENABLED) {
            $this->markTestSkipped('SQLite tests disabled. See TESTS_PHINX_DB_ADAPTER_SQLITE_ENABLED constant.');
        }

        $options = [
            'name' => TESTS_PHINX_DB_ADAPTER_SQLITE_DATABASE
        ];
        $this->adapter = new SQLiteAdapter($options, new ArrayInput([]), new NullOutput());

        // ensure the database is empty for each test
        $this->adapter->dropDatabase($options['name']);
        $this->adapter->createDatabase($options['name']);

        // leave the adapter in a disconnected state for each test
        $this->adapter->disconnect();
    }

    public function tearDown()
    {
        unset($this->adapter);
    }

    public function testConnection()
    {
        $this->assertTrue($this->adapter->getConnection() instanceof \PDO);
    }

    public function testBeginTransaction()
    {
        $this->adapter->getConnection()
            ->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->adapter->beginTransaction();

        $this->assertTrue(true, 'Transaction query succeeded');
    }

    public function testCreatingTheSchemaTableOnConnect()
    {
        $this->adapter->connect();
        $this->assertTrue($this->adapter->hasTable($this->adapter->getSchemaTableName()));
        $this->adapter->dropTable($this->adapter->getSchemaTableName());
        $this->assertFalse($this->adapter->hasTable($this->adapter->getSchemaTableName()));
        $this->adapter->disconnect();
        $this->adapter->connect();
        $this->assertTrue($this->adapter->hasTable($this->adapter->getSchemaTableName()));
    }

    public function testSchemaTableIsCreatedWithPrimaryKey()
    {
        $this->adapter->connect();
        $table = new \Phinx\Db\Table($this->adapter->getSchemaTableName(), [], $this->adapter);
        $this->assertTrue($this->adapter->hasIndex($this->adapter->getSchemaTableName(), ['version']));
    }

    public function testQuoteTableName()
    {
        $this->assertEquals('`test_table`', $this->adapter->quoteTableName('test_table'));
    }

    public function testQuoteColumnName()
    {
        $this->assertEquals('`test_column`', $this->adapter->quoteColumnName('test_column'));
    }

    public function testCreateTable()
    {
        $table = new \Phinx\Db\Table('ntable', [], $this->adapter);
        $table->addColumn('realname', 'string')
              ->addColumn('email', 'integer')
              ->save();
        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'realname'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'email'));
        $this->assertFalse($this->adapter->hasColumn('ntable', 'address'));
    }

    public function testCreateTableCustomIdColumn()
    {
        $table = new \Phinx\Db\Table('ntable', ['id' => 'custom_id'], $this->adapter);
        $table->addColumn('realname', 'string')
              ->addColumn('email', 'integer')
              ->save();

        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'custom_id'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'realname'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'email'));
        $this->assertFalse($this->adapter->hasColumn('ntable', 'address'));
    }

    public function testCreateTableWithNoOptions()
    {
        $this->markTestIncomplete();
        //$this->adapter->createTable('ntable', )
    }

    public function testCreateTableWithNoPrimaryKey()
    {
        $options = [
            'id' => false
        ];
        $table = new \Phinx\Db\Table('atable', $options, $this->adapter);
        $table->addColumn('user_id', 'integer')
              ->save();
        $this->assertFalse($this->adapter->hasColumn('atable', 'id'));
    }

    public function testCreateTableWithMultiplePrimaryKeys()
    {
        $options = [
            'id'            => false,
            'primary_key'   => ['user_id', 'tag_id']
        ];
        $table = new \Phinx\Db\Table('table1', $options, $this->adapter);
        $table->addColumn('user_id', 'integer')
              ->addColumn('tag_id', 'integer')
              ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', ['user_id', 'tag_id']));
        $this->assertTrue($this->adapter->hasIndex('table1', ['tag_id', 'USER_ID']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['tag_id', 'user_email']));
    }

    public function testCreateTableWithMultipleIndexes()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addColumn('name', 'string')
              ->addIndex('email')
              ->addIndex('name')
              ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', ['email']));
        $this->assertTrue($this->adapter->hasIndex('table1', ['name']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['email', 'user_email']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['email', 'user_name']));
    }

    public function testCreateTableWithUniqueIndexes()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addIndex('email', ['unique' => true])
              ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', ['email']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['email', 'user_email']));
    }

    public function testCreateTableWithNamedIndexes()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addIndex('email', ['name' => 'myemailindex'])
              ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', ['email']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['email', 'user_email']));
        $this->assertTrue($this->adapter->hasIndexByName('table1', 'myemailindex'));
    }

    public function testCreateTableWithMultiplePKsAndUniqueIndexes()
    {
        $this->markTestIncomplete();
    }

    public function testCreateTableWithForeignKey()
    {
        $refTable = new \Phinx\Db\Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new \Phinx\Db\Table('table', [], $this->adapter);
        $table->addColumn('ref_table_id', 'integer');
        $table->addForeignKey('ref_table_id', 'ref_table', 'id');
        $table->save();

        $this->assertTrue($this->adapter->hasTable($table->getName()));
        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testRenameTable()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $this->assertTrue($this->adapter->hasTable('table1'));
        $this->assertFalse($this->adapter->hasTable('table2'));
        $this->adapter->renameTable('table1', 'table2');
        $this->assertFalse($this->adapter->hasTable('table1'));
        $this->assertTrue($this->adapter->hasTable('table2'));
    }

    public function testAddColumn()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('email'));
        $table->addColumn('email', 'string')
              ->save();
        $this->assertTrue($table->hasColumn('email'));

        // In SQLite it is not possible to dictate order of added columns.
        // $table->addColumn('realname', 'string', array('after' => 'id'))
        //       ->save();
        // $this->assertEquals('realname', $rows[1]['Field']);
    }

    public function testAddColumnWithDefaultValue()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'string', ['default' => 'test'])
              ->save();
        $rows = $this->adapter->fetchAll(sprintf('pragma table_info(%s)', 'table1'));
        $this->assertEquals("'test'", $rows[1]['dflt_value']);
    }

    public function testAddColumnWithDefaultZero()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'integer', ['default' => 0])
              ->save();
        $rows = $this->adapter->fetchAll(sprintf('pragma table_info(%s)', 'table1'));
        $this->assertNotNull($rows[1]['dflt_value']);
        $this->assertEquals("0", $rows[1]['dflt_value']);
    }

    public function testAddColumnWithDefaultEmptyString()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_empty', 'string', ['default' => ''])
              ->save();
        $rows = $this->adapter->fetchAll(sprintf('pragma table_info(%s)', 'table1'));
        $this->assertEquals("''", $rows[1]['dflt_value']);
    }

    public function testRenameColumn()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $this->adapter->renameColumn('t', 'column1', 'column2');
        $this->assertFalse($this->adapter->hasColumn('t', 'column1'));
        $this->assertTrue($this->adapter->hasColumn('t', 'column2'));
    }

    public function testRenamingANonExistentColumn()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();

        try {
            $this->adapter->renameColumn('t', 'column2', 'column1');
            $this->fail('Expected the adapter to throw an exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertInstanceOf(
                'InvalidArgumentException',
                $e,
                'Expected exception of type InvalidArgumentException, got ' . get_class($e)
            );
            $this->assertEquals('The specified column doesn\'t exist: column2', $e->getMessage());
        }
    }

    public function testChangeColumn()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1->setType('string');
        $table->changeColumn('column1', $newColumn1);
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $newColumn2 = new \Phinx\Db\Table\Column();
        $newColumn2->setName('column2')
                   ->setType('string');
        $table->changeColumn('column1', $newColumn2);
        $this->assertFalse($this->adapter->hasColumn('t', 'column1'));
        $this->assertTrue($this->adapter->hasColumn('t', 'column2'));
    }

    public function testChangeColumnDefaultValue()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string', ['default' => 'test'])
              ->save();
        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1->setDefault('test1')
                   ->setType('string');
        $table->changeColumn('column1', $newColumn1);
        $rows = $this->adapter->fetchAll('pragma table_info(t)');

        $this->assertEquals("'test1'", $rows[1]['dflt_value']);
    }

    /**
     * @group bug922
     */
    public function testChangeColumnWithForeignKey()
    {
        $refTable = new \Phinx\Db\Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new \Phinx\Db\Table('another_table', [], $this->adapter);
        $table->addColumn('ref_table_id', 'integer')->save();

        $fk = new \Phinx\Db\Table\ForeignKey();
        $fk->setReferencedTable($refTable)
           ->setColumns(['ref_table_id'])
           ->setReferencedColumns(['id']);

        $this->adapter->addForeignKey($table, $fk);

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));

        $table->changeColumn('ref_table_id', 'float');

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testChangeColumnDefaultToZero()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'integer')
              ->save();
        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1->setDefault(0)
                   ->setType('integer');
        $table->changeColumn('column1', $newColumn1);
        $rows = $this->adapter->fetchAll('pragma table_info(t)');
        $this->assertEquals("0", $rows[1]['dflt_value']);
    }

    public function testChangeColumnDefaultToNull()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string', ['default' => 'test'])
              ->save();
        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1->setDefault(null)
                   ->setType('string');
        $table->changeColumn('column1', $newColumn1);
        $rows = $this->adapter->fetchAll('pragma table_info(t)');
        $this->assertNull($rows[1]['dflt_value']);
    }

    public function testChangeColumnWithCommasInCommentsOrDefaultValue()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string', ['default' => 'one, two or three', 'comment' => 'three, two or one'])
              ->save();
        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1->setDefault('another default')
                   ->setComment('another comment')
                   ->setType('string');
        $table->changeColumn('column1', $newColumn1);
        $rows = $this->adapter->fetchAll('pragma table_info(t)');
        $this->assertEquals("'another default'", $rows[1]['dflt_value']);
    }

    /**
     * @dataProvider columnCreationArgumentProvider
     */
    public function testDropColumn($columnCreationArgs)
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $columnName = $columnCreationArgs[0];
        call_user_func_array([$table, 'addColumn'], $columnCreationArgs);
        $table->save();
        $this->assertTrue($this->adapter->hasColumn('t', $columnName));

        $this->adapter->dropColumn('t', $columnName);

        $this->assertFalse($this->adapter->hasColumn('t', $columnName));
    }

    public function columnCreationArgumentProvider()
    {
        return [
            [ ['column1', 'string'] ],
            [ ['profile_colour', 'enum', ['values' => ['blue', 'red', 'white']]] ]
        ];
    }

    public function testGetColumns()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->addColumn('column2', 'integer')
              ->addColumn('column3', 'biginteger')
              ->addColumn('column4', 'text')
              ->addColumn('column5', 'float')
              ->addColumn('column6', 'decimal')
              ->addColumn('column7', 'datetime')
              ->addColumn('column8', 'time')
              ->addColumn('column9', 'timestamp')
              ->addColumn('column10', 'date')
              ->addColumn('column11', 'binary')
              ->addColumn('column12', 'boolean')
              ->addColumn('column13', 'string', ['limit' => 10])
              ->addColumn('column15', 'integer', ['limit' => 10])
              ->addColumn('column16', 'enum', ['values' => ['a', 'b', 'c']]);
        $pendingColumns = $table->getPendingColumns();
        $table->save();
        $columns = $this->adapter->getColumns('t');
        $this->assertCount(count($pendingColumns) + 1, $columns);
        for ($i = 0; $i++; $i < count($pendingColumns)) {
            $this->assertEquals($pendingColumns[$i], $columns[$i + 1]);
        }
    }

    public function testAddIndex()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->save();
        $this->assertFalse($table->hasIndex('email'));
        $table->addIndex('email')
              ->save();
        $this->assertTrue($table->hasIndex('email'));
    }

    public function testDropIndex()
    {
        // single column index
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addIndex('email')
              ->save();
        $this->assertTrue($table->hasIndex('email'));
        $this->adapter->dropIndex($table->getName(), 'email');
        $this->assertFalse($table->hasIndex('email'));

        // multiple column index
        $table2 = new \Phinx\Db\Table('table2', [], $this->adapter);
        $table2->addColumn('fname', 'string')
               ->addColumn('lname', 'string')
               ->addIndex(['fname', 'lname'])
               ->save();
        $this->assertTrue($table2->hasIndex(['fname', 'lname']));
        $this->adapter->dropIndex($table2->getName(), ['fname', 'lname']);
        $this->assertFalse($table2->hasIndex(['fname', 'lname']));

        // single column index with name specified
        $table3 = new \Phinx\Db\Table('table3', [], $this->adapter);
        $table3->addColumn('email', 'string')
               ->addIndex('email', ['name' => 'someindexname'])
               ->save();
        $this->assertTrue($table3->hasIndex('email'));
        $this->adapter->dropIndex($table3->getName(), 'email');
        $this->assertFalse($table3->hasIndex('email'));

        // multiple column index with name specified
        $table4 = new \Phinx\Db\Table('table4', [], $this->adapter);
        $table4->addColumn('fname', 'string')
               ->addColumn('lname', 'string')
               ->addIndex(['fname', 'lname'], ['name' => 'multiname'])
               ->save();
        $this->assertTrue($table4->hasIndex(['fname', 'lname']));
        $this->adapter->dropIndex($table4->getName(), ['fname', 'lname']);
        $this->assertFalse($table4->hasIndex(['fname', 'lname']));
    }

    public function testDropIndexByName()
    {
        // single column index
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addIndex('email', ['name' => 'myemailindex'])
              ->save();
        $this->assertTrue($table->hasIndex('email'));
        $this->adapter->dropIndexByName($table->getName(), 'myemailindex');
        $this->assertFalse($table->hasIndex('email'));

        // multiple column index
        $table2 = new \Phinx\Db\Table('table2', [], $this->adapter);
        $table2->addColumn('fname', 'string')
               ->addColumn('lname', 'string')
               ->addIndex(['fname', 'lname'], ['name' => 'twocolumnindex'])
               ->save();
        $this->assertTrue($table2->hasIndex(['fname', 'lname']));
        $this->adapter->dropIndexByName($table2->getName(), 'twocolumnindex');
        $this->assertFalse($table2->hasIndex(['fname', 'lname']));
    }

    public function testAddForeignKey()
    {
        $refTable = new \Phinx\Db\Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new \Phinx\Db\Table('table', [], $this->adapter);
        $table->addColumn('ref_table_id', 'integer')->save();

        $fk = new \Phinx\Db\Table\ForeignKey();
        $fk->setReferencedTable($refTable)
           ->setColumns(['ref_table_id'])
           ->setReferencedColumns(['id']);

        $this->adapter->addForeignKey($table, $fk);

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testAddForeignKeyWithPdoExceptionErrorMode()
    {
        $this->adapter->getConnection()
            ->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $refTable = new \Phinx\Db\Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new \Phinx\Db\Table('table', [], $this->adapter);
        $table->addColumn('ref_table_id', 'integer')->save();

        $fk = new \Phinx\Db\Table\ForeignKey();
        $fk->setReferencedTable($refTable)
            ->setColumns(['ref_table_id'])
            ->setReferencedColumns(['id']);

        $this->adapter->addForeignKey($table, $fk);

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testDropForeignKey()
    {
        $refTable = new \Phinx\Db\Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')
                 ->addIndex(['field1'], ['unique' => true])
                 ->save();

        $table = new \Phinx\Db\Table('another_table', [], $this->adapter);
        $table->addColumn('ref_table_id', 'integer')->addColumn('ref_table_field', 'string')->save();

        $fk = new \Phinx\Db\Table\ForeignKey();
        $fk->setReferencedTable($refTable)
           ->setColumns(['ref_table_id'])
           ->setReferencedColumns(['id']);

        $secondFk = new \Phinx\Db\Table\ForeignKey();
        $secondFk->setReferencedTable($refTable)
           ->setColumns(['ref_table_field'])
           ->setReferencedColumns(['field1'])
           ->setOptions([
               'update' => 'CASCADE',
               'delete' => 'CASCADE'
           ]);

        $this->adapter->addForeignKey($table, $fk);
        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));

        $this->adapter->dropForeignKey($table->getName(), ['ref_table_id']);
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));

        $this->adapter->addForeignKey($table, $secondFk);
        $this->adapter->addForeignKey($table, $fk);
        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_field']));

        $this->adapter->dropForeignKey($table->getName(), ['ref_table_field']);
        $this->assertTrue($this->adapter->hasTable($table->getName()));
    }

    public function testHasDatabase()
    {
        $this->assertFalse($this->adapter->hasDatabase('fake_database_name'));
        $this->assertTrue($this->adapter->hasDatabase(TESTS_PHINX_DB_ADAPTER_SQLITE_DATABASE));
    }

    public function testDropDatabase()
    {
        $this->assertFalse($this->adapter->hasDatabase('phinx_temp_database'));
        $this->adapter->createDatabase('phinx_temp_database');
        $this->assertTrue($this->adapter->hasDatabase('phinx_temp_database'));
        $this->adapter->dropDatabase('phinx_temp_database');
    }

    public function testAddColumnWithComment()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'string', ['comment' => $comment = 'Comments from "column1"'])
              ->save();

        $rows = $this->adapter->fetchAll('select * from sqlite_master where `type` = \'table\'');

        foreach ($rows as $row) {
            if ($row['tbl_name'] == 'table1') {
                $sql = $row['sql'];
            }
        }

        $this->assertRegExp('/\/\* Comments from "column1" \*\//', $sql);
    }

    public function testPhinxTypeNotValidType()
    {
        $this->setExpectedException('\RuntimeException', 'The type: "fake" is not supported.');
        $this->adapter->getPhinxType('fake');
    }

    public function testPhinxTypeNotValidTypeRegex()
    {
        $this->setExpectedException('\RuntimeException', 'Column type ?int? is not supported');
        $this->adapter->getPhinxType('?int?');
    }

    public function testAddIndexTwoTablesSameIndex()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->save();
        $table2 = new \Phinx\Db\Table('table2', [], $this->adapter);
        $table2->addColumn('email', 'string')
               ->save();

        $this->assertFalse($table->hasIndex('email'));
        $this->assertFalse($table2->hasIndex('email'));

        $table->addIndex('email')
              ->save();
        $table2->addIndex('email')
               ->save();

        $this->assertTrue($table->hasIndex('email'));
        $this->assertTrue($table2->hasIndex('email'));
    }

    public function testBulkInsertData()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->addColumn('column2', 'integer')
              ->insert([
                  [
                      'column1' => 'value1',
                      'column2' => 1,
                  ],
                  [
                      'column1' => 'value2',
                      'column2' => 2,
                  ]
              ])
              ->insert(
                  [
                      'column1' => 'value3',
                      'column2' => 3,
                  ]
              )
              ->insert(
                  [
                      'column1' => '\'value4\'',
                      'column2' => null,
                  ]
              );
        $this->adapter->createTable($table);
        $this->adapter->bulkinsert($table, $table->getData());
        $table->reset();

        $rows = $this->adapter->fetchAll('SELECT * FROM table1');

        $this->assertEquals('value1', $rows[0]['column1']);
        $this->assertEquals('value2', $rows[1]['column1']);
        $this->assertEquals('value3', $rows[2]['column1']);
        $this->assertEquals('\'value4\'', $rows[3]['column1']);
        $this->assertEquals(1, $rows[0]['column2']);
        $this->assertEquals(2, $rows[1]['column2']);
        $this->assertEquals(3, $rows[2]['column2']);
        $this->assertEquals(null, $rows[3]['column2']);
    }

    public function testInsertData()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->addColumn('column2', 'integer')
              ->insert([
                  [
                      'column1' => 'value1',
                      'column2' => 1,
                  ],
                  [
                      'column1' => 'value2',
                      'column2' => 2,
                  ]
              ])
              ->insert(
                  [
                      'column1' => 'value3',
                      'column2' => 3,
                  ]
              )
              ->insert(
                  [
                      'column1' => '\'value4\'',
                      'column2' => null,
                  ]
              )
              ->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM table1');

        $this->assertEquals('value1', $rows[0]['column1']);
        $this->assertEquals('value2', $rows[1]['column1']);
        $this->assertEquals('value3', $rows[2]['column1']);
        $this->assertEquals('\'value4\'', $rows[3]['column1']);
        $this->assertEquals(1, $rows[0]['column2']);
        $this->assertEquals(2, $rows[1]['column2']);
        $this->assertEquals(3, $rows[2]['column2']);
        $this->assertEquals(null, $rows[3]['column2']);
    }

    public function testBulkInsertDataEnum()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'enum', ['values' => ['a', 'b', 'c']])
              ->addColumn('column2', 'enum', ['values' => ['a', 'b', 'c'], 'null' => true])
              ->addColumn('column3', 'enum', ['values' => ['a', 'b', 'c'], 'default' => 'c'])
              ->insert([
                  'column1' => 'a',
              ]);
        $this->adapter->createTable($table);
        $this->adapter->bulkinsert($table, $table->getData());
        $table->reset();

        $rows = $this->adapter->fetchAll('SELECT * FROM table1');

        $this->assertEquals('a', $rows[0]['column1']);
        $this->assertEquals(null, $rows[0]['column2']);
        $this->assertEquals('c', $rows[0]['column3']);
    }

    public function testInsertDataEnum()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'enum', ['values' => ['a', 'b', 'c']])
              ->addColumn('column2', 'enum', ['values' => ['a', 'b', 'c'], 'null' => true])
              ->addColumn('column3', 'enum', ['values' => ['a', 'b', 'c'], 'default' => 'c'])
              ->insert([
                  'column1' => 'a',
              ])
              ->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM table1');

        $this->assertEquals('a', $rows[0]['column1']);
        $this->assertEquals(null, $rows[0]['column2']);
        $this->assertEquals('c', $rows[0]['column3']);
    }

    public function testNullWithoutDefaultValue()
    {
        $this->markTestSkipped('Skipping for now. See Github Issue #265.');

        // construct table with default/null combinations
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn("aa", "string", ["null" => true]) // no default value
              ->addColumn("bb", "string", ["null" => false]) // no default value
              ->addColumn("cc", "string", ["null" => true, "default" => "some1"])
              ->addColumn("dd", "string", ["null" => false, "default" => "some2"])
              ->save();

        // load table info
        $columns = $this->adapter->getColumns("table1");

        $this->assertEquals(count($columns), 5);

        $aa = $columns[1];
        $bb = $columns[2];
        $cc = $columns[3];
        $dd = $columns[4];

        $this->assertEquals("aa", $aa->getName());
        $this->assertEquals(true, $aa->isNull());
        $this->assertEquals(null, $aa->getDefault());

        $this->assertEquals("bb", $bb->getName());
        $this->assertEquals(false, $bb->isNull());
        $this->assertEquals(null, $bb->getDefault());

        $this->assertEquals("cc", $cc->getName());
        $this->assertEquals(true, $cc->isNull());
        $this->assertEquals("some1", $cc->getDefault());

        $this->assertEquals("dd", $dd->getName());
        $this->assertEquals(false, $dd->isNull());
        $this->assertEquals("some2", $dd->getDefault());
    }

    public function testTruncateTable()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->addColumn('column2', 'integer')
              ->insert([
                  [
                      'column1' => 'value1',
                      'column2' => 1,
                  ],
                  [
                      'column1' => 'value2',
                      'column2' => 2,
                  ]
              ])
              ->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM table1');
        $this->assertEquals(2, count($rows));
        $table->truncate();
        $rows = $this->adapter->fetchAll('SELECT * FROM table1');
        $this->assertEquals(0, count($rows));
    }

    public function testDumpCreateTable()
    {
        $inputDefinition = new InputDefinition([new InputOption('dry-run')]);
        $this->adapter->setInput(new ArrayInput(['--dry-run' => true], $inputDefinition));

        $consoleOutput = new BufferedOutput();
        $this->adapter->setOutput($consoleOutput);

        $table = new \Phinx\Db\Table('table1', [], $this->adapter);

        $table->addColumn('column1', 'string')
            ->addColumn('column2', 'integer')
            ->addColumn('column3', 'string', ['default' => 'test'])
            ->save();

        $expectedOutput = <<<'OUTPUT'
CREATE TABLE `table1` (`id` INTEGER NULL PRIMARY KEY AUTOINCREMENT, `column1` VARCHAR(255) NULL, `column2` INTEGER NULL, `column3` VARCHAR(255) NOT NULL DEFAULT 'test');
OUTPUT;
        $actualOutput = $consoleOutput->fetch();
        $this->assertContains($expectedOutput, $actualOutput, 'Passing the --dry-run option does not dump create table query to the output');
    }
}
