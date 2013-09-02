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

include_once "./Modules/Test/classes/inc.AssessmentConstants.php";
include_once 'Modules/Test/classes/class.ilTestExpressPage.php';
/**
* Basic GUI class for assessment questions
*
* The assQuestionGUI class encapsulates basic GUI functions
* for assessment questions.
*
* @ilCtrl_Calls assQuestionGUI: ilPageObjectGUI
*
* @author		Helmut Schottmüller <helmut.schottmueller@mac.com>
* @author		Björn Heyser <bheyser@databay.de>
* @version		$Id: class.assQuestionGUI.php 42157 2013-05-11 07:04:52Z mjansen $
* @ingroup		ModulesTestQuestionPool
*/
abstract class assQuestionGUI
{
	/**
	* Question object
	*
	* A reference to the matching question object
	*
	* @var object
	*/
	var $object;

	var $tpl;
	var $lng;
	var $error;
	var $errormessage;
	
	/**
	 * sequence number in test
	 */
	var $sequence_no;
	/**
	 * question count in test
	 */
	var $question_count;

	/**
	 * do not use rte for editing
	 */
	var $prevent_rte_usage = false;
	
	/**
	* assQuestionGUI constructor
	*/
	function __construct()
	{
		global $lng, $tpl, $ilCtrl;


		$this->lng =& $lng;
		$this->tpl =& $tpl;
		$this->ctrl =& $ilCtrl;
		$this->ctrl->saveParameter($this, "q_id");
		$this->ctrl->saveParameter($this, "prev_qid");
		$this->ctrl->saveParameter($this, "calling_test");
		$this->ctrl->saveParameterByClass('ilPageObjectGUI', 'test_express_mode');
		$this->ctrl->saveParameterByClass('ilobjquestionpoolgui', 'test_express_mode');

		include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
		$this->errormessage = $this->lng->txt("fill_out_all_required_fields");
		
		$this->selfassessmenteditingmode = false;
		$this->new_id_listeners = array();
		$this->new_id_listener_cnt = 0;
	}

	/**
	* execute command
	*/
	function &executeCommand()
	{
		$cmd = $this->ctrl->getCmd("editQuestion");
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
	* needed for page editor compliance
	*/
	function getType()
	{
		return $this->getQuestionType();
	}

	/**
	* Evaluates a posted edit form and writes the form data in the question object
	*
	* @return integer A positive value, if one of the required fields wasn't set, else 0
	* @access private
	*/
	function writePostData()
	{
	}

	/**
	* output assessment
	*/
	function assessment()
	{
		$this->tpl->addBlockFile("CONTENT", "content", "tpl.il_as_qpl_content.html", "Modules/TestQuestionPool");
		$this->tpl->addBlockFile("STATUSLINE", "statusline", "tpl.statusline.html");

		$total_of_answers = $this->object->getTotalAnswers();
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
			$this->tpl->setVariable("TXT_VALUE", sprintf("%2.2f", $this->object->_getTotalRightAnswers($_GET["q_id"]) * 100.0) . " %");
			$this->tpl->setVariable("COLOR_CLASS", $color_class[$counter % 2]);
			$this->tpl->parseCurrentBlock();
		}

		$instances =& $this->object->getInstances();
		$counter = 0;
		foreach ($instances as $instance)
		{
			if (is_array($instance["refs"]))
			{
				foreach ($instance["refs"] as $ref_id)
				{
					$this->tpl->setCurrentBlock("references");
					$this->tpl->setVariable("GOTO", "./goto.php?target=tst_" . $ref_id);
					$this->tpl->setVariable("TEXT_GOTO", $this->lng->txt("perma_link"));
					$this->tpl->parseCurrentBlock();
				}
			}
			$this->tpl->setCurrentBlock("instance_row");
			$this->tpl->setVariable("TEST_TITLE", $instance["title"]);
			$this->tpl->setVariable("TEST_AUTHOR", $instance["author"]);
			$this->tpl->setVariable("QUESTION_ID", $instance["question_id"]);
			$this->tpl->setVariable("COLOR_CLASS", $color_class[$counter % 2]);
			$counter++;
			$this->tpl->parseCurrentBlock();
		}
		$this->tpl->setCurrentBlock("instances");
		$this->tpl->setVariable("TEXT_TEST_TITLE", $this->lng->txt("title"));
		$this->tpl->setVariable("TEXT_TEST_AUTHOR", $this->lng->txt("author"));
		$this->tpl->setVariable("TEXT_TEST_LOCATION", $this->lng->txt("location"));
		$this->tpl->setVariable("INSTANCES_TITLE", $this->lng->txt("question_instances_title"));
		$this->tpl->parseCurrentBlock();

		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("TXT_QUESTION_TITLE", $this->lng->txt("question_cumulated_statistics"));
		$this->tpl->setVariable("TXT_RESULT", $this->lng->txt("result"));
		$this->tpl->setVariable("TXT_VALUE", $this->lng->txt("value"));
		$this->tpl->parseCurrentBlock();
	}

	/**
	* Creates a question gui representation and returns the alias to the question gui
	* note: please do not use $this inside this method to allow static calls
	*
	* @param string $question_type The question type as it is used in the language database
	* @param integer $question_id The database ID of an existing question to load it into assQuestionGUI
	* @return object The alias to the question object
	* @access public
	*/
	function &_getQuestionGUI($question_type, $question_id = -1)
	{
		include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
		if ((!$question_type) and ($question_id > 0))
		{
			$question_type = assQuestion::getQuestionTypeFromDb($question_id);
		}
		if (strlen($question_type) == 0) return NULL;
		$question_type_gui = $question_type . "GUI";
		assQuestion::_includeClass($question_type, 1);
		$question =& new $question_type_gui();
		if ($question_id > 0)
		{
			$question->object->loadFromDb($question_id);
		}
		return $question;
	}
	
	function _getGUIClassNameForId($a_q_id)
	{
		include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
		include_once "./Modules/TestQuestionPool/classes/class.assQuestionGUI.php";
		$q_type =  assQuestion::getQuestionTypeFromDb($a_q_id);
		$class_name = assQuestionGUI::_getClassNameForQType($q_type);
		return $class_name;
	}

	function _getClassNameForQType($q_type)
	{
		return $q_type . "GUI";
	}

	/**
	* Creates a question gui representation
	*
	* Creates a question gui representation and returns the alias to the question gui
	*
	* @param string $question_type The question type as it is used in the language database
	* @param integer $question_id The database ID of an existing question to load it into assQuestionGUI
	* @return object The alias to the question object
	* @access public
	*/
	function &createQuestionGUI($question_type, $question_id = -1)
	{
		include_once "./Modules/TestQuestionPool/classes/class.assQuestionGUI.php";
		$this->question =& assQuestionGUI::_getQuestionGUI($question_type, $question_id);
	}

	/**
	* get question template
	*/
	function getQuestionTemplate()
	{
		$this->tpl->addBlockFile("CONTENT", "content", "tpl.il_as_qpl_content.html", "Modules/TestQuestionPool");
		$this->tpl->addBlockFile("STATUSLINE", "statusline", "tpl.statusline.html");
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_question.html", "Modules/TestQuestionPool");
	}

