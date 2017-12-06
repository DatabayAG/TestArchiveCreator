<?php

/**
 * Base class for elements of a test archive (questions and participants)
 */
abstract class ilTestArchiveCreatorElement
{
	/** @var ilTestArchiveCreator $creator */
	protected $creator;

	/** @var  ilTestArchiveCreatorPlugin $plugin */
	protected $plugin;

	/**
	 * list of files that are created for this element
	 * The files are given with relative paths from the root directory of the archive
	 * @var array file => display title
	 */
	public $files = [];


	/**
	 * Constructor
	 * @param ilTestArchiveCreator $creator
	 */
	final public function __construct($creator)
	{
		$this->creator = $creator;
		$this->plugin = $this->creator->plugin;
	}

	/**
	 * Get a unique prefix that can be used for file and directory names
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
	 * Get the data row for this element
	 * The file list should have the key 'files' and an array as content
	 * @return array key => content
	 */
	abstract function getRowData();
}