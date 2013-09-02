<?php
 /*
   +----------------------------------------------------------------------------+
   | ILIAS open source                                                          |
   +----------------------------------------------------------------------------+
   | Copyright (c) 1998-2001 ILIAS open source, University of Cologne           |
   |                                                                            |
   | This program is free software; you can redistribute it and/or              |
   | modify it under the terms of the GNU General Public License                |
   | as published by the Free Software Foundation; either version 2             |
   | of the License, or (at your option) any later version.                     |
   |                                                                            |
   | This program is distributed in the hope that it will be useful,            |
   | but WITHOUT ANY WARRANTY; without even the implied warranty of             |
   | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the              |
   | GNU General Public License for more details.                               |
   |                                                                            |
   | You should have received a copy of the GNU General Public License          |
   | along with this program; if not, write to the Free Software                |
   | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA. |
   +----------------------------------------------------------------------------+
*/

include_once "./Modules/Survey/classes/inc.SurveyConstants.php";

/**
* Basic class for all survey question types
*
* The SurveyQuestionGUI class defines and encapsulates basic methods and attributes
* for survey question types to be used for all parent classes.
*
* @author		Helmut Schottmüller <helmut.schottmueller@mac.com>
* @version	$Id: class.SurveyQuestionGUI.php 31118 2011-10-13 12:45:48Z jluetzen $
* @ingroup ModulesSurveyQuestionPool
*/
class SurveyQuestionGUI 
{
/**
* Question object
*
* A reference to the metric question object
*
* @var object
*/
  var $object;
	var $tpl;
	var $lng;
	private $errormessages;

	/**
	* An array containing the cumulated results of the question for a given survey
	*/
	var $cumulated;
	
/**
* SurveyQuestion constructor
*
* The constructor takes possible arguments an creates an instance of the SurveyQuestion object.
*
* @param string $title A title string to describe the question
* @param string $description A description string to describe the question
* @param string $author A string containing the name of the questions author
* @param integer $owner A numerical ID to identify the owner/creator
* @access public
*/
	function SurveyQuestionGUI()
	{
		global $lng, $tpl, $ilCtrl;

		$this->lng =& $lng;
		$this->tpl =& $tpl;
		$this->ctrl =& $ilCtrl;
		$this->ctrl->saveParameter($this, "q_id");
		$this->ctrl->setParameterByClass($_GET["cmdClass"], "sel_question_types", $_GET["sel_question_types"]);
		$this->cumulated = array();
		$this->errormessages = array();
	}

	function addErrorMessage($errormessage)
	{
		if (strlen($errormessage)) array_push($this->errormessages, $errormessage);
	}
	
	function outErrorMessages()
	{
		if (count($this->errormessages))
		{
			$out = implode("<br />", $this->errormessages);
			ilUtil::sendInfo($out);
		}
	}

	/**
	* execute command
	*/
	function &executeCommand()
	{
		$cmd = $this->ctrl->getCmd();
		$next_class = $this->ctrl->getNextClass($this);

		$cmd = $this->getCommand($cmd);
		switch($next_class)
		{
			default:
				$ret =& $this->$cmd();
				break;
		}
		return $ret;
	}

	function getCommand($cmd)
	{
		return $cmd;
	}

	/**
	* Creates a question gui representation
	*
	* Creates a question gui representation and returns the alias to the question gui
	* note: please do not use $this inside this method to allow static calls
	*
	* @param string $question_type The question type as it is used in the language database
	* @param integer $question_id The database ID of an existing question to load it into ASS_QuestionGUI
	* @return object The alias to the question object
	* @access public
	*/
	static function &_getQuestionGUI($questiontype, $question_id = -1)
	{
		include_once "./Modules/SurveyQuestionPool/classes/class.SurveyQuestion.php";
		if ((!$questiontype) and ($question_id > 0))
		{
			$questiontype = SurveyQuestion::_getQuestiontype($question_id);
		}
		SurveyQuestion::_includeClass($questiontype, 1);
		$question_type_gui = $questiontype . "GUI";
		$question = new $question_type_gui($question_id);
		return $question;
	}
	
