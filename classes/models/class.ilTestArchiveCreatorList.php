<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * List of archive elements
 */
class ilTestArchiveCreatorList
{
	/** @var ilTestArchiveCreator $creator */
	protected $creator;

	/** @var  ilTestArchiveCreatorElement */
	public $prototype;

	/** @var ilTestArchiveCreatorElement[] */
	public $elements = array();

	/** @var  string */
	protected $title;

	/**
	 * Constructor
	 * @param ilTestArchiveCreator $creator
	 * @param ilTestArchiveCreatorElement $prototype
	 */
	public function __construct($creator, $prototype)
	{
		$this->creator = $creator;
		$this->prototype = $prototype;
	}

	/**
	 * Set the title for HTML output
	 * @param $title
	 */
	public function setTitle($title)
	{
		$this->title = $title;
	}

	/**
	 * Add an element to the list
	 * @param ilTestArchiveCreatorElement $element
	 */
	public function add($element)
	{
		$index = $element->getSortIndex();
		$this->elements[$index] = $element;
	}

	/**
	 * Get the index as comma separated content
	 * @param ilObjTest $testObj
	 * @return string
	 */
	public function getCSV($testObj)
	{
		ksort($this->elements, SORT_NATURAL);

		$rows = array();

		$columns = $this->prototype->getColumns();

		$row = array();
		foreach ($columns as $key => $label)
		{
			$row[] = $key;
		}
		$rows[] = $row;

		foreach ($this->elements as $element)
		{
			$row = array();
			$data = $element->getRowData('csv');
			foreach ($columns as $key => $label)
			{
				$content = $data[$key];
				$row[] = $content;
			}
			$rows[] = $row;
		}

		$writer = new ilCSVWriter();
		$writer->setSeparator(';');

		foreach ($rows as $row)
		{
			$writer->addRow();
			foreach ($row as $column)
			{
				$writer->addColumn($column);
			}
		}

		return $writer->getCSVString();
	}

	/**
	 * Get the index as HTML
	 * @return string
	 */
	public function getHTML()
	{
		ksort($this->elements, SORT_NATURAL);

		$tpl = $this->creator->plugin->getTemplate('tpl.list.html');

		$columns = $this->prototype->getColumns();
		foreach ($columns as $key => $label)
		{
			$tpl->setCurrentBlock('header_column');
			$tpl->setVariable('CONTENT', $label);
			$tpl->parseCurrentBlock();
		}
		foreach ($this->elements as $element)
		{
			$data = $element->getRowData('html');
			$labels = $element->getLinkedLabels();
			foreach ($columns as $key => $label)
			{
				if (isset($labels[$key]))
				{
					$content = '<a href="'.$data[$key].'">'.$labels[$key].'</a>';
				}
				else
				{
					$content = $data[$key];
				}

				$tpl->setCurrentBlock('data_column');
				$tpl->setVariable('CONTENT', (string) $content);
				$tpl->parseCurrentBlock();

			}
			$tpl->setCurrentBlock('data_row');
			$tpl->parseCurrentBlock();
		}
		$tpl->setVariable('TITLE', $this->title);

		return $tpl->get();
	}
}