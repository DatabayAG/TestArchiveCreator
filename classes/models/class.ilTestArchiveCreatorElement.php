<?php

/**
 * Base class for elements of a test archive (questions and participants)
 */
abstract class ilTestArchiveCreatorElement
{
	/** @var ilLanguage $lng */
	protected $lng;

	/** @var ilTestArchiveCreator $creator */
	protected $creator;

	/** @var  ilTestArchiveCreatorPlugin $plugin */
	protected $plugin;


	/**
	 * Constructor
	 * @param ilTestArchiveCreator $creator
	 */
	final public function __construct($creator)
	{
		global $DIC;
		$this->lng = $DIC->language();

		$this->creator = $creator;
		$this->plugin = $this->creator->plugin;
	}

	/**
	 * Get a name of the folder where generated files are stored
	 * @return mixed
	 */
	abstract public function getFolderName();

	/**
	 * Get a unique prefix that can be used for generated files
	 * @return mixed
	 */
	abstract public function getFilePrefix();

	/**
	 * Get a unique index for sorting the list of elements
	 * @return mixed
	 */
	abstract function getSortIndex();


	/**
	 * Get the list of columns for this element type
	 * The file list should have the key 'files'
	 * @return array	key => title
	 */
	abstract function getColumns();


	/**
	 * Get the labels of contents where the data is a link
	 * @return array key => label
	 */
	abstract function getLinkedLabels();

	/**
	 * Get the data row for this element
	 * @param string $format	('csv' or 'html')
	 * @return array key => content
	 */
	abstract function getRowData($format = 'csv');
}