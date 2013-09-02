<?php

/* Copyright (c) 1998-2011 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once "./Services/Object/classes/class.ilObjectGUI.php";

/**
 * Class ilObjAdvancedEditingGUI
 *
 * @author Helmut Schottmüller <hschottm@gmx.de>
 * @version $Id: class.ilObjAdvancedEditingGUI.php 33512 2012-03-04 14:57:57Z akill $
 * 
 * @ilCtrl_Calls ilObjAdvancedEditingGUI: ilPermissionGUI
 *
 * @ingroup ServicesAdvancedEditing
 */
class ilObjAdvancedEditingGUI extends ilObjectGUI
{
	var $conditions;

	/**
	 * Constructor
	 */
	function ilObjAdvancedEditingGUI($a_data,$a_id,$a_call_by_reference)
	{
		global $rbacsystem, $lng;

		$this->type = "adve";
		$this->ilObjectGUI($a_data,$a_id,$a_call_by_reference,false);
		$this->lng->loadLanguageModule('adve');
		$this->lng->loadLanguageModule('meta');

		if (!$rbacsystem->checkAccess('read',$this->object->getRefId()))
		{
			$this->ilias->raiseError($this->lng->txt("msg_no_perm_read_adve"),$this->ilias->error_obj->WARNING);
		}
	}
	
	function &executeCommand()
	{
		$next_class = $this->ctrl->getNextClass($this);
		$cmd = $this->ctrl->getCmd();
		$this->prepareOutput();

		switch($next_class)
		{
			
			case 'ilpermissiongui':
				include_once("Services/AccessControl/classes/class.ilPermissionGUI.php");
				$perm_gui =& new ilPermissionGUI($this);
				$ret =& $this->ctrl->forwardCommand($perm_gui);
				break;

			default:
				if($cmd == "" || $cmd == "view")
				{
					$cmd = "showGeneralPageEditorSettings";
				}
				$cmd .= "Object";
				$this->$cmd();

				break;
		}
		return true;
	}


	/**
	* save object
	* @access	public
	*/
	function saveObject()
	{
		global $rbacadmin;

		// create and insert forum in objecttree
		$newObj = parent::saveObject();

		// put here object specific stuff

		// always send a message
		ilUtil::sendSuccess($this->lng->txt("object_added"),true);

		$this->ctrl->redirect($this);
		//header("Location:".$this->getReturnLocation("save","adm_object.php?".$this->link_params));
		//exit();
	}


	/**
	 * Display assessment folder settings form
	 */
	function settingsObject()
	{
		global $ilAccess, $tpl, $ilCtrl, $lng;
		
		$editor = $this->object->_getRichTextEditor();
		
		include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
		$this->form = new ilPropertyFormGUI();
		$this->form->setFormAction($ilCtrl->getFormAction($this));
		$this->form->setTitle($lng->txt("adve_activation"));
		$cb = new ilCheckboxInputGUI($this->lng->txt("adve_use_tiny_mce"), "use_tiny");
		if ($editor == "tinymce")
		{
			$cb->setChecked(true);
		}
		$this->form->addItem($cb);
		$this->form->addCommandButton("saveSettings", $lng->txt("save"));
		
		$tpl->setContent($this->form->getHTML());
	}
	
	/**
	* Display settings for test and assessment.
	*/
	function assessmentObject()
	{
		global $ilAccess;
		
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.advanced_editing_assessment.html",
			"Services/AdvancedEditing");
		
		$alltags =& $this->object->getHTMLTags();
		$usedtags =& $this->object->_getUsedHTMLTags("assessment");
		foreach ($alltags as $tag)
		{
			$this->tpl->setCurrentBlock("html_tag_row");
			$this->tpl->setVariable("HTML_TAG", $tag);
			if (is_array($usedtags))
			{
				if (in_array($tag, $usedtags))
				{
					$this->tpl->setVariable("HTML_TAG_SELECTED", " selected=\"selected\"");
				}
			}
			$this->tpl->parseCurrentBlock();
		}
		
		if ($ilAccess->checkAccess("write", "", $this->object->getRefId()))
		{
			$this->tpl->setCurrentBlock("save");
			$this->tpl->setVariable("TXT_SAVE", $this->lng->txt("save"));
			$this->tpl->parseCurrentBlock();
		}

		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("FORMACTION", $this->ctrl->getFormAction($this));
		$this->tpl->setVariable("TXT_ASSESSMENT_SETTINGS", $this->lng->txt("advanced_editing_assessment_settings"));
		$this->tpl->setVariable("TXT_ALLOW_HTML_TAGS", $this->lng->txt("advanced_editing_allow_html_tags"));

