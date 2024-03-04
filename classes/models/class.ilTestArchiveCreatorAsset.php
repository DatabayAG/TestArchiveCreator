<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Data model for an asset
 */
class ilTestArchiveCreatorAsset extends ilTestArchiveCreatorElement
{
    public string $asset_name = '';
	public string $original_url = '';


	/**
	 * Get a name of the folder where generated files are stored
	 */
	public function getFolderName() : string
	{
		return "assets";
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
		return $this->original_url;
	}

	/**
	 * Get the list of columns for this element type
	 * The file list should have the key 'files'
	 * @return string[]    key => title
	 */
	function getColumns() : array
	{
		return array(
			'asset_name' => 'asset_name',
			'original_url' => 'original_url',
		);
	}

	/**
	 * Get the labels of contents where the data is a link
	 * @return string[] key => label
	 */
	function getLinkedLabels() : array
	{
		return array('asset_name' => $this->asset_name);
	}

	/**
	 * Get the data row for this element
	 * @param string $format ('csv' or 'html')
	 * @return string[] key => content
	 */
	function getRowData(string $format = 'csv') : array
	{
		return array(
			'asset_name' => ($format == 'html' ? './assets/' : '') . $this->asset_name,
			'original_url' => $this->original_url
		);
	}
}