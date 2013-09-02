<?php
/*
	+-----------------------------------------------------------------------------+
	| ILIAS open source                                                           |
	+-----------------------------------------------------------------------------+
	| Copyright (c) 1998-2001 ILIAS open source, University of Cologne            |
	|                                                                             |
	| This program is free software; you can redistribute it and/or               |
	| modify it under the terms of the GNU General Public License                 |
	| as published by the Free Software Foundation; either version 2              |
	| of the License, or (at your option) any later version.                      |
	|                                                                             |
	| This program is distributed in the hope that it will be useful,             |
	| but WITHOUT ANY WARRANTY; without even the implied warranty of              |
	| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               |
	| GNU General Public License for more details.                                |
	|                                                                             |
	| You should have received a copy of the GNU General Public License           |
	| along with this program; if not, write to the Free Software                 |
	| Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA. |
	+-----------------------------------------------------------------------------+
*/

include_once "./Services/Object/classes/class.ilObjectGUI.php";
include_once "./Modules/TestQuestionPool/classes/class.assQuestionGUI.php";
include_once "./Modules/TestQuestionPool/classes/class.ilObjQuestionPool.php";
include_once "./Modules/Test/classes/inc.AssessmentConstants.php";
include_once "./Modules/Test/classes/class.ilObjTest.php";

/**
 * Class ilObjQuestionPoolGUI
 *
 * @author		Helmut Schottmüller <helmut.schottmueller@mac.com>
 * @author		Björn Heyser <bheyser@databay.de>
 * 
 * @version		$Id: class.ilObjQuestionPoolGUI.php 40273 2013-03-02 17:52:41Z bheyser $
 *
 * @ilCtrl_Calls ilObjQuestionPoolGUI: ilPageObjectGUI
 * @ilCtrl_Calls ilObjQuestionPoolGUI: assMultipleChoiceGUI, assClozeTestGUI, assMatchingQuestionGUI
 * @ilCtrl_Calls ilObjQuestionPoolGUI: assOrderingQuestionGUI, assImagemapQuestionGUI, assJavaAppletGUI
 * @ilCtrl_Calls ilObjQuestionPoolGUI: assNumericGUI
 * @ilCtrl_Calls ilObjQuestionPoolGUI: assTextSubsetGUI
 * @ilCtrl_Calls ilObjQuestionPoolGUI: assSingleChoiceGUI
 * @ilCtrl_Calls ilObjQuestionPoolGUI: assTextQuestionGUI, ilMDEditorGUI, ilPermissionGUI, ilObjectCopyGUI
 * @ilCtrl_Calls ilObjQuestionPoolGUI: ilExportGUI, ilInfoScreenGUI
 * @ilCtrl_Calls ilObjQuestionPoolGUI: ilAssQuestionHintsGUI, ilCommonActionDispatcherGUI
 *
 * @extends ilObjectGUI
 * @ingroup ModulesTestQuestionPool
 */
class ilObjQuestionPoolGUI extends ilObjectGUI
{
	/**
	 * @var ilObjQuestionPool
	 */
	public $object;
	
	/**
	* Constructor
	* @access public
	*/
	function ilObjQuestionPoolGUI()
	{
		global $lng, $ilCtrl, $rbacsystem;
		$lng->loadLanguageModule("assessment");
		$this->type = "qpl";
		$this->ctrl =& $ilCtrl;
		$this->ctrl->saveParameter($this, array("ref_id", "test_ref_id", "calling_test", "test_express_mode", "q_id"));

		$this->ilObjectGUI("",$_GET["ref_id"], true, false);
	}

