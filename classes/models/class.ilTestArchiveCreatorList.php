<?php

/**
 * List of archive elements
 */
class ilTestArchiveCreatorList
{
	/** @var ilTestArchiveCreator $creator */
	protected $creator;

	/** @var  ilTestArchiveCreatorElement */
	public $prototype;

	/** @var array ilTestArchiveCreatorElement[] */
	protected $elements = array();

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
		array_push($rows, $row);

		foreach ($this->elements as $element)
		{
			$row = array();
			$data = $element->getRowData();
			foreach ($columns as $key => $label)
			{
				$content = $data[$key];
				if (is_array($content))
				{
					if ($key == 'files')
					{
						foreach ($content as $file => $name)
						{
							$row[] = $file;
						}
					}
					else
					{
						$content = implode(', ', $content);
						$row[] = $content;
					}
				}
				else
				{
					$row[] = $content;
				}


			}
			array_push($rows, $row);
		}

		include_once('Services/Utilities/classes/class.ilCSVWriter.php');
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
			$data = $element->getRowData();
			foreach ($columns as $key => $label)
			{
				$content = $data[$key];
				if (is_array($content))
				{
					if ($key == 'files')
					{
						$content2 = array();
						foreach ($content as $file => $name)
						{
							$content2[] = '<a href="'.$file.'">'.$name.'</a>';
						}
						$content = $content2;
					}
					$content = implode('<br />', $content);
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