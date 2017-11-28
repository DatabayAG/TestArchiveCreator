<?php

/**
 * Config and object settings for the test archive creator plugin
 */
class ilTestArchiveCreatorSettings
{
	const STATUS_INACTIVE = 'inactive';
	const STATUS_PLANNED = 'planned';
	const STATUS_FINISHED = 'finished';

	const PASS_ALL = 'all';
	const PASS_LAST = 'last';
	const PASS_BEST = 'best';
	const PASS_SCORED = 'scored';

	/** @var string archive status */
	public $status = self::STATUS_INACTIVE;

	/** @var string pass selection */
	public $pass_selection = self::PASS_SCORED;

	/** @var ilDateTime|null */
	public $schedule = null;


	/** @var  ilDB $db */
	protected $db;

	/** @var ilTestArchiveCreatorPlugin $plugin */
	protected $plugin;

	/** @var  int id of the test object */
	protected $obj_id;


	/**
	 * ilTestArchiveCreatorSettings constructor.
	 * @param ilTestArchiveCreatorPlugin $plugin
	 * @param int $obj_id test object id
	 */
	public function __construct($plugin, $obj_id)
	{
		global $DIC;

		$this->pluin = $plugin;
		$this->db = $DIC->database();
		$this->obj_id = $obj_id;
		$this->read();
	}

	/**
	 * Delete the archive settings of a test
	 * @param integer object id
	 */
	public static function _deleteForObject($obj_id)
	{
		global $DIC;
		$db = $DIC->database();

		$query = 'DELETE FROM tarc_ui_settings WHERE obj_id = ' . $db->quote($obj_id,'integer');

		$db->manipulate($query);
	}

	/**
	 * Read the archive settings
	 */
	protected function read()
	{
		$query = "SELECT * FROM tarc_ui_settings WHERE obj_id = " . $this->db->quote($this->obj_id,'integer');
		$result = $this->db->query($query);
		if ($row = $this->db->fetchAssoc($result))
		{
			$this->status = $row['status'];
			$this->pass_selection = $row['pass_selection'];
			if (!empty($row['schedule'])) {
				$this->schedule = new ilDateTime($row['schedule'], IL_CAL_DATETIME);
			}
		}
	}

	/**
	 * Save the archive settings
	 * @return  boolean     success
	 */
	public function save()
	{
		$rows = $this->db->replace('tarc_ui_settings',
			array(
				'obj_id' => array('integer', $this->obj_id)
			),
			array(
				'status' => array('text', $this->status),
				'schedule' => array('timestamp', isset($this->schedule) ? $this->schedule->get(IL_CAL_DATETIME) : null),
				'pass_selection' => array('text', $this->pass_selection)
			)
		);
		return $rows > 0;
	}
}