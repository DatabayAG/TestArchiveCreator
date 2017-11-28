<?php

/**
 * Data model for question list
 */
class ilTestArchiveCreatorQuestion extends ilTestArchiveCreatorElement
{
	public $question_id;
	public $title;
	public $type;
	public $max_points;


	/**
	 * Get a unique prefix that can be used for file and directory names
	 * @return mixed
	 */
	public function getFilePrefix()
	{
		return $this->creator->sanitizeFilename($this->title.'_'.$this->question_id);
	}

	/**
	 * Get a unique index for sorting the list of elements
	 * @return mixed
	 */
	function getSortIndex()
	{
		return $this->title.'_'.$this->question_id;
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
			'question_id' => $this->question_id,
			'title' => $this->title,
			'type' => $this->type,
			'max_points' => $this->max_points,
			'files' => $this->files,
		);
	}
}