	/**
	 * execute command
	 *
	 * @global	ilLocatorGUI		$ilLocator
	 * @global	ilAccessHandler		$ilAccess
	 * @global	ilNavigationHistory	$ilNavigationHistory
	 * @global	ilTemplate			$tpl
	 * @global	ilCtrl				$ilCtrl
	 * @global	ILIAS				$ilias 
	 */
	function executeCommand()
	{
		global $ilLocator, $ilAccess, $ilNavigationHistory, $tpl, $ilCtrl, $ilErr;
		
		if ((!$ilAccess->checkAccess("read", "", $_GET["ref_id"])) && (!$ilAccess->checkAccess("visible", "", $_GET["ref_id"])))
		{
			global $ilias;
			$ilias->raiseError($this->lng->txt("permission_denied"), $ilias->error_obj->MESSAGE);
		}
		
		// add entry to navigation history
		if (!$this->getCreationMode() &&
			$ilAccess->checkAccess("read", "", $_GET["ref_id"]))
		{
			$ilNavigationHistory->addItem($_GET["ref_id"],
				"ilias.php?baseClass=ilObjQuestionPoolGUI&cmd=questions&ref_id=".$_GET["ref_id"], "qpl");
		}
		
		$cmd = $this->ctrl->getCmd("questions");
		$next_class = $this->ctrl->getNextClass($this);
		
		if( in_array($next_class, array('', 'ilobjquestionpoolgui')) && $cmd == 'questions' )
		{
			$_GET['q_id'] = '';
		}
				
		$this->prepareOutput();
		
		$this->ctrl->setReturn($this, "questions");
		
		$this->tpl->addCss(ilUtil::getStyleSheetLocation("output", "test_print.css", "Modules/Test"), "print");
		$this->tpl->addCss(ilUtil::getStyleSheetLocation("output", "ta.css", "Modules/Test"), "screen");
		
		if ($_GET["q_id"] < 1)
		{
			$q_type = ($_POST["sel_question_types"] != "")
				? $_POST["sel_question_types"]
				: $_GET["sel_question_types"];
		}
		if ($cmd != "createQuestion" && $cmd != "createQuestionForTest"
			&& $next_class != "ilpageobjectgui")
		{
			if (($_GET["test_ref_id"] != "") or ($_GET["calling_test"]))
			{
				$ref_id = $_GET["test_ref_id"];
				if (!$ref_id)
				{
					$ref_id = $_GET["calling_test"];
				}
			}
		}
		switch($next_class)
		{
			case 'ilmdeditorgui':
				if(!$ilAccess->checkAccess('write','',$this->object->getRefId()))
				{
					$ilErr->raiseError($this->lng->txt('permission_denied'),$ilErr->WARNING);
				}
				
				include_once 'Services/MetaData/classes/class.ilMDEditorGUI.php';

				$md_gui = new ilMDEditorGUI($this->object->getId(), 0, $this->object->getType());
				$md_gui->addObserver($this->object,'MDUpdateListener','General');
				$this->ctrl->forwardCommand($md_gui);
				break;
			case "ilpageobjectgui":
				include_once("./Services/Style/classes/class.ilObjStyleSheet.php");
				$this->tpl->setCurrentBlock("ContentStyle");
				$this->tpl->setVariable("LOCATION_CONTENT_STYLESHEET",
					ilObjStyleSheet::getContentStylePath(0));
				$this->tpl->parseCurrentBlock();
		
				// syntax style
				$this->tpl->setCurrentBlock("SyntaxStyle");
				$this->tpl->setVariable("LOCATION_SYNTAX_STYLESHEET",
					ilObjStyleSheet::getSyntaxStylePath());
				$this->tpl->parseCurrentBlock();
				include_once "./Modules/TestQuestionPool/classes/class.assQuestionGUI.php";
				$q_gui = assQuestionGUI::_getQuestionGUI("", $_GET["q_id"]);
				$q_gui->setQuestionTabs();
				$q_gui->outAdditionalOutput();
				$q_gui->object->setObjId($this->object->getId());
				$question = $q_gui->object;
				$this->ctrl->saveParameter($this, "q_id");
				include_once("./Services/COPage/classes/class.ilPageObject.php");
				include_once("./Services/COPage/classes/class.ilPageObjectGUI.php");
				$this->lng->loadLanguageModule("content");
				$this->ctrl->setReturnByClass("ilPageObjectGUI", "view");
				$this->ctrl->setReturn($this, "questions");
				//$page =& new ilPageObject("qpl", $_GET["q_id"]);
				$page_gui = new ilPageObjectGUI("qpl", $_GET["q_id"]);
				$page_gui->setEditPreview(true);
				$page_gui->setEnabledTabs(false);
				$page_gui->setEnabledInternalLinks(false);
				if (strlen($this->ctrl->getCmd()) == 0 && !isset($_POST["editImagemapForward_x"])) // workaround for page edit imagemaps, keep in mind
				{
					$this->ctrl->setCmdClass(get_class($page_gui));
					$this->ctrl->setCmd("preview");
				}
				//$page_gui->setQuestionXML($question->toXML(false, false, true));
				$page_gui->setQuestionHTML(array($q_gui->object->getId() => $q_gui->getPreview(TRUE)));
				$page_gui->setTemplateTargetVar("ADM_CONTENT");
				$page_gui->setOutputMode("edit");
				$page_gui->setHeader($question->getTitle());
				$page_gui->setFileDownloadLink($this->ctrl->getLinkTarget($this, "downloadFile"));
				$page_gui->setFullscreenLink($this->ctrl->getLinkTarget($this, "fullscreen"));
				$page_gui->setSourcecodeDownloadScript($this->ctrl->getLinkTarget($this));
				$page_gui->setPresentationTitle($question->getTitle());
				$ret = $this->ctrl->forwardCommand($page_gui);
				$tpl->setContent($ret);
				break;
				
			case 'ilpermissiongui':
				include_once("Services/AccessControl/classes/class.ilPermissionGUI.php");
				$perm_gui = new ilPermissionGUI($this);
				$ret = $this->ctrl->forwardCommand($perm_gui);
				break;
				
			case 'ilobjectcopygui':
				include_once './Services/Object/classes/class.ilObjectCopyGUI.php';
				$cp = new ilObjectCopyGUI($this);
				$cp->setType('qpl');
				$this->ctrl->forwardCommand($cp);
				break;
				
			case "ilexportgui":
				include_once("./Services/Export/classes/class.ilExportGUI.php");
				$exp_gui = new ilExportGUI($this);
				$exp_gui->addFormat("zip", $this->lng->txt('qpl_export_xml'), $this, "createExportQTI");
				$exp_gui->addFormat("xls", $this->lng->txt('qpl_export_excel'), $this, "createExportExcel");
	//			$exp_gui->addCustomColumn($lng->txt("cont_public_access"), $this, "getPublicAccessColValue");
	//			$exp_gui->addCustomMultiCommand($lng->txt("cont_public_access"), $this, "publishExportFile");
				$ret = $this->ctrl->forwardCommand($exp_gui);
				break;
			
			case "ilinfoscreengui":
				$this->infoScreenForward();
				break;
			
			case 'ilassquestionhintsgui':
	
				// set return target
				$this->ctrl->setReturn($this, "questions");

				// set context tabs
				require_once 'Modules/TestQuestionPool/classes/class.assQuestionGUI.php';
				$questionGUI = assQuestionGUI::_getQuestionGUI($q_type, $_GET['q_id']);
				$questionGUI->object->setObjId($this->object->getId());
				$questionGUI->setQuestionTabs();
				
				// forward to ilAssQuestionHintsGUI
				require_once 'Modules/TestQuestionPool/classes/class.ilAssQuestionHintsGUI.php';
				$gui = new ilAssQuestionHintsGUI($questionGUI);
				$ilCtrl->forwardCommand($gui);
				
				break;

			case "ilobjquestionpoolgui":
			case "":
				
				if( $cmd == 'questions' )
				{
					$this->ctrl->setParameter($this, 'q_id', '');
				}
				
				$cmd.= "Object";
				$ret = $this->$cmd();
				break;
				
			default:
				$this->ctrl->setReturn($this, "questions");
				include_once "./Modules/TestQuestionPool/classes/class.assQuestionGUI.php";
				$q_gui = assQuestionGUI::_getQuestionGUI($q_type, $_GET["q_id"]);
				$q_gui->object->setObjId($this->object->getId());
				$q_gui->setQuestionTabs();
				$ret = $this->ctrl->forwardCommand($q_gui);
				break;
		}

		if (strtolower($_GET["baseClass"]) != "iladministrationgui" &&
			$this->getCreationMode() != true)
		{
			$this->tpl->show();
		}
	}

