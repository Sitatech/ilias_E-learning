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

include_once "./Modules/TestQuestionPool/classes/class.assQuestionGUI.php";
include_once "./Modules/Test/classes/inc.AssessmentConstants.php";

/**
* The assErrorTextGUI class encapsulates the GUI representation
* for error text questions.
*
* @author		Helmut Schottmüller <helmut.schottmueller@mac.com>
* @author		Björn Heyser <bheyser@databay.de>
* @version	$Id: class.assErrorTextGUI.php 42975 2013-06-25 14:50:49Z bheyser $
* @ingroup ModulesTestQuestionPool
* @ilctrl_iscalledby assErrorTextGUI: ilObjQuestionPoolGUI
* */
class assErrorTextGUI extends assQuestionGUI
{
	/**
	* assErrorTextGUI constructor
	*
	* The constructor takes possible arguments an creates an instance of the assOrderingHorizontalGUI object.
	*
	* @param integer $id The database id of a single choice question object
	* @access public
	*/
	function __construct($id = -1)
	{
		parent::__construct();
		include_once "./Modules/TestQuestionPool/classes/class.assErrorText.php";
		$this->object = new assErrorText();
		$this->setErrorMessage($this->lng->txt("msg_form_save_error"));
		if ($id >= 0)
		{
			$this->object->loadFromDb($id);
		}
	}

	function getCommand($cmd)
	{
		return $cmd;
	}

	/**
	* Evaluates a posted edit form and writes the form data in the question object
	*
	* @return integer A positive value, if one of the required fields wasn't set, else 0
	* @access private
	*/
	function writePostData($always = false)
	{
		$hasErrors = (!$always) ? $this->editQuestion(true) : false;
		if (!$hasErrors)
		{
			$this->object->setTitle($_POST["title"]);
			$this->object->setAuthor($_POST["author"]);
			$this->object->setComment($_POST["comment"]);
			if ($this->getSelfAssessmentEditingMode())
			{
				$this->object->setNrOfTries($_POST['nr_of_tries']);
			}
			
			include_once "./Services/AdvancedEditing/classes/class.ilObjAdvancedEditing.php";
			$questiontext = $_POST["question"];
			$this->object->setQuestion($questiontext);
			// adding estimated working time
			$this->object->setEstimatedWorkingTime(
				$_POST["Estimated"]["hh"],
				$_POST["Estimated"]["mm"],
				$_POST["Estimated"]["ss"]
			);			
			$this->object->setErrorText($_POST["errortext"]);
			$points_wrong = str_replace(",", ".", $_POST["points_wrong"]);
			if (strlen($points_wrong) == 0) $points_wrong = -1.0;
			$this->object->setPointsWrong($points_wrong);
			
			if (!$this->getSelfAssessmentEditingMode())
			{
				$this->object->setTextSize($_POST["textsize"]);
			}
			
			$this->object->flushErrorData();
			if (is_array($_POST['errordata']['key']))
			{
				foreach ($_POST['errordata']['key'] as $idx => $val)
				{
					$this->object->addErrorData($val, $_POST['errordata']['value'][$idx], $_POST['errordata']['points'][$idx]);
				}
			}
			return 0;
		}
		else
		{
			return 1;
		}
	}

