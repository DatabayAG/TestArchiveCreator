<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Data model for a mark in the mark scheme
 */
class ilTestArchiveCreatorMark extends ilTestArchiveCreatorElement
{
	public $short_form;
	public $official_form;
	public $minimum_level;
	public $passed;


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
		return $this->minimum_level;
	}

	/**
	 * Get the list of columns for this element type
	 * The file list should have the key 'files'
	 * @return string[]    key => title
	 */
	function getColumns() : array
	{
		return array(
			'short_form' => $this->lng->txt('tst_mark_short_form'),
			'official_form' => $this->lng->txt('tst_mark_official_form'),
			'minimum_level' => $this->lng->txt('tst_mark_minimum_level'),
			'passed' => $this->lng->txt('tst_mark_passed'),
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
		return array(
			'short_form' => $this->short_form,
			'official_form' => $this->official_form,
			'minimum_level' => $this->minimum_level,
			'passed' => $this->passed
		);
	}
}