	function _getGUIClassNameForId($a_q_id)
	{
		include_once "./Modules/SurveyQuestionPool/classes/class.SurveyQuestion.php";
		include_once "./Modules/SurveyQuestionPool/classes/class.SurveyQuestionGUI.php";
		$q_type = SurveyQuestion::_getQuestiontype($a_q_id);
		$class_name = SurveyQuestionGUI::_getClassNameForQType($q_type);
		return $class_name;
	}

	function _getClassNameForQType($q_type)
	{
		return $q_type;
	}
	
	function originalSyncForm()
	{
		$this->ctrl->saveParameter($this, "rtrn");

		ilUtil::sendQuestion($this->lng->txt("confirm_sync_questions"));
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_svy_qpl_sync_original.html", "Modules/SurveyQuestionPool");
		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("BUTTON_YES", $this->lng->txt("yes"));
		$this->tpl->setVariable("BUTTON_NO", $this->lng->txt("no"));
		$this->tpl->setVariable("FORM_ACTION", $this->ctrl->getFormAction($this));
		$this->tpl->parseCurrentBlock();
	}
	
	function sync()
	{
		$original_id = $this->object->original_id;
		if ($original_id)
		{
			$this->object->syncWithOriginal();
		}

		ilUtil::sendSuccess($this->lng->txt("msg_obj_modified"), true);
		$this->redirectAfterSaving($_REQUEST["rtrn"]);
	}

	function cancelSync()
	{
		ilUtil::sendInfo($this->lng->txt("question_changed_in_survey_only"), true);
		$this->redirectAfterSaving($_REQUEST["rtrn"]);
	}

	/**
	 * Redirect to calling survey or to edit form
	 *
	 * @param bool $a_return
	 */
	function redirectAfterSaving($a_return = false)
	{
		// return?
		if($a_return)
		{
			// to calling survey
			if($_GET["calling_survey"] || $_GET["new_for_survey"])
			{			
				$addurl = "";
				if ($_REQUEST["pgov"])
				{
					$addurl .= "&pgov=".$_REQUEST["pgov"];
					$addurl .= "&pgov_pos=".$_REQUEST["pgov_pos"];
				}

				// edit
				if($_GET["calling_survey"])
				{
					$_GET["ref_id"] = $_GET["calling_survey"];
				}
				// create
				else if($_GET["new_for_survey"])
				{
					$_GET["ref_id"] = $_GET["new_for_survey"];
					$addurl .= "&new_id=".$this->object->getId();
				}

				// we cannot use ilctrl here as pool has no "knowledge" of calling survey
				include_once "./Services/Utilities/classes/class.ilUtil.php";
				ilUtil::redirect("ilias.php?baseClass=ilObjSurveyGUI&ref_id=" . $_GET["ref_id"] . "&cmd=questions".$addurl);
			}
			// to pool
			else
			{
				$this->ctrl->setParameterByClass("ilObjSurveyQuestionPoolGUI", "q_id_table_nav", $_SESSION['q_id_table_nav']);
				$this->ctrl->redirectByClass("ilObjSurveyQuestionPoolGUI", "questions");
			}
		}
		// stay in form
		else
		{

			$this->ctrl->setParameterByClass($_GET["cmdClass"], "q_id", $this->object->getId());
			$this->ctrl->setParameterByClass($_GET["cmdClass"], "sel_question_types", $_GET["sel_question_types"]);
			$this->ctrl->setParameterByClass($_GET["cmdClass"], "new_for_survey", $_GET["new_for_survey"]);
			$this->ctrl->redirectByClass($_GET["cmdClass"], "editQuestion");
		}
	}
	
	protected function addCommandButtons($a_form)
	{
		$a_form->addCommandButton("saveReturn", $this->lng->txt("save_return"));
		$a_form->addCommandButton("save", $this->lng->txt("save"));
		
		// pool question?
		if(ilObject::_lookupType($this->object->getObjId()) == "spl")
		{
			if($this->object->hasCopies())
			{				
				$a_form->addCommandButton("saveSync", $this->lng->txt("svy_save_sync"));
			}
		}		
	}