	/**
	* Returns the ILIAS Page around a question
	*
	* @return string The ILIAS page content
	* @access public
	*/
	function getILIASPage($html = "")
	{
		include_once("./Services/COPage/classes/class.ilPageObject.php");
		include_once("./Services/COPage/classes/class.ilPageObjectGUI.php");
		//$page =& new ilPageObject("qpl", $this->object->getId());
		$page_gui =& new ilPageObjectGUI("qpl", $this->object->getId());
		$page_gui->setTemplateTargetVar($a_temp_var);
		$page_gui->setEnabledInternalLinks(false);
		$page_gui->setQuestionHTML(array($this->object->getId() => $html));
		$page_gui->setFileDownloadLink("ilias.php?baseClass=ilObjTestGUI&cmd=downloadFile".
			"&amp;ref_id=".$_GET["ref_id"]);
		$page_gui->setFullscreenLink("ilias.php?baseClass=ilObjTestGUI&cmd=fullscreen".
			"&amp;ref_id=".$_GET["ref_id"]);
		$page_gui->setSourcecodeDownloadScript("ilias.php?baseClass=ilObjTestGUI&ref_id=".$_GET["ref_id"]);
		$page_gui->setEnabledPageFocus(false);
		$page_gui->setOutputMode("presentation");
		$page_gui->setPresentationTitle("");
		$presentation = $page_gui->presentation();
		// bugfix for non XHTML conform img tags in ILIAS Learning Module Editor
		$presentation = preg_replace("/src=\".\\//ims", "src=\"" . ILIAS_HTTP_PATH . "/", $presentation);
		return $presentation;
	}

	/**
	* output question page
	*/
	function outQuestionPage($a_temp_var, $a_postponed = false, $active_id = "", $html = "")
	{
		$postponed = "";
		if ($a_postponed)
		{
			$postponed = " (" . $this->lng->txt("postponed") . ")";
		}

		include_once("./Services/COPage/classes/class.ilPageObject.php");
		include_once("./Services/COPage/classes/class.ilPageObjectGUI.php");
		$this->lng->loadLanguageModule("content");
		//$page =& new ilPageObject("qpl", $this->object->getId());
		$page_gui =& new ilPageObjectGUI("qpl", $this->object->getId());
		$page_gui->setTemplateTargetVar($a_temp_var);
		$page_gui->setFileDownloadLink("ilias.php?baseClass=ilObjTestGUI&cmd=downloadFile".
			"&amp;ref_id=".$_GET["ref_id"]);
		$page_gui->setFullscreenLink("ilias.php?baseClass=ilObjTestGUI&cmd=fullscreen".
			"&amp;ref_id=".$_GET["ref_id"]);
		$page_gui->setEnabledPageFocus(false);
		if (strlen($html))
		{
			$page_gui->setQuestionHTML(array($this->object->getId() => $html));
		}
		$page_gui->setSourcecodeDownloadScript("ilias.php?baseClass=ilObjTestGUI&ref_id=".$_GET["ref_id"]);
		$page_gui->setOutputMode("presentation");

		include_once "./Modules/Test/classes/class.ilObjTest.php";
		$title_output = ilObjTest::_getTitleOutput($active_id);
		
		if( $this->object->areObligationsToBeConsidered() && ilObjTest::isQuestionObligatory($this->object->getId()) )
		{
			$obligatoryString = ' *';
		}
		else
		{
			$obligatoryString = '';
		}

		switch ($title_output)
		{
			case 1:
				$page_gui->setPresentationTitle(sprintf($this->lng->txt("tst_position"), $this->getSequenceNumber(), $this->getQuestionCount())." - ".$this->object->getTitle().$postponed . $obligatoryString);
				break;
			case 2:
				$page_gui->setPresentationTitle(sprintf($this->lng->txt("tst_position"), $this->getSequenceNumber(), $this->getQuestionCount()).$postponed . $obligatoryString);
				break;
			case 0:
			default:
				$maxpoints = $this->object->getMaximumPoints();
				if ($maxpoints == 1)
				{
					$maxpoints = " (".$maxpoints." ".$this->lng->txt("point").")";
				}
				else
				{
					$maxpoints = " (".$maxpoints." ".$this->lng->txt("points").")";
				}
				$page_gui->setPresentationTitle(sprintf($this->lng->txt("tst_position"), $this->getSequenceNumber(), $this->getQuestionCount())." - ".$this->object->getTitle().$postponed.$maxpoints  . $obligatoryString);
				break;
		}
		$presentation = $page_gui->presentation();
		if (strlen($maxpoints)) $presentation = str_replace($maxpoints, "<em>$maxpoints</em>", $presentation);
		// bugfix for non XHTML conform img tags in ILIAS Learning Module Editor
		$presentation = preg_replace("/src=\".\\//ims", "src=\"" . ILIAS_HTTP_PATH . "/", $presentation);
		return $presentation;
	}
	
	/**
	* cancel action
	*/
	function cancel()
	{
		if ($_GET["calling_test"])
		{
			$_GET["ref_id"] = $_GET["calling_test"];
			ilUtil::redirect("ilias.php?baseClass=ilObjTestGUI&cmd=questions&ref_id=".$_GET["calling_test"]);
		}
		elseif ($_GET["test_ref_id"])
		{
			$_GET["ref_id"] = $_GET["test_ref_id"];
			ilUtil::redirect("ilias.php?baseClass=ilObjTestGUI&cmd=questions&ref_id=".$_GET["test_ref_id"]);
		}
		else
		{
			if ($_GET["q_id"] > 0)
			{
				$this->ctrl->setParameterByClass("ilpageobjectgui", "q_id", $_GET["q_id"]);
				$this->ctrl->redirectByClass("ilpageobjectgui", "edit");
			}
			else
			{
				$this->ctrl->redirectByClass("ilobjquestionpoolgui", "questions");
			}
		}
	}

	function originalSyncForm($return_to = "")
	{
		if (strlen($return_to))
		{
			$this->ctrl->setParameter($this, "return_to", $return_to);
		}
		else if ($_REQUEST['return_to']) {
			$this->ctrl->setParameter($this, "return_to", $_REQUEST['return_to']);
		}
		$template = new ilTemplate("tpl.il_as_qpl_sync_original.html",TRUE, TRUE, "Modules/TestQuestionPool");
		$template->setVariable("BUTTON_YES", $this->lng->txt("yes"));
		$template->setVariable("BUTTON_NO", $this->lng->txt("no"));
		$template->setVariable("FORM_ACTION", $this->ctrl->getFormAction($this));
		$template->setVariable("TEXT_SYNC", $this->lng->txt("confirm_sync_questions"));
		$this->tpl->setVariable("ADM_CONTENT", $template->get());
	}
	
	function sync()
	{
		$original_id = $this->object->original_id;
		if ($original_id)
		{
			$this->object->syncWithOriginal();
		}
		if (strlen($_GET["return_to"]))
		{
			$this->ctrl->redirect($this, $_GET["return_to"]);
		}
		else
		{
			$_GET["ref_id"] = $_GET["calling_test"];
			ilUtil::redirect("ilias.php?baseClass=ilObjTestGUI&cmd=questions&ref_id=".$_GET["calling_test"]);
		}
	}

	function cancelSync()
	{
		if (strlen($_GET["return_to"]))
		{
			$this->ctrl->redirect($this, $_GET["return_to"]);
		}
		else
		{
			$_GET["ref_id"] = $_GET["calling_test"];
			ilUtil::redirect("ilias.php?baseClass=ilObjTestGUI&cmd=questions&ref_id=".$_GET["calling_test"]);
		}
	}
		
	/**
	* Saves the feedback for a single choice question
	*
	* Saves the feedback for a single choice question
	*
	* @access public
	*/
	function saveFeedback()
	{
		global $ilUser;
		
		$originalexists = $this->object->_questionExistsInPool($this->object->original_id);
		include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
		if ($_GET["calling_test"] && $originalexists && assQuestion::_isWriteable($this->object->original_id, $ilUser->getId()))
		{
			$this->originalSyncForm("feedback");
		}
		else
		{
			ilUtil::sendSuccess($this->lng->txt("msg_obj_modified"), false);
			$this->feedback();
		}
	}
	