		$this->tpl->parseCurrentBlock();
	}
	
	
	/**
	* Display settings for surveys.
	*/
	function surveyObject()
	{
		global $ilAccess;
		
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.advanced_editing_survey.html",
			"Services/AdvancedEditing");
		
		$alltags =& $this->object->getHTMLTags();
		$usedtags =& $this->object->_getUsedHTMLTags("survey");
		foreach ($alltags as $tag)
		{
			$this->tpl->setCurrentBlock("html_tag_row");
			$this->tpl->setVariable("HTML_TAG", $tag);
			if (is_array($usedtags))
			{
				if (in_array($tag, $usedtags))
				{
					$this->tpl->setVariable("HTML_TAG_SELECTED", " selected=\"selected\"");
				}
			}
			$this->tpl->parseCurrentBlock();
		}
		
		if ($ilAccess->checkAccess("write", "", $this->object->getRefId()))
		{
			$this->tpl->setCurrentBlock("save");
			$this->tpl->setVariable("TXT_SAVE", $this->lng->txt("save"));
			$this->tpl->parseCurrentBlock();
		}

		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("FORMACTION", $this->ctrl->getFormAction($this));
		$this->tpl->setVariable("TXT_SURVEY_SETTINGS", $this->lng->txt("advanced_editing_survey_settings"));
		$this->tpl->setVariable("TXT_ALLOW_HTML_TAGS", $this->lng->txt("advanced_editing_allow_html_tags"));

		$this->tpl->parseCurrentBlock();
	}
	
	/**
	* Display settings for learning module page JS editor (Currently HTMLArea)
	*/
/*
	function learningModuleObject()
	{
		global $ilSetting;

		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.advanced_editing_learning_module.html",
			"Services/AdvancedEditing");
				
		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("FORMACTION", $this->ctrl->getFormAction($this));
		$this->tpl->setVariable("TXT_LM_SETTINGS", $this->lng->txt("advanced_editing_lm_settings"));
		$this->tpl->setVariable("TXT_LM_JS_EDITING", $this->lng->txt("advanced_editing_lm_js_editing"));
		$this->tpl->setVariable("TXT_LM_JS_EDITING_DESC", $this->lng->txt("advanced_editing_lm_js_editing_desc"));
		$this->tpl->setVariable("TXT_SAVE", $this->lng->txt("save"));

		if ($ilSetting->get("enable_js_edit", 1))
		{
			$this->tpl->setVariable("JS_EDIT", "checked=\"checked\"");
		}

		$this->tpl->parseCurrentBlock();
	}
*/
	/**
	* Save settings for learning module JS editing.
	*/
