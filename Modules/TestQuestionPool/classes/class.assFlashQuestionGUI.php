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
* The assFlashQuestionGUI class encapsulates the GUI representation
* for Mathematik Online based questions.
*
* @author		Helmut Schottmüller <helmut.schottmueller@mac.com>
* @author		Björn Heyser <bheyser@databay.de>
* @version	$Id: class.assFlashQuestionGUI.php 40872 2013-03-25 14:53:54Z bheyser $
* @ingroup ModulesTestQuestionPool
* @ilctrl_iscalledby assFlashQuestionGUI: ilObjQuestionPoolGUI
* */
class assFlashQuestionGUI extends assQuestionGUI
{
	private $newUnitId;
	
	/**
	* assFlashQuestionGUI constructor
	*
	* The constructor takes possible arguments an creates an instance of the assFlashQuestionGUI object.
	*
	* @param integer $id The database id of a single choice question object
	* @access public
	*/
	function __construct($id = -1)
	{
		parent::__construct();
		include_once "./Modules/TestQuestionPool/classes/class.assFlashQuestion.php";
		$this->object = new assFlashQuestion();
		$this->newUnitId = null;
		if ($id >= 0)
		{
			$this->object->loadFromDb($id);
		}
	}

	function getCommand($cmd)
	{
		if (preg_match("/suggestrange_(.*?)/", $cmd, $matches))
		{
			$cmd = "suggestRange";
		}
		return $cmd;
	}