	/**
	 * save question and return to calling survey
	 */
	function saveSync()
	{
		$this->save($_REQUEST["rtrn"], true);
	}
	
	/**
	 * save question and return to calling survey
	 */
	function saveReturn()
	{
		$this->save(true);
	}

	/**
	* save question
	*/
	function save($a_return = false, $a_sync = false)
	{
		global $ilUser;

		$old_id = $_GET["q_id"];
		$result = $this->writePostData();
		if ($result == 0)
		{
			$ilUser->setPref("svy_lastquestiontype", $this->object->getQuestionType());
			$ilUser->writePref("svy_lastquestiontype", $this->object->getQuestionType());
			$this->object->saveToDb();
			$originalexists = $this->object->_questionExists($this->object->original_id);
			$this->ctrl->setParameter($this, "q_id", $this->object->getId());
			include_once "./Modules/SurveyQuestionPool/classes/class.SurveyQuestion.php";
			
			// pool question?
			if($a_sync)
			{				
				ilUtil::sendSuccess($this->lng->txt("msg_obj_modified"), true);
				$this->ctrl->redirect($this, 'copySyncForm');
			}			
			else
			{
				// form: update original pool question, too?
				if ($_GET["calling_survey"] && $originalexists &&
					SurveyQuestion::_isWriteable($this->object->original_id, $ilUser->getId()))
				{
					if($a_return)
					{
						$this->ctrl->setParameter($this, 'rtrn', 1);
					}
					$this->ctrl->redirect($this, 'originalSyncForm');
				}
			}
			
			ilUtil::sendSuccess($this->lng->txt("msg_obj_modified"), true);
			$this->redirectAfterSaving($a_return);
		}
	}
	
	function cancel()
	{
		if ($_GET["calling_survey"])
		{
			$_GET["ref_id"] = $_GET["calling_survey"];
			include_once "./Services/Utilities/classes/class.ilUtil.php";
			ilUtil::redirect("ilias.php?baseClass=ilObjSurveyGUI&cmd=questions&ref_id=".$_GET["calling_survey"]);
		}
		elseif ($_GET["new_for_survey"])
		{
			$_GET["ref_id"] = $_GET["new_for_survey"];
			include_once "./Services/Utilities/classes/class.ilUtil.php";
			ilUtil::redirect("ilias.php?baseClass=ilObjSurveyGUI&cmd=questions&ref_id=".$_GET["new_for_survey"]);
		}
		else
		{
			$this->ctrl->redirectByClass("ilobjsurveyquestionpoolgui", "questions");
		}
	}

/**
* Cancels the form adding a phrase
*
* @access public
*/
	function cancelDeleteCategory() 
	{
		$this->ctrl->redirect($this, "editQuestion");
	}
	
	/**
	* Creates the HTML output of the question material(s)
	*/
	protected function getMaterialOutput()
	{
		if (count($this->object->getMaterial()))
		{
			$template = new ilTemplate("tpl.il_svy_qpl_material.html", TRUE, TRUE, "Modules/SurveyQuestionPool");
			foreach ($this->object->getMaterial() as $material)
			{
				$template->setCurrentBlock('material');
				switch ($material->type)
				{
					case 0:
						$href = SurveyQuestion::_getInternalLinkHref($material->internal_link);
						$template->setVariable('MATERIAL_TYPE', 'internallink');
						$template->setVariable('MATERIAL_HREF', $href);
						break;
				}
				$template->setVariable('MATERIAL_TITLE', (strlen($material->title)) ? ilUtil::prepareFormOutput($material->title) : $this->lng->txt('material'));
				$template->setVariable('TEXT_AVAILABLE_MATERIALS', $this->lng->txt('material'));
				$template->parseCurrentBlock();
			}
			return $template->get();
		}
		return "";
	}
	

