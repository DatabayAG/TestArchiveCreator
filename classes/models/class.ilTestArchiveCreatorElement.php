<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Base class for elements of a test archive (questions and participants)
 */
abstract class ilTestArchiveCreatorElement
{
	protected ilLanguage $lng;
    protected ilTestArchiveCreator $creator;
    protected ilTestArchiveCreatorPlugin $plugin;
    protected ilTestArchiveCreatorSettings $settings;
    protected ilTestArchiveCreatorConfig $config;

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
		$this->settings = $this->creator->settings;
        $this->config = $this->creator->config;
	}

	/**
	 * Get a name of the folder where generated files are stored
	 */
	abstract public function getFolderName() : string;

	/**
	 * Get a unique prefix that can be used for generated files
	 */
	abstract public function getFilePrefix() : string;

	/**
	 * Get a unique index for sorting the list of elements
	 */
	abstract function getSortIndex() : string;


	/**
	 * Get the list of columns for this element type
	 * The file list should have the key 'files'
	 * @return string[]	key => title
	 */
	abstract function getColumns() : array;


	/**
	 * Get the labels of contents where the data is a link
	 * @return string[] key => label
	 */
	abstract function getLinkedLabels() : array;

	/**
	 * Get the data row for this element
	 * @param string $format	('csv' or 'html')
	 * @return string[] key => content
	 */
	abstract function getRowData(string $format = 'csv') : array;
}