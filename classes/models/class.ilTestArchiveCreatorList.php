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
	 * St the title for HTML output
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
	 * @return string
	 */
	public function getCSV()
	{
		ksort($this->elements, SORT_NATURAL);

		return '';
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
						foreach ($content as $file)
						{
							$content2[] = '<a href="file://'.$file.'">'.$file.'</a>';
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