	/**
	* Material tab of the survey questions
	*/
	public function material($checkonly = FALSE)
	{
		global $rbacsystem;

		$add_html = '';
		if ($rbacsystem->checkAccess('write', $_GET['ref_id']))
		{
			include_once("./Services/Form/classes/class.ilPropertyFormGUI.php");
			$form = new ilPropertyFormGUI();
			$form->setFormAction($this->ctrl->getFormAction($this));
			$form->setTitle($this->lng->txt('add_material'));
			$form->setMultipart(FALSE);
			$form->setTableWidth("100%");
			$form->setId("material");

			// material
			$material = new ilRadioGroupInputGUI($this->lng->txt("material"), "internalLinkType");
			$material->setRequired(true);
			$material->addOption(new ilRadioOption($this->lng->txt('obj_lm'), "lm"));
			$material->addOption(new ilRadioOption($this->lng->txt('obj_st'), "st"));
			$material->addOption(new ilRadioOption($this->lng->txt('obj_pg'), "pg"));
			$material->addOption(new ilRadioOption($this->lng->txt('glossary_term'), "glo"));
			$form->addItem($material);

			$form->addCommandButton("addMaterial", $this->lng->txt("add"));

			$errors = false;

			if ($checkonly)
			{
				$form->setValuesByPost();
				$errors = !$form->checkInput();
				if ($errors) $checkonly = false;
			}
			$add_html = $form->getHTML();
		}


		$mat_html = "";
		if (count($this->object->getMaterial()))
		{
			include_once "./Modules/SurveyQuestionPool/classes/tables/class.ilSurveyMaterialsTableGUI.php";
			$table_gui = new ilSurveyMaterialsTableGUI($this, 'material', (($rbacsystem->checkAccess('write', $_GET['ref_id']) ? true : false)));
			$data = array();
			foreach ($this->object->getMaterial() as $material)
			{
				switch ($material->type)
				{
					case 0:
						$href = SurveyQuestion::_getInternalLinkHref($material->internal_link);
						$type = $this->lng->txt('internal_link');
						break;
				}
				$title = (strlen($material->title)) ? ilUtil::prepareFormOutput($material->title) : $this->lng->txt('material');
				array_push($data, array('href' => $href, 'title' => $title, 'type' => $type));
			}
			$table_gui->setData($data);
			$mat_html = $table_gui->getHTML();
		}

		if (!$checkonly) $this->tpl->setVariable("ADM_CONTENT", $add_html . $mat_html);
		return $errors;
	}
	
	public function deleteMaterial()
	{
		if (is_array($_POST['idx']))
		{
			$this->object->deleteMaterials($_POST['idx']);
			ilUtil::sendSuccess($this->lng->txt('materials_deleted'), true);
		}
		else
		{
			ilUtil::sendFailure($this->lng->txt('no_checkbox'), true);
		}
		$this->ctrl->redirect($this, 'material');
	}

	/**
	* Add materials to a question
	*/
	public function addMaterial()
	{
		global $tree;
		
		if (strlen($_SESSION["link_new_type"]) || !$this->material(true))
		{
			include_once("./Modules/SurveyQuestionPool/classes/class.ilMaterialExplorer.php");
			switch ($_POST["internalLinkType"])
			{
				case "lm":
					$_SESSION["link_new_type"] = "lm";
					$_SESSION["search_link_type"] = "lm";
					break;
				case "glo":
					$_SESSION["link_new_type"] = "glo";
					$_SESSION["search_link_type"] = "glo";
					break;
				case "st":
					$_SESSION["link_new_type"] = "lm";
					$_SESSION["search_link_type"] = "st";
					break;
				case "pg":
					$_SESSION["link_new_type"] = "lm";
					$_SESSION["search_link_type"] = "pg";
					break;
			}

			ilUtil::sendInfo($this->lng->txt("select_object_to_link"));

			$exp = new ilMaterialExplorer($this->ctrl->getLinkTarget($this, 'addMaterial'), get_class($this));

			// expand current path (if no specific node given)
			if(!$_GET["expand"])
			{
				$path = $tree->getPathId($_GET["ref_id"]);
				$exp->setForceOpenPath($path);
			}
			else
			{
				$exp->setExpand($_GET["expand"]);
			}
			$exp->setExpandTarget($this->ctrl->getLinkTarget($this,'addMaterial'));
			$exp->setTargetGet("ref_id");
			$exp->setRefId($_GET["ref_id"]);
			$exp->addFilter($_SESSION["link_new_type"]);
			$exp->setSelectableType($_SESSION["link_new_type"]);

			// build html-output
			$exp->setOutput(0);

			$this->tpl->addBlockFile("ADM_CONTENT", "explorer", "tpl.il_svy_qpl_explorer.html", "Modules/SurveyQuestionPool");
			$this->tpl->setVariable("EXPLORER_TREE",$exp->getOutput());
			$this->tpl->setVariable("BUTTON_CANCEL",$this->lng->txt("cancel"));
			$this->tpl->setVariable("FORMACTION",$this->ctrl->getFormAction($this));
		}
	}
	