	/**
	* Creates an output of the edit form for the question
	*
	* @access public
	*/
	public function editQuestion($checkonly = FALSE)
	{
		$save = $this->isSaveCommand();
		$this->getQuestionTemplate();

		include_once("./Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this));
		$form->setTitle($this->outQuestionType());
		$form->setMultipart(FALSE);
		$form->setTableWidth("100%");
		$form->setId("orderinghorizontal");

		$this->addBasicQuestionFormProperties($form);

		// errortext
		$errortext = new ilTextAreaInputGUI($this->lng->txt("errortext"), "errortext");
		$errortext->setValue(ilUtil::prepareFormOutput($this->object->getErrorText()));
		$errortext->setRequired(TRUE);
		$errortext->setInfo($this->lng->txt("errortext_info"));
		$errortext->setRows(10);
		$errortext->setCols(80);
		$form->addItem($errortext);

		if (!$this->getSelfAssessmentEditingMode())
		{
			// textsize
			$textsize = new ilNumberInputGUI($this->lng->txt("textsize"), "textsize");
			$textsize->setValue(strlen($this->object->getTextSize()) ? $this->object->getTextSize() : 100.0);
			$textsize->setInfo($this->lng->txt("textsize_errortext_info"));
			$textsize->setSize(6);
			$textsize->setSuffix("%");
			$textsize->setMinValue(10);
			$textsize->setRequired(true);
			$form->addItem($textsize);
		}
		
		if (count($this->object->getErrorData()) || $checkonly)
		{
			$header = new ilFormSectionHeaderGUI();
			$header->setTitle($this->lng->txt("errors_section"));
			$form->addItem($header);

			include_once "./Modules/TestQuestionPool/classes/class.ilErrorTextWizardInputGUI.php";
			$errordata = new ilErrorTextWizardInputGUI($this->lng->txt("errors"), "errordata");
			$values = array();
			$errordata->setKeyName($this->lng->txt('text_wrong'));
			$errordata->setValueName($this->lng->txt('text_correct'));
			$errordata->setValues($this->object->getErrorData());
			$form->addItem($errordata);

			// points for wrong selection
			$points_wrong = new ilNumberInputGUI($this->lng->txt("points_wrong"), "points_wrong");
			$points_wrong->setValue($this->object->getPointsWrong());
			$points_wrong->setInfo($this->lng->txt("points_wrong_info"));
			$points_wrong->setSize(6);
			$points_wrong->setRequired(true);
			$form->addItem($points_wrong);
		}

		$form->addCommandButton("analyze", $this->lng->txt('analyze_errortext'));
		$this->addQuestionFormCommandButtons($form);
		
		$errors = false;
	
		if ($save)
		{
			$form->setValuesByPost();
			$errors = !$form->checkInput();
			$form->setValuesByPost(); // again, because checkInput now performs the whole stripSlashes handling and we need this if we don't want to have duplication of backslashes
			if ($errors) $checkonly = false;
		}

		if (!$checkonly) $this->tpl->setVariable("QUESTION_DATA", $form->getHTML());
		return $errors;
	}
	
	/**
	* Parse the error text
	*/
	public function analyze()
	{
		$this->writePostData(true);
		$this->object->setErrorData($this->object->getErrorsFromText($_POST['errortext']));
		$this->editQuestion();
	}

	function outQuestionForTest($formaction, $active_id, $pass = NULL, $is_postponed = FALSE, $use_post_solutions = FALSE, $show_feedback = FALSE)
	{
		$test_output = $this->getTestOutput($active_id, $pass, $is_postponed, $use_post_solutions, $show_feedback); 
		$this->tpl->setVariable("QUESTION_OUTPUT", $test_output);
		$this->tpl->setVariable("FORMACTION", $formaction);
	}

	/**
	* Get the question solution output
	*
	* @param integer $active_id The active user id
	* @param integer $pass The test pass
	* @param boolean $graphicalOutput Show visual feedback for right/wrong answers
	* @param boolean $result_output Show the reached points for parts of the question
	* @param boolean $show_question_only Show the question without the ILIAS content around
	* @param boolean $show_feedback Show the question feedback
	* @param boolean $show_correct_solution Show the correct solution instead of the user solution
	* @param boolean $show_manual_scoring Show specific information for the manual scoring output
	* @return The solution output of the question as HTML code
	*/
	function getSolutionOutput(
		$active_id,
		$pass = NULL,
		$graphicalOutput = FALSE,
		$result_output = FALSE,
		$show_question_only = TRUE,
		$show_feedback = FALSE,
		$show_correct_solution = FALSE,
		$show_manual_scoring = FALSE,
		$show_question_text = TRUE
	)
	{
		// get the solution of the user for the active pass or from the last pass if allowed
		$template = new ilTemplate("tpl.il_as_qpl_errortext_output_solution.html", TRUE, TRUE, "Modules/TestQuestionPool");

		$selections = array();
		if (($active_id > 0) && (!$show_correct_solution))
		{
			$solutions =& $this->object->getSolutionValues($active_id, $pass);
			if (is_array($solutions))
			{
				foreach ($solutions as $solution)
				{
					array_push($selections, $solution['value1']);
				}
				$errortext_value = join(",", $selections);
			}
		}
		else
		{
			$selections = $this->object->getBestSelection();
		}

		if (($active_id > 0) && (!$show_correct_solution))
		{
			$reached_points = $this->object->getReachedPoints($active_id, $pass);
		}
		else
		{
			$reached_points = $this->object->getPoints();
		}

		if ($result_output)
		{
			$resulttext = ($reached_points == 1) ? "(%s " . $this->lng->txt("point") . ")" : "(%s " . $this->lng->txt("points") . ")"; 
			$template->setVariable("RESULT_OUTPUT", sprintf($resulttext, $reached_points));
		}
		if ($this->object->getTextSize() >= 10) echo $template->setVariable("STYLE", " style=\"font-size: " . $this->object->getTextSize() . "%;\"");
		if ($show_question_text==true)
		{
			$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($this->object->getQuestion(), TRUE));
		}
		$errortext = $this->object->createErrorTextOutput($selections, $graphicalOutput, $show_correct_solution);
		$errortext = preg_replace("/#HREF\d+/is", "javascript:void(0);", $errortext);
		$template->setVariable("ERRORTEXT", $errortext);
		$questionoutput = $template->get();

		$solutiontemplate = new ilTemplate("tpl.il_as_tst_solution_output.html",TRUE, TRUE, "Modules/TestQuestionPool");

		$feedback = '';
		if($show_feedback)
		{
			$fb = $this->getGenericFeedbackOutput($active_id, $pass);
			$feedback .=  strlen($fb) ? $fb : '';
			
			$fb = $this->getSpecificFeedbackOutput($active_id, $pass);
			$feedback .=  strlen($fb) ? $fb : '';
		}
		if (strlen($feedback)) $solutiontemplate->setVariable("FEEDBACK", $feedback);
		
		$solutiontemplate->setVariable("SOLUTION_OUTPUT", $questionoutput);

		$solutionoutput = $solutiontemplate->get(); 
		if (!$show_question_only)
		{
			// get page object output
			$solutionoutput = '<div class="ilc_question_Standard">'.$solutionoutput."</div>";
		}
		return $solutionoutput;
	}
	