	/**
	* Suggest a range for a result
	*
	* @access public
	*/
	function suggestRange()
	{
		if ($this->writePostData())
		{
			ilUtil::sendInfo($this->getErrorMessage());
		}
		$this->editQuestion();
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
			global $ilLog;
			$this->setErrorMessage("");
			if ($_POST['flash']['delete'] == 1)
			{
				$this->object->deleteApplet();
			}
			else
			{
				$this->object->setApplet($_POST['flash']['filename']);
			}
			if ($_FILES["flash"]["tmp_name"])
			{
				$this->object->deleteApplet();
				$filename = $this->object->moveUploadedFile($_FILES["flash"]["tmp_name"], $_FILES["flash"]["name"]);
				$this->object->setApplet($filename);
			}
			$this->object->clearParameters();
			if (is_array($_POST["flash"]["flash_param_name"]))
			{
				foreach ($_POST['flash']['flash_param_name'] as $idx => $val)
				{
					$this->object->addParameter($val, $_POST['flash']['flash_param_value'][$idx]);
				}
			}
			if (is_array($_POST['flash']['flash_param_delete']))
			{
				foreach ($_POST['flash']['flash_param_delete'] as $key => $value)
				{
					$this->object->removeParameter($_POST['flash']['flash_param_name'][$key]);
				}
			}
			$this->object->setTitle($_POST["title"]);
			$this->object->setAuthor($_POST["author"]);
			$this->object->setComment($_POST["comment"]);
			$questiontext = $_POST["question"];
			$this->object->setQuestion($questiontext);
			$this->object->setEstimatedWorkingTime(
				$_POST["Estimated"]["hh"],
				$_POST["Estimated"]["mm"],
				$_POST["Estimated"]["ss"]
			);
			$this->object->setWidth($_POST["flash"]["width"]);
			$this->object->setHeight($_POST["flash"]["height"]);
			$this->object->setPoints($_POST["points"]);
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
		//$save = ((strcmp($this->ctrl->getCmd(), "save") == 0) || (strcmp($this->ctrl->getCmd(), "saveEdit") == 0)) ? TRUE : FALSE;
		$save = $this->isSaveCommand();
		$this->getQuestionTemplate();

		include_once("./Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this));
		$form->setTitle($this->outQuestionType());
		$form->setMultipart(TRUE);
		$form->setTableWidth("100%");
		$form->setId("flash");

		$this->addBasicQuestionFormProperties($form);

		// flash file
		$flash = new ilFlashFileInputGUI($this->lng->txt("flashfile"), "flash");
		$flash->setRequired(TRUE);
		if (strlen($this->object->getApplet()))
		{
			$flash->setApplet($this->object->getApplet());
			$flash->setAppletPathWeb($this->object->getFlashPathWeb());
		}
		$flash->setWidth($this->object->getWidth());
		$flash->setHeight($this->object->getHeight());
		$flash->setParameters($this->object->getParameters());
		$form->addItem($flash);
		if ($this->object->getId())
		{
			$hidden = new ilHiddenInputGUI("", "ID");
			$hidden->setValue($this->object->getId());
			$form->addItem($hidden);
		}
		// points
		$points = new ilNumberInputGUI($this->lng->txt("points"), "points");
		$points->setValue($this->object->getPoints());
		$points->setRequired(TRUE);
		$points->setSize(3);
		$points->setMinValue(0.0);
		$form->addItem($points);

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
	
	function flashAddParam()
	{
		$this->writePostData();
		$this->object->addParameter("", "");
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
		$template = new ilTemplate("tpl.il_as_qpl_flash_question_output_solution.html", TRUE, TRUE, "Modules/TestQuestionPool");

		$params = array();
		if (is_array($this->object->getParameters()))
		{
			foreach ($this->object->getParameters() as $name => $value)
			{
				array_push($params, urlencode($name) . "=" . urlencode($value));
			}
		}

		array_push($params, "session_id=" . urlencode($_COOKIE["PHPSESSID"]));
		array_push($params, "client=" . urlencode(CLIENT_ID));
		array_push($params, "points_max=" . urlencode($this->object->getPoints()));
		array_push($params, "server=" . urlencode(ilUtil::removeTrailingPathSeparators(ILIAS_HTTP_PATH) . "/webservice/soap/server.php?wsdl"));
		if (!is_null($pass))
		{
			array_push($params, "pass=" . $pass);
		}
		else
		{
			include_once "./Modules/Test/classes/class.ilObjTest.php";
			array_push($params, "pass=" . ilObjTest::_getPass($active_id));
		}
		if ($active_id)
		{
			array_push($params, "active_id=" . $active_id);
		}
		array_push($params, "question_id=" . $this->object->getId());

		if ($show_correct_solution)
		{
			array_push($params, "solution=correct");
		}
		else
		{
			array_push($params, "solution=user");
		}

		if (($active_id > 0) && (!$show_correct_solution))
		{
			if ($graphicalOutput)
			{
				// output of ok/not ok icons for user entered solutions
				$reached_points = $this->object->getReachedPoints($active_id, $pass);
				if ($reached_points == $this->object->getMaximumPoints())
				{
					$template->setCurrentBlock("icon_ok");
					$template->setVariable("ICON_OK", ilUtil::getImagePath("icon_ok.png"));
					$template->setVariable("TEXT_OK", $this->lng->txt("answer_is_right"));
					$template->parseCurrentBlock();
				}
				else
				{
					$template->setCurrentBlock("icon_ok");
					if ($reached_points > 0)
					{
						$template->setVariable("ICON_NOT_OK", ilUtil::getImagePath("icon_mostly_ok.png"));
						$template->setVariable("TEXT_NOT_OK", $this->lng->txt("answer_is_not_correct_but_positive"));
					}
					else
					{
						$template->setVariable("ICON_NOT_OK", ilUtil::getImagePath("icon_not_ok.png"));
						$template->setVariable("TEXT_NOT_OK", $this->lng->txt("answer_is_wrong"));
					}
					$template->parseCurrentBlock();
				}
			}
		}

		if (count($params))
		{
			$template->setCurrentBlock("flash_vars");
			$template->setVariable("FLASH_VARS", join($params, "&"));
			$template->parseCurrentBlock();
			$template->setCurrentBlock("applet_parameters");
			$template->setVariable("PARAM_VALUE", join($params, "&"));
			$template->parseCurrentBlock();
		}
		if ($show_question_text==true)
		{
			$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($this->object->getQuestion(), TRUE));
		}
		$template->setVariable("APPLET_WIDTH", $this->object->getWidth());
		$template->setVariable("APPLET_HEIGHT", $this->object->getHeight());
		$template->setVariable("ID", $this->object->getId());
		$template->setVariable("APPLET_PATH", $this->object->getFlashPathWeb() . $this->object->getApplet());
		$template->setVariable("APPLET_FILE", $this->object->getApplet());

		$questionoutput = $template->get();
		$solutiontemplate = new ilTemplate("tpl.il_as_tst_solution_output.html",TRUE, TRUE, "Modules/TestQuestionPool");
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
		$template = new ilTemplate("tpl.il_as_qpl_flash_question_output.html",TRUE, TRUE, "Modules/TestQuestionPool");
		$params = array();
		if (is_array($this->object->getParameters()))
		{
			foreach ($this->object->getParameters() as $name => $value)
			{
				array_push($params, urlencode($name) . "=" . urlencode($value));
			}
		}
		if (count($params))
		{
			$template->setCurrentBlock("flash_vars");
			$template->setVariable("FLASH_VARS", join($params, "&"));
			$template->parseCurrentBlock();
			$template->setCurrentBlock("applet_parameters");
			$template->setVariable("PARAM_VALUE", join($params, "&"));
			$template->parseCurrentBlock();
		}
		$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($this->object->getQuestion(), TRUE));
		$template->setVariable("APPLET_WIDTH", $this->object->getWidth());
		$template->setVariable("APPLET_HEIGHT", $this->object->getHeight());
		$template->setVariable("ID", $this->object->getId());
		$template->setVariable("APPLET_PATH", $this->object->getFlashPathWeb() . $this->object->getApplet());
		$template->setVariable("APPLET_FILE", $this->object->getApplet());
		$questionoutput = $template->get();
		if (!$show_question_only)
		{
			// get page object output
			$questionoutput = $this->getILIASPage($questionoutput);
		}
		return $questionoutput;
	}

