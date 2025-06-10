<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Data model for question list
 */
class ilTestArchiveCreatorParticipant extends ilTestArchiveCreatorElement
{
    public int $active_id = 0;
    public string $firstname = '';
    public string $lastname = '';
    public string $fullname = '';
    public string $login = '';
    public string $matriculation = '';
    public string $email = '';
    public string $exam_id = '';
    public string $ip_range_from = '';
    public string $ip_range_to = '';

    public int $pass_number = 0;
    public bool $pass_scored = false;
    public int $pass_working_time = 0;
    public int $pass_finish_date = 0;
    public float $pass_reached_points = 0;

    public ?string $answers_file = null;
    public string $answers_hash = '';

    /**
     * Get a unique prefix that can be used for file and directory names
     */
    public function getFolderName(): string
    {
        return $this->creator->filesystems->sanitizeFilename($this->lastname . '_' . $this->firstname . '_' . $this->active_id);
    }

    /**
     * Get a unique prefix that can be used for file and directory names
     */
    public function getFilePrefix(): string
    {
        return $this->creator->filesystems->sanitizeFilename($this->exam_id);
    }

    /**
     * Get a unique index for sorting the list of elements
     */
    public function getSortIndex(): string
    {
        return $this->lastname . '_' . $this->firstname . '_' . $this->exam_id;
    }

    /**
     * Get the list of columns for this element type
     * The file list should have the key 'files'
     * @return string[]    key => title
     */
    public function getColumns(): array
    {
        $columns = array(
            'firstname' => $this->lng->txt('firstname'),
            'lastname' => $this->lng->txt('lastname'),
            'login' => $this->lng->txt('login'),
            'matriculation' => $this->lng->txt('matriculation'),
            'exam_id' => $this->plugin->txt('exam_id'),
            'ip_range_from' => $this->plugin->txt('ip_range_from'),
            'ip_range_to' => $this->plugin->txt('ip_range_to'),
            'pass_number' => $this->plugin->txt('pass_number'),
            'pass_scored' => $this->plugin->txt('is_scored'),
            'pass_working_time' => $this->plugin->txt('working_time'),
            'pass_finish_date' => $this->plugin->txt('finish_date'),
            'pass_reached_points' => $this->plugin->txt('points'),
            'answers_file' => $this->plugin->txt('answers'),
            'answers_hash' => $this->plugin->txt('answers_hash')
        );

        if (!$this->creator->config->with_login) {
            unset($columns['login']);
        }
        if (!$this->creator->config->with_matriculation) {
            unset($columns['matriculation']);
        }
        if (!$this->creator->config->include_ip_ranges) {
            unset($columns['ip_range_from']);
            unset($columns['ip_range_to']);
        }
        return $columns;
    }

    /**
     * Get the labels of contents where the data is a link
     * @return string[] key => label
     */
    public function getLinkedLabels(): array
    {
        return array(
            'answers_file' => $this->has_pdf ? 'PDF' : 'HTML'
        );
    }


    /**
     * Get the data row for this element
     * @param string $format ('csv' or 'html')
     * @return string[] key => content
     */
    public function getRowData(string $format = 'csv'): array
    {
        $pass_finish_date = new ilDateTime($this->pass_finish_date, IL_CAL_UNIX);
        if ($this->active_id) {

            return [
                'firstname' => $this->firstname,
                'lastname' => $this->lastname,
                'login' => $this->login,
                'matriculation' => $this->matriculation,
                'exam_id' => $this->exam_id,
                'ip_range_from' => $this->ip_range_from,
                'ip_range_to' => $this->ip_range_to,
                'pass_number' => $this->pass_number,
                'pass_scored' => $format == 'csv' ? $this->pass_scored : ($this->pass_scored ? $this->lng->txt('yes') : $this->lng->txt('no')),
                'pass_finish_date' => $format == 'csv' ? $pass_finish_date->get(IL_CAL_DATETIME) : ilDatePresentation::formatDate($pass_finish_date),
                'pass_working_time' => $format == 'csv' ? $this->pass_working_time : ilDatePresentation::secondsToString($this->pass_working_time),
                'pass_reached_points' => $this->pass_reached_points,
                'answers_file' => $this->answers_file ? ($this->answers_file . ($this->has_pdf ? '.pdf' : '.html')) : '',
                'answers_hash' => $this->answers_hash,
            ];
        } else {

            return [
                'firstname' => $this->firstname,
                'lastname' => $this->lastname,
                'login' => $this->login,
                'matriculation' => $this->matriculation,
                'exam_id' => $this->exam_id,
                'ip_range_from' => $this->ip_range_from,
                'ip_range_to' => $this->ip_range_to,
                'pass_number' => '',
                'pass_scored' => '',
                'pass_finish_date' => '',
                'pass_working_time' => '',
                'pass_reached_points' => '',
                'answers_file' => '',
                'answers_hash' => '',
            ];
        }

    }
}
