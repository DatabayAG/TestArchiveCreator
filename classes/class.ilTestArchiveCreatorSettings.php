<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Object settings for the test archive creator plugin
 */
class ilTestArchiveCreatorSettings
{
    protected ilDBInterface $db;
    protected ilTestArchiveCreatorPlugin $plugin;
    protected int $obj_id;

    public string $status = ilTestArchiveCreatorPlugin::STATUS_INACTIVE;
    public ?ilDateTime $schedule = null;
    public string $pass_selection;
    public string $random_questions;
    public bool $include_questions;
    public bool $include_answers;
    public bool $questions_with_best_solution;
    public bool $answers_with_best_solution;
    public float $zoom_factor;
    public string $orientation;
    public string $file_prefix;
    /** @var int[] */
    public array $notification_ids;

    private $failed_logins = [];
    private $failed_prefix = false;

    /**
     * ilTestArchiveCreatorSettings constructor.
     */
    public function __construct(ilTestArchiveCreatorPlugin $plugin, int $obj_id)
    {
        global $DIC;

        $this->plugin = $plugin;
        $this->db = $DIC->database();
        $this->obj_id = $obj_id;
        $this->read();
    }

    /**
     * Read the archive settings
     */
    protected function read()
    {
        // read the saved settings
        $query = "SELECT * FROM tarc_ui_settings WHERE obj_id = " . $this->db->quote($this->obj_id, 'integer');
        $result = $this->db->query($query);
        if ($row = $this->db->fetchAssoc($result)) {
            $this->status = (string) $row['status'];
            if (!empty($row['schedule'])) {
                $this->schedule = new ilDateTime($row['schedule'], IL_CAL_DATETIME);
            }

            $this->include_questions = (bool) $row['include_questions'];
            $this->include_answers = (bool) $row['include_answers'];
            $this->questions_with_best_solution = (bool) $row['questions_with_best_solution'];
            $this->answers_with_best_solution = (bool) $row['answers_with_best_solution'];
            $this->pass_selection = (string) $row['pass_selection'];
            $this->random_questions = (string) $row['random_questions'];
            $this->zoom_factor = (float) $row['zoom_factor'];
            $this->orientation = (string) $row['orientation'];
            $this->file_prefix = (string) $row['file_prefix'];
            $this->notification_ids = array_map(
                'intval',
                explode(',', (string) $row['notification_ids'])
            );
        } else {
            // initialize values with those if the global configuration
            $config = $this->plugin->getConfig();
            $this->include_questions = (bool) $config->include_questions;
            $this->include_answers = (bool) $config->include_answers;
            $this->questions_with_best_solution = (bool) $config->questions_with_best_solution;
            $this->answers_with_best_solution = (bool) $config->answers_with_best_solution;
            $this->pass_selection = (string) $config->pass_selection;
            $this->random_questions = (string) $config->random_questions;
            $this->zoom_factor = (float) $config->zoom_factor;
            $this->orientation = (string) $config->orientation;
            $this->file_prefix = '';
            $this->notification_ids = [];
        }
    }

    /**
     * Save the archive settings
     * @return  boolean     success
     */
    public function save(): bool
    {
        $rows = $this->db->replace(
            'tarc_ui_settings',
            array(
                'obj_id' => array('integer', $this->obj_id)
            ),
            array(
                'status' => array('text', $this->status),
                'schedule' => array('timestamp', isset($this->schedule) ? $this->schedule->get(IL_CAL_DATETIME) : null),
                'include_questions' => array('integer', $this->include_questions),
                'include_answers' => array('integer', $this->include_answers),
                'questions_with_best_solution' => array('integer', $this->questions_with_best_solution),
                'answers_with_best_solution' => array('integer', $this->answers_with_best_solution),
                'pass_selection' => array('text', $this->pass_selection),
                'random_questions' => array('text', $this->random_questions),
                'zoom_factor' => array('float', $this->zoom_factor),
                'orientation' => array('string', $this->orientation),
                'file_prefix' => array('text', $this->file_prefix),
                'notification_ids' => array('text', implode(',', $this->notification_ids)),
            )
        );
        return $rows > 0;
    }

    /**
     * Get the object ids of tests with scheduled archive creation that are due
     * @return int[]
     */
    public static function getScheduledObjects(): array
    {
        global $DIC;
        $db = $DIC->database();

        $time = new ilDateTime(time(), IL_CAL_UNIX);

        $query = "SELECT obj_id FROM tarc_ui_settings WHERE status = %s AND schedule <= %s";
        $result = $db->queryF(
            $query,
            array('text', 'text'),
            array(ilTestArchiveCreatorPlugin::STATUS_PLANNED, $time->get(IL_CAL_DATETIME))
        );

        $obj_ids = array();
        while ($row = $db->fetchAssoc($result)) {
            $obj_ids[] = $row['obj_id'];
        }
        return $obj_ids;
    }

    /**
     * Delete the archive settings of a test
     */
    public static function deleteForObject(int $obj_id): void
    {
        global $DIC;
        $db = $DIC->database();

        $query = 'DELETE FROM tarc_ui_settings WHERE obj_id = ' . $db->quote($obj_id, 'integer');

        $db->manipulate($query);
    }

    public function getNotificationLogins(): array
    {
        $logins = [];
        foreach ($this->notification_ids as $id) {
            if ($login = ilObjUser::_lookupLogin($id)) {
                $logins[] = $login;
            }
        }
        return $logins;
    }

    public function setNotificationLogins(array $logins): bool
    {
        $ids = [];
        $this->failed_logins = [];
        foreach ($logins as $login) {
            if ($id = ilObjUser::_lookupId($login)) {
                $ids[] = $id;
            } else {
                $this->failed_logins[] = $login;
            }
        }
        if (empty($this->failed_logins)) {
            $this->notification_ids = $ids;
            return true;
        }
        return false;
    }

    public function getNotificationLoginsError(): string
    {
        if (!empty($this->failed_logins)) {
            return sprintf($this->plugin->txt('wrong_notification_logins'), implode(', ', $this->failed_logins));
        }
        return '';
    }

    public function setFilePrefix(string $prefix): bool
    {
        $this->failed_prefix = false;
        // non-printing and special characters
        // '/[\000-\031\/<>:"\\\\|?* ]/'

        // restrict to latin characters and numbers
        if ($prefix === '' || preg_match('/[A-Za-z0-9.\-]/', $prefix)) {
            $this->file_prefix = $prefix;
            return true;
        }
        $this->failed_prefix = true;
        return false;
    }

    public function getFilePrefixError(): string
    {
        if ($this->failed_prefix) {
            return $this->plugin->txt('wrong_file_prefix');
        }
        return '';
    }
}