/*
	function saveLearningModuleSettingsObject()
	{
		global $ilSetting;

		ilUtil::sendSuccess($this->lng->txt("msg_obj_modified"),true);
		$ilSetting->set("enable_js_edit", (int) $_POST["js_edit"]);
		$this->ctrl->redirect($this, 'learningmodule');
	}
*/
	/**
	* Save Assessment settings
	*/
	function saveSettingsObject()
	{
		if ($_POST["use_tiny"])
		{
			$this->object->_setRichTextEditor("tinymce");
		}
		else
		{
			$this->object->_setRichTextEditor("");
		}
		ilUtil::sendSuccess($this->lng->txt("msg_obj_modified"),true);

		$this->ctrl->redirect($this,'settings');
	}
	
	function saveAssessmentSettingsObject()
	{
		ilUtil::sendSuccess($this->lng->txt("msg_obj_modified"),true);

		$this->object->_setUsedHTMLTags($_POST["html_tags"], "assessment");
		$this->ctrl->redirect($this,'assessment');
	}
	
	function saveSurveySettingsObject()
	{
		ilUtil::sendSuccess($this->lng->txt("msg_obj_modified"),true);

		$this->object->_setUsedHTMLTags($_POST["html_tags"], "survey");
		$this->ctrl->redirect($this,'survey');
	}
	
	function getAdminTabs(&$tabs_gui)
	{
		$this->getTabs($tabs_gui);
	}
	
	/**
	* Show page editor settings
	*/
	function showPageEditorSettingsObject()
	{
		global $tpl, $ilTabs, $ilCtrl;
		
		$this->addPageEditorSettingsSubTabs();
		
		include_once("./Services/COPage/classes/class.ilPageEditorSettings.php");
		$grps = ilPageEditorSettings::getGroups();
		
		$this->cgrp = $_GET["grp"];
		if ($this->cgrp == "")
		{
			$this->cgrp = key($grps);
		}

		$ilCtrl->setParameter($this, "grp", $this->cgrp);
		$ilTabs->setSubTabActive("adve_grp_".$this->cgrp);
		
		$this->initPageEditorForm();
		$tpl->setContent($this->form->getHtml());
	}
	
	/**
	* Init page editor form.
	*
	* @param        int        $a_mode        Edit Mode
	*/
	public function initPageEditorForm($a_mode = "edit")
	{
		global $lng, $ilSetting;
		
		$lng->loadLanguageModule("content");
		
		include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
		$this->form = new ilPropertyFormGUI();
	
		if ($this->cgrp == "rep")
		{
			$this->form->setTitle($lng->txt("adve_activation"));
			$cb = new ilCheckboxInputGUI($this->lng->txt("advanced_editing_rep_page_editing"), "cat_page_edit");
			$cb->setInfo($this->lng->txt("advanced_editing_rep_page_editing_desc"));
			if ($ilSetting->get("enable_cat_page_edit"))
			{
				$cb->setChecked(true);
			}
			$this->form->addItem($cb);

			$sh = new ilFormSectionHeaderGUI();
			$sh->setTitle($lng->txt("adve_text_content_features"));
			$this->form->addItem($sh);
		}
		else
		{
			$this->form->setTitle($lng->txt("adve_text_content_features"));
		}

		
		include_once("./Services/COPage/classes/class.ilPageEditorSettings.php");
		
		include_once("./Services/COPage/classes/class.ilPageContentGUI.php");
		$buttons = ilPageContentGUI::_getCommonBBButtons();
		foreach ($buttons as $b => $t)
		{
			// command button activation
			$cb = new ilCheckboxInputGUI(str_replace(":", "", $this->lng->txt("cont_text_".$b)), "active_".$b);
			$cb->setChecked(ilPageEditorSettings::lookupSetting($this->cgrp, "active_".$b, true));
			$this->form->addItem($cb);
		}
	
		// save and cancel commands
		$this->form->addCommandButton("savePageEditorSettings", $lng->txt("save"));
		
		$this->form->setFormAction($this->ctrl->getFormAction($this));
	 
	}
	
	/**
	* Save page editor settings form
	*
	*/
	public function savePageEditorSettingsObject()
	{
		global $tpl, $lng, $ilCtrl, $ilSetting;
	
		$this->initPageEditorForm();
		if ($this->form->checkInput())
		{
			include_once("./Services/COPage/classes/class.ilPageEditorSettings.php");
			include_once("./Services/COPage/classes/class.ilPageContentGUI.php");
			$buttons = ilPageContentGUI::_getCommonBBButtons();
			foreach ($buttons as $b => $t)
			{
				ilPageEditorSettings::writeSetting($_GET["grp"], "active_".$b,
					$this->form->getInput("active_".$b));
			}
			
			if ($_GET["grp"] == "rep")
			{
				$ilSetting->set("enable_cat_page_edit", (int) $_POST["cat_page_edit"]);
			}
			
			ilUtil::sendInfo($lng->txt("msg_obj_modified"), true);
		}
		
		$ilCtrl->setParameter($this, "grp", $_GET["grp"]);
		$ilCtrl->redirect($this, "showPageEditorSettings");
	}
	
	/**
	 * Show general page editor settings
	 */
	function showGeneralPageEditorSettingsObject()
	{
		global $tpl, $ilTabs;

		$this->addPageEditorSettingsSubTabs();
		$ilTabs->activateTab("adve_page_editor_settings");
		
		$form = $this->initGeneralPageSettingsForm();
		$tpl->setContent($form->getHTML());
	}
	
	/**
	 * Init general page editor settings form.
	 */
	public function initGeneralPageSettingsForm()
	{
		global $lng, $ilCtrl;
	
		include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();
		
		$aset = new ilSetting("adve");

		// use physical character styles
		$cb = new ilCheckboxInputGUI($this->lng->txt("adve_use_physical"), "use_physical");
		$cb->setInfo($this->lng->txt("adve_use_physical_info"));
		$cb->setChecked($aset->get("use_physical"));
		$form->addItem($cb);
		
		$form->addCommandButton("saveGeneralPageSettings", $lng->txt("save"));
	                
		$form->setTitle($lng->txt("adve_pe_general"));
		$form->setFormAction($ilCtrl->getFormAction($this));
	 
		return $form;
	}
	
	/**
	 * Save general page settings
	 */
	function saveGeneralPageSettingsObject()
	{
		global $ilCtrl, $lng;
		
		$form = $this->initGeneralPageSettingsForm();
		if ($form->checkInput())
		{
			$aset = new ilSetting("adve");
			$aset->set("use_physical", $_POST["use_physical"]);
		}
		ilUtil::sendSuccess($lng->txt("msg_obj_modified"), true);
		$ilCtrl->redirect($this, "showGeneralPageEditorSettings");
	}
	
	/**
	* Add rte subtabs
	*/
	function addSubtabs(&$tabs_gui)
	{
		global $ilCtrl;

		if ($ilCtrl->getNextClass() != "ilpermissiongui" &&
			!in_array($ilCtrl->getCmd(), array("showPageEditorSettings",
				"showGeneralPageEditorSettings", "", "view")))
		{
			$tabs_gui->addSubTabTarget("adve_general_settings",
											 $this->ctrl->getLinkTarget($this, "settings"),
											 array("settings", "saveSettings"),
											 "", "");
			$tabs_gui->addSubTabTarget("adve_assessment_settings",
											 $this->ctrl->getLinkTarget($this, "assessment"),
											 array("assessment", "saveAssessmentSettings"),
											 "", "");
			$tabs_gui->addSubTabTarget("adve_survey_settings",
											 $this->ctrl->getLinkTarget($this, "survey"),
											 array("survey", "saveSurveySettings"),
											 "", "");
			/*$tabs_gui->addSubTabTarget("adve_lm_settings",
											 $this->ctrl->getLinkTarget($this, "learningModule"),
											 array("learningModule", "saveLearningModuleSettings"),
											 "", "");*/
			$tabs_gui->addSubTabTarget("adve_frm_post_settings",
											 $this->ctrl->getLinkTarget($this, "frmPost"),
											 array("frmPost", "saveFrmPostSettings"),
											 "", "");
		}
	}
	
	public function saveFrmPostSettingsObject()
	{
		ilUtil::sendSuccess($this->lng->txt('msg_obj_modified'), true);
		
		try
		{
			$this->object->_setUsedHTMLTags((array)$_POST['html_tags'], 'frm_post');	
		}
		catch(ilAdvancedEditingRequiredTagsException $e)
		{
			ilUtil::sendInfo($e->getMessage(), true);	
		}
		
		$this->ctrl->redirect($this,'frmPost');
	}
	
	public function frmPostObject()
	{
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.advanced_editing_frm_post.html",
			"Services/AdvancedEditing");
		
		$alltags =& $this->object->getHTMLTags();
		$usedtags =& $this->object->_getUsedHTMLTags("frm_post");
		foreach ($alltags as $tag)
		{
			$this->tpl->setCurrentBlock("html_tag_row");
			$this->tpl->setVariable("HTML_TAG", $tag);
			if (is_array($usedtags))
			{
				if (in_array($tag, $usedtags))
				{
					$this->tpl->setVariable("HTML_TAG_SELECTED", " selected=\"selected\"");
				}
			}
			$this->tpl->parseCurrentBlock();
		}
		
		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("FORMACTION", $this->ctrl->getFormAction($this));
		$this->tpl->setVariable("TXT_FRM_POST_SETTINGS", $this->lng->txt("advanced_editing_frm_post_settings"));
		$this->tpl->setVariable("TXT_ALLOW_HTML_TAGS", $this->lng->txt("advanced_editing_allow_html_tags"));
		$this->tpl->setVariable("TXT_SAVE", $this->lng->txt("save"));

		$this->tpl->parseCurrentBlock();
	}
	
	
	/**
	* Show page editor settings subtabs
	*/
	function addPageEditorSettingsSubtabs()
	{
		global $ilCtrl, $ilTabs;

		$ilTabs->addSubTabTarget("adve_pe_general",
			 $ilCtrl->getLinkTarget($this, "showGeneralPageEditorSettings"),
			 array("showGeneralPageEditorSettings", "", "view")); 
		
		include_once("./Services/COPage/classes/class.ilPageEditorSettings.php");
		$grps = ilPageEditorSettings::getGroups();
		
		foreach ($grps as $g => $types)
		{
			$ilCtrl->setParameter($this, "grp", $g);
			$ilTabs->addSubTabTarget("adve_grp_".$g,
				 $ilCtrl->getLinkTarget($this, "showPageEditorSettings"),
				 array("showPageEditorSettings")); 
		}
		$ilCtrl->setParameter($this, "grp", $_GET["grp"]);
	}

	
	/**
	* get tabs
	* @access	public
	* @param	object	tabs gui object
	*/
	function getTabs(&$tabs_gui)
	{
		global $rbacsystem;

		if ($rbacsystem->checkAccess("visible,read",$this->object->getRefId()))
		{
			$tabs_gui->addTarget("adve_page_editor_settings",
				$this->ctrl->getLinkTarget($this, "showGeneralPageEditorSettings"),
					array("showPageEditorSettings", "","view"));

			$tabs_gui->addTarget("adve_rte_settings",
				$this->ctrl->getLinkTarget($this, "settings"),
					array("settings","assessment", "survey", "learningModule",
					"frmPost"), "", "");
		}

		if ($rbacsystem->checkAccess('edit_permission',$this->object->getRefId()))
		{
			$tabs_gui->addTarget("perm_settings",
				$this->ctrl->getLinkTargetByClass(array(get_class($this),'ilpermissiongui'), "perm"), array("perm","info","owner"), 'ilpermissiongui');
		}
		$this->addSubtabs($tabs_gui);
	}
} // END class.ilObjAdvancedEditingGUI
?>