	function removeMaterial()
	{
		$this->object->material = array();
		$this->object->saveToDb();
		$this->editQuestion();
	}
	
	function cancelExplorer()
	{
		unset($_SESSION["link_new_type"]);
		ilUtil::sendInfo($this->lng->txt("msg_cancel"), true);
		$this->ctrl->redirect($this, 'material');
	}
		
	function addPG()
	{
		$this->object->addInternalLink("il__pg_" . $_GET["pg"]);
		unset($_SESSION["link_new_type"]);
		unset($_SESSION["search_link_type"]);
		ilUtil::sendSuccess($this->lng->txt("material_added_successfully"), true);
		$this->ctrl->redirect($this, "material");
	}
	
	function addST()
	{
		$this->object->addInternalLink("il__st_" . $_GET["st"]);
		unset($_SESSION["link_new_type"]);
		unset($_SESSION["search_link_type"]);
		ilUtil::sendSuccess($this->lng->txt("material_added_successfully"), true);
		$this->ctrl->redirect($this, "material");
	}

	function addGIT()
	{
		$this->object->addInternalLink("il__git_" . $_GET["git"]);
		unset($_SESSION["link_new_type"]);
		unset($_SESSION["search_link_type"]);
		ilUtil::sendSuccess($this->lng->txt("material_added_successfully"), true);
		$this->ctrl->redirect($this, "material");
	}
	