	/**
	* Questionpool properties
	*/
	function propertiesObject()
	{
		$save = ((strcmp($this->ctrl->getCmd(), "save") == 0)) ? true : false;

		include_once("./Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this, 'properties'));
		$form->setTitle($this->lng->txt("properties"));
		$form->setMultipart(false);
//		$form->setTableWidth("100%");
		$form->setId("properties");

		// online
		$online = new ilCheckboxInputGUI($this->lng->txt("qpl_online_property"), "online");
		$online->setInfo($this->lng->txt("qpl_online_property_description"));
		$online->setChecked($this->object->getOnline());
		$form->addItem($online);

		$form->addCommandButton("saveProperties", $this->lng->txt("save"));

		if ($save)
		{
			$form->checkInput();
		}
		$this->tpl->setVariable("ADM_CONTENT", $form->getHTML());
	}

	/**
	* Save questionpool properties
	*/
	function savePropertiesObject()
	{
		$qpl_online = $_POST["online"];
		if (strlen($qpl_online) == 0) $qpl_online = "0";
		$this->object->setOnline($qpl_online);
		$this->object->saveToDb();
		ilUtil::sendSuccess($this->lng->txt("saved_successfully"), true);
		$this->ctrl->redirect($this, "properties");
	}
	
	/**
	* download file
	*/
	function downloadFileObject()
	{
		$file = explode("_", $_GET["file_id"]);
		include_once("./Modules/File/classes/class.ilObjFile.php");
		$fileObj =& new ilObjFile($file[count($file) - 1], false);
		$fileObj->sendFile();
		exit;
	}
	
	/**
	* show fullscreen view
	*/
	function fullscreenObject()
	{
		include_once("./Services/COPage/classes/class.ilPageObject.php");
		include_once("./Services/COPage/classes/class.ilPageObjectGUI.php");
		//$page =& new ilPageObject("qpl", $_GET["pg_id"]);
		$page_gui =& new ilPageObjectGUI("qpl", $_GET["pg_id"]);
		$page_gui->showMediaFullscreen();
		
	}


	/**
	* set question list filter
	*/
	function filterObject()
	{
		$this->questionsObject();
	}

	/**
	* resets filter
	*/
	function resetFilterObject()
	{
		$_POST["filter_text"] = "";
		$_POST["sel_filter_type"] = "";
		$this->questionsObject();
	}

	/**
	* download source code paragraph
	*/
	function download_paragraphObject()
	{
		include_once("./Services/COPage/classes/class.ilPageObject.php");
		$pg_obj =& new ilPageObject("qpl", $_GET["pg_id"]);
		$pg_obj->send_paragraph ($_GET["par_id"], $_GET["downloadtitle"]);
		exit;
	}

	/**
	* imports question(s) into the questionpool
	*/
	function uploadQplObject($questions_only = false)
	{
		if ($_FILES["xmldoc"]["error"] > UPLOAD_ERR_OK)
		{
			ilUtil::sendFailure($this->lng->txt("error_upload"));
			$this->importObject();
			return;
		}
		// create import directory
		include_once "./Modules/TestQuestionPool/classes/class.ilObjQuestionPool.php";
		$basedir = ilObjQuestionPool::_createImportDirectory();

		// copy uploaded file to import directory
		$file = pathinfo($_FILES["xmldoc"]["name"]);
		$full_path = $basedir."/".$_FILES["xmldoc"]["name"];
		$GLOBALS['ilLog']->write(__METHOD__.": full path " . $full_path);
		include_once "./Services/Utilities/classes/class.ilUtil.php";
		ilUtil::moveUploadedFile($_FILES["xmldoc"]["tmp_name"], $_FILES["xmldoc"]["name"], $full_path);
		$GLOBALS['ilLog']->write(__METHOD__.": full path " . $full_path);
		if (strcmp($_FILES["xmldoc"]["type"], "text/xml") == 0)
		{
			$qti_file = $full_path;
			ilObjTest::_setImportDirectory($basedir);
		}
		else
		{
			// unzip file
			ilUtil::unzip($full_path);
	
			// determine filenames of xml files
			$subdir = basename($file["basename"],".".$file["extension"]);
			ilObjQuestionPool::_setImportDirectory($basedir);
			$xml_file = ilObjQuestionPool::_getImportDirectory().'/'.$subdir.'/'.$subdir.".xml";
			$qti_file = ilObjQuestionPool::_getImportDirectory().'/'.$subdir.'/'. str_replace("qpl", "qti", $subdir).".xml";
		}

		// start verification of QTI files
		include_once "./Services/QTI/classes/class.ilQTIParser.php";
		$qtiParser = new ilQTIParser($qti_file, IL_MO_VERIFY_QTI, 0, "");
		$result = $qtiParser->startParsing();
		$founditems =& $qtiParser->getFoundItems();
		if (count($founditems) == 0)
		{
			// nothing found

			// delete import directory
			ilUtil::delDir($basedir);

			ilUtil::sendInfo($this->lng->txt("qpl_import_no_items"));
			$this->importObject();
			return;
		}
		
		$complete = 0;
		$incomplete = 0;
		foreach ($founditems as $item)
		{
			if (strlen($item["type"]))
			{
				$complete++;
			}
			else
			{
				$incomplete++;
			}
		}
		
		if ($complete == 0)
		{
			// delete import directory
			ilUtil::delDir($basedir);

			ilUtil::sendInfo($this->lng->txt("qpl_import_non_ilias_files"));
			$this->importObject();
			return;
		}
		
		$_SESSION["qpl_import_xml_file"] = $xml_file;
		$_SESSION["qpl_import_qti_file"] = $qti_file;
		$_SESSION["qpl_import_subdir"] = $subdir;
		// display of found questions
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.qpl_import_verification.html",
			"Modules/TestQuestionPool");
		$row_class = array("tblrow1", "tblrow2");
		$counter = 0;
		foreach ($founditems as $item)
		{
			$this->tpl->setCurrentBlock("verification_row");
			$this->tpl->setVariable("ROW_CLASS", $row_class[$counter++ % 2]);
			$this->tpl->setVariable("QUESTION_TITLE", $item["title"]);
			$this->tpl->setVariable("QUESTION_IDENT", $item["ident"]);
			include_once "./Services/QTI/classes/class.ilQTIItem.php";
			switch ($item["type"])
			{
				case CLOZE_TEST_IDENTIFIER:
					$type = $this->lng->txt("assClozeTest");
					break;
				case IMAGEMAP_QUESTION_IDENTIFIER:
					$type = $this->lng->txt("assImagemapQuestion");
					break;
				case JAVAAPPLET_QUESTION_IDENTIFIER:
					$type = $this->lng->txt("assJavaApplet");
					break;
				case MATCHING_QUESTION_IDENTIFIER:
					$type = $this->lng->txt("assMatchingQuestion");
					break;
				case MULTIPLE_CHOICE_QUESTION_IDENTIFIER:
					$type = $this->lng->txt("assMultipleChoice");
					break;
				case SINGLE_CHOICE_QUESTION_IDENTIFIER:
					$type = $this->lng->txt("assSingleChoice");
					break;
				case ORDERING_QUESTION_IDENTIFIER:
					$type = $this->lng->txt("assOrderingQuestion");
					break;
				case TEXT_QUESTION_IDENTIFIER:
					$type = $this->lng->txt("assTextQuestion");
					break;
				case NUMERIC_QUESTION_IDENTIFIER:
					$type = $this->lng->txt("assNumeric");
					break;
				case TEXTSUBSET_QUESTION_IDENTIFIER:
					$type = $this->lng->txt("assTextSubset");
					break;
				default:
					$type = $this->lng->txt($item["type"]);
					break;
			}
			
			if (strcmp($type, "-" . $item["type"] . "-") == 0)
			{
				global $ilPluginAdmin;
				$pl_names = $ilPluginAdmin->getActivePluginsForSlot(IL_COMP_MODULE, "TestQuestionPool", "qst");
				foreach ($pl_names as $pl_name)
				{
					$pl = ilPlugin::getPluginObject(IL_COMP_MODULE, "TestQuestionPool", "qst", $pl_name);
					if (strcmp($pl->getQuestionType(), $item["type"]) == 0)
					{
						$type = $pl->getQuestionTypeTranslation();
					}
				}
			}
			$this->tpl->setVariable("QUESTION_TYPE", $type);
			$this->tpl->parseCurrentBlock();
		}
		
		$this->tpl->setCurrentBlock("import_qpl");
		if (is_file($xml_file))
		{
			// read file into a string
			$fh = @fopen($xml_file, "r") or die("");
			$xml = @fread($fh, filesize($xml_file));
			@fclose($fh);
			if (preg_match("/<ContentObject.*?MetaData.*?General.*?Title[^>]*?>([^<]*?)</", $xml, $matches))
			{
				$this->tpl->setVariable("VALUE_NEW_QUESTIONPOOL", $matches[1]);
			}
		}
		$this->tpl->setVariable("TEXT_CREATE_NEW_QUESTIONPOOL", $this->lng->txt("qpl_import_create_new_qpl"));
		$this->tpl->parseCurrentBlock();
		
		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("TEXT_TYPE", $this->lng->txt("question_type"));
		$this->tpl->setVariable("TEXT_TITLE", $this->lng->txt("question_title"));
		$this->tpl->setVariable("FOUND_QUESTIONS_INTRODUCTION", $this->lng->txt("qpl_import_verify_found_questions"));
		if ($questions_only)
		{
			$this->tpl->setVariable("VERIFICATION_HEADING", $this->lng->txt("import_questions_into_qpl"));
			$this->tpl->setVariable("FORMACTION", $this->ctrl->getFormAction($this));
		}
		else
		{
			$this->tpl->setVariable("VERIFICATION_HEADING", $this->lng->txt("import_qpl"));
			
			$this->ctrl->setParameter($this, "new_type", $this->type);
			$this->tpl->setVariable("FORMACTION", $this->ctrl->getFormAction($this));

			//$this->tpl->setVariable("FORMACTION", $this->getFormAction("save","adm_object.php?cmd=gateway&ref_id=".$_GET["ref_id"]."&new_type=".$this->type));
		}
		$this->tpl->setVariable("ARROW", ilUtil::getImagePath("arrow_downright.png"));
		$this->tpl->setVariable("VALUE_IMPORT", $this->lng->txt("import"));
		$this->tpl->setVariable("VALUE_CANCEL", $this->lng->txt("cancel"));
		$value_questions_only = 0;
		if ($questions_only) $value_questions_only = 1;
		$this->tpl->setVariable("VALUE_QUESTIONS_ONLY", $value_questions_only);

		$this->tpl->parseCurrentBlock();
	}
	
