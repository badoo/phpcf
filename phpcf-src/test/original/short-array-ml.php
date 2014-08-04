<?php
/**
 * Test to explode long short-syntax array into multiple lines
 */
$arr = ['something', 'other thing1', 'other thing2', 'other thing3', 'other thing4', 'other thing5', 'other thing6', 'other thing7', 'other thing8', 'other thing6', 'other thing7', 'other thing8', 'other thing6', 'other thing7', 'other thing8', 'other thing6', 'other thing7', 'other thing8', 'other thing6', 'other thing7', 'other thing8', 'other thing6', 'other thing7', 'other thing8', 'other thing6', 'other thing7', 'other thing8'];

function test()
{
    $insert_args = [ 
        'queue_table' => self::QUEUE_TABLE,
        'spot_id' => intval( $dest_data [self::DEST_DATA_FIELD_SPOT_ID] ),
        'table' => 
            \SQL::qstr_real($dest_data
            [self::DEST_DATA_FIELD_TABLE], $dbh),
        'obj_hash' => \SQL::
                qstr_real( $object_data_hash, $dbh ),
        'obj_data' => \SQL::qstr_real($object_data, $dbh),
        'created' => \SQL::qstr_real( \SQL::now(   ), $dbh),
        'test' => func()?'a':'b'
    ];
}

$a = [[
    'is_cli' => false,
    'query_config' => ['result' => false,
        'called' => 0],
    'errno_config' => ['result' => 0, 'called' => 0],
    'data' => [
        'obj_data' => $obj_data,
        'obj_hash' => sha1($obj_data),
        'table' => '`Spot#db_index#`.`UserContacts#table_index#`',
        'spot_id' => 1,
        'test' => func()?'a':'b' 
    ],
    'spot_data' => [],
],
];