	function linkChilds()
	{
		switch ($_SESSION["search_link_type"])
		{
			case "pg":
				include_once "./Modules/LearningModule/classes/class.ilLMPageObject.php";
				include_once("./Modules/LearningModule/classes/class.ilObjContentObjectGUI.php");
				$cont_obj_gui =& new ilObjContentObjectGUI("", $_GET["source_id"], true);
				$cont_obj = $cont_obj_gui->object;
				$pages = ilLMPageObject::getPageList($cont_obj->getId());
				$this->ctrl->setParameter($this, "q_id", $this->object->getId());
				$color_class = array("tblrow1", "tblrow2");
				$counter = 0;
				$this->tpl->addBlockFile("ADM_CONTENT", "link_selection", "tpl.il_svy_qpl_internallink_selection.html", "Modules/SurveyQuestionPool");
				foreach($pages as $page)
				{
					if($page["type"] == $_SESSION["search_link_type"])
					{
						$this->tpl->setCurrentBlock("linktable_row");
						$this->tpl->setVariable("TEXT_LINK", $page["title"]);
						$this->tpl->setVariable("TEXT_ADD", $this->lng->txt("add"));
						$this->tpl->setVariable("LINK_HREF", $this->ctrl->getLinkTargetByClass(get_class($this), "add" . strtoupper($page["type"])) . "&" . $page["type"] . "=" . $page["obj_id"]);
						$this->tpl->setVariable("COLOR_CLASS", $color_class[$counter % 2]);
						$this->tpl->parseCurrentBlock();
						$counter++;
					}
				}
				$this->tpl->setCurrentBlock("link_selection");
				$this->tpl->setVariable("BUTTON_CANCEL",$this->lng->txt("cancel"));
				$this->tpl->setVariable("TEXT_LINK_TYPE", $this->lng->txt("obj_" . $_SESSION["search_link_type"]));
				$this->tpl->setVariable("FORMACTION",$this->ctrl->getFormAction($this));
				$this->tpl->parseCurrentBlock();
				break;
			case "st":
				$this->ctrl->setParameter($this, "q_id", $this->object->getId());
				$color_class = array("tblrow1", "tblrow2");
				$counter = 0;
				include_once("./Modules/LearningModule/classes/class.ilObjContentObjectGUI.php");
				$cont_obj_gui =& new ilObjContentObjectGUI("", $_GET["source_id"], true);
				$cont_obj = $cont_obj_gui->object;
				// get all chapters
				$ctree =& $cont_obj->getLMTree();
				$nodes = $ctree->getSubtree($ctree->getNodeData($ctree->getRootId()));
				$this->tpl->addBlockFile("ADM_CONTENT", "link_selection", "tpl.il_svy_qpl_internallink_selection.html", "Modules/SurveyQuestionPool");
				foreach($nodes as $node)
				{
					if($node["type"] == $_SESSION["search_link_type"])
					{
						$this->tpl->setCurrentBlock("linktable_row");
						$this->tpl->setVariable("TEXT_LINK", $node["title"]);
						$this->tpl->setVariable("TEXT_ADD", $this->lng->txt("add"));
						$this->tpl->setVariable("LINK_HREF", $this->ctrl->getLinkTargetByClass(get_class($this), "add" . strtoupper($node["type"])) . "&" . $node["type"] . "=" . $node["obj_id"]);
						$this->tpl->setVariable("COLOR_CLASS", $color_class[$counter % 2]);
						$this->tpl->parseCurrentBlock();
						$counter++;
					}
				}
				$this->tpl->setCurrentBlock("link_selection");
				$this->tpl->setVariable("BUTTON_CANCEL",$this->lng->txt("cancel"));
				$this->tpl->setVariable("TEXT_LINK_TYPE", $this->lng->txt("obj_" . $_SESSION["search_link_type"]));
				$this->tpl->setVariable("FORMACTION",$this->ctrl->getFormAction($this));
				$this->tpl->parseCurrentBlock();
				break;
			case "glo":
				$this->ctrl->setParameter($this, "q_id", $this->object->getId());
				$color_class = array("tblrow1", "tblrow2");
				$counter = 0;
				$this->tpl->addBlockFile("ADM_CONTENT", "link_selection", "tpl.il_svy_qpl_internallink_selection.html", "Modules/SurveyQuestionPool");
				include_once "./Modules/Glossary/classes/class.ilObjGlossary.php";
				$glossary =& new ilObjGlossary($_GET["source_id"], true);
				// get all glossary items
				$terms = $glossary->getTermList();
				foreach($terms as $term)
				{
					$this->tpl->setCurrentBlock("linktable_row");
					$this->tpl->setVariable("TEXT_LINK", $term["term"]);
					$this->tpl->setVariable("TEXT_ADD", $this->lng->txt("add"));
					$this->tpl->setVariable("LINK_HREF", $this->ctrl->getLinkTargetByClass(get_class($this), "addGIT") . "&git=" . $term["id"]);
					$this->tpl->setVariable("COLOR_CLASS", $color_class[$counter % 2]);
					$this->tpl->parseCurrentBlock();
					$counter++;
				}
				$this->tpl->setCurrentBlock("link_selection");
				$this->tpl->setVariable("BUTTON_CANCEL",$this->lng->txt("cancel"));
				$this->tpl->setVariable("TEXT_LINK_TYPE", $this->lng->txt("glossary_term"));
				$this->tpl->setVariable("FORMACTION",$this->ctrl->getFormAction($this));
				$this->tpl->parseCurrentBlock();
				break;
			case "lm":
				$this->object->addInternalLink("il__lm_" . $_GET["source_id"]);
				unset($_SESSION["link_new_type"]);
				unset($_SESSION["search_link_type"]);
				ilUtil::sendSuccess($this->lng->txt("material_added_successfully"), true);
				$this->ctrl->redirect($this, "material");
				break;
		}
	}

