<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Data model for question list
 */
class ilTestArchiveCreatorQuestion extends ilTestArchiveCreatorElement
{
	public $question_id;
	public $exam_question_id;
	public $title;
	public $type;
	public $max_points;

	public $presentation;
	public $best_solution;


	/**
	 * Get a name of the folder where generated files are stored
	 * @return mixed
	 */
	public function getFolderName()
	{
		return $this->creator->sanitizeFilename($this->title.'_'.$this->question_id);
	}



	/**
	 * Get a unique prefix that can be used for file and directory names
	 * @return mixed
	 */
	public function getFilePrefix()
	{
		return $this->creator->sanitizeFilename($this->exam_question_id);
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
			'exam_question_id' => $this->plugin->txt('question_id'),
			'title' => $this->plugin->txt('question_title'),
			'type' => $this->plugin->txt('question_type'),
			'max_points' => $this->plugin->txt('max_points'),
			'presentation' => $this->plugin->txt('question_presentation'),
			'best_solution' => $this->plugin->txt('question_best_solution'),
		);
	}

	/**
	 * Get the labels of contents where the data is a link
	 * @return array key => label
	 */
	function getLinkedLabels()
	{
		return array(
			'presentation' => $this->plugin->txt('question_presentation'),
			'best_solution' => $this->plugin->txt('question_best_solution'),
		);
	}

	/**
	 * Get the data row for this element
	 * @param string $format ('csv' or 'html')
	 * @return array key => content
	 */
	function getRowData($format = 'csv')
	{
		return array(
			'exam_question_id' => $this->exam_question_id,
			'title' => $this->title,
			'type' => $this->type,
			'max_points' => $this->max_points,
			'presentation' => $this->presentation,
			'best_solution' => $this->best_solution,
		);
	}
}