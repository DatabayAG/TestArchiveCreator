<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * List of archive elements
 */
class ilTestArchiveCreatorList
{
	public ilTestArchiveCreator $creator;
	public ilTestArchiveCreatorElement $prototype;

	/** @var ilTestArchiveCreatorElement[] */
	public array $elements = [];

	protected string $title = '';

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
     * Check if an element is already in the list
     * @param ilTestArchiveCreatorElement $element
     */
    public function has($element)
    {
        $index = $element->getSortIndex();
        return isset($this->elements[$index]);
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
	 * @return string
	 */
	public function getCSV()
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
        $writer->setDoUTF8Decoding(true);
        $writer->setDelimiter('"');
		$writer->setSeparator(';');

		foreach ($rows as $row)
		{
			foreach ($row as $column)
			{
				$writer->addColumn($column);
			}
            $writer->addRow();
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