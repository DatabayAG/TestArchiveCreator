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
	 */
	public function getFolderName() : string
	{
		return $this->creator->filesystems->sanitizeFilename($this->title.'_'.$this->question_id);
	}



	/**
	 * Get a unique prefix that can be used for file and directory names
	 */
	public function getFilePrefix() : string
	{
		return $this->creator->filesystems->sanitizeFilename($this->exam_question_id);
	}

	/**
	 * Get a unique index for sorting the list of elements
	 */
	function getSortIndex() : string
	{
		return $this->title . '_' . $this->question_id;
	}

	/**
	 * Get the list of columns for this element type
	 * The file list should have the key 'files'
	 * @return string[]    key => title
	 */
	function getColumns() : array
	{
		$columns = array(
			'exam_question_id' => $this->plugin->txt('question_id'),
			'title' => $this->plugin->txt('question_title'),
			'type' => $this->plugin->txt('question_type'),
			'max_points' => $this->plugin->txt('max_points'),
			'presentation' => $this->plugin->txt('question_presentation')
		);

		if ($this->settings->questions_with_best_solution)
        {
            $columns['best_solution'] = $this->plugin->txt('question_best_solution');
        }
        return $columns;
	}

	/**
	 * Get the labels of contents where the data is a link
	 * @return string[] key => label
	 */
	function getLinkedLabels() : array
	{
        $label = empty($this->config->pdf_engine) ? 'HTML' : 'PDF';

		return array(
			'presentation' => $label,
			'best_solution' => $label
		);
	}

	/**
	 * Get the data row for this element
	 * @param string $format ('csv' or 'html')
	 * @return array key => content
	 */
	function getRowData(string $format = 'csv') : array
	{
		$row = array(
			'exam_question_id' => $this->exam_question_id,
			'title' => $this->title,
			'type' => $this->type,
			'max_points' => $this->max_points,
			'presentation' => $this->presentation . (empty($this->config->pdf_engine) ? '.html' : '.pdf'),
		);
		if ($this->settings->questions_with_best_solution)
        {
            $row['best_solution'] = $this->best_solution. (empty($this->config->pdf_engine) ? '.html' : '.pdf');
        }

        return $row;
	}
}