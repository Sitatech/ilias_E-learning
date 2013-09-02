<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once './Services/Tracking/classes/class.ilLPStatus.php';

/**
* @author Stefan Meyer <meyer@leifos.com>
*
* @version $Id: class.ilLPStatusTestFinished.php 23880 2010-05-14 15:01:56Z jluetzen $
*
* @ingroup	ServicesTracking
*
*/
class ilLPStatusTestFinished extends ilLPStatus
{

	function ilLPStatusTestFinished($a_obj_id)
	{
		global $ilDB;

		parent::ilLPStatus($a_obj_id);
		$this->db =& $ilDB;
	}

	function _getInProgress($a_obj_id)
	{
		global $ilDB;

		include_once './Modules/Test/classes/class.ilObjTestAccess.php';

		$query = "SELECT DISTINCT(user_fi) FROM tst_active ".
			" WHERE tries = ".$ilDB->quote(0, "integer").
			" AND test_fi = ".$ilDB->quote(ilObjTestAccess::_getTestIDFromObjectID($a_obj_id), "integer");

		$res = $ilDB->query($query);
		while($row = $res->fetchRow(DB_FETCHMODE_OBJECT))
		{
			$user_ids[] = $row->user_fi;
		}
		return $user_ids ? $user_ids : array();
	}


	function _getCompleted($a_obj_id)
	{
		global $ilDB;

		include_once './Modules/Test/classes/class.ilObjTestAccess.php';

		$query = "SELECT DISTINCT(user_fi) FROM tst_active ".
			" WHERE tries > ".$ilDB->quote(0, "integer").
			" AND test_fi = ".$ilDB->quote(ilObjTestAccess::_getTestIDFromObjectID($a_obj_id));

		$res = $ilDB->query($query);
		while($row = $res->fetchRow(DB_FETCHMODE_OBJECT))
		{
			$user_ids[] = $row->user_fi;
		}
		return $user_ids ? $user_ids : array();
	}

	/**
	 * Get participants
	 *
	 * @param
	 * @return
	 */
	function getParticipants($a_obj_id)
	{
		global $ilDB;

		include_once './Modules/Test/classes/class.ilObjTestAccess.php';

		$res = $ilDB->query("SELECT DISTINCT user_fi FROM tst_active".
			" WHERE test_fi = ".$ilDB->quote(ilObjTestAccess::_getTestIDFromObjectID($a_obj_id)));
		$user_ids = array();

		while($rec = $ilDB->fetchAssoc($res))
		{
			$user_ids[] = $rec["user_fi"];
		}
		return $user_ids;
	}

	
	/**
	 * Determine status
	 *
	 * @param	integer		object id
	 * @param	integer		user id
	 * @param	object		object (optional depends on object type)
	 * @return	integer		status
	 */
	function determineStatus($a_obj_id, $a_user_id, $a_obj = null)
	{
		global $ilObjDataCache, $ilDB, $ilLog;
		
		$status = LP_STATUS_NOT_ATTEMPTED_NUM;
		
		include_once './Modules/Test/classes/class.ilObjTestAccess.php';

		$res = $ilDB->query("SELECT tries FROM tst_active".
			" WHERE user_fi = ".$ilDB->quote($a_user_id, "integer").
			" AND test_fi = ".$ilDB->quote(ilObjTestAccess::_getTestIDFromObjectID($a_obj_id)));
		
		if ($rec = $ilDB->fetchAssoc($res))
		{
			if ($rec["tries"] == 0)
			{
				$status = LP_STATUS_IN_PROGRESS_NUM;
			}
			else if ($rec["tries"] > 0)
			{
				$status = LP_STATUS_COMPLETED_NUM;
			}

		}

		return $status;		
	}

}	
?>