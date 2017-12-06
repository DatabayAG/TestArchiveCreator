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
	public $exam_id;

	public $first_access;
	public $last_access;
	public $working_time;

	public $pass;
	public $scored;
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
		global $DIC;
		$this->lng = $DIC->language();

		return array(
			'firstname' => $this->lng->txt('firstname'),
			'lastname' => $this->lng->txt('lastname'),
			'login' => $this->lng->txt('login'),
			'matriculation' => $this->lng->txt('matriculation'),
			'exam_id' => $this->plugin->txt('exam_id'),
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
			'firstname' => $this->firstname,
			'lastname' => $this->lastname,
			'login' => $this->login,
			'matriculation' => $this->matriculation,
			'exam_id' => $this->exam_id,
			'files' => $this->files,
		);
	}
}