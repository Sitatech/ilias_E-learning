<?php

/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once("./Services/Object/classes/class.ilObjectAccess.php");

/**
* Class ilObjContentObjectAccess
*
*
* @author Alex Killing <alex.killing@gmx.de>
* @version $Id: class.ilObjSAHSLearningModuleAccess.php 37317 2012-10-03 12:57:24Z akill $
*
* @ingroup ModulesScormAicc
*/
class ilObjSAHSLearningModuleAccess extends ilObjectAccess
{
    /**
    * checks wether a user may invoke a command or not
    * (this method is called by ilAccessHandler::checkAccess)
    *
    * @param    string        $a_cmd        command (not permission!)
    * @param    string        $a_permission    permission
    * @param    int            $a_ref_id    reference id
    * @param    int            $a_obj_id    object id
    * @param    int            $a_user_id    user id (if not provided, current user is taken)
    *
    * @return    boolean        true, if everything is ok
    */
    function _checkAccess($a_cmd, $a_permission, $a_ref_id, $a_obj_id, $a_user_id = "")
    {
        global $ilUser, $lng, $rbacsystem, $ilAccess;

        if ($a_user_id == "")
        {
            $a_user_id = $ilUser->getId();
        }

        switch ($a_cmd)
        {
            case "view":

                if(!ilObjSAHSLearningModuleAccess::_lookupOnline($a_obj_id)
                    && !$rbacsystem->checkAccessOfUser($a_user_id,'write',$a_ref_id))
                {
                    $ilAccess->addInfoItem(IL_NO_OBJECT_ACCESS, $lng->txt("offline"));
                    return false;
                }
                break;
        }

        switch ($a_permission)
        {
            case "visible":
                if (!ilObjSAHSLearningModuleAccess::_lookupOnline($a_obj_id) &&
                    (!$rbacsystem->checkAccessOfUser($a_user_id,'write', $a_ref_id)))
                {
                    $ilAccess->addInfoItem(IL_NO_OBJECT_ACCESS, $lng->txt("offline"));
                    return false;
                }
                break;
        }


        return true;
    }
    
    /**
     * get commands
     * 
     * this method returns an array of all possible commands/permission combinations
     * 
     * example:    
     * $commands = array
     *    (
     *        array("permission" => "read", "cmd" => "view", "lang_var" => "show"),
     *        array("permission" => "write", "cmd" => "edit", "lang_var" => "edit"),
     *    );
     */
    function _getCommands()
    {
        $commands = array
        (
            array("permission" => "read", "cmd" => "view", "lang_var" => "show",
                "default" => true),
            array("permission" => "write", "cmd" => "editContent", "lang_var" => "edit_content"),
            array("permission" => "write", "cmd" => "edit", "lang_var" => "settings")
        );
        
        return $commands;
    }

    //
    // access relevant methods
    //

    /**
    * check wether learning module is online
    */
    function _lookupOnline($a_id)
    {
        global $ilDB;

        $set = $ilDB->queryF('SELECT * FROM sahs_lm WHERE id = %s', 
        array('integer'), array($a_id));
        $rec = $ilDB->fetchAssoc($set);
        
        return ilUtil::yn2tf($rec["c_online"]);
    }
    
    /**
    * Lookup editable
    */
    static function _lookupEditable($a_obj_id)
    {
		global $ilDB;
		
		$set = $ilDB->queryF('SELECT * FROM sahs_lm WHERE id = %s', 
			array('integer'), array($a_obj_id));
		$rec = $ilDB->fetchAssoc($set);

		return $rec["editable"];
    }
    

    /**
    * check whether goto script will succeed
    */
    function _checkGoto($a_target)
    {
        global $ilAccess;
        
        $t_arr = explode("_", $a_target);

        if ($t_arr[0] != "sahs" || ((int) $t_arr[1]) <= 0)
        {
            return false;
        }

        if ($ilAccess->checkAccess("visible", "", $t_arr[1]))
        {
            return true;
        }
        return false;
    }

    /**
     * Returns the number of bytes used on the harddisk by the learning module
     * with the specified object id.
     * @param int object id of a file object.
     */
    function _lookupDiskUsage($a_id)
    {
        $lm_data_dir = ilUtil::getWebspaceDir('filesystem')."/lm_data";
        $lm_dir = $lm_data_dir.DIRECTORY_SEPARATOR."lm_".$a_id;
        
        return file_exists($lm_dir) ? ilUtil::dirsize($lm_dir) : 0;
        
    }

	/**
		* Checks whether a certificate exists for the active user or not
		* @param int obj_id Object ID of the SCORM Learning Module
		* @param int usr_id Object ID of the user. If not given, the active user will be taken
		* @return true/false
		*/
	public static function _lookupUserCertificate($obj_id, $usr_id = 0)
	{
		global $ilUser;
		$uid = ($usr_id) ? $usr_id : $ilUser->getId();
		
		$completed = false;
		// check for certificates
		include_once "./Services/Certificate/classes/class.ilCertificate.php";
		if (ilCertificate::isActive() && ilCertificate::isObjectActive($obj_id))
		{
			$lpdata = false;
			include_once "./Modules/ScormAicc/classes/class.ilObjSAHSLearningModule.php";
			$type = ilObjSAHSLearningModule::_lookupSubType($obj_id);
			include_once("Services/Tracking/classes/class.ilObjUserTracking.php");
			if (ilObjUserTracking::_enabledLearningProgress())
			{
				include_once "./Services/Tracking/classes/class.ilLPStatus.php";
				$completed = ilLPStatus::_lookupStatus($obj_id, $uid);
				$completed = ($completed == LP_STATUS_COMPLETED_NUM);
				$lpdata = true;
			}
			switch ($type)
			{
				case "scorm":
					if (!$lpdata)
					{
						include_once "./Modules/ScormAicc/classes/class.ilObjSCORMLearningModule.php";
						$completed = ilObjSCORMLearningModule::_getCourseCompletionForUser($obj_id, $uid);
					}
					break;
				case "scorm2004":
					if (!$lpdata)
					{
						include_once "./Modules/Scorm2004/classes/class.ilObjSCORM2004LearningModule.php";
						$completed = ilObjSCORM2004LearningModule::_getCourseCompletionForUser($obj_id, $uid);
					}
					break;
				default:
					break;
			}
		}
		return $completed;
	}

	/**
	 * Type-specific implementation of general status
	 *
	 * Used in ListGUI and Learning Progress
	 *
	 * @param int $a_obj_id
	 * @return bool
	 */
	static function _isOffline($a_obj_id)
	{
		return !self::_lookupOnline($a_obj_id);
	}
}

?>