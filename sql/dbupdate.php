<#1>
<?php
    /**
     * Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
     * GPLv3, see docs/LICENSE
     */

    /**
     * Test Archive Creator Plugin: database update script
     *
     * @author Fred Neumann <fred.neumann@fau.de>
     */
?>
<#2>
<?php
    $fields = array(
        'obj_id' => array(
            'type' => 'integer',
            'length' => 4,
            'notnull' => true
        ),
        'status' => array(
            'type' => 'text',
            'length' => 10,
            'notnull' => true,
            'default' => 'inactive'
        ),
        'schedule' => array(
            'type' => 'timestamp',
            'notnull' => false
        ),
        'pass_selection' => array(
            'type' => 'text',
            'length' => 10,
            'notnull' => true,
            'default' => 'scored'
        )
    );

    $ilDB->createTable('tarc_ui_settings', $fields);
    $ilDB->addPrimaryKey('tarc_ui_settings', array('obj_id'));
?>
