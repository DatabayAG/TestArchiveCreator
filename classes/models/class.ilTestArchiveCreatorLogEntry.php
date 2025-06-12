<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Data model for test log entry
 */
class ilTestArchiveCreatorLogEntry extends ilTestArchiveCreatorElement
{
    public string $date_time;
    public string $test;
    public string $author;
    public string $tst_participant;
    public string $client_ip;
    public string $question;
    public string $log_entry_type;
    public string $interaction_type;
    public string $additional_info;


    /**
     * Get a name of the folder where generated files are stored
     */
    public function getFolderName(): string
    {
        return "";
    }


    /**
     * Get a unique prefix that can be used for file and directory names
     */
    public function getFilePrefix(): string
    {
        return "";
    }

    /**
     * Get a unique index for sorting the list of elements
     */
    public function getSortIndex(): string
    {
        return $this->date_time . ' ' . md5(serialize($this->getRowData()));
    }

    /**
     * Get the list of columns for this element type
     * The file list should have the key 'files'
     * @return string[]    key => title
     */
    public function getColumns(): array
    {
        return  [
            'date_time' => $this->lng->txt('date_time'),
            'test' => $this->lng->txt('test'),
            'author' => $this->lng->txt('author'),
            'tst_participant' => $this->lng->txt('tst_participant'),
            'client_ip' => $this->lng->txt('client_ip'),
            'question' => $this->lng->txt('question'),
            'log_entry_type' => $this->lng->txt('log_entry_type'),
            'interaction_type' => $this->lng->txt('interaction_type'),
            'additional_info' => $this->lng->txt('additional_info')
        ];
    }

    /**
     * Get the labels of contents where the data is a link
     * @return string[] key => label
     */
    public function getLinkedLabels(): array
    {
        return [];
    }

    /**
     * Get the data row for this element
     * @param string $format ('csv' or 'html')
     * @return string[] key => content
     */
    public function getRowData(string $format = 'csv'): array
    {
        return  [
            'date_time' => $this->date_time,
            'test' => $this->test,
            'author' => $this->author,
            'tst_participant' => $this->tst_participant,
            'client_ip' => $this->client_ip,
            'question' => $this->question,
            'log_entry_type' => $this->log_entry_type,
            'interaction_type' => $this->interaction_type,
            'additional_info' => $this->additional_info
        ];
    }
}
