<?php

/**
 * Data model for question list
 */
class ilTestArchiveCreatorParticipant extends ilTestArchiveCreatorElement
{
	public $active_id;
	public $firstname;
	public $lastname;
	public $login;
	public $matriculation;

	public $first_access;
	public $last_access;
	public $working_time;

	public $passes;
	public $scored_pass;
	public $finished;

	public $reached_points;
	public $mark;
	public $passed;


	/**
	 * Get a unique prefix that can be used for file and directory names
	 * @return mixed
	 */
	public function getFilePrefix()
	{
		return $this->creator->sanitizeFilename($this->lastname.'_'.$this->firstname.'_'.$this->login.'_'.$this->active_id);
	}

	/**
	 * Get a unique index for sorting the list of elements
	 * @return mixed
	 */
	function getSortIndex()
	{
		return $this->lastname.'_'.$this->firstname.'_'.$this->login.'_'.$this->active_id;
	}

	/**
	 * Get the list of columns for this element type
	 * The file list should have the key 'files'
	 * @return array    key => title
	 */
	function getColumns()
	{
		return array(
			'question_id' => $this->plugin->txt('question_id'),
			'title' => $this->plugin->txt('question_title'),
			'type' => $this->plugin->txt('question_type'),
			'max_points' => $this->plugin->txt('max_points'),
			'files' => $this->plugin->txt('files')
		);
	}

	/**
	 * Get the data row for this element
	 * The file list should have the key 'files' and an array as content
	 * @return array key => content
	 */
	function getRowData()
	{
		return array(
			'files' => $this->files,
		);
	}
}