	/**
	* imports question(s) into the questionpool (after verification)
	*/
	function importVerifiedFileObject()
	{
		if ($_POST["questions_only"] == 1)
		{
			$newObj =& $this->object;
		}
		else
		{
			include_once("./Modules/TestQuestionPool/classes/class.ilObjQuestionPool.php");
			// create new questionpool object
			$newObj = new ilObjQuestionPool(0, true);
			// set type of questionpool object
			$newObj->setType($_GET["new_type"]);
			// set title of questionpool object to "dummy"
			$newObj->setTitle("dummy");
			// set description of questionpool object
			$newObj->setDescription("questionpool import");
			// create the questionpool class in the ILIAS database (object_data table)
			$newObj->create(true);
			// create a reference for the questionpool object in the ILIAS database (object_reference table)
			$newObj->createReference();
			// put the questionpool object in the administration tree
			$newObj->putInTree($_GET["ref_id"]);
			// get default permissions and set the permissions for the questionpool object
			$newObj->setPermissions($_GET["ref_id"]);
			// notify the questionpool object and all its parent objects that a "new" object was created
			$newObj->notify("new",$_GET["ref_id"],$_GET["parent_non_rbac_id"],$_GET["ref_id"],$newObj->getRefId());
		}

		// start parsing of QTI files
		include_once "./Services/QTI/classes/class.ilQTIParser.php";
		$qtiParser = new ilQTIParser($_SESSION["qpl_import_qti_file"], IL_MO_PARSE_QTI, $newObj->getId(), $_POST["ident"]);
		$result = $qtiParser->startParsing();

		// import page data
		if (strlen($_SESSION["qpl_import_xml_file"]))
		{
			include_once ("./Modules/LearningModule/classes/class.ilContObjParser.php");
			$contParser = new ilContObjParser($newObj, $_SESSION["qpl_import_xml_file"], $_SESSION["qpl_import_subdir"]);
			$contParser->setQuestionMapping($qtiParser->getImportMapping());
			$contParser->startParsing();
		}

		// set another question pool name (if possible)
		$qpl_name = $_POST["qpl_new"];
		if ((strcmp($qpl_name, $newObj->getTitle()) != 0) && (strlen($qpl_name) > 0))
		{
			$newObj->setTitle($qpl_name);
			$newObj->update();
		}
		
		// delete import directory
		include_once "./Services/Utilities/classes/class.ilUtil.php";
		ilUtil::delDir(dirname(ilObjQuestionPool::_getImportDirectory()));

		if ($_POST["questions_only"] == 1)
		{
			$this->ctrl->redirect($this, "questions");
		}
		else
		{
			ilUtil::sendSuccess($this->lng->txt("object_imported"),true);
			ilUtil::redirect("ilias.php?ref_id=".$newObj->getRefId().
				"&baseClass=ilObjQuestionPoolGUI");
		}
	}
	
	function cancelImportObject()
	{
		if ($_POST["questions_only"] == 1)
		{
			$this->ctrl->redirect($this, "questions");
		}
		else
		{
			$this->ctrl->redirect($this, "cancel");
		}
	}
	
	/**
	* imports question(s) into the questionpool
	*/
	function uploadObject()
	{
		$this->uploadQplObject(true);
	}
	
	/**
	* display the import form to import questions into the questionpool
	*/
		function importQuestionsObject()
	{
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_import_question.html", "Modules/TestQuestionPool");
		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("TEXT_IMPORT_QUESTION", $this->lng->txt("import_question"));
		$this->tpl->setVariable("TEXT_SELECT_FILE", $this->lng->txt("select_file"));
		$this->tpl->setVariable("TEXT_UPLOAD", $this->lng->txt("upload"));
		$this->tpl->setVariable("FORM_ACTION", $this->ctrl->getFormAction($this));
		$this->tpl->parseCurrentBlock();
	}