	/**
	* save question
	*/
	function saveEdit()
	{
		global $ilUser;

		$result = $this->writePostData();
		if ($result == 0)
		{
			$ilUser->setPref("tst_lastquestiontype", $this->object->getQuestionType());
			$ilUser->writePref("tst_lastquestiontype", $this->object->getQuestionType());
			$this->object->saveToDb();
			$originalexists = $this->object->_questionExists($this->object->original_id);
			include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
			if ($_GET["calling_test"] && $originalexists && assQuestion::_isWriteable($this->object->original_id, $ilUser->getId()))
			{
				$this->ctrl->redirect($this, "originalSyncForm");
			}
			elseif ($_GET["calling_test"])
			{
				$_GET["ref_id"] = $_GET["calling_test"];
				ilUtil::redirect("ilias.php?baseClass=ilObjTestGUI&cmd=questions&ref_id=".$_GET["calling_test"]);
				return;
			}
			elseif ($_GET["test_ref_id"])
			{
				include_once ("./Modules/Test/classes/class.ilObjTest.php");
				$_GET["ref_id"] = $_GET["test_ref_id"];
				$test =& new ilObjTest($_GET["test_ref_id"], true);
				$test->insertQuestion($this->object->getId());
				ilUtil::redirect("ilias.php?baseClass=ilObjTestGUI&cmd=questions&ref_id=".$_GET["test_ref_id"]);
			}
			else
			{
				$this->ctrl->setParameter($this, "q_id", $this->object->getId());
				$this->editQuestion();
				if (strcmp($_SESSION["info"], "") != 0)
				{
					ilUtil::sendSuccess($_SESSION["info"] . "<br />" . $this->lng->txt("msg_obj_modified"), false);
				}
				else
				{
					ilUtil::sendSuccess($this->lng->txt("msg_obj_modified"), false);
				}
				$this->ctrl->setParameterByClass("ilpageobjectgui", "q_id", $this->object->getId());
				$this->ctrl->redirectByClass("ilpageobjectgui", "edit");
			}
		}
	}

	/**
	* save question
	*/
	function save()
	{
		global $ilUser;
		$old_id = $_GET["q_id"];
		$result = $this->writePostData();

		if ($result == 0)
		{
			$ilUser->setPref("tst_lastquestiontype", $this->object->getQuestionType());
			$ilUser->writePref("tst_lastquestiontype", $this->object->getQuestionType());
			$this->object->saveToDb();
			$originalexists = $this->object->_questionExistsInPool($this->object->original_id);



			include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
			if ($_GET["calling_test"] && $originalexists && assQuestion::_isWriteable($this->object->original_id, $ilUser->getId()))
			{
				$this->ctrl->setParameter($this, 'return_to', 'editQuestion');
				$this->ctrl->redirect($this, "originalSyncForm");
				return;
			}
			elseif ($_GET["calling_test"])
			{
				require_once 'Modules/Test/classes/class.ilObjTest.php';
				$test = new ilObjTest($_GET["calling_test"]);
				if (!assQuestion::_questionExistsInTest($this->object->getId(), $test->getTestId()))
				{
				    include_once ("./Modules/Test/classes/class.ilObjTest.php");
				    $_GET["ref_id"] = $_GET["calling_test"];
				    $test =& new ilObjTest($_GET["calling_test"], true);
				    $new_id = $test->insertQuestion($this->object->getId());

				    if(isset($_REQUEST['prev_qid'])) {
					$test->moveQuestionAfter($this->object->getId() + 1, $_REQUEST['prev_qid']);
				    }

				    $this->ctrl->setParameter($this, 'q_id', $new_id);
				    $this->ctrl->setParameter($this, 'calling_test', $_GET['calling_test']);
				    #$this->ctrl->setParameter($this, 'test_ref_id', false);
				}
				$this->ctrl->redirect($this, 'editQuestion');
			    
			}
			else
			{
				$this->callNewIdListeners($this->object->getId());
				
				if ($this->object->getId() !=  $old_id)
				{
					// first save
					$this->ctrl->setParameterByClass($_GET["cmdClass"], "q_id", $this->object->getId());
					$this->ctrl->setParameterByClass($_GET["cmdClass"], "sel_question_types", $_GET["sel_question_types"]);					
					ilUtil::sendSuccess($this->lng->txt("msg_obj_modified"), true);

					//global $___test_express_mode;
					/**
					 * in express mode, so add question to test directly
					 */
					if($_REQUEST['prev_qid']) {
						$test->moveQuestionAfter($_REQUEST['prev_qid'], $this->object->getId());
					}
					if(/*$___test_express_mode || */$_REQUEST['express_mode']) {

						include_once ("./Modules/Test/classes/class.ilObjTest.php");
						$test =& new ilObjTest($_GET["ref_id"], true);
						$test->insertQuestion($this->object->getId());
						require_once 'Modules/Test/classes/class.ilTestExpressPage.php';
						$_REQUEST['q_id'] = $this->object->getId();
						ilUtil::redirect(ilTestExpressPage::getReturnToPageLink());
					}

					$this->ctrl->redirectByClass($_GET["cmdClass"], "editQuestion");
				}
				if (strcmp($_SESSION["info"], "") != 0)
				{
					ilUtil::sendSuccess($_SESSION["info"] . "<br />" . $this->lng->txt("msg_obj_modified"), true);
				}
				else
				{
					ilUtil::sendSuccess($this->lng->txt("msg_obj_modified"), true);
				}
				$this->ctrl->redirect($this, 'editQuestion');
			}
		}
	}

	/**
	* save question
	*/
	function saveReturn()
	{
		global $ilUser;
		$old_id = $_GET["q_id"];
		$result = $this->writePostData();
		if ($result == 0)
		{
			$ilUser->setPref("tst_lastquestiontype", $this->object->getQuestionType());
			$ilUser->writePref("tst_lastquestiontype", $this->object->getQuestionType());
			$this->object->saveToDb();
			$originalexists = $this->object->_questionExistsInPool($this->object->original_id);
			include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
			if ($_GET["calling_test"] && $originalexists && assQuestion::_isWriteable($this->object->original_id, $ilUser->getId()))
			{
				$this->ctrl->redirect($this, "originalSyncForm");
				return;
			}
			elseif ($_GET["calling_test"])
			{
			    require_once 'Modules/Test/classes/class.ilObjTest.php';
			    $test = new ilObjTest($_GET["calling_test"]);
			    #var_dump(assQuestion::_questionExistsInTest($this->object->getId(), $test->getTestId()));
			    $q_id = $this->object->getId();
			    if (!assQuestion::_questionExistsInTest($this->object->getId(), $test->getTestId()))
			    {
				include_once ("./Modules/Test/classes/class.ilObjTest.php");
				$_GET["ref_id"] = $_GET["calling_test"];
				$test =& new ilObjTest($_GET["calling_test"], true);
				$new_id = $test->insertQuestion($this->object->getId());
				$q_id = $new_id;
				if(isset($_REQUEST['prev_qid'])) {
				    $test->moveQuestionAfter($this->object->getId() + 1, $_REQUEST['prev_qid']);
				}

				$this->ctrl->setParameter($this, 'q_id', $new_id);
				$this->ctrl->setParameter($this, 'calling_test', $_GET['calling_test']);
				#$this->ctrl->setParameter($this, 'test_ref_id', false);

			    }

			    if(/*$___test_express_mode || */$_REQUEST['test_express_mode']) {
				ilUtil::redirect(ilTestExpressPage::getReturnToPageLink($q_id));
			    }
			    else
			    {
				ilUtil::redirect("ilias.php?baseClass=ilObjTestGUI&cmd=questions&ref_id=".$_GET["calling_test"]);
			    }
			}
			else
			{
				if ($this->object->getId() !=  $old_id)
				{
					$this->callNewIdListeners($this->object->getId());
					ilUtil::sendSuccess($this->lng->txt("msg_obj_modified"), true);
					$this->ctrl->redirectByClass("ilobjquestionpoolgui", "questions");
				}
				if (strcmp($_SESSION["info"], "") != 0)
				{
					ilUtil::sendSuccess($_SESSION["info"] . "<br />" . $this->lng->txt("msg_obj_modified"), true);
				}
				else
				{
					ilUtil::sendSuccess($this->lng->txt("msg_obj_modified"), true);
				}
				    $this->ctrl->redirectByClass("ilobjquestionpoolgui", "questions");
				}
			}
		}

