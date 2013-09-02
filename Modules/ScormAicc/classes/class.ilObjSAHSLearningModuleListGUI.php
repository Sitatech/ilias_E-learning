<?php

/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once "Services/Object/classes/class.ilObjectListGUI.php";

/**
 * Class ilObjSAHSLearningModuleListGUI
 *
 * @author Alex Killing <alex.killing@gmx.de>
 * $Id: class.ilObjSAHSLearningModuleListGUI.php 41204 2013-04-08 13:17:52Z akill $
 *
 * @ingroup ModulesScormAicc
 */
class ilObjSAHSLearningModuleListGUI extends ilObjectListGUI
{
	/**
	* constructor
	*
	*/
	function ilObjSAHSLearningModuleListGUI()
	{
		$this->ilObjectListGUI();
	}

	/**
	* initialisation
	*
	* this method should be overwritten by derived classes
	*/
	function init()
	{
		$this->copy_enabled = true;
		$this->delete_enabled = true;
		$this->cut_enabled = true;
		$this->subscribe_enabled = true;
		$this->link_enabled = true;
		$this->payment_enabled = true;
		$this->info_screen_enabled = true;
		$this->type = "sahs";
		$this->gui_class_name = "ilobjsahslearningmodulegui";
		
		// general commands array
		include_once('./Modules/ScormAicc/classes/class.ilObjSAHSLearningModuleAccess.php');
		$this->commands = ilObjSAHSLearningModuleAccess::_getCommands();
	}

	/**
	* Overwrite this method, if link target is not build by ctrl class
	* (e.g. "lm_presentation.php", "forum.php"). This is the case
	* for all links now, but bringing everything to ilCtrl should
	* be realised in the future.
	*
	* @param	string		$a_cmd			command
	*
	*/
	function getCommandLink($a_cmd)
	{
		global $ilCtrl;
		
		switch($a_cmd)
		{
			case "view":
				$cmd_link = null;
				require_once "./Modules/ScormAicc/classes/class.ilObjSAHSLearningModuleAccess.php";
				if (!ilObjSAHSLearningModuleAccess::_lookupEditable($this->obj_id))
				{
					$cmd_link = "ilias.php?baseClass=ilSAHSPresentationGUI&amp;ref_id=".$this->ref_id;
				}
				else
				{
					$cmd_link = "ilias.php?baseClass=ilSAHSEditGUI&amp;ref_id=".$this->ref_id;
				}
				break;

			case "editContent":
				$cmd_link = "ilias.php?baseClass=ilSAHSEditGUI&amp;ref_id=".$this->ref_id."&amp;cmd=editContent";
				break;

			case "edit":
				$cmd_link = "ilias.php?baseClass=ilSAHSEditGUI&amp;ref_id=".$this->ref_id;
				break;

			case "infoScreen":
				$cmd_link = "ilias.php?baseClass=ilSAHSPresentationGUI&amp;ref_id=".$this->ref_id.
					"&amp;cmd=infoScreen";
				break;

			default:
				$ilCtrl->setParameterByClass("ilrepositorygui", "ref_id", $this->ref_id);
				$cmd_link = $ilCtrl->getLinkTargetByClass("ilrepositorygui", $a_cmd);
				$ilCtrl->setParameterByClass("ilrepositorygui", "ref_id", $_GET["ref_id"]);
				break;
		}

		return $cmd_link;
	}


	/**
	* Get command target frame
	*
	* @param	string		$a_cmd			command
	*
	* @return	string		command target frame
	*/
	function getCommandFrame($a_cmd)
	{
		global $ilias;
		
		switch($a_cmd)
		{
			case "view":
				include_once 'Services/Payment/classes/class.ilPaymentObject.php';
				require_once "./Modules/ScormAicc/classes/class.ilObjSAHSLearningModule.php";
				$sahs_obj = new ilObjSAHSLearningModule($this->ref_id);
				if(ilPaymentObject::_isBuyable($this->ref_id) && 
				   !ilPaymentObject::_hasAccess($this->ref_id))
				{
					$frame = '';
				}
				else
				{
					$frame = "ilContObj".$this->obj_id;
				}
				if ($sahs_obj->getEditable() == 1)
				{
					$frame = ilFrameTargetInfo::_getFrame("MainContent");
				}
				break;

			case "edit":
			case "editContent":
				$frame = ilFrameTargetInfo::_getFrame("MainContent");
				break;
				
			case "infoScreen":
				$frame = ilFrameTargetInfo::_getFrame("MainContent");
				break;

			default:
				$frame = "";
				break;
		}

		return $frame;
	}


	/**
	* Get item properties
	*
	* @return	array		array of property arrays:
	*						"alert" (boolean) => display as an alert property (usually in red)
	*						"property" (string) => property name
	*						"value" (string) => property value
	*/
	function getProperties()
	{
		global $lng, $rbacsystem;

		$props = array();

		include_once("./Modules/ScormAicc/classes/class.ilObjSAHSLearningModuleAccess.php");

		$editable = ilObjSAHSLearningModuleAccess::_lookupEditable($this->obj_id);
		
		if (!$editable && ilObjSAHSLearningModuleAccess::_isOffline($this->obj_id))
		{
			$props[] = array("alert" => true, "property" => $lng->txt("status"),
				"value" => $lng->txt("offline"));
		}
		else if ($editable)
		{
			$props[] = array("alert" => true,
				"value" => $lng->txt("authoring_mode"));
		}

		if ($rbacsystem->checkAccess("write", $this->ref_id))
		{
			$props[] = array("alert" => false, "property" => $lng->txt("type"),
				"value" => $lng->txt("sahs"));
		}
		
		// check for certificates
		if (ilObjSAHSLearningModuleAccess::_lookupUserCertificate($this->obj_id))
		{
			include_once "./Modules/ScormAicc/classes/class.ilObjSAHSLearningModule.php";
			$type = ilObjSAHSLearningModule::_lookupSubType($this->obj_id);
			switch ($type)
			{
				case "scorm":
					$lng->loadLanguageModule('certificate');
					$cmd_link = "ilias.php?baseClass=ilSAHSPresentationGUI&amp;ref_id=".$this->ref_id.
							"&amp;cmd=downloadCertificate";
					$props[] = array("alert" => false, "property" => $lng->txt("condition_finished"),
						"value" => '<a href="' . $cmd_link . '">' . $lng->txt("download_certificate") . '</a>');
					break;
				case "scorm2004":
					$lng->loadLanguageModule('certificate');
					$cmd_link = "ilias.php?baseClass=ilSAHSPresentationGUI&amp;ref_id=".$this->ref_id.
							"&amp;cmd=downloadCertificate";
					$props[] = array("alert" => false, "property" => $lng->txt("condition_finished"),
						"value" => '<a href="' . $cmd_link . '">' . $lng->txt("download_certificate") . '</a>');
					break;
			}
		}

		return $props;
	}


} // END class.ilObjCategoryGUI
?>
