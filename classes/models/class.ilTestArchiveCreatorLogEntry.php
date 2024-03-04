<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Data model for test log entry
 */
class ilTestArchiveCreatorLogEntry extends ilTestArchiveCreatorElement
{
    public int $log_id = 0;
	public int $timestamp = 0;
    public int $user_id = 0;
    public ?int $question_id = null;
    public string $login = '';
    public string $question = '';
    public string $logtext = '';


	/**
	 * Get a name of the folder where generated files are stored
	 */
	public function getFolderName() : string
	{
		return "";
	}


	/**
	 * Get a unique prefix that can be used for file and directory names
	 */
	public function getFilePrefix() : string
	{
		return "";
	}

	/**
	 * Get a unique index for sorting the list of elements
	 */
	function getSortIndex() : string
	{
		return $this->log_id;
	}

	/**
	 * Get the list of columns for this element type
	 * The file list should have the key 'files'
	 * @return string[]    key => title
	 */
	function getColumns() : array
	{
		return array(
            'timestamp' => $this->plugin->txt('log_timestamp'),
            'user_id' => $this->plugin->txt('log_user_id'),
            'login' => $this->plugin->txt('log_login'),
            'question_id' => $this->plugin->txt('log_question_id'),
            'question' => $this->plugin->txt('log_question'),
            'logtext' => $this->plugin->txt('log_logtext')
		);
	}

	/**
	 * Get the labels of contents where the data is a link
	 * @return string[] key => label
	 */
	function getLinkedLabels() : array
	{
		return array();
	}

	/**
	 * Get the data row for this element
	 * @param string $format ('csv' or 'html')
	 * @return string[] key => content
	 */
	function getRowData(string $format = 'csv') : array
	{
        $datetime = new ilDateTime($this->timestamp, IL_CAL_UNIX);
		return array(
			'timestamp' => ilDatePresentation::formatDate($datetime),
			'user_id' => $this->user_id,
			'login' => $this->login,
            'question_id' => $this->question_id,
            'question' => $this->question,
            'logtext' => $this->logtext
		);
	}
}