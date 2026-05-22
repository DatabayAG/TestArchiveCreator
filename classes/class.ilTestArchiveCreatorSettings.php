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
    public ?DateTimeImmutable $schedule = null;
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
    private DateTimeZone $time_zone;

    private $failed_logins = [];

    /**
     * ilTestArchiveCreatorSettings constructor.
     */
    public function __construct(ilTestArchiveCreatorPlugin $plugin, int $obj_id)
    {
        global $DIC;

        $this->plugin = $plugin;
        $this->db = $DIC->database();
        $this->obj_id = $obj_id;
        $this->time_zone = new DateTimeZone(date_default_timezone_get());
        $this->read();
    }

    /**
     * Read the archive settings
     */
    protected function read()
    {
        $config = $this->plugin->getConfig();

        // read the saved settings
        $query = "SELECT * FROM tarc_ui_settings WHERE obj_id = " . $this->db->quote($this->obj_id, 'integer');
        $result = $this->db->query($query);
        if ($row = $this->db->fetchAssoc($result)) {
            $this->status = $this->matchStatus($row['status']);
            if (!empty($row['schedule'])) {
                $this->schedule = empty($row['schedule']) ? null : new DateTimeImmutable($row['schedule'], $this->time_zone);
            }

            $this->include_questions = (bool) $row['include_questions'];
            $this->include_answers = (bool) $row['include_answers'];
            $this->questions_with_best_solution = (bool) $row['questions_with_best_solution'];
            $this->answers_with_best_solution = (bool) $row['answers_with_best_solution'];
            $this->pass_selection = $config->matchPassSelection($row['pass_selection']);
            $this->random_questions = $config->matchRandomQuestions($row['random_questions']);
            $this->zoom_factor = (float) $row['zoom_factor'];
            $this->orientation = $config->matchOrientation($row['orientation']);
            $this->file_prefix = (string) $row['file_prefix'];
            $this->notification_ids = array_map(
                'intval',
                explode(',', (string) $row['notification_ids'])
            );
        } else {
            // initialize values with those if the global configuration
            $this->include_questions = $config->include_questions;
            $this->include_answers = $config->include_answers;
            $this->questions_with_best_solution = $config->questions_with_best_solution;
            $this->answers_with_best_solution = $config->answers_with_best_solution;
            $this->pass_selection = $config->pass_selection;
            $this->random_questions = $config->random_questions;
            $this->zoom_factor = $config->zoom_factor;
            $this->orientation = $config->orientation;
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
        $config = $this->plugin->getConfig();

        $rows = $this->db->replace(
            'tarc_ui_settings',
            [
                'obj_id' => ['integer', $this->obj_id]
            ],
            [
                'status' => ['text', $this->matchStatus($this->status)],
                'schedule' => ['timestamp', $this->schedule?->setTimezone($this->time_zone)?->format('Y-m-d H:i:s')],
                'include_questions' => ['integer', $this->include_questions],
                'include_answers' => ['integer', $this->include_answers],
                'questions_with_best_solution' => ['integer', $this->questions_with_best_solution],
                'answers_with_best_solution' => ['integer', $this->answers_with_best_solution],
                'pass_selection' => ['text', $config->matchPassSelection($this->pass_selection)],
                'random_questions' => ['text', $config->matchRandomQuestions($this->random_questions)],
                'zoom_factor' => ['float', $this->zoom_factor],
                'orientation' => ['string', $config->matchOrientation($this->orientation)],
                'file_prefix' => ['text', $this->file_prefix],
                'notification_ids' => ['text', implode(',', $this->notification_ids)],
            ]
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

    public function getNotificationLogins(): string
    {
        $logins = [];
        foreach ($this->notification_ids as $id) {
            if ($login = ilObjUser::_lookupLogin($id)) {
                $logins[] = $login;
            }
        }
        return implode(',', $logins);
    }

    public function setNotificationLogins(string $input): void
    {
        $logins = array_map('trim', explode(',', $input));
        $this->notification_ids = [];
        foreach ($logins as $login) {
            if (!empty($login)) {
                if ($id = ilObjUser::_lookupId($login)) {
                    $this->notification_ids[] = $id;
                }
            }
        }
    }

    public function checkNotificationLogins(string $input): bool
    {
        $logins = array_map('trim', explode(',', $input));
        $this->failed_logins = [];
        foreach ($logins as $login) {
            if (!empty($login)) {
                if (!ilObjUser::_lookupId($login)) {
                    $this->failed_logins[] = $login;
                }
            }
        }
        return empty($this->failed_logins);
    }

    public function getNotificationLoginsError(): string
    {
        return sprintf($this->plugin->txt('wrong_notification_logins'), implode(', ', $this->failed_logins));
    }

    public function checkFilePrefix(string $prefix): bool
    {
        // non-printing and special characters
        // '/[\000-\031\/<>:"\\\\|?* ]/'

        // restrict to latin characters and numbers
        if (preg_match('/^[A-Za-z0-9.\-]*$/', $prefix)) {
            return true;
        }
        return false;
    }

    public function matchStatus(?string $status): string
    {
        return match($status) {
            ilTestArchiveCreatorPlugin::STATUS_PLANNED,
            ilTestArchiveCreatorPlugin::STATUS_RUNNING,
            ilTestArchiveCreatorPlugin::STATUS_FINISHED => $status,
            default => ilTestArchiveCreatorPlugin::STATUS_INACTIVE
        };
    }

    /**
     * This is used to set the value in the settings GUI
     */
    public function selectedStatus(): string
    {
        return match($this->status) {
            ilTestArchiveCreatorPlugin::STATUS_PLANNED => $this->status,
            default => ilTestArchiveCreatorPlugin::STATUS_INACTIVE
        };
    }
}