	/**
	* apply changes
	*/
	function apply()
	{
		$this->writePostData();
		$this->object->saveToDb();
		$this->ctrl->setParameter($this, "q_id", $this->object->getId());
		$this->editQuestion();
	}
	
	/**
	* get context path in content object tree
	*
	* @param	int		$a_endnode_id		id of endnode
	* @param	int		$a_startnode_id		id of startnode
	*/
	function getContextPath($cont_obj, $a_endnode_id, $a_startnode_id = 1)
	{
		$path = "";

		$tmpPath = $cont_obj->getLMTree()->getPathFull($a_endnode_id, $a_startnode_id);

		// count -1, to exclude the learning module itself
		for ($i = 1; $i < (count($tmpPath) - 1); $i++)
		{
			if ($path != "")
			{
				$path .= " > ";
			}

			$path .= $tmpPath[$i]["title"];
		}

		return $path;
	}

	function setSequenceNumber($nr) 
	{
		$this->sequence_no = $nr;
	}
	
	function getSequenceNumber() 
	{
		return $this->sequence_no;
	}
	
	function setQuestionCount($a_question_count)
	{
		$this->question_count = $a_question_count;
	}
	
	function getQuestionCount()
	{
		return $this->question_count;
	}
	
	function getErrorMessage()
	{
		return $this->errormessage;
	}
	
	function setErrorMessage($errormessage)
	{
		$this->errormessage = $errormessage;
	}

	function addErrorMessage($errormessage)
	{
		$this->errormessage .= ((strlen($this->errormessage)) ? "<br />" : "") . $errormessage;
	}
	
	function outAdditionalOutput()
	{
	}

	/**
	* Returns the question type string
	*
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
	* Returns a HTML value attribute
	*
	* @param mixed $a_value A given text or value
	* @result string The value as HTML value attribute
	* @access public
	*/
	public function getAsValueAttribute($a_value)
	{
		$result = "";
		if (strlen($a_value))
		{
			$result = " value=\"$a_value\" ";
		}
		return $result;
	}

	// scorm2004-start
	/**
	* Add a listener that is notified with the new question ID, when
	* a new question is saved
	*/
	function addNewIdListener(&$a_object, $a_method, $a_parameters = "")
	{
		$cnt = $this->new_id_listener_cnt;
		$this->new_id_listeners[$cnt]["object"] =& $a_object;
		$this->new_id_listeners[$cnt]["method"] = $a_method;
		$this->new_id_listeners[$cnt]["parameters"] = $a_parameters;
		$this->new_id_listener_cnt++;
	}

	/**
	* Call the new id listeners
	*/
	function callNewIdListeners($a_new_id)
	{

		for ($i=0; $i<$this->new_id_listener_cnt; $i++)
		{
			$this->new_id_listeners[$i]["parameters"]["new_id"] = $a_new_id;
			$object =& $this->new_id_listeners[$i]["object"];
			$method = $this->new_id_listeners[$i]["method"];
			$parameters = $this->new_id_listeners[$i]["parameters"];
//var_dump($object);
//var_dump($method);
//var_dump($parameters);

			$object->$method($parameters);
		}
	}
	
	/**
	* Set Self-Assessment Editing Mode.
	*
	* @param	boolean	$a_selfassessmenteditingmode	Self-Assessment Editing Mode
	*/
	function setSelfAssessmentEditingMode($a_selfassessmenteditingmode)
	{
		$this->selfassessmenteditingmode = $a_selfassessmenteditingmode;
	}

	/**
	* Get Self-Assessment Editing Mode.
	*
	* @return	boolean	Self-Assessment Editing Mode
	*/
	function getSelfAssessmentEditingMode()
	{
		return $this->selfassessmenteditingmode;
	}

	/**
	 * Set prevent rte usage
	 *
	 * @param	boolean	prevent rte usage
	 */
	function setPreventRteUsage($a_val)
	{
		$this->prevent_rte_usage = $a_val;
	}

	/**
	 * Get prevent rte usage
	 *
	 * @return	boolean	prevent rte usage
	 */
	function getPreventRteUsage()
	{
		return $this->prevent_rte_usage;
	}

	/**
	* Set  Default Nr of Tries
	*
	* @param	int	$a_defaultnroftries		Default Nr. of Tries
	*/
	function setDefaultNrOfTries($a_defaultnroftries)
	{
		$this->defaultnroftries = $a_defaultnroftries;
	}
	
	/**
	* Get Default Nr of Tries
	*
	* @return	int	Default Nr of Tries
	*/
	function getDefaultNrOfTries()
	{
		return $this->defaultnroftries;
	}
	
	/**
	* Add the command buttons of a question properties form
	*/
	function addQuestionFormCommandButtons($form)
	{
		//if (!$this->getSelfAssessmentEditingMode() && !$_GET["calling_test"]) $form->addCommandButton("saveEdit", $this->lng->txt("save_edit"));
		if(!$this->getSelfAssessmentEditingMode())
		{
			$form->addCommandButton("saveReturn", $this->lng->txt("save_return"));
		}
		$form->addCommandButton("save", $this->lng->txt("save"));
	}
	
	/**
	* Add basic question form properties:
	* assessment: title, author, description, question, working time
	*
	* @return	int	Default Nr of Tries
	*/
	function addBasicQuestionFormProperties($form)
	{
	    // title
		$title = new ilTextInputGUI($this->lng->txt("title"), "title");
		$title->setValue($this->object->getTitle());
		$title->setRequired(TRUE);
		$form->addItem($title);

		if (!$this->getSelfAssessmentEditingMode())
		{
			// author
			$author = new ilTextInputGUI($this->lng->txt("author"), "author");
			$author->setValue($this->object->getAuthor());
			$author->setRequired(TRUE);
			$form->addItem($author);
	
			// description
			$description = new ilTextInputGUI($this->lng->txt("description"), "comment");
			$description->setValue($this->object->getComment());
			$description->setRequired(FALSE);
			$form->addItem($description);
		}
		else
		{
			// author as hidden field
			$hi = new ilHiddenInputGUI("author");
			$author = ilUtil::prepareFormOutput($this->object->getAuthor());
			if (trim($author) == "")
			{
				$author = "-";
			}
			$hi->setValue($author);
			$form->addItem($hi);
			
		}

		// questiontext
		$question = new ilTextAreaInputGUI($this->lng->txt("question"), "question");
		$question->setValue($this->object->prepareTextareaOutput($this->object->getQuestion()));
		$question->setRequired(TRUE);
		$question->setRows(10);
		$question->setCols(80);
		if (!$this->getSelfAssessmentEditingMode())
		{
			$question->setUseRte(TRUE);
			include_once "./Services/AdvancedEditing/classes/class.ilObjAdvancedEditing.php";
			$question->setRteTags(ilObjAdvancedEditing::_getUsedHTMLTags("assessment"));
			$question->addPlugin("latex");
			$question->addButton("latex");
			$question->addButton("pastelatex");
			$question->setRTESupport($this->object->getId(), "qpl", "assessment", null, false, '3.4.7');
		}
		else
		{
			$question->setRteTags(self::getSelfAssessmentTags());
			$question->setUseTagsForRteOnly(false);
		}
		$form->addItem($question);

		if (!$this->getSelfAssessmentEditingMode())
		{
			// duration
			$duration = new ilDurationInputGUI($this->lng->txt("working_time"), "Estimated");
			$duration->setShowHours(TRUE);
			$duration->setShowMinutes(TRUE);
			$duration->setShowSeconds(TRUE);
			$ewt = $this->object->getEstimatedWorkingTime();
			$duration->setHours($ewt["h"]);
			$duration->setMinutes($ewt["m"]);
			$duration->setSeconds($ewt["s"]);
			$duration->setRequired(FALSE);
			$form->addItem($duration);
		}
		else
		{
			// number of tries
			if (strlen($this->object->getNrOfTries()))
			{
				$nr_tries = $this->object->getNrOfTries();
			}
			else
			{
				$nr_tries = $this->getDefaultNrOfTries();
			}
			/*if ($nr_tries <= 0)
			{
				$nr_tries = 1;
			}*/
			
			if ($nr_tries < 0)
			{
				$nr_tries = 0;
			}
			
			$ni = new ilNumberInputGUI($this->lng->txt("qst_nr_of_tries"), "nr_of_tries");
			$ni->setValue($nr_tries);
			//$ni->setMinValue(1);
			$ni->setMinValue(0);
			$ni->setSize(5);
			$ni->setMaxLength(5);
			$ni->setRequired(true);
			$form->addItem($ni);
		}
	}

