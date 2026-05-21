<?php

declare(strict_types=1);

namespace ILIAS\Plugin\TestArchiveCreator;

use ilDBInterface;
use ilDBStepExecutionDB;
use ilDBStepReader;
use ilDBConstants;
use ilDatabaseUpdateSteps;
use DateTime;

class DBUpdateSteps11 implements ilDatabaseUpdateSteps
{
    private const string PREFIX = 'step_';
    private const string SETTINGS = 'tarc_ui_settings';

    private ilDBInterface $db;

    public function prepare(ilDBInterface $db): void
    {
        $this->db = $db;
    }

    /**
     * Install the plugin
     * This can be called from the Plugin Administration GUI
     * @see \ilDatabaseUpdateStepsExecutedObjective::achieve
     */
    public function install(ilDBInterface $db): void
    {
        $this->prepare($db);

        $execution_log = new ilDBStepExecutionDB($this->db, fn() => new DateTime());
        $step_reader = new ilDBStepReader();

        $last_started_step = $execution_log->getLastStartedStep(self::class);
        $last_finished_step = $execution_log->getLastFinishedStep(self::class);

        foreach ($step_reader->readStepNumbers(self::class, self::PREFIX) as $step) {
            if ($step <= $last_finished_step) {
                continue;
            }
            $execution_log->started(self::class, $step);
            $method = self::PREFIX . $step;
            $this->$method();
            $execution_log->finished(self::class, $step);
        }
    }

    /**
     * Uninstall the plugin
     * This can be called from the Plugin Administration GUI
     */
    public function uninstall(ilDBInterface $db): void
    {
        $this->prepare($db);
        $this->db->manipulate("DELETE FROM il_db_steps WHERE `class` = " . $this->db->quote(self::class));

        $tables = [
            self::SETTINGS,
         ];

        foreach ($tables as $table) {
            $this->db->dropTable($table, false);
        }
    }


    public function step_1(): void
    {
        // dummy step to align step numbers with former update
    }

    public function step_2(): void
    {
        $fields = [
            'obj_id' => [
                'type' => ilDBConstants::T_INTEGER,
                'length' => 4,
                'notnull' => true
            ],
            'status' => [
                'type' => ilDBConstants::T_TEXT,
                'length' => 10,
                'notnull' => true,
                'default' => 'inactive'
            ],
            'schedule' => [
                'type' => ilDBConstants::T_TIMESTAMP,
                'notnull' => false
            ],
            'pass_selection' => [
                'type' => ilDBConstants::T_TEXT,
                'length' => 10,
                'notnull' => true,
                'default' => 'scored'
            ]
        ];

        $this->db->createTable(self::SETTINGS, $fields);
        $this->db->addPrimaryKey(self::SETTINGS, ['obj_id']);
    }

    public function step_3(): void
    {
        if (!$this->db->tableColumnExists(self::SETTINGS, 'zoom_factor')) {
            $this->db->addTableColumn(self::SETTINGS, 'zoom_factor', [
                'type' => ilDBConstants::T_FLOAT,
                'notnull' => true,
                'default' => 1
            ]);
        }
    }

    public function step_4(): void
    {
        if (!$this->db->tableColumnExists(self::SETTINGS, 'orientation')) {
            $this->db->addTableColumn(self::SETTINGS, 'orientation', [
                'type' => ilDBConstants::T_TEXT,
                'length' => 10,
                'notnull' => true,
                'default' => 'landscape'
            ]);
        }
    }

    public function step_5(): void
    {
        if (!$this->db->tableColumnExists(self::SETTINGS, 'random_questions')) {
            $this->db->addTableColumn(self::SETTINGS, 'random_questions', [
                'type' => ilDBConstants::T_TEXT,
                'length' => 10,
                'notnull' => true,
                'default' => 'used'
            ]);
        }
    }

    public function step_6(): void
    {
        if (!$this->db->tableColumnExists(self::SETTINGS, 'include_questions')) {
            $this->db->addTableColumn(self::SETTINGS, 'include_questions', [
                'type' => ilDBConstants::T_INTEGER,
                'notnull' => true,
                'default' => 1
            ]);
        }
    }

    public function step_7(): void
    {
        if (!$this->db->tableColumnExists(self::SETTINGS, 'questions_with_best_solution')) {
            $this->db->addTableColumn(self::SETTINGS, 'questions_with_best_solution', [
                'type' => ilDBConstants::T_INTEGER,
                'notnull' => true,
                'default' => 1
            ]);
        }
    }

    public function step_8(): void
    {
        if (!$this->db->tableColumnExists(self::SETTINGS, 'include_answers')) {
            $this->db->addTableColumn(self::SETTINGS, 'include_answers', [
                'type' => ilDBConstants::T_INTEGER,
                'notnull' => true,
                'default' => 1
            ]);
        }
    }

    public function step_9(): void
    {
        if (!$this->db->tableColumnExists(self::SETTINGS, 'answers_with_best_solution')) {
            $this->db->addTableColumn(self::SETTINGS, 'answers_with_best_solution', [
                'type' => ilDBConstants::T_INTEGER,
                'notnull' => true,
                'default' => 1
            ]);
        }
    }

    public function step_10(): void
    {
        if (!$this->db->tableColumnExists(self::SETTINGS, 'min_rendering_wait')) {
            $this->db->addTableColumn(self::SETTINGS, 'min_rendering_wait', [
                'type' => ilDBConstants::T_INTEGER,
                'notnull' => true,
                'default' => 200
            ]);
        }
    }

    public function step_11(): void
    {
        if (!$this->db->tableColumnExists(self::SETTINGS, 'max_rendering_wait')) {
            $this->db->addTableColumn(self::SETTINGS, 'max_rendering_wait', [
                'type' => ilDBConstants::T_INTEGER,
                'notnull' => true,
                'default' => 2000
            ]);
        }
    }

    public function step_12(): void
    {
        if ($this->db->tableColumnExists(self::SETTINGS, 'min_rendering_wait')) {
            $this->db->dropTableColumn(self::SETTINGS, 'min_rendering_wait');
        }
    }

    public function step_13(): void
    {
        if ($this->db->tableColumnExists(self::SETTINGS, 'max_rendering_wait')) {
            $this->db->dropTableColumn(self::SETTINGS, 'max_rendering_wait');
        }
    }

    public function step_14(): void
    {
        if (!$this->db->tableColumnExists(self::SETTINGS, 'file_prefix')) {
            $this->db->addTableColumn(self::SETTINGS, 'file_prefix', [
                'type' => ilDBConstants::T_TEXT,
                'length' => 8,
                'notnull' => false
            ]);
        }
    }

    public function step_15(): void
    {
        if (!$this->db->tableColumnExists(self::SETTINGS, 'notification_ids')) {
            $this->db->addTableColumn(self::SETTINGS, 'notification_ids', [
                'type' => ilDBConstants::T_TEXT,
                'length' => 250,
                'notnull' => false
            ]);
        }
    }
}