	/**
	* Creates a HTML representation of the question
	*
	* @access private
	*/
	function getPrintView($question_title = 1, $show_questiontext = 1)
	{
		return "";
	}
	
	function setQuestionTabsForClass($guiclass)
	{
		global $rbacsystem,$ilTabs;
		$this->ctrl->setParameterByClass("$guiclass", "sel_question_types", $this->getQuestionType());
		$this->ctrl->setParameterByClass("$guiclass", "q_id", $_GET["q_id"]);

		if (($_GET["calling_survey"] > 0) || ($_GET["new_for_survey"] > 0))
		{
			$ref_id = $_GET["calling_survey"];
			if (!strlen($ref_id)) $ref_id = $_GET["new_for_survey"];
			$addurl = "";
			if (strlen($_GET["new_for_survey"]))
			{
				$addurl = "&new_id=" . $_GET["q_id"];
			}
			if ($_REQUEST["pgov"])
			{
				$addurl .= "&pgov=".$_REQUEST["pgov"];
				$addurl .= "&pgov_pos=".$_REQUEST["pgov_pos"];
			}
			$ilTabs->setBackTarget($this->lng->txt("menubacktosurvey"), "ilias.php?baseClass=ilObjSurveyGUI&ref_id=$ref_id&cmd=questions" . $addurl);
		}
		else
		{
			$this->ctrl->setParameterByClass("ilObjSurveyQuestionPoolGUI", "q_id_table_nav", $_SESSION['q_id_table_nav']);
			$ilTabs->setBackTarget($this->lng->txt("spl"), $this->ctrl->getLinkTargetByClass("ilObjSurveyQuestionPoolGUI", "questions"));
		}
		if ($_GET["q_id"])
		{
			$ilTabs->addTarget("preview",
									 $this->ctrl->getLinkTargetByClass("$guiclass", "preview"), "preview",
									 "$guiclass");
		}
		if ($rbacsystem->checkAccess('edit', $_GET["ref_id"])) {
			$ilTabs->addTarget("edit_properties",
									 $this->ctrl->getLinkTargetByClass("$guiclass", "editQuestion"), 
									 array("editQuestion", "save", "cancel", "originalSyncForm"),
									 "$guiclass");
		}
		if ($_GET["q_id"])
		{
			$ilTabs->addTarget("material",
									 $this->ctrl->getLinkTargetByClass("$guiclass", "material"), 
									array("material", "cancelExplorer", "linkChilds", "addGIT", "addST",
											 "addPG", "addMaterial", "removeMaterial"),
									 "$guiclass");
		}

		if ($this->object->getId() > 0) 
		{
			$title = $this->lng->txt("edit") . " &quot;" . $this->object->getTitle() . "&quot";
		} 
		else 
		{
			$title = $this->lng->txt("create_new") . " " . $this->lng->txt($this->getQuestionType());
		}

		$this->tpl->setVariable("HEADER", $title);
	}

/**
* Returns the question type string
*
* @result string The question type string
* @access public
*/
	function getQuestionType()
	{
		return $this->object->getQuestionType();
	}

/**
* Creates a the cumulated results row for the question
*
* @return string HTML text with the cumulated results
* @access private
*/
	function getCumulatedResultRow($counter, $css_class, $survey_id)
	{
		// overwrite in parent classes
		return "";
	}
	
	function editQuestion()
	{
		$this->outErrorMessages();
	}
	
