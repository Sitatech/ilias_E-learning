<?php

/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once("./Modules/LearningModule/classes/class.ilObjContentObjectAccess.php");

/**
* Class ilObjLearningModuleAccess
*
*
* @author Alex Killing <alex.killing@gmx.de>
* @version $Id: class.ilObjLearningModuleAccess.php 37317 2012-10-03 12:57:24Z akill $
*
* @ingroup ModulesIliasLearningModule
*/
class ilObjLearningModuleAccess extends ilObjContentObjectAccess
{

	/**
	 * get commands
	 * 
	 * this method returns an array of all possible commands/permission combinations
	 * 
	 * example:	
	 * $commands = array
	 *	(
	 *		array("permission" => "read", "cmd" => "view", "lang_var" => "show"),
	 *		array("permission" => "write", "cmd" => "edit", "lang_var" => "edit"),
	 *	);
	 */
	function _getCommands()
	{
		$commands = array
		(
			array("permission" => "read", "cmd" => "view", "lang_var" => "show",
				"default" => true),
			array("permission" => "read", "cmd" => "continue", "lang_var" => "continue_work"),
			array("permission" => "write", "cmd" => "edit", "lang_var" => "edit_content"),
			array("permission" => "write", "cmd" => "properties", "lang_var" => "settings")
		);
		
		return $commands;
	}
	
}

?>