	/**
	* create new question
	*/
	function &createQuestionObject()
	{
		include_once "./Modules/TestQuestionPool/classes/class.assQuestionGUI.php";
		$q_gui =& assQuestionGUI::_getQuestionGUI($_POST["sel_question_types"]);
		$q_gui->object->setObjId($this->object->getId());
		$q_gui->object->createNewQuestion();
		$this->ctrl->setParameterByClass(get_class($q_gui), "q_id", $q_gui->object->getId());
		$this->ctrl->setParameterByClass(get_class($q_gui), "sel_question_types", $_POST["sel_question_types"]);
		$this->ctrl->redirectByClass(get_class($q_gui), "editQuestion");
	}

	/**
	* create new question
	*/
	function &createQuestionForTestObject()
	{
	    if (!$_REQUEST['q_id']) {
		include_once "./Modules/TestQuestionPool/classes/class.assQuestionGUI.php";
		$q_gui =& assQuestionGUI::_getQuestionGUI($_GET["sel_question_types"]);
		$q_gui->object->setObjId($this->object->getId());
		$q_gui->object->createNewQuestion();
		$this->ctrl->setParameterByClass(get_class($q_gui), "q_id", $q_gui->object->getId());
		$this->ctrl->setParameterByClass(get_class($q_gui), "sel_question_types", $_REQUEST["sel_question_types"]);
		$this->ctrl->setParameterByClass(get_class($q_gui), "prev_qid", $_REQUEST["prev_qid"]);
		$this->ctrl->redirectByClass(get_class($q_gui), "editQuestion");
	    }
	    else {
		$class = $_GET["sel_question_types"] . 'gui';
		$this->ctrl->setParameterByClass($class, "q_id", $_REQUEST['q_id']);
		$this->ctrl->setParameterByClass($class, "sel_question_types", $_REQUEST["sel_question_types"]);
		$this->ctrl->setParameterByClass($class, "prev_qid", $_REQUEST["prev_qid"]);
		$this->ctrl->redirectByClass($class, "editQuestion");
	    }
	}

	/**
	* save object
	* @access	public
	*/
	function afterSave(ilObject $a_new_object)
	{
		// always send a message
		ilUtil::sendSuccess($this->lng->txt("object_added"),true);

		ilUtil::redirect("ilias.php?ref_id=".$a_new_object->getRefId().
			"&baseClass=ilObjQuestionPoolGUI");
	}

	/**
	* show assessment data of object
	*/
	function assessmentObject()
	{
		$this->tpl->addBlockFile("CONTENT", "content", "tpl.il_as_qpl_content.html", "Modules/TestQuestionPool");
		$this->tpl->addBlockFile("STATUSLINE", "statusline", "tpl.statusline.html");

		// catch feedback message
		ilUtil::sendInfo();

		include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
		$question_title = assQuestion::_getTitle($_GET["q_id"]);
		$title = $this->lng->txt("statistics") . " - $question_title";
		if (!empty($title))
		{
			$this->tpl->setVariable("HEADER", $title);
		}
		include_once("./Modules/TestQuestionPool/classes/class.assQuestion.php");
		$total_of_answers = assQuestion::_getTotalAnswers($_GET["q_id"]);
		$counter = 0;
		$color_class = array("tblrow1", "tblrow2");
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_qpl_assessment_of_questions.html", "Modules/TestQuestionPool");
		if (!$total_of_answers)
		{
			$this->tpl->setCurrentBlock("emptyrow");
			$this->tpl->setVariable("TXT_NO_ASSESSMENT", $this->lng->txt("qpl_assessment_no_assessment_of_questions"));
			$this->tpl->setVariable("COLOR_CLASS", $color_class[$counter % 2]);
			$this->tpl->parseCurrentBlock();
		}
		else
		{
			$this->tpl->setCurrentBlock("row");
			$this->tpl->setVariable("TXT_RESULT", $this->lng->txt("qpl_assessment_total_of_answers"));
			$this->tpl->setVariable("TXT_VALUE", $total_of_answers);
			$this->tpl->setVariable("COLOR_CLASS", $color_class[$counter % 2]);
			$counter++;
			$this->tpl->parseCurrentBlock();
			$this->tpl->setCurrentBlock("row");
			$this->tpl->setVariable("TXT_RESULT", $this->lng->txt("qpl_assessment_total_of_right_answers"));
			$this->tpl->setVariable("TXT_VALUE", sprintf("%2.2f", assQuestion::_getTotalRightAnswers($_GET["edit"]) * 100.0) . " %");
			$this->tpl->setVariable("COLOR_CLASS", $color_class[$counter % 2]);
			$this->tpl->parseCurrentBlock();
		}
		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("TXT_QUESTION_TITLE", $question_title);
		$this->tpl->setVariable("TXT_RESULT", $this->lng->txt("result"));
		$this->tpl->setVariable("TXT_VALUE", $this->lng->txt("value"));
		$this->tpl->parseCurrentBlock();
	}

	function questionObject()
	{
//echo "<br>ilObjQuestionPoolGUI->questionObject()";
		$type = $_GET["sel_question_types"];
		$this->editQuestionForm($type);
	}

	/**
	* delete questions confirmation screen
	*/
	function deleteQuestionsObject()
	{
		global $rbacsystem;
		
		if (count($_POST["q_id"]) < 1)
		{
			ilUtil::sendInfo($this->lng->txt("qpl_delete_select_none"), true);
			$this->ctrl->redirect($this, "questions");
		}
		
		ilUtil::sendQuestion($this->lng->txt("qpl_confirm_delete_questions"));
		$deleteable_questions =& $this->object->getDeleteableQuestionDetails($_POST["q_id"]);
		include_once "./Modules/TestQuestionPool/classes/tables/class.ilQuestionBrowserTableGUI.php";
		$table_gui = new ilQuestionBrowserTableGUI($this, 'questions', (($rbacsystem->checkAccess('write', $_GET['ref_id']) ? true : false)), true);
		$table_gui->setEditable($rbacsystem->checkAccess('write', $_GET['ref_id']));
		$table_gui->setData($deleteable_questions);
		$this->tpl->setVariable('ADM_CONTENT', $table_gui->getHTML());	
	}