	/**
	 * Get tags allowed in question tags in self assessment mode
	 * @return array array of tags
	 */
	function getSelfAssessmentTags()
	{
		// set tags we allow in self assessment mode
		$st = ilUtil::getSecureTags();
		
		// we allow these tags, since they are typically used in the Tiny Assessment editor
		// and should not be deleted, if questions are copied from pools to learning modules
		$not_supported = array("img", "p");
		$tags = array();
		foreach ($st as $s)
		{
			if (!in_array($s, $not_supported))
			{
				$tags[] = $s;
			}
		}

		return $tags;
	}
	
	
	/**
	* Returns the answer generic feedback depending on the results of the question
	*
	* @deprecated Use getGenericFeedbackOutput instead.
	* @param integer $active_id Active ID of the user
	* @param integer $pass Active pass
	* @return string HTML Code with the answer specific feedback
	* @access public
	*/
	function getAnswerFeedbackOutput($active_id, $pass)
	{
		$output = "";
		include_once "./Modules/Test/classes/class.ilObjTest.php";
		$manual_feedback = ilObjTest::getManualFeedback($active_id, $this->object->getId(), $pass);
		if (strlen($manual_feedback))
		{
			return $manual_feedback;
		}
		$correct_feedback = $this->object->getFeedbackGeneric(1);
		$incorrect_feedback = $this->object->getFeedbackGeneric(0);
		if (strlen($correct_feedback.$incorrect_feedback))
		{
			$reached_points = $this->object->calculateReachedPoints($active_id, $pass);
			$max_points = $this->object->getMaximumPoints();
			if ($reached_points == $max_points)
			{
				$output = $correct_feedback;
			}
			else
			{
				$output = $incorrect_feedback;
			}
		}
		return $this->object->prepareTextareaOutput($output, TRUE);
	}

	/**
	 * Returns the answer specific feedback for the question

	 *
	 * @param integer $active_id Active ID of the user
	 * @param integer $pass Active pass
	 * @return string HTML Code with the answer specific feedback
	 * @access public
	 */
	function getGenericFeedbackOutput($active_id, $pass)
	{
		$output = "";
		include_once "./Modules/Test/classes/class.ilObjTest.php";
		$manual_feedback = ilObjTest::getManualFeedback($active_id, $this->object->getId(), $pass);
		if (strlen($manual_feedback))
		{
			return $manual_feedback;
		}
		$correct_feedback = $this->object->getFeedbackGeneric(1);
		$incorrect_feedback = $this->object->getFeedbackGeneric(0);
		if (strlen($correct_feedback.$incorrect_feedback))
		{
			$reached_points = $this->object->calculateReachedPoints($active_id, $pass);
			$max_points = $this->object->getMaximumPoints();
			if ($reached_points == $max_points)
			{
				$output = $correct_feedback;
			}
			else
			{
				$output = $incorrect_feedback;
			}
		}
		return $this->object->prepareTextareaOutput($output, TRUE);
	}

	/**
	 * Returns the answer specific feedback for the question
	 * 
	 * This method should be overwritten by the actual question.
	 * 
	 * @todo Mark this method abstract!
	 * @param integer $active_id Active ID of the user
	 * @param integer $pass Active pass
	 * @return string HTML Code with the answer specific feedback
	 * @access public
	 */
	abstract function getSpecificFeedbackOutput($active_id, $pass);