	function getTestOutput($active_id, $pass = NULL, $is_postponed = FALSE, $use_post_solutions = FALSE, $show_feedback = FALSE)
	{
		// generate the question output
		$template = new ilTemplate("tpl.il_as_qpl_flash_question_output.html",TRUE, TRUE, "Modules/TestQuestionPool");
		$params = array();
		if (is_array($this->object->getParameters()))
		{
			foreach ($this->object->getParameters() as $name => $value)
			{
				array_push($params, urlencode($name) . "=" . urlencode($value));
			}
		}

		array_push($params, "session_id=" . urlencode($_COOKIE["PHPSESSID"]));
		array_push($params, "client=" . urlencode(CLIENT_ID));
		array_push($params, "points_max=" . urlencode($this->object->getPoints()));
		array_push($params, "server=" . urlencode(ilUtil::removeTrailingPathSeparators(ILIAS_HTTP_PATH) . "/webservice/soap/server.php?wsdl"));
		if (strlen($pass))
		{
			array_push($params, "pass=" . $pass);
		}
		else
		{
			include_once "./Modules/Test/classes/class.ilObjTest.php";
			array_push($params, "pass=" . ilObjTest::_getPass($active_id));
		}
		if ($active_id)
		{
			array_push($params, "active_id=" . $active_id);
		}
		array_push($params, "question_id=" . $this->object->getId());

		if (count($params))
		{
			$template->setCurrentBlock("flash_vars");
			$template->setVariable("FLASH_VARS", join($params, "&"));
			$template->parseCurrentBlock();
			$template->setCurrentBlock("applet_parameters");
			$template->setVariable("PARAM_VALUE", join($params, "&"));
			$template->parseCurrentBlock();
		}
		$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($this->object->getQuestion(), TRUE));
		$template->setVariable("APPLET_WIDTH", $this->object->getWidth());
		$template->setVariable("APPLET_HEIGHT", $this->object->getHeight());
		$template->setVariable("ID", $this->object->getId());
		$template->setVariable("APPLET_PATH", $this->object->getFlashPathWeb() . $this->object->getApplet());
		$template->setVariable("APPLET_FILE", $this->object->getFlashPathWeb() . $this->object->getApplet());
		$questionoutput = $template->get();
		
		$pageoutput = $this->outQuestionPage("", $is_postponed, $active_id, $questionoutput);
		return $pageoutput;
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
			$commands = $_POST["cmd"];
			if (is_array($commands))
			{
				foreach ($commands as $key => $value)
				{
					if (preg_match("/^suggestrange_.*/", $key, $matches))
					{
						$force_active = true;
					}
				}
			}
			// edit question properties
			$ilTabs->addTarget("edit_question",
				$url,
				array("editQuestion", "save", "flashAddParam", "saveEdit", "originalSyncForm"),
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
	
	function getSpecificFeedbackOutput($active_id, $pass)
	{
		$output = "";
		return $this->object->prepareTextareaOutput($output, TRUE);
	}
}