	/**
	* delete questions
	*/
	function confirmDeleteQuestionsObject()
	{
		// delete questions after confirmation
		foreach ($_POST["q_id"] as $key => $value)
		{
			$this->object->deleteQuestion($value);
		}
		if (count($_POST["q_id"])) ilUtil::sendSuccess($this->lng->txt("qpl_questions_deleted"), true);

		$this->ctrl->setParameter($this, 'q_id', '');
		
		$this->ctrl->redirect($this, "questions");
	}
	
	/**
	* Cancel question deletion
	*/
	function cancelDeleteQuestionsObject()
	{
		$this->ctrl->redirect($this, "questions");
	}

	/**
	* export question
	*/
	function exportQuestionObject()
	{
		// export button was pressed
		if (count($_POST["q_id"]) > 0)
		{
			include_once("./Modules/TestQuestionPool/classes/class.ilQuestionpoolExport.php");
			$qpl_exp = new ilQuestionpoolExport($this->object, "xml", $_POST["q_id"]);
			$export_file = $qpl_exp->buildExportFile();
			$filename = $export_file;
			$filename = preg_replace("/.*\//", "", $filename);
			include_once "./Services/Utilities/classes/class.ilUtil.php";
			ilUtil::deliverFile($export_file, $filename);
			exit();
		}
		else
		{
			ilUtil::sendInfo($this->lng->txt("qpl_export_select_none"), true);
		}
		$this->ctrl->redirect($this, "questions");
	}
	
	function filterQuestionBrowserObject()
	{
		include_once "./Modules/TestQuestionPool/classes/tables/class.ilQuestionBrowserTableGUI.php";
		$table_gui = new ilQuestionBrowserTableGUI($this, 'questions');
		$table_gui->resetOffset();
		$table_gui->writeFilterToSession();
		$this->questionsObject();
	}

	function resetQuestionBrowserObject()
	{
		include_once "./Modules/TestQuestionPool/classes/tables/class.ilQuestionBrowserTableGUI.php";
		$table_gui = new ilQuestionBrowserTableGUI($this, 'questions');
		$table_gui->resetOffset();
		$table_gui->resetFilter();
		$this->questionsObject();
	}
	
	/**
	* list questions of question pool
	*/
	function questionsObject($arrFilter = null)
	{
		global $rbacsystem;
		global $ilUser;

		if(get_class($this->object) == "ilObjTest")
		{
			if ($_GET["calling_test"] > 0)
			{
				$ref_id = $_GET["calling_test"];
				$q_id = $_GET["q_id"];

				if ($_REQUEST['test_express_mode']) {
				    if ($q_id)
					ilUtil::redirect("ilias.php?ref_id=".$ref_id."&q_id=".$q_id."&test_express_mode=1&cmd=showPage&cmdClass=iltestexpresspageobjectgui&baseClass=ilObjTestGUI");
				    else
					ilUtil::redirect("ilias.php?ref_id=".$ref_id."&test_express_mode=1&cmd=showPage&cmdClass=iltestexpresspageobjectgui&baseClass=ilObjTestGUI");
				}
				else
				    ilUtil::redirect("ilias.php?baseClass=ilObjTestGUI&ref_id=".$ref_id."&cmd=questions");

			}
		}

		$this->object->purgeQuestions();
		// reset test_id SESSION variable
		$_SESSION["test_id"] = "";

		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_qpl_questionbrowser.html", "Modules/TestQuestionPool");
		include_once "./Modules/TestQuestionPool/classes/tables/class.ilQuestionBrowserTableGUI.php";
		$table_gui = new ilQuestionBrowserTableGUI($this, 'questions', (($rbacsystem->checkAccess('write', $_GET['ref_id']) ? true : false)));
		$table_gui->setEditable($rbacsystem->checkAccess('write', $_GET['ref_id']));
		$arrFilter = array();
		foreach ($table_gui->getFilterItems() as $item)
		{
			if ($item->getValue() !== false)
			{
				$arrFilter[$item->getPostVar()] = $item->getValue();
			}
		}
		$data = $this->object->getQuestionBrowserData($arrFilter);
		$table_gui->setData($data);
		$this->tpl->setVariable('TABLE', $table_gui->getHTML());	

		if ($rbacsystem->checkAccess('write', $_GET['ref_id']))
		{
			$this->tpl->setCurrentBlock("QTypes");
			$types =& $this->object->getQuestionTypes(false, true);
			$lastquestiontype = $ilUser->getPref("tst_lastquestiontype");
			foreach ($types as $translation => $data)
			{
				if ($data["type_tag"] == $lastquestiontype)
				{
					$this->tpl->setVariable("QUESTION_TYPE_SELECTED", " selected=\"selected\"");
				}
				$this->tpl->setVariable("QUESTION_TYPE_ID", $data["type_tag"]);
				$this->tpl->setVariable("QUESTION_TYPE", $translation);
				$this->tpl->parseCurrentBlock();
			}
			$this->tpl->setVariable("QUESTION_ADD", $this->lng->txt("create"));
			$this->tpl->setVariable("FORMACTION", $this->ctrl->getFormAction($this, 'questions'));
			$this->tpl->parseCurrentBlock();
		}
	}

	/**
	* Creates a print view for a question pool
	*
	* @access	public
	*/
	function printObject()
	{
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_qpl_printview.html", "Modules/TestQuestionPool");
		switch ($_POST["output"])
		{
			case 'detailed':
				$this->tpl->setVariable("SELECTED_DETAILED", " selected=\"selected\"");
				break;
			case 'detailed_printview':
				$this->tpl->setVariable("SELECTED_DETAILED_PRINTVIEW", " selected=\"selected\"");
				break;
			default:
				break;
		}
		$this->tpl->setVariable("TEXT_DETAILED", $this->lng->txt("detailed_output_solutions"));
		$this->tpl->setVariable("TEXT_DETAILED_PRINTVIEW", $this->lng->txt("detailed_output_printview"));
		$this->tpl->setVariable("TEXT_OVERVIEW", $this->lng->txt("overview"));
		$this->tpl->setVariable("TEXT_SUBMIT", $this->lng->txt("submit"));
		$this->tpl->setVariable("OUTPUT_MODE", $this->lng->txt("output_mode"));
		$this->tpl->setVariable("FORMACTION", $this->ctrl->getFormAction($this, 'print'));

		include_once "./Modules/TestQuestionPool/classes/tables/class.ilQuestionPoolPrintViewTableGUI.php";
		$table_gui = new ilQuestionPoolPrintViewTableGUI($this, 'print', $_POST['output']);
		$data = $this->object->getPrintviewQuestions();
		$table_gui->setData($data);
		$this->tpl->setVariable('TABLE', $table_gui->getHTML());
	}