	/**
	* Creates the output of the feedback page for the question
	*
	* @access public
	*/
	function feedback($checkonly = false)
	{
		$save = (strcmp($this->ctrl->getCmd(), "saveFeedback") == 0) ? TRUE : FALSE;
		include_once("./Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this));
		$form->setTitle($this->lng->txt('feedback_answers'));
		$form->setTableWidth("100%");
		$form->setId("feedback");

		$complete = new ilTextAreaInputGUI($this->lng->txt("feedback_complete_solution"), "feedback_complete");
		$complete->setValue($this->object->prepareTextareaOutput($this->object->getFeedbackGeneric(1)));
		$complete->setRequired(false);
		$complete->setRows(10);
		$complete->setCols(80);
		if (!$this->getPreventRteUsage())
		{
			$complete->setUseRte(true);
		}
		include_once "./Services/AdvancedEditing/classes/class.ilObjAdvancedEditing.php";
		$complete->setRteTags(ilObjAdvancedEditing::_getUsedHTMLTags("assessment"));
		$complete->addPlugin("latex");
		$complete->addButton("latex");
		$complete->addButton("pastelatex");
		$complete->setRTESupport($this->object->getId(), "qpl", "assessment", null, false, '3.4.7');
		$form->addItem($complete);

		$incomplete = new ilTextAreaInputGUI($this->lng->txt("feedback_incomplete_solution"), "feedback_incomplete");
		$incomplete->setValue($this->object->prepareTextareaOutput($this->object->getFeedbackGeneric(0)));
		$incomplete->setRequired(false);
		$incomplete->setRows(10);
		$incomplete->setCols(80);
		if (!$this->getPreventRteUsage())
		{
			$incomplete->setUseRte(true);
		}
		include_once "./Services/AdvancedEditing/classes/class.ilObjAdvancedEditing.php";
		$incomplete->setRteTags(ilObjAdvancedEditing::_getUsedHTMLTags("assessment"));
		$incomplete->addPlugin("latex");
		$incomplete->addButton("latex");
		$incomplete->addButton("pastelatex");
		$incomplete->setRTESupport($this->object->getId(), "qpl", "assessment", null, false, '3.4.7');
		$form->addItem($incomplete);

		global $ilAccess;
		if ($ilAccess->checkAccess("write", "", $_GET['ref_id']) || $this->getSelfAssessmentEditingMode())
		{
			$form->addCommandButton("saveFeedback", $this->lng->txt("save"));
		}

		if ($save)
		{
			$form->setValuesByPost();
			$errors = !$form->checkInput();
			$form->setValuesByPost(); // again, because checkInput now performs the whole stripSlashes handling and we need this if we don't want to have duplication of backslashes
		}
		if (!$checkonly) $this->tpl->setVariable("ADM_CONTENT", $form->getHTML());
		return $errors;
	}
	
	public function outQuestionType()
	{
		include_once "./Services/UICore/classes/class.ilTemplate.php";
		$template = new ilTemplate("tpl.il_as_qpl_questiontype.html", TRUE, TRUE, "Modules/TestQuestionPool");
		$count = $this->object->isInUse();
		if (assQuestion::_questionExistsInPool($this->object->getId()) && $count)
		{
			global $rbacsystem;
			if ($rbacsystem->checkAccess("write", $_GET["ref_id"]))
			{
				$template->setCurrentBlock("infosign");
				$template->setVariable("INFO_IMG_SRC", ilUtil::getImagePath("messagebox_tip.png"));
				$template->setVariable("INFO_IMG_ALT", sprintf($this->lng->txt("qpl_question_is_in_use"), $count));
				$template->setVariable("INFO_IMG_TITLE", sprintf($this->lng->txt("qpl_question_is_in_use"), $count));
				$template->parseCurrentBlock();
			}
		}
		$template->setVariable("TEXT_QUESTION_TYPE", assQuestion::_getQuestionTypeName($this->object->getQuestionType()));
		return $template->get();
	}

	/**
	* Allows to add suggested solutions for questions
	*
	* @access public
	*/
	public function suggestedsolution()
	{
		global $ilUser;
		global $ilAccess;
		
		if ($_POST["deleteSuggestedSolution"] == 1)
		{
			$this->object->deleteSuggestedSolutions();
			ilUtil::sendSuccess($this->lng->txt("msg_obj_modified"), true);
			$this->ctrl->redirect($this, "suggestedsolution");
		}

		$save = (is_array($_POST["cmd"]) && array_key_exists("suggestedsolution", $_POST["cmd"])) ? TRUE : FALSE;
		$output = "";
		$solution_array = $this->object->getSuggestedSolution(0);
		$options = array(
			"lm" => $this->lng->txt("obj_lm"),
			"st" => $this->lng->txt("obj_st"),
			"pg" => $this->lng->txt("obj_pg"),
			"git" => $this->lng->txt("glossary_term"),
			"file" => $this->lng->txt("fileDownload"),
			"text" => $this->lng->txt("solutionText")
		);

		if ((strcmp($_POST["solutiontype"], "file") == 0) && (strcmp($solution_array["type"], "file") != 0))
		{
			$solution_array = array(
				"type" => "file"
			);
		} 
		elseif ((strcmp($_POST["solutiontype"], "text") == 0) && (strcmp($solution_array["type"], "text") != 0))
		{
			$solution_array = array(
				"type" => "text",
				"value" => $this->getSolutionOutput(0, NULL, FALSE, FALSE, TRUE, FALSE, TRUE)
			);
		}
		if ($save && strlen($_POST["filename"]))
		{
			$solution_array["value"]["filename"] = $_POST["filename"];
		}
		if ($save && strlen($_POST["solutiontext"]))
		{
			$solution_array["value"] = $_POST["solutiontext"];
		}
		include_once("./Services/Form/classes/class.ilPropertyFormGUI.php");
		if (count($solution_array))
		{
			$form = new ilPropertyFormGUI();
			$form->setFormAction($this->ctrl->getFormAction($this));
			$form->setTitle($this->lng->txt("solution_hint"));
			$form->setMultipart(TRUE);
			$form->setTableWidth("100%");
			$form->setId("suggestedsolutiondisplay");

			// suggested solution output
			include_once "./Modules/TestQuestionPool/classes/class.ilSolutionTitleInputGUI.php";
			$title = new ilSolutionTitleInputGUI($this->lng->txt("showSuggestedSolution"), "solutiontype");
			$template = new ilTemplate("tpl.il_as_qpl_suggested_solution_input_presentation.html", TRUE, TRUE, "Modules/TestQuestionPool");
			if (strlen($solution_array["internal_link"]))
			{
				$href = assQuestion::_getInternalLinkHref($solution_array["internal_link"]);
				$template->setCurrentBlock("preview");
				$template->setVariable("TEXT_SOLUTION", $this->lng->txt("solution_hint"));
				$template->setVariable("VALUE_SOLUTION", " <a href=\"$href\" target=\"content\">" . $this->lng->txt("view"). "</a> ");
				$template->parseCurrentBlock();
			}
			elseif ((strcmp($solution_array["type"], "file") == 0) && (is_array($solution_array["value"])))
			{
				$href = $this->object->getSuggestedSolutionPathWeb() . $solution_array["value"]["name"];
				$template->setCurrentBlock("preview");
				$template->setVariable("TEXT_SOLUTION", $this->lng->txt("solution_hint"));
				$template->setVariable("VALUE_SOLUTION", " <a href=\"$href\" target=\"content\">" . ilUtil::prepareFormOutput((strlen($solution_array["value"]["filename"])) ? $solution_array["value"]["filename"] : $solution_array["value"]["name"]). "</a> ");
				$template->parseCurrentBlock();
			}
			$template->setVariable("TEXT_TYPE", $this->lng->txt("type"));
			$template->setVariable("VALUE_TYPE", $options[$solution_array["type"]]);
			$title->setHtml($template->get());
			$deletesolution = new ilCheckboxInputGUI("", "deleteSuggestedSolution");
			$deletesolution->setOptionTitle($this->lng->txt("deleteSuggestedSolution"));
			$title->addSubItem($deletesolution);
			$form->addItem($title);

			if (strcmp($solution_array["type"], "file") == 0)
			{
				// file
				$file = new ilFileInputGUI($this->lng->txt("fileDownload"), "file");
				$file->setRequired(TRUE);
				$file->enableFileNameSelection("filename");
				//$file->setSuffixes(array("doc","xls","png","jpg","gif","pdf"));
				if ($_FILES["file"]["tmp_name"])
				{
					if (!file_exists($this->object->getSuggestedSolutionPath())) ilUtil::makeDirParents($this->object->getSuggestedSolutionPath());
					$res = ilUtil::moveUploadedFile($_FILES["file"]["tmp_name"], $_FILES["file"]["name"], $this->object->getSuggestedSolutionPath() . $_FILES["file"]["name"]);
					if ($res)
					{
						// remove an old file download
						if (is_array($solution_array["value"])) @unlink($this->object->getSuggestedSolutionPath() . $solution_array["value"]["name"]);
						$file->setValue($_FILES["file"]["name"]);
						$this->object->saveSuggestedSolution("file", "", 0, array("name" => $_FILES["file"]["name"], "type" => $_FILES["file"]["type"], "size" => $_FILES["file"]["size"], "filename" => $_POST["filename"]));
						$originalexists = $this->object->_questionExistsInPool($this->object->original_id);
						if ($_GET["calling_test"] && $originalexists && assQuestion::_isWriteable($this->object->original_id, $ilUser->getId()))
						{
							return $this->originalSyncForm("suggestedsolution");
						}
						else
						{
							ilUtil::sendSuccess($this->lng->txt("suggested_solution_added_successfully"), TRUE);
							$this->ctrl->redirect($this, "suggestedsolution");
						}
					}
					else
					{
						ilUtil::sendInfo($res);
					}
				}
				else
				{
					if (is_array($solution_array["value"]))
					{
						$file->setValue($solution_array["value"]["name"]);
						$file->setFilename((strlen($solution_array["value"]["filename"])) ? $solution_array["value"]["filename"] : $solution_array["value"]["name"]);
					}
				}
				$form->addItem($file);
				$hidden = new ilHiddenInputGUI("solutiontype");
				$hidden->setValue("file");
				$form->addItem($hidden);
			}
			else if (strcmp($solution_array["type"], "text") == 0)
			{
				$question = new ilTextAreaInputGUI($this->lng->txt("solutionText"), "solutiontext");
				$question->setValue($this->object->prepareTextareaOutput($solution_array["value"]));
				$question->setRequired(TRUE);
				$question->setRows(10);
				$question->setCols(80);
				$question->setUseRte(TRUE);
				$question->addPlugin("latex");
				$question->addButton("latex");
				$question->setRTESupport($this->object->getId(), "qpl", "assessment", null, false, '3.4.7');
				$hidden = new ilHiddenInputGUI("solutiontype");
				$hidden->setValue("text");
				$form->addItem($hidden);
				$form->addItem($question);
			}
			if ($ilAccess->checkAccess("write", "", $_GET['ref_id']))	$form->addCommandButton("suggestedsolution", $this->lng->txt("save"));
			
			if ($save)
			{
				if ($form->checkInput())
				{
					switch ($solution_array["type"])
					{
						case "file":
							$this->object->saveSuggestedSolution("file", "", 0, array("name" => $solution_array["value"]["name"], "type" => $solution_array["value"]["type"], "size" => $solution_array["value"]["size"], "filename" => $_POST["filename"]));
							break;
						case "text":
							$this->object->saveSuggestedSolution("text", "", 0, $solution_array["value"]);
							break;
					}
					$originalexists = $this->object->_questionExistsInPool($this->object->original_id);
					if ($_GET["calling_test"] && $originalexists && assQuestion::_isWriteable($this->object->original_id, $ilUser->getId()))
					{
						return $this->originalSyncForm("suggestedsolution");
					}
					else
					{
						ilUtil::sendSuccess($this->lng->txt("msg_obj_modified"), true);
						$this->ctrl->redirect($this, "suggestedsolution");
					}
				}
			}
			$output = $form->getHTML();
		}
		
		$savechange = (strcmp($this->ctrl->getCmd(), "saveSuggestedSolution") == 0) ? TRUE : FALSE;

		$changeoutput = "";
		if ($ilAccess->checkAccess("write", "", $_GET['ref_id']))
		{
			$formchange = new ilPropertyFormGUI();
			$formchange->setFormAction($this->ctrl->getFormAction($this));
			$formchange->setTitle((count($solution_array)) ? $this->lng->txt("changeSuggestedSolution") : $this->lng->txt("addSuggestedSolution"));
			$formchange->setMultipart(FALSE);
			$formchange->setTableWidth("100%");
			$formchange->setId("suggestedsolution");

			$solutiontype = new ilRadioMatrixInputGUI($this->lng->txt("suggestedSolutionType"), "solutiontype");
			$solutiontype->setOptions($options);
			if (count($solution_array))
			{
				$solutiontype->setValue($solution_array["type"]);
			}
			$solutiontype->setRequired(TRUE);
			$formchange->addItem($solutiontype);

			$formchange->addCommandButton("saveSuggestedSolution", $this->lng->txt("select"));

			if ($savechange) 
			{
				$formchange->checkInput();
			}
			$changeoutput = $formchange->getHTML();
		}
		
		$this->tpl->setVariable("ADM_CONTENT", $changeoutput . $output);
	}
	
	public function outSolutionExplorer()
	{
		global $tree;

		include_once("./Modules/TestQuestionPool/classes/class.ilSolutionExplorer.php");
		$type = $_GET["link_new_type"];
		$search = $_GET["search_link_type"];
		$this->ctrl->setParameter($this, "link_new_type", $type);
		$this->ctrl->setParameter($this, "search_link_type", $search);
		$this->ctrl->saveParameter($this, array("subquestion_index", "link_new_type", "search_link_type"));

		ilUtil::sendInfo($this->lng->txt("select_object_to_link"));

		$parent_ref_id = $tree->getParentId($_GET["ref_id"]);
		$exp = new ilSolutionExplorer($this->ctrl->getLinkTarget($this, 'suggestedsolution'), get_class($this));
		$exp->setExpand($_GET['expand_sol'] ? $_GET['expand_sol'] : $parent_ref_id);
		$exp->setExpandTarget($this->ctrl->getLinkTarget($this, 'outSolutionExplorer'));
		$exp->setTargetGet("ref_id");
		$exp->setRefId($_GET["ref_id"]);
		$exp->addFilter($type);
		$exp->setSelectableType($type);
		if(isset($_GET['expandCurrentPath']) && $_GET['expandCurrentPath'])
		{
			$exp->expandPathByRefId($parent_ref_id);
		}

		// build html-output
		$exp->setOutput(0);

		$template = new ilTemplate("tpl.il_as_qpl_explorer.html", TRUE, TRUE, "Modules/TestQuestionPool");
		$template->setVariable("EXPLORER_TREE",$exp->getOutput());
		$template->setVariable("BUTTON_CANCEL",$this->lng->txt("cancel"));
		$template->setVariable("FORMACTION",$this->ctrl->getFormAction($this, "suggestedsolution"));
		$this->tpl->setVariable("ADM_CONTENT", $template->get());
	}
	
	public function saveSuggestedSolution()
	{
		global $tree;

		include_once("./Modules/TestQuestionPool/classes/class.ilSolutionExplorer.php");
		switch ($_POST["solutiontype"])
		{
			case "lm":
				$type = "lm";
				$search = "lm";
				break;
			case "git":
				$type = "glo";
				$search = "glo";
				break;
			case "st":
				$type = "lm";
				$search = "st";
				break;
			case "pg":
				$type = "lm";
				$search = "pg";
				break;
			case "file":
			case "text":
				return $this->suggestedsolution();
				break;
			default:
				return $this->suggestedsolution();
				break;
		}
		if(isset($_POST['solutiontype']))
		{
			$this->ctrl->setParameter($this, 'expandCurrentPath', 1);
		}
		$this->ctrl->setParameter($this, "link_new_type", $type);
		$this->ctrl->setParameter($this, "search_link_type", $search);
		$this->ctrl->redirect($this, "outSolutionExplorer");
	}

	function cancelExplorer()
	{
		$this->ctrl->redirect($this, "suggestedsolution");
	}
	
	function outPageSelector()
	{
		include_once "./Modules/LearningModule/classes/class.ilLMPageObject.php";
		include_once("./Modules/LearningModule/classes/class.ilObjContentObjectGUI.php");
		$cont_obj_gui =& new ilObjContentObjectGUI("", $_GET["source_id"], true);
		$cont_obj = $cont_obj_gui->object;
		$pages = ilLMPageObject::getPageList($cont_obj->getId());
		$shownpages = array();
		$tree = $cont_obj->getLMTree();
		$chapters = $tree->getSubtree($tree->getNodeData($tree->getRootId()));
		$this->ctrl->setParameter($this, "q_id", $this->object->getId());
		$color_class = array("tblrow1", "tblrow2");
		$counter = 0;
		$template = new ilTemplate("tpl.il_as_qpl_internallink_selection.html", TRUE, TRUE, "Modules/TestQuestionPool");
		foreach ($chapters as $chapter)
		{
			$chapterpages = $tree->getChildsByType($chapter["obj_id"], "pg");
			foreach ($chapterpages as $page)
			{
				if($page["type"] == $_GET["search_link_type"])
				{
					array_push($shownpages, $page["obj_id"]);
					$template->setCurrentBlock("linktable_row");
					$template->setVariable("TEXT_LINK", $page["title"]);
					$template->setVariable("TEXT_ADD", $this->lng->txt("add"));
					$template->setVariable("LINK_HREF", $this->ctrl->getLinkTargetByClass(get_class($this), "add" . strtoupper($page["type"])) . "&" . $page["type"] . "=" . $page["obj_id"]);
					$template->setVariable("COLOR_CLASS", $color_class[$counter % 2]);
					if ($tree->isInTree($page["obj_id"]))
					{
						$path_str = $this->getContextPath($cont_obj, $page["obj_id"]);
					}
					else
					{
						$path_str = "---";
					}
					$template->setVariable("TEXT_DESCRIPTION", ilUtil::prepareFormOutput($path_str));
					$template->parseCurrentBlock();
					$counter++;
				}
			}
		}
		foreach ($pages as $page)
		{
			if (!in_array($page["obj_id"], $shownpages))
			{
				$template->setCurrentBlock("linktable_row");
				$template->setVariable("TEXT_LINK", $page["title"]);
				$template->setVariable("TEXT_ADD", $this->lng->txt("add"));
				$template->setVariable("LINK_HREF", $this->ctrl->getLinkTargetByClass(get_class($this), "add" . strtoupper($page["type"])) . "&" . $page["type"] . "=" . $page["obj_id"]);
				$template->setVariable("COLOR_CLASS", $color_class[$counter % 2]);
				$path_str = "---";
				$template->setVariable("TEXT_DESCRIPTION", ilUtil::prepareFormOutput($path_str));
				$template->parseCurrentBlock();
				$counter++;
			}
		}
		$template->setCurrentBlock("link_selection");
		$template->setVariable("BUTTON_CANCEL",$this->lng->txt("cancel"));
		$template->setVariable("TEXT_LINK_TYPE", $this->lng->txt("obj_" . $_GET["search_link_type"]));
		$template->setVariable("FORMACTION",$this->ctrl->getFormAction($this, "cancelExplorer"));
		$template->parseCurrentBlock();
		$this->tpl->setVariable("ADM_CONTENT", $template->get());
	}
	
	public function outChapterSelector()
	{
		$template = new ilTemplate("tpl.il_as_qpl_internallink_selection.html", TRUE, TRUE, "Modules/TestQuestionPool");
		$this->ctrl->setParameter($this, "q_id", $this->object->getId());
		$color_class = array("tblrow1", "tblrow2");
		$counter = 0;
		include_once("./Modules/LearningModule/classes/class.ilObjContentObjectGUI.php");
		$cont_obj_gui =& new ilObjContentObjectGUI("", $_GET["source_id"], true);
		$cont_obj = $cont_obj_gui->object;
		// get all chapters
		$ctree =& $cont_obj->getLMTree();
		$nodes = $ctree->getSubtree($ctree->getNodeData($ctree->getRootId()));
		foreach($nodes as $node)
		{
			if($node["type"] == $_GET["search_link_type"])
			{
				$template->setCurrentBlock("linktable_row");
				$template->setVariable("TEXT_LINK", $node["title"]);
				$template->setVariable("TEXT_ADD", $this->lng->txt("add"));
				$template->setVariable("LINK_HREF", $this->ctrl->getLinkTargetByClass(get_class($this), "add" . strtoupper($node["type"])) . "&" . $node["type"] . "=" . $node["obj_id"]);
				$template->setVariable("COLOR_CLASS", $color_class[$counter % 2]);
				$template->parseCurrentBlock();
				$counter++;
			}
		}
		$template->setCurrentBlock("link_selection");
		$template->setVariable("BUTTON_CANCEL",$this->lng->txt("cancel"));
		$template->setVariable("TEXT_LINK_TYPE", $this->lng->txt("obj_" . $_GET["search_link_type"]));
		$template->setVariable("FORMACTION",$this->ctrl->getFormAction($this, "cancelExplorer"));
		$template->parseCurrentBlock();
		$this->tpl->setVariable("ADM_CONTENT", $template->get());
	}

	public function outGlossarySelector()
	{
		$template = new ilTemplate("tpl.il_as_qpl_internallink_selection.html", TRUE, TRUE, "Modules/TestQuestionPool");
		$this->ctrl->setParameter($this, "q_id", $this->object->getId());
		$color_class = array("tblrow1", "tblrow2");
		$counter = 0;
		include_once "./Modules/Glossary/classes/class.ilObjGlossary.php";
		$glossary =& new ilObjGlossary($_GET["source_id"], true);
		// get all glossary items
		$terms = $glossary->getTermList();
		foreach($terms as $term)
		{
			$template->setCurrentBlock("linktable_row");
			$template->setVariable("TEXT_LINK", $term["term"]);
			$template->setVariable("TEXT_ADD", $this->lng->txt("add"));
			$template->setVariable("LINK_HREF", $this->ctrl->getLinkTargetByClass(get_class($this), "addGIT") . "&git=" . $term["id"]);
			$template->setVariable("COLOR_CLASS", $color_class[$counter % 2]);
			$template->parseCurrentBlock();
			$counter++;
		}
		$template->setCurrentBlock("link_selection");
		$template->setVariable("BUTTON_CANCEL",$this->lng->txt("cancel"));
		$template->setVariable("TEXT_LINK_TYPE", $this->lng->txt("glossary_term"));
		$template->setVariable("FORMACTION",$this->ctrl->getFormAction($this, "cancelExplorer"));
		$template->parseCurrentBlock();
		$this->tpl->setVariable("ADM_CONTENT", $template->get());
	}
	
	function linkChilds()
	{
		$this->ctrl->saveParameter($this, array("subquestion_index", "link_new_type", "search_link_type"));
		switch ($_GET["search_link_type"])
		{
			case "pg":
				return $this->outPageSelector();
				break;
			case "st":
				return $this->outChapterSelector();
				break;
			case "glo":
				return $this->outGlossarySelector();
				break;
			case "lm":
				$subquestion_index = ($_GET["subquestion_index"] > 0) ? $_GET["subquestion_index"] : 0;
				$this->object->saveSuggestedSolution("lm", "il__lm_" . $_GET["source_id"], $subquestion_index);
				ilUtil::sendSuccess($this->lng->txt("suggested_solution_added_successfully"), TRUE);
				$this->ctrl->redirect($this, "suggestedsolution");
				break;
		}
	}

	function addPG()
	{
		$subquestion_index = 0;
		if (strlen($_GET["subquestion_index"]) && $_GET["subquestion_index"] >= 0)
		{
			$subquestion_index = $_GET["subquestion_index"];
		}
		$this->object->saveSuggestedSolution("pg", "il__pg_" . $_GET["pg"], $subquestion_index);
		ilUtil::sendSuccess($this->lng->txt("suggested_solution_added_successfully"), TRUE);
		$this->ctrl->redirect($this, "suggestedsolution");
	}

	function addST()
	{
		$subquestion_index = 0;
		if (strlen($_GET["subquestion_index"]) && $_GET["subquestion_index"] >= 0)
		{
			$subquestion_index = $_GET["subquestion_index"];
		}
		$this->object->saveSuggestedSolution("st", "il__st_" . $_GET["st"], $subquestion_index);
		ilUtil::sendSuccess($this->lng->txt("suggested_solution_added_successfully"), TRUE);
		$this->ctrl->redirect($this, "suggestedsolution");
	}

	function addGIT()
	{
		$subquestion_index = 0;
		if (strlen($_GET["subquestion_index"]) && $_GET["subquestion_index"] >= 0)
		{
			$subquestion_index = $_GET["subquestion_index"];
		}
		$this->object->saveSuggestedSolution("git", "il__git_" . $_GET["git"], $subquestion_index);
		ilUtil::sendSuccess($this->lng->txt("suggested_solution_added_successfully"), TRUE);
		$this->ctrl->redirect($this, "suggestedsolution");
	}

	function isSaveCommand()
	{
	    return in_array($this->ctrl->getCmd(), array('save', 'saveEdit', 'saveReturn'));
	}
	
	public function setQuestionTabs()
	{
	}
	
	/**
	 * adds the hints tab to ilTabsGUI
	 *
	 * @global ilCtrl $ilCtrl
	 * @param ilTabsGUI $tabs
	 */
	protected function addTab_QuestionHints(ilTabsGUI $tabs)
	{
		global $ilCtrl;

		require_once 'Modules/TestQuestionPool/classes/class.ilAssQuestionHintsGUI.php';

		$reflectionClass = null;

		switch( $ilCtrl->getCmdClass() )
		{
			case 'ilassquestionhintsgui':
				
				$reflectionClass = new ReflectionClass('ilAssQuestionHintsGUI');
				break;

			case 'ilassquestionhintgui':
				
				require_once 'Modules/TestQuestionPool/classes/class.ilAssQuestionHintGUI.php';
				$reflectionClass = new ReflectionClass('ilAssQuestionHintGUI');
				break;
		}
		
		$tabCommands = array();
		
		if( $reflectionClass instanceof ReflectionClass )
			foreach($reflectionClass->getConstants() as $constName => $constValue)
				if( substr($constName, 0, strlen('CMD_')) == 'CMD_' ) $tabCommands[] = $constValue;
		
		$tabLink = $ilCtrl->getLinkTargetByClass('ilAssQuestionHintsGUI', ilAssQuestionHintsGUI::CMD_SHOW_LIST);
		
		$tabs->addTarget('tst_question_hints_tab', $tabLink, $tabCommands, $ilCtrl->getCmdClass(), '');
	}
	
	abstract public function getSolutionOutput(
		$active_id,
		$pass = NULL,
		$graphicalOutput = FALSE,
		$result_output = FALSE,
		$show_question_only = TRUE,
		$show_feedback = FALSE,
		$show_correct_solution = FALSE,
		$show_manual_scoring = FALSE,
		$show_question_text = TRUE
	);
	
	public function isAutosaveable()
	{
		return $this->object->isAutosaveable();
	}
}