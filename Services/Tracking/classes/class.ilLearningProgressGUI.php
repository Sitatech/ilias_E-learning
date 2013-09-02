<?php
/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once './Services/Tracking/classes/class.ilLearningProgressBaseGUI.php';

/**
* Class ilObjUserTrackingGUI
*
* @author Stefan Meyer <meyer@leifos.com>
*
* @version $Id: class.ilLearningProgressGUI.php 37947 2012-10-30 12:10:34Z jluetzen $
*
* @ilCtrl_Calls ilLearningProgressGUI: ilLPListOfObjectsGUI, ilLPListOfSettingsGUI, ilLPListOfProgressGUI,  ilLMStatisticsGUI
* @ilCtrl_Calls ilLearningProgressGUI: ilLPObjectStatisticsGUI
*
*/
class ilLearningProgressGUI extends ilLearningProgressBaseGUI
{
	function ilLearningProgressGUI($a_mode,$a_ref_id = 0,$a_user_id = 0)
	{
		parent::ilLearningProgressBaseGUI($a_mode,$a_ref_id,$a_user_id);
	}

	/**
	* execute command
	*/
	function &executeCommand()
	{
		global $ilBench, $ilHelp;
		
		$ilBench->start('LearningProgress','0000_Start');


		$this->ctrl->setReturn($this, "");

		// E.g personal desktop mode needs locator header icon ...
		$this->__buildHeader();
		switch($this->__getNextClass())
		{
			case 'illplistofprogressgui':
				include_once 'Services/Tracking/classes/class.ilLPListOfProgressGUI.php';
				
				$ilHelp->setScreenIdComponent("lp");

				$this->__setSubTabs(LP_ACTIVE_PROGRESS);
				$this->__setCmdClass('illplistofprogressgui');
				$lop_gui = new ilLPListOfProgressGUI($this->getMode(),$this->getRefId(),$this->getUserId());
				$this->ctrl->forwardCommand($lop_gui);
				break;

			case 'illplistofobjectsgui':
				include_once 'Services/Tracking/classes/class.ilLPListOfObjectsGUI.php';
				if(stristr($this->ctrl->getCmd(), "matrix"))
				{
					$this->__setSubTabs(LP_ACTIVE_MATRIX);
				}
				else if(stristr($this->ctrl->getCmd(), "summary"))
				{
					$this->__setSubTabs(LP_ACTIVE_SUMMARY);
				}
				else
				{
					$this->__setSubTabs(LP_ACTIVE_OBJECTS);
				}
				$loo_gui = new ilLPListOfObjectsGUI($this->getMode(),$this->getRefId());
				$this->__setCmdClass('illplistofobjectsgui');
				$this->ctrl->forwardCommand($loo_gui);
				break;

			case 'illplistofsettingsgui':
				include_once 'Services/Tracking/classes/class.ilLPListOfSettingsGUI.php';

				$this->__setSubTabs(LP_ACTIVE_SETTINGS);
				$los_gui = new ilLPListOfSettingsGUI($this->getMode(),$this->getRefId());
				$this->__setCmdClass('illplistofsettingsgui');
				$this->ctrl->forwardCommand($los_gui);
				break;
				
			case 'illmstatisticsgui':
				include_once 'Services/Tracking/classes/class.ilLMStatisticsGUI.php';

				$this->__setSubTabs(LP_ACTIVE_LM_STATISTICS);
				$los_gui = new ilLMStatisticsGUI($this->getMode(),$this->getRefId());
				$this->__setCmdClass('illmstatisticsgui');
				$this->ctrl->forwardCommand($los_gui);
				break;

			case 'illpobjectstatisticsgui':
				include_once 'Services/Tracking/classes/class.ilLPObjectStatisticsGUI.php';
				if(stristr($this->ctrl->getCmd(), "access"))
				{
					$this->__setSubTabs(LP_ACTIVE_OBJSTATACCESS);
				}
				else if(stristr($this->ctrl->getCmd(), "types"))
				{
					$this->__setSubTabs(LP_ACTIVE_OBJSTATTYPES);
				}
				else if(stristr($this->ctrl->getCmd(), "daily"))
				{
					$this->__setSubTabs(LP_ACTIVE_OBJSTATDAILY);
				}
				else
				{
					$this->__setSubTabs(LP_ACTIVE_OBJSTATADMIN);
				}
				$this->__setCmdClass('illpobjectstatisticsgui');
				$ost_gui = new ilLPObjectStatisticsGUI($this->getMode(),$this->getRefId());
				$this->ctrl->forwardCommand($ost_gui);
				break;
			
			default:
				die("No mode given");
		}

		// E.G personal desktop mode needs $tpl->show();
		$this->__buildFooter();


		$ilBench->stop('LearningProgress','0000_Start');

		return true;
	}

	function __setCmdClass($a_class)
	{
		// If cmd class == 'illearningprogressgui' the cmd class is set to the the new forwarded class
		// otherwise e.g illplistofprogressgui tries to forward (back) to illearningprogressgui.

		if($this->ctrl->getCmdClass() == strtolower(get_class($this)))
		{
			$this->ctrl->setCmdClass(strtolower($a_class));
		}
		return true;
	}

	function __getNextClass()
	{
		global $ilAccess, $ilUser;
		
		// #9857
		if(!ilObjUserTracking::_enabledLearningProgress())
		{
			return;
		}

		if(strlen($next_class = $this->ctrl->getNextClass()))
		{
			if($this->getMode() == LP_MODE_PERSONAL_DESKTOP)
			{
				$_SESSION['il_lp_history'] = $next_class;
			}
			return $next_class;
		}
		switch($this->getMode())
		{
			case LP_MODE_ADMINISTRATION:
				return 'illplistofobjectsgui';

			case LP_MODE_REPOSITORY:
				if(!$this->anonymized and 
				   !$this->isObjectAnonymized() and 
				   $ilAccess->checkAccess('edit_learning_progress','',$this->getRefId()))
				{
					return 'illplistofobjectsgui';
				}
				return 'illplistofprogressgui';

			case LP_MODE_PERSONAL_DESKTOP:
								
				include_once("Services/Tracking/classes/class.ilObjUserTracking.php");			
				$has_edit = ilObjUserTracking::_hasLearningProgressOtherUsers();		
				$has_personal = ilObjUserTracking::_hasLearningProgressLearner();
				
				if($has_edit || $has_personal)
				{														
					// default
					$tgt = null;
					if($has_edit)
					{
						$tgt = 'illplistofobjectsgui';
					}
					else if($has_personal)
					{
						$tgt = 'illplistofprogressgui';
					}

					// validate session
					switch($_SESSION['il_lp_history'])
					{
						case 'illplistofobjectsgui':
							if(!$has_edit)
							{
								$_SESSION['il_lp_history'] = null;
							}
							break;

						case 'illplistofprogressgui':
							if(!$has_personal)
							{
								$_SESSION['il_lp_history'] = null;
							}
							break;					
					}

					if($_SESSION['il_lp_history'])
					{
						return $_SESSION['il_lp_history'];
					}
					else if($tgt)
					{
						return $tgt;
					}
				}
				
				// should not happen
				ilUtil::redirect("ilias.php?baseClass=ilPersonalDesktopGUI");
				
			case LP_MODE_USER_FOLDER:
				if(ilObjUserTracking::_enabledUserRelatedData())
				{
					return 'illplistofprogressgui';
				}
		}
	}
}
?>