	function updateObject()
	{
//		$this->update = $this->object->updateMetaData();
		$this->update = $this->object->update();
		ilUtil::sendSuccess($this->lng->txt("msg_obj_modified"), true);
	}

	/**
	* paste questios from the clipboard into the question pool
	*/
	function pasteObject()
	{
		if (array_key_exists("qpl_clipboard", $_SESSION))
		{
			$this->object->pasteFromClipboard();
			ilUtil::sendSuccess($this->lng->txt("qpl_paste_success"), true);
		}
		else
		{
			ilUtil::sendInfo($this->lng->txt("qpl_paste_no_objects"), true);
		}
		$this->ctrl->redirect($this, "questions");
	}

	/**
	* copy one or more question objects to the clipboard
	*/
	public function copyObject()
	{
		if (count($_POST["q_id"]) > 0)
		{
			foreach ($_POST["q_id"] as $key => $value)
			{
				$this->object->copyToClipboard($value);
			}
			ilUtil::sendInfo($this->lng->txt("qpl_copy_insert_clipboard"), true);
		}
		else
		{
			ilUtil::sendInfo($this->lng->txt("qpl_copy_select_none"), true);
		}
		$this->ctrl->redirect($this, "questions");
	}
	
	/**
	* mark one or more question objects for moving
	*/
	function moveObject()
	{
		if (count($_POST["q_id"]) > 0)
		{
			foreach ($_POST["q_id"] as $key => $value)
			{
				$this->object->moveToClipboard($value);
			}
			ilUtil::sendInfo($this->lng->txt("qpl_move_insert_clipboard"), true);
		}
		else
		{
			ilUtil::sendInfo($this->lng->txt("qpl_move_select_none"), true);
		}
		$this->ctrl->redirect($this, "questions");
	}
	
	/**
	* create export file
	*/
	function createExportQTI()
	{
		global $rbacsystem;
		if ($rbacsystem->checkAccess("write", $_GET['ref_id']))
		{
			include_once("./Modules/TestQuestionPool/classes/class.ilQuestionpoolExport.php");
			$question_ids =& $this->object->getAllQuestionIds();
			$qpl_exp = new ilQuestionpoolExport($this->object, 'xml', $question_ids);
			$qpl_exp->buildExportFile();
			$this->ctrl->redirectByClass("ilexportgui", "");
		}
	}

	function createExportExcel()
	{
		global $rbacsystem;
		if ($rbacsystem->checkAccess("write", $_GET['ref_id']))
		{
			include_once("./Modules/TestQuestionPool/classes/class.ilQuestionpoolExport.php");
			$question_ids =& $this->object->getAllQuestionIds();
			$qpl_exp = new ilQuestionpoolExport($this->object, 'xls', $question_ids);
			$qpl_exp->buildExportFile();
			$this->ctrl->redirectByClass("ilexportgui", "");
		}
	}

	/**
	* edit question
	*/
	function &editQuestionForTestObject()
	{
		include_once "./Modules/TestQuestionPool/classes/class.assQuestionGUI.php";
		$q_gui =& assQuestionGUI::_getQuestionGUI("", $_GET["q_id"]);
		$this->ctrl->redirectByClass(get_class($q_gui), "editQuestion");
	}

	protected function initImportForm($a_new_type)
	{
		include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();
		$form->setTarget("_top");
		$form->setFormAction($this->ctrl->getFormAction($this));
		$form->setTitle($this->lng->txt("import_qpl"));

		include_once("./Services/Form/classes/class.ilFileInputGUI.php");
		$fi = new ilFileInputGUI($this->lng->txt("import_file"), "xmldoc");
		$fi->setSuffixes(array("zip"));
		$fi->setRequired(true);
		$form->addItem($fi);

		$form->addCommandButton("importFile", $this->lng->txt("import"));
		$form->addCommandButton("cancel", $this->lng->txt("cancel"));

		return $form;
	}
	
	/**
	* form for new questionpool object import
	*/
	function importFileObject()
	{
		$form = $this->initImportForm($_REQUEST["new_type"]);
		if($form->checkInput())
		{
			$this->uploadQplObject();
		}

		// display form to correct errors
		$this->tpl->setContent($form->getHTML());
	}

	function addLocatorItems()
	{
		global $ilLocator;
		switch ($this->ctrl->getCmd())
		{
			case "create":
			case "importFile":
			case "cancel":
				break;
			default:
				$ilLocator->addItem($this->object->getTitle(), $this->ctrl->getLinkTarget($this, ""), "", $_GET["ref_id"]);
				break;
		}
		if ($_GET["q_id"] > 0)
		{
			include_once "./Modules/TestQuestionPool/classes/class.assQuestionGUI.php";
			$q_gui = assQuestionGUI::_getQuestionGUI("", $_GET["q_id"]);
			if($q_gui->object instanceof assQuestion)
			{
				$q_gui->object->setObjId($this->object->getId());
				$title = $q_gui->object->getTitle();
				if(!$title)
				{
					$title = $this->lng->txt('new').': '.assQuestion::_getQuestionTypeName($q_gui->object->getQuestionType());
				}
				$ilLocator->addItem($title, $this->ctrl->getLinkTargetByClass(get_class($q_gui), "editQuestion"));
			}
			else
			{
				// Workaround for context issues: If no object was found, redirect without q_id parameter
				$this->ctrl->setParameter($this, 'q_id', '');
				$this->ctrl->redirect($this);
			}
		}
	}
	
	/**
	* called by prepare output
	*/
	function setTitleAndDescription()
	{
		parent::setTitleAndDescription();
		if ($_GET["q_id"] > 0)
		{
			include_once "./Modules/TestQuestionPool/classes/class.assQuestionGUI.php";
			$q_gui = assQuestionGUI::_getQuestionGUI("", $_GET["q_id"]);
			if($q_gui->object instanceof assQuestion)
			{
				$q_gui->object->setObjId($this->object->getId());
				$title = $q_gui->object->getTitle();
				if (strcmp($this->ctrl->getCmd(), "assessment") == 0)
				{
					$title .= " - " . $this->lng->txt("statistics");
				}
				if(!$title)
				{
					$title = $this->lng->txt('new').': '.assQuestion::_getQuestionTypeName($q_gui->object->getQuestionType());
				}
				$this->tpl->setTitle($title);
				$this->tpl->setDescription($q_gui->object->getComment());
				$this->tpl->setTitleIcon(ilUtil::getImagePath("icon_".$this->object->getType()."_b.png"), $this->lng->txt("obj_qpl"));
			}
			else
			{
				// Workaround for context issues: If no object was found, redirect without q_id parameter
				$this->ctrl->setParameter($this, 'q_id', '');
				$this->ctrl->redirect($this);
			}
		}
		else
		{
			$this->tpl->setTitle($this->object->getTitle());
			$this->tpl->setDescription($this->object->getLongDescription());
			$this->tpl->setTitleIcon(ilUtil::getImagePath("icon_".$this->object->getType()."_b.png"), $this->lng->txt("obj_qpl"));
		}
	}