	function getPreview($show_question_only = FALSE)
	{
		$template = new ilTemplate("tpl.il_as_qpl_errortext_output.html",TRUE, TRUE, "Modules/TestQuestionPool");
		if ($this->object->getTextSize() >= 10) echo $template->setVariable("STYLE", " style=\"font-size: " . $this->object->getTextSize() . "%;\"");
		$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($this->object->getQuestion(), TRUE));
		$errortext = $this->object->createErrorTextOutput($selections);
		$errortext = preg_replace("/#HREF\d+/is", "javascript:void(0);", $errortext);
		$template->setVariable("ERRORTEXT", $errortext);
		$template->setVariable("ERRORTEXT_ID", "qst_" . $this->object->getId());
		$questionoutput = $template->get();
		if (!$show_question_only)
		{
			// get page object output
			$questionoutput = $this->getILIASPage($questionoutput);
		}
		include_once "./Services/YUI/classes/class.ilYuiUtil.php";
		ilYuiUtil::initElementSelection();
		$this->tpl->addJavascript("./Modules/TestQuestionPool/templates/default/errortext.js");
		return $questionoutput;
	}

	function getTestOutput($active_id, $pass = NULL, $is_postponed = FALSE, $use_post_solutions = FALSE, $show_feedback = FALSE)
	{
		// generate the question output
		$template = new ilTemplate("tpl.il_as_qpl_errortext_output.html",TRUE, TRUE, "Modules/TestQuestionPool");
		if ($active_id)
		{
			$solutions = NULL;
			include_once "./Modules/Test/classes/class.ilObjTest.php";
			if (!ilObjTest::_getUsePreviousAnswers($active_id, true))
			{
				if (is_null($pass)) $pass = ilObjTest::_getPass($active_id);
			}
			$solutions =& $this->object->getSolutionValues($active_id, $pass);
		}
		$errortext_value = "";
		if (strlen($_SESSION['qst_selection']))
		{
			$this->object->toggleSelection($_SESSION['qst_selection'], $active_id, $pass);
			unset($_SESSION['qst_selection']);
			$solutions =& $this->object->getSolutionValues($active_id, $pass);
		}
		$selections = array();
		if (is_array($solutions))
		{
			foreach ($solutions as $solution)
			{
				array_push($selections, $solution['value1']);
			}
			$errortext_value = join(",", $selections);
		}
		if ($this->object->getTextSize() >= 10) echo $template->setVariable("STYLE", " style=\"font-size: " . $this->object->getTextSize() . "%;\"");
		$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($this->object->getQuestion(), TRUE));
		$errortext = $this->object->createErrorTextOutput($selections);
		$errortext = preg_replace_callback("/#HREF(\d+)/is", array(&$this, 'exchangeURL'), $errortext);
		$this->ctrl->setParameterByClass('iltestoutputgui', 'errorvalue', '');
		$template->setVariable("ERRORTEXT", $errortext);
		$template->setVariable("ERRORTEXT_ID", "qst_" . $this->object->getId());
		$template->setVariable("ERRORTEXT_VALUE", $errortext_value);
			
		$questionoutput = $template->get();
		if (!$show_question_only)
		{
			// get page object output
			$questionoutput = $this->getILIASPage($questionoutput);
		}
		include_once "./Services/YUI/classes/class.ilYuiUtil.php";
		ilYuiUtil::initElementSelection();
		$this->tpl->addJavascript("./Modules/TestQuestionPool/templates/default/errortext.js");
		$questionoutput = $template->get();
		$pageoutput = $this->outQuestionPage("", $is_postponed, $active_id, $questionoutput);
		return $pageoutput;
	}
	
	public function exchangeURL($matches)
	{
		$this->ctrl->setParameterByClass('iltestoutputgui', 'qst_selection', $matches[1]);
		return $this->ctrl->getLinkTargetByClass('iltestoutputgui', 'gotoQuestion');
	}

	/**
	* Saves the feedback for a single choice question
	*
	* @access public
	*/
	function saveFeedback()
	{
		include_once "./Services/AdvancedEditing/classes/class.ilObjAdvancedEditing.php";
		$errors = $this->feedback(true);
		$this->object->saveFeedbackGeneric(0, $_POST["feedback_incomplete"]);
		$this->object->saveFeedbackGeneric(1, $_POST["feedback_complete"]);
		foreach ($this->object->getErrorData() as $index => $answer)
		{
			$this->object->saveFeedbackSingleAnswer($index, $_POST["feedback_answer_$index"]);
		}
		
		$this->object->cleanupMediaObjectUsage();
		parent::saveFeedback();
	}

	/**
	 * Sets the ILIAS tabs for this question type
	 *
	 * @access public
	 *
	 * @todo:	MOVE THIS STEPS TO COMMON QUESTION CLASS assQuestionGUI
	 */
	function setQuestionTabs()
	{
		global $rbacsystem, $ilTabs;
		
		$this->ctrl->setParameterByClass("ilpageobjectgui", "q_id", $_GET["q_id"]);
		include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
		$q_type = $this->object->getQuestionType();

		if (strlen($q_type))
		{
			$classname = $q_type . "GUI";
			$this->ctrl->setParameterByClass(strtolower($classname), "sel_question_types", $q_type);
			$this->ctrl->setParameterByClass(strtolower($classname), "q_id", $_GET["q_id"]);
		}

		if ($_GET["q_id"])
		{
			if ($rbacsystem->checkAccess('write', $_GET["ref_id"]))
			{
				// edit page
				$ilTabs->addTarget("edit_page",
					$this->ctrl->getLinkTargetByClass("ilPageObjectGUI", "edit"),
					array("edit", "insert", "exec_pg"),
					"", "", $force_active);
			}
	
			// edit page
			$ilTabs->addTarget("preview",
				$this->ctrl->getLinkTargetByClass("ilPageObjectGUI", "preview"),
				array("preview"),
				"ilPageObjectGUI", "", $force_active);
		}

		$force_active = false;
		if ($rbacsystem->checkAccess('write', $_GET["ref_id"]))
		{
			$url = "";
			if ($classname) $url = $this->ctrl->getLinkTargetByClass($classname, "editQuestion");
			// edit question properties
			$ilTabs->addTarget("edit_question",
				$url,
				array("editQuestion", "save", "saveEdit", "analyze", "originalSyncForm"),
				$classname, "", $force_active);
		}

		if ($_GET["q_id"])
		{
			$ilTabs->addTarget("feedback",
				$this->ctrl->getLinkTargetByClass($classname, "feedback"),
				array("feedback", "saveFeedback"),
				$classname, "");
		}
		
		// add tab for question hint within common class assQuestionGUI
		$this->addTab_QuestionHints($ilTabs);
		
		if ($_GET["q_id"])
		{
			$ilTabs->addTarget("solution_hint",
				$this->ctrl->getLinkTargetByClass($classname, "suggestedsolution"),
				array("suggestedsolution", "saveSuggestedSolution", "outSolutionExplorer", "cancel", 
				"addSuggestedSolution","cancelExplorer", "linkChilds", "removeSuggestedSolution"
				),
				$classname, 
				""
			);
		}

		// Assessment of questions sub menu entry
		if ($_GET["q_id"])
		{
			$ilTabs->addTarget("statistics",
				$this->ctrl->getLinkTargetByClass($classname, "assessment"),
				array("assessment"),
				$classname, "");
		}
		
		if (($_GET["calling_test"] > 0) || ($_GET["test_ref_id"] > 0))
		{
			$ref_id = $_GET["calling_test"];
			if (strlen($ref_id) == 0) $ref_id = $_GET["test_ref_id"];

                        global $___test_express_mode;

                        if (!$_GET['test_express_mode'] && !$___test_express_mode) {
                            $ilTabs->setBackTarget($this->lng->txt("backtocallingtest"), "ilias.php?baseClass=ilObjTestGUI&cmd=questions&ref_id=$ref_id");
                        }
                        else {
                            $link = ilTestExpressPage::getReturnToPageLink();
                            $ilTabs->setBackTarget($this->lng->txt("backtocallingtest"), $link);
                        }
		}
		else
		{
			$ilTabs->setBackTarget($this->lng->txt("qpl"), $this->ctrl->getLinkTargetByClass("ilobjquestionpoolgui", "questions"));
		}
	}
	
			/**
	* Creates the output of the feedback page for a single choice question
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
		$form->setTableWidth("98%");
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
	
		if (!$this->getSelfAssessmentEditingMode())
		{
			foreach ($this->object->getErrorData() as $index => $answer)
			{
				$caption = $ordinal = $index+1;
				$caption .= '. <br />"' . $answer->text_wrong . '" =&gt; ';
				$caption .= '"' . $answer->text_correct . '"';
				$caption .= '</i>';
				
				$answerobj = new ilTextAreaInputGUI($this->object->prepareTextareaOutput($caption, true), "feedback_answer_$index");
				$answerobj->setValue($this->object->prepareTextareaOutput($this->object->getFeedbackSingleAnswer($index)));
				$answerobj->setRequired(false);
				$answerobj->setRows(10);
				$answerobj->setCols(80);
				$answerobj->setUseRte(true);
				include_once "./Services/AdvancedEditing/classes/class.ilObjAdvancedEditing.php";
				$answerobj->setRteTags(ilObjAdvancedEditing::_getUsedHTMLTags("assessment"));
				$answerobj->addPlugin("latex");
				$answerobj->addButton("latex");
				$answerobj->addButton("pastelatex");
				$answerobj->setRTESupport($this->object->getId(), "qpl", "assessment", null, false, '3.4.7');
				$form->addItem($answerobj);
			}
		}

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
	
	function getSpecificFeedbackOutput($active_id, $pass)
	{
		$feedback = '<table><tbody>';
		$selection = $this->object->getBestSelection(false);
		$elements = array();
		foreach(preg_split("/[\n\r]+/", $this->object->errortext) as $line)
		{
			$elements = array_merge( $elements, preg_split("/\s+/", $line));
		}
		$i = 0;
		foreach ($selection as $index => $answer)
		{
			$caption = $ordinal = $index+1 .'.<i> ';
			$caption .= $elements[$answer];
			$caption = str_replace('#', '', $caption);
			$caption .= '</i>:';

			$feedback .= '<tr><td>';

			$feedback .= $caption .'</td><td>';
			foreach ($this->object->getErrorData() as $idx => $ans)
			{
				$cand = '#'.$ans->text_wrong;
				if ($elements[$answer] == $cand)
				{
					$feedback .= $this->object->getFeedbackSingleAnswer($idx) . '</td> </tr>';
				}
			}
			#$feedback .= $this->object->getFeedbackSingleAnswer($answer) . '</td> </tr>';
		}
		$feedback .= '</tbody></table>';		
		return $this->object->prepareTextareaOutput($feedback, TRUE);
	}

}
?>