	protected function outQuestionText($template)
	{
		$questiontext = $this->object->getQuestiontext();
		if (preg_match("/^<.[\\>]?>(.*?)<\\/.[\\>]*?>$/", $questiontext, $matches))
		{
			$questiontext = $matches[1];
		}
		$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($questiontext, TRUE));
		if ($this->object->getObligatory($survey_id))
		{
			$template->setVariable("OBLIGATORY_TEXT", ' *');
		}
	}

	function isSaveCommand(array $a_cmds = array())
	{
		$a_cmds[] = "save";
		$a_cmds[] = "saveReturn";
	    return in_array($this->ctrl->getCmd(), $a_cmds);
	}
	
	/**
	* Creates a preview of the question
	*
	* @access private
	*/
	function preview()
	{
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_svy_qpl_preview.html", "Modules/SurveyQuestionPool");
		$question_output = $this->getWorkingForm();
		
		if ($this->object->getObligatory())
		{
			$this->tpl->setCurrentBlock("required");
			$this->tpl->setVariable("TEXT_REQUIRED", $this->lng->txt("required_field"));
			$this->tpl->parseCurrentBlock();
		}
		
		$this->tpl->setVariable("QUESTION_OUTPUT", $question_output);
	}
	
	protected function renderChart($a_id, $a_variables)
	{
		include_once "Services/Chart/classes/class.ilChart.php";
		$chart = new ilChart($a_id, 700, 400);

		$legend = new ilChartLegend();
		$chart->setLegend($legend);	
		$chart->setYAxisToInteger(true);
		
		$data = new ilChartData("bars");
		$data->setLabel($this->lng->txt("users_answered"));
		$data->setBarOptions(0.5, "center");
		
		$max = 5;
		
		if(sizeof($a_variables) <= $max)
		{
			if($a_variables)
			{
				$labels = array();
				foreach($a_variables as $idx => $points)
				{			
					$data->addPoint($idx, $points["selected"]);		
					$labels[$idx] = ($idx+1).". ".ilUtil::prepareFormOutput($points["title"]);
				}
				$chart->addData($data);

				$chart->setTicks($labels, false, true);
			}

			return "<div style=\"margin:10px\">".$chart->getHTML()."</div>";		
		}
		else
		{
			$chart_legend = array();			
			$labels = array();
			foreach($a_variables as $idx => $points)
			{			
				$data->addPoint($idx, $points["selected"]);		
				$labels[$idx] = ($idx+1).".";				
				$chart_legend[($idx+1)] = ilUtil::prepareFormOutput($points["title"]);
			}
			$chart->addData($data);
						
			$chart->setTicks($labels, false, true);
			
			$legend = "<table>";
			foreach($chart_legend as $number => $caption)
			{
				$legend .= "<tr valign=\"top\"><td>".$number.".</td><td>".$caption."</td></tr>";
			}
			$legend .= "</table>";

			return "<div style=\"margin:10px\"><table><tr valign=\"bottom\"><td>".
				$chart->getHTML()."</td><td class=\"small\" style=\"padding-left:15px\">".
				$legend."</td></tr></table></div>";					
		}				
	}
	
	protected function copySyncForm()
	{
		include_once "Modules/SurveyQuestionPool/classes/class.ilSurveySyncTableGUI.php";
		$tbl = new ilSurveySyncTableGUI($this, "copySyncForm", $this->object);
		
		$this->tpl->setContent($tbl->getHTML());		
	}
	
	protected function syncCopies()
	{
		global $lng, $ilAccess;
		
		if(!sizeof($_POST["qid"]))
		{
			ilUtil::sendFailure($lng->txt("select_one"));
			return $this->copySyncForm();
		}
		
		foreach($this->object->getCopyIds(true) as $survey_id => $questions)
		{
			// check permissions for "parent" survey
			$can_write = false;
			$ref_ids = ilObject::_getAllReferences($survey_id);
			foreach($ref_ids as $ref_id)
			{
				if($ilAccess->checkAccess("edit", "", $ref_id))
				{
					$can_write = true;
					break;
				}
			}
			
			if($can_write)
			{
				foreach($questions as $qid)
				{
					if(in_array($qid, $_POST["qid"]))
					{
						$id = $this->object->getId();
						
						$this->object->setId($qid);
						$this->object->setOriginalId($id);
						$this->object->saveToDb();
						
						$this->object->setId($id);
						$this->object->setOriginalId(null);
						
						// see: SurveyQuestion::syncWithOriginal()
						// what about material?												
					}
				}												
			}						
		}
		
		ilUtil::sendSuccess($lng->txt("survey_sync_success"), true);
		$this->redirectAfterSaving($_REQUEST["rtrn"]);
	}
}

?>