	/**
	* adds tabs to tab gui object
	*
	* @param	object		$tabs_gui		ilTabsGUI object
	*/
	function getTabs(&$tabs_gui)
	{
		global $ilAccess, $ilHelp;

		
		$ilHelp->setScreenIdComponent("qpl");
		
		$next_class = strtolower($this->ctrl->getNextClass());
		switch ($next_class)
		{
			case "":
			case "ilpermissiongui":
			case "ilmdeditorgui":
			case "ilexportgui":
				break;
			default:
				return;
				break;
		}
		// questions
		$force_active = false;
		$commands = $_POST["cmd"];
		if (is_array($commands))
		{
			foreach ($commands as $key => $value)
			{
				if (preg_match("/^delete_.*/", $key, $matches) || 
					preg_match("/^addSelectGap_.*/", $key, $matches) ||
					preg_match("/^addTextGap_.*/", $key, $matches) ||
					preg_match("/^deleteImage_.*/", $key, $matches) ||
					preg_match("/^upload_.*/", $key, $matches) ||
					preg_match("/^addSuggestedSolution_.*/", $key, $matches)
					)
				{
					$force_active = true;
				}
			}
		}
		if (array_key_exists("imagemap_x", $_POST))
		{
			$force_active = true;
		}
		if (!$force_active)
		{
			$force_active = ((strtolower($this->ctrl->getCmdClass()) == strtolower(get_class($this)) || strlen($this->ctrl->getCmdClass()) == 0) &&
				$this->ctrl->getCmd() == "")
				? true
				: false;
		}
		$tabs_gui->addTarget("assQuestions",
			 $this->ctrl->getLinkTarget($this, "questions"),
			 array("questions", "filter", "resetFilter", "createQuestion", 
			 	"importQuestions", "deleteQuestions", "filterQuestionBrowser",
				"view", "preview", "editQuestion", "exec_pg",
				"addItem", "upload", "save", "cancel", "addSuggestedSolution",
				"cancelExplorer", "linkChilds", "removeSuggestedSolution",
				"add", "addYesNo", "addTrueFalse", "createGaps", "saveEdit",
				"setMediaMode", "uploadingImage", "uploadingImagemap", "addArea",
				"deletearea", "saveShape", "back", "addPair", "uploadingJavaapplet",
				"addParameter", "assessment", "addGIT", "addST", "addPG", "delete",
				"toggleGraphicalAnswers", "deleteAnswer", "deleteImage", "removeJavaapplet"),
			 "", "", $force_active);

		if ($ilAccess->checkAccess("visible", "", $this->ref_id))
		{
			$tabs_gui->addTarget("info_short",
				 $this->ctrl->getLinkTarget($this, "infoScreen"),
				array("infoScreen", "showSummary"));		
		}
		
		if ($ilAccess->checkAccess("write", "", $_GET['ref_id']))
		{
			// properties
			$tabs_gui->addTarget("settings",
				 $this->ctrl->getLinkTarget($this,'properties'),
				 "properties", "",
				 "");
		}

		// print view
		$tabs_gui->addTarget("print_view",
			 $this->ctrl->getLinkTarget($this,'print'),
			 array("print"),
			 "", "");

		if ($ilAccess->checkAccess("write", "", $this->object->getRefId()))
		{
			// meta data
			$tabs_gui->addTarget("meta_data",
				 $this->ctrl->getLinkTargetByClass('ilmdeditorgui','listSection'),
				 "", "ilmdeditorgui");

//			$tabs_gui->addTarget("export",
//				 $this->ctrl->getLinkTarget($this,'export'),
//				 array("export", "createExportFile", "confirmDeleteExportFile", "downloadExportFile"),
//				 "", "");
		}

		if ($ilAccess->checkAccess("write", "", $this->object->getRefId()))
		{
			$tabs_gui->addTarget("export",
				$this->ctrl->getLinkTargetByClass("ilexportgui", ""),
				"", "ilexportgui");
		}

		if ($ilAccess->checkAccess("edit_permission", "", $this->object->getRefId()))
		{
			$tabs_gui->addTarget("perm_settings",
			$this->ctrl->getLinkTargetByClass(array(get_class($this),'ilpermissiongui'), "perm"), array("perm","info","owner"), 'ilpermissiongui');
		}
	}
	
	/**
	* this one is called from the info button in the repository
	* not very nice to set cmdClass/Cmd manually, if everything
	* works through ilCtrl in the future this may be changed
	*/
	function infoScreenObject()
	{
		$this->ctrl->setCmd("showSummary");
		$this->ctrl->setCmdClass("ilinfoscreengui");
		$this->infoScreenForward();
	}
	
	/**
	* show information screen
	*/
	function infoScreenForward()
	{
		global $ilErr, $ilAccess;
		
		if(!$ilAccess->checkAccess("visible", "", $this->ref_id))
		{
			$ilErr->raiseError($this->lng->txt("msg_no_perm_read"));
		}

		include_once("./Services/InfoScreen/classes/class.ilInfoScreenGUI.php");
		$info = new ilInfoScreenGUI($this);
		$info->enablePrivateNotes();

		// standard meta data
		$info->addMetaDataSections($this->object->getId(), 0, $this->object->getType());
		
		$this->ctrl->forwardCommand($info);
	}

	/**
	* Redirect script to call a test with the question pool reference id
	* 
	* Redirect script to call a test with the question pool reference id
	*
	* @param integer $a_target The reference id of the question pool
	* @access	public
	*/
	function _goto($a_target)
	{
		global $ilAccess, $ilErr, $lng;

		if ($ilAccess->checkAccess("write", "", $a_target))
		{
			$_GET["baseClass"] = "ilObjQuestionPoolGUI";
			$_GET["cmd"] = "questions";
			$_GET["ref_id"] = $a_target;
			include_once("ilias.php");
			exit;
		}
		else if ($ilAccess->checkAccess("read", "", ROOT_FOLDER_ID))
		{
			ilUtil::sendInfo(sprintf($lng->txt("msg_no_perm_read_item"),
				ilObject::_lookupTitle(ilObject::_lookupObjId($a_target))), true);
			ilObjectGUI::_gotoRepositoryRoot();
		}
		$ilErr->raiseError($lng->txt("msg_no_perm_read_lm"), $ilErr->FATAL);
	}

} // END class.ilObjQuestionPoolGUI
?>
