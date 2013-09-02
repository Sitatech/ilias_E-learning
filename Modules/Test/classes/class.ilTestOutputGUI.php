<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once "./Modules/Test/classes/inc.AssessmentConstants.php";
include_once "./Modules/Test/classes/class.ilTestServiceGUI.php";
require_once 'Modules/TestQuestionPool/classes/class.assQuestion.php';

/**
 * Output class for assessment test execution
 *
 * The ilTestOutputGUI class creates the output for the ilObjTestGUI
 * class when learners execute a test. This saves some heap space because 
 * the ilObjTestGUI class will be much smaller then
 *
 * @extends ilTestServiceGUI
 * 
 * @author		Helmut Schottmüller <helmut.schottmueller@mac.com>
 * @author		Björn Heyser <bheyser@databay.de>
 * @version		$Id: class.ilTestOutputGUI.php 42772 2013-06-18 10:02:28Z bheyser $
 * 
 * @ingroup ModulesTest
 * 
 * @ilCtrl_Calls ilTestOutputGUI: ilAssQuestionHintRequestGUI
 */
class ilTestOutputGUI extends ilTestServiceGUI
{
	var $ref_id;

	var $saveResult;
	var $sequence;
	var $cmdCtrl;
	var $maxProcessingTimeReached;
	var $endingTimeReached;

	/**
	* ilTestOutputGUI constructor
	*
	* @param object $a_object Associated ilObjSurvey class
	* @access public
	*/
	function __construct($a_object)
	{
		parent::ilTestServiceGUI($a_object);
		$this->ref_id = $_GET["ref_id"];
	}

	/*
	* Save tags for tagging gui
	*
	* Needed this function here because the test info page 
	* uses another class to send its form results
	*/
	function saveTags()
	{
		include_once("./Services/Tagging/classes/class.ilTaggingGUI.php");
		$tagging_gui = new ilTaggingGUI();
		$tagging_gui->setObject($this->object->getId(), $this->object->getType());
		$tagging_gui->saveInput();
		$this->ctrl->redirectByClass("ilobjtestgui", "infoScreen");
	}
	
	/**
	 * execute command
	 */
	function executeCommand()
	{
		global $ilUser;
		
		$cmd = $this->ctrl->getCmd();
		$next_class = $this->ctrl->getNextClass($this);
		
		$this->ctrl->saveParameter($this, "sequence");
		$this->ctrl->saveParameter($this, "active_id");
		
		if (preg_match("/^gotoquestion_(\\d+)$/", $cmd, $matches))
		{
			$cmd = "gotoquestion";
			if (strlen($matches[1]))
			{
				$this->ctrl->setParameter($this, 'gotosequence', $matches[1]);
			}
			
		}
		
		if ($_GET["active_id"])
		{
			$this->object->setTestSession($_GET["active_id"]);
		}
		else
		{
			$this->object->setTestSession();
		}

		include_once 'Services/jQuery/classes/class.iljQueryUtil.php';
		iljQueryUtil::initjQuery();
		include_once "./Services/YUI/classes/class.ilYuiUtil.php";
		ilYuiUtil::initConnectionWithAnimation();
		
		$cmd = $this->getCommand($cmd);
		
		switch($next_class)
		{
			case 'ilassquestionhintrequestgui':
				
				$questionGUI = $this->object->createQuestionGUI(
					"", $this->object->getTestSequence()->getQuestionForSequence( $this->calculateSequence() )
				);

				require_once 'Modules/TestQuestionPool/classes/class.ilAssQuestionHintRequestGUI.php';
				$gui = new ilAssQuestionHintRequestGUI($this, $this->object->getTestSession(), $questionGUI);
				
				$ret = $this->ctrl->forwardCommand($gui);
				
				break;
				
			default:
				
				$ret =& $this->$cmd();
				break;
		}
		return $ret;
	}
	
	/**
	 * @global ilCtrl $iCtrl
	 * @return type 
	 */
	public function outResultsToplist()
	{
		global $ilCtrl;
		$ilCtrl->redirectByClass('ilTestToplistGUI', 'outResultsToplist');
		
		#require_once 'Modules/Test/classes/class.ilTestToplistGUI.php';
		#$gui = new ilTestToplistGUI($this);
		#return $this->ctrl->forwardCommand($gui);		
	}
	
	/**
	 * updates working time and stores state saveresult to see if question has to be stored or not
	 */
	
	function updateWorkingTime() 
	{
		if ($_SESSION["active_time_id"])
		{
			$this->object->updateWorkingTime($_SESSION["active_time_id"]);
		}	
	}	

/**
 * saves the user input of a question
 */
	function saveQuestionSolution($force = FALSE)
	{
		$this->updateWorkingTime();
		$this->saveResult = FALSE;
		if (!$force)
		{
			$formtimestamp = $_POST["formtimestamp"];
			if (strlen($formtimestamp) == 0) $formtimestamp = $_GET["formtimestamp"];
			if ($formtimestamp != $_SESSION["formtimestamp"])
			{
				$_SESSION["formtimestamp"] = $formtimestamp;
			}
			else
			{
				return FALSE;
			}
		}
		// save question solution
		if ($this->canSaveResult() || $force)
		{
			// but only if the ending time is not reached
			$q_id = $this->object->getTestSequence()->getQuestionForSequence($_GET["sequence"]);
			if (is_numeric($q_id) && (int)$q_id) 
			{
				global $ilUser;
				
				$question_gui = $this->object->createQuestionGUI("", $q_id);
				if ($this->object->getJavaScriptOutput())
				{
					$question_gui->object->setOutputType(OUTPUT_JAVASCRIPT);
				}
				$pass = NULL;
				$active_id = $this->object->getTestSession()->getActiveId();
				if ($this->object->isRandomTest())
				{
					$pass = $this->object->_getPass($active_id);
				}
				$this->saveResult = $question_gui->object->persistWorkingState(
						$active_id, $pass, $this->object->areObligationsEnabled()
				);

				// update learning progress (is done in ilTestSession)
				//include_once("./Services/Tracking/classes/class.ilLPStatusWrapper.php");
				//ilLPStatusWrapper::_updateStatus($this->object->getId(), $ilUser->getId());
			}
		}
		if ($this->saveResult == FALSE)
		{
			$this->ctrl->setParameter($this, "save_error", "1");
			$_SESSION["previouspost"] = $_POST;
		}
		return $this->saveResult;
	}
	
	/**
	* Returns TRUE if the answers of the current user could be saved
	*
	* Returns TRUE if the answers of the current user could be saved
	*
	* @return boolean TRUE if the answers could be saved, FALSE otherwise
	* @access private
	*/
	 function canSaveResult() 
	 {
		 return !$this->object->endingTimeReached() && !$this->isMaxProcessingTimeReached() && !$this->isNrOfTriesReached();
	 }
	 
	/**
	* Creates the introduction page for a test
	*
	* Creates the introduction page for a test
	*
	* @access public
	*/
	function outIntroductionPage()
	{
		$this->ctrl->redirectByClass("ilobjtestgui", "infoScreen"); 
	}
	
	/**
	* Checks wheather the maximum processing time is reached or not
	*
	* Checks wheather the maximum processing time is reached or not
	*
	* @return TRUE if the maximum processing time is reached, FALSE otherwise
	* @access public
	*/
	function isMaxProcessingTimeReached() 
	{
		global $ilUser;
		$active_id = $this->object->getTestSession()->getActiveId();
		$starting_time = $this->object->getStartingTimeOfUser($active_id);
		if ($starting_time === FALSE)
		{
			return FALSE;
		}
		else
		{
			return $this->object->isMaxProcessingTimeReached($starting_time);
		}
	}
	
	/**
	* Creates the learners output of a question
	*/
	public function outWorkingForm($sequence = "", $test_id, $postpone_allowed, $directfeedback = 0)
	{
		global $ilUser;

		if ($sequence < 1) $sequence = $this->object->getTestSequence()->getFirstSequence();
		
		$_SESSION["active_time_id"]= $this->object->startWorkingTime($this->object->getTestSession()->getActiveId(), 
																	 $this->object->getTestSession()->getPass()
		);

		$this->populateContentStyleBlock();
		$this->populateSyntaxStyleBlock();

		if ($this->object->getListOfQuestions())
		{
			$this->showSideList();
		}
		
		$question_gui = $this->object->createQuestionGUI("", $this->object->getTestSequence()->getQuestionForSequence($sequence));
		
		if ($this->object->getJavaScriptOutput())
		{
			$question_gui->object->setOutputType(OUTPUT_JAVASCRIPT);
		}

		$is_postponed = $this->object->getTestSequence()->isPostponedQuestion($question_gui->object->getId());
		$this->ctrl->setParameter($this, "sequence", "$sequence");
		$formaction = $this->ctrl->getFormAction($this, "gotoQuestion");

		$question_gui->setSequenceNumber($this->object->getTestSequence()->getPositionOfSequence($sequence));
		$question_gui->setQuestionCount($this->object->getTestSequence()->getUserQuestionCount());
		
		
		// output question
		$user_post_solution = FALSE;
		if (array_key_exists("previouspost", $_SESSION))
		{
			$user_post_solution = $_SESSION["previouspost"];
			unset($_SESSION["previouspost"]);
		}

		global $ilNavigationHistory;
		$ilNavigationHistory->addItem($_GET["ref_id"], $this->ctrl->getLinkTarget($this, "resume"), "tst");

		// Determine $answer_feedback: It should hold a boolean stating if answer-specific-feedback is to be given.
		// It gets the parameter "Scoring and Results" -> "Instant Feedback" -> "Show Answer-Specific Feedback"
		// $directfeedback holds a boolean stating if the instant feedback was requested using the "Check" button.
		$answer_feedback = FALSE;
		if (($directfeedback) && ($this->object->getSpecificAnswerFeedback()))
		{
			$answer_feedback = TRUE;
		}
		
		// Answer specific feedback is rendered into the display of the test question with in the concrete question types outQuestionForTest-method.
		// Notation of the params prior to getting rid of this crap in favor of a class
		$question_gui->outQuestionForTest(
				$formaction, 										#form_action
				$this->object->getTestSession()->getActiveId(), 	#active_id
				NULL, 												#pass
				$is_postponed, 										#is_postponed
				$user_post_solution, 								#user_post_solution
				$answer_feedback									#answer_feedback == inline_specific_feedback
			);
		// The display of specific inline feedback and specific feedback in an own block is to honor questions, which
		// have the possibility to embed the specific feedback into their output while maintaining compatibility to
		// questions, which do not have such facilities. E.g. there can be no "specific inline feedback" for essay
		// questions, while the multiple-choice questions do well.
				
		$this->fillQuestionRelatedNavigation($question_gui);

		if ($directfeedback)
		{
			// This controls if the solution should be shown.
			// It gets the parameter "Scoring and Results" -> "Instant Feedback" -> "Show Solutions"			
			if ($this->object->getInstantFeedbackSolution())
			{
				$show_question_inline_score = $this->determineInlineScoreDisplay();
				
				// Notation of the params prior to getting rid of this crap in favor of a class
				$solutionoutput = $question_gui->getSolutionOutput(
					$this->object->getTestSession()->getActiveId(), 	#active_id
					NULL, 												#pass
					FALSE,												#graphical_output
					$show_question_inline_score,						#result_output
					FALSE, 												#show_question_only
					FALSE,												#show_feedback
					TRUE, 												#show_correct_solution
					FALSE, 												#show_manual_scoring
					FALSE												#show_question_text
				);
				$this->populateSolutionBlock( $solutionoutput );
			}
			
			// This controls if the score should be shown.
			// It gets the parameter "Scoring and Results" -> "Instant Feedback" -> "Show Results (Only Points)"				
			if ($this->object->getAnswerFeedbackPoints())
			{
				$reachedPoints = $question_gui->object->getAdjustedReachedPoints($this->object->getTestSession()->getActiveId(), NULL);
				$maxPoints = $question_gui->object->getMaximumPoints();

				$this->populateScoreBlock( $reachedPoints, $maxPoints );
			}
			
			// This controls if the generic feedback should be shown.
			// It gets the parameter "Scoring and Results" -> "Instant Feedback" -> "Show Solutions"				
			if ($this->object->getGenericAnswerFeedback())
			{
				$this->populateGenericFeedbackBlock( $question_gui );
			}
			
			// This controls if the specific feedback should be shown.
			// It gets the parameter "Scoring and Results" -> "Instant Feedback" -> "Show Answer-Specific Feedback"
			if ($this->object->getSpecificAnswerFeedback())
			{
				$this->populateSpecificFeedbackBlock( $question_gui );				
			}
		}

		$this->populatePreviousButtons( $sequence );

		if ($postpone_allowed && !$is_postponed)
		{
			$this->populatePostponeButtons();
		}
		
		if ($this->object->getListOfQuestions()) 
		{
			if (!(($finish) && ($this->object->getListOfQuestionsEnd())))
			{
				$this->populateSummaryButtons();
			}
		}

		if ($this->object->getShowCancel()) 
		{
			$this->populateCancelButtonBlock();
		}		

		if ($this->isLastQuestionInSequence( $question_gui ))
		{
			if ($this->object->getListOfQuestionsEnd()) 
			{
				$this->populateNextButtonsLeadingToSummary();				
			} 
			else 
			{
				$this->populateNextButtonsLeadingToEndOfTest();
			}
		}
		else
		{
			$this->populateNextButtonsLeadingToQuestion();
		}

		if ($this->object->getShowMarker())
		{
			include_once "./Modules/Test/classes/class.ilObjTest.php";
			$solved_array = ilObjTest::_getSolvedQuestions($this->object->getTestSession()->getActiveId(), $question_gui->object->getId());
			$solved = 0;
			
			if (count ($solved_array) > 0) 
			{
				$solved = array_pop($solved_array);
				$solved = $solved["solved"];
			}
			
			if ($solved==1) 
			{
				$this->populateQuestionMarkingBlockAsMarked();
			} 
			else 
			{
				$this->populateQuestionMarkingBlockAsUnmarked();
			}
		}

		if ($this->object->getJavaScriptOutput())
		{
			$this->tpl->setVariable("JAVASCRIPT_IMAGE", ilUtil::getImagePath("javascript_disable.png"));
			$this->tpl->setVariable("JAVASCRIPT_IMAGE_ALT", $this->lng->txt("disable_javascript"));
			$this->tpl->setVariable("JAVASCRIPT_IMAGE_TITLE", $this->lng->txt("disable_javascript"));
			$this->ctrl->setParameter($this, "tst_javascript", "0");
			$this->tpl->setVariable("JAVASCRIPT_URL", $this->ctrl->getLinkTarget($this, "gotoQuestion"));
		}
		else
		{
			$this->tpl->setVariable("JAVASCRIPT_IMAGE", ilUtil::getImagePath("javascript.png"));
			$this->tpl->setVariable("JAVASCRIPT_IMAGE_ALT", $this->lng->txt("enable_javascript"));
			$this->tpl->setVariable("JAVASCRIPT_IMAGE_TITLE", $this->lng->txt("enable_javascript"));
			$this->ctrl->setParameter($this, "tst_javascript", "1");
			$this->tpl->setVariable("JAVASCRIPT_URL", $this->ctrl->getLinkTarget($this, "gotoQuestion"));
		}

		if ($question_gui->object->supportsJavascriptOutput())
		{
			$this->tpl->touchBlock("jsswitch");
		}

		$this->tpl->addJavaScript(ilUtil::getJSLocation("autosave.js", "Modules/Test"));
		
		$this->tpl->setVariable("AUTOSAVE_URL", $this->ctrl->getFormAction($this, "autosave", "", true));

		if ($question_gui->isAutosaveable()&& $this->object->getAutosave())
		{
			$this->tpl->touchBlock('autosave');
			//$this->tpl->setVariable("BTN_SAVE", "Zwischenspeichern");
			//$this->tpl->setVariable("CMD_SAVE", "gotoquestion_{$sequence}");
			//$this->tpl->setVariable("AUTOSAVEFORMACTION", str_replace("&amp;", "&", $this->ctrl->getFormAction($this)));
			$this->tpl->setVariable("AUTOSAVEFORMACTION", str_replace("&amp;", "&", $this->ctrl->getLinkTarget($this, "autosave")));
			$this->tpl->setVariable("AUTOSAVEINTERVAL", $this->object->getAutosaveIval());
		}
		
		if( $this->object->areObligationsEnabled() && ilObjTest::isQuestionObligatory($question_gui->object->getId()) )
		{
		    $this->tpl->touchBlock('question_obligatory');
		    $this->tpl->setVariable('QUESTION_OBLIGATORY', $this->lng->txt('required_field'));
		}
	}

	private function determineInlineScoreDisplay()
	{
		$show_question_inline_score = FALSE;
		if ($this->object->getAnswerFeedbackPoints())
		{
			$show_question_inline_score = TRUE;
			return $show_question_inline_score;
		}
		return $show_question_inline_score;
	}

	private function populatePreviousButtons($sequence)
	{
		if ($this->isFirstPageInSequence( $sequence ))
		{
			$this->populatePreviousButtonsLeadingToIntroduction();
		}
		else
		{
			$this->populatePreviousButtonLeadingToQuestion();
		}
	}

	private function populateQuestionMarkingBlockAsUnmarked()
	{
		$this->tpl->setCurrentBlock( "isnotmarked" );
		$this->tpl->setVariable( "IMAGE_UNSET", ilUtil::getImagePath( "marked_.png" ) );
		$this->tpl->setVariable( "TEXT_UNSET", $this->lng->txt( "tst_question_mark" ) );
		$this->tpl->parseCurrentBlock();
	}

	private function populateQuestionMarkingBlockAsMarked()
	{
		$this->tpl->setCurrentBlock( "ismarked" );
		$this->tpl->setVariable( "IMAGE_SET", ilUtil::getImagePath( "marked.png" ) );
		$this->tpl->setVariable( "TEXT_SET", $this->lng->txt( "tst_remove_mark" ) );
		$this->tpl->parseCurrentBlock();
	}

	private function populateNextButtonsLeadingToQuestion()
	{
		$this->populateUpperNextButtonBlockLeadingToQuestion();
		$this->populateLowerNextButtonBlockLeadingToQuestion();
	}

	private function populateLowerNextButtonBlockLeadingToQuestion()
	{
		$this->tpl->setCurrentBlock( "next_bottom" );
		$this->tpl->setVariable( "BTN_NEXT", $this->lng->txt( "save_next" ) . " &gt;&gt;" );
		$this->tpl->parseCurrentBlock();
	}

	private function populateUpperNextButtonBlockLeadingToQuestion()
	{
		$this->tpl->setCurrentBlock( "next" );
		$this->tpl->setVariable( "BTN_NEXT", $this->lng->txt( "save_next" ) . " &gt;&gt;" );
		$this->tpl->parseCurrentBlock();
	}

	private function isLastQuestionInSequence($question_gui)
	{
		return $this->object->getTestSequence()->getQuestionForSequence( $this->object->getTestSequence()
																			 ->getLastSequence()
		) == $question_gui->object->getId();
	}

	private function populateNextButtonsLeadingToEndOfTest()
	{
		$this->populateUpperNextButtonBlockLeadingToEndOfTest();
		$this->populateLowerNextButtonBlockLeadingToEndOfTest();
	}

	private function populateLowerNextButtonBlockLeadingToEndOfTest()
	{
		$this->tpl->setCurrentBlock( "next_bottom" );
		$this->tpl->setVariable( "BTN_NEXT", $this->lng->txt( "save_finish" ) . " &gt;&gt;" );
		$this->tpl->parseCurrentBlock();
	}

	private function populateUpperNextButtonBlockLeadingToEndOfTest()
	{
		$this->tpl->setCurrentBlock( "next" );
		$this->tpl->setVariable( "BTN_NEXT", $this->lng->txt( "save_finish" ) . " &gt;&gt;" );
		$this->tpl->parseCurrentBlock();
	}

	private function populateNextButtonsLeadingToSummary()
	{
		$this->populateUpperNextButtonBlockLeadingToSummary();
		$this->populateLowerNextButtonBlockLeadingToSummary();
	}

	private function populateLowerNextButtonBlockLeadingToSummary()
	{
		$this->tpl->setCurrentBlock( "next_bottom" );
		$this->tpl->setVariable( "BTN_NEXT", $this->lng->txt( "question_summary" ) . " &gt;&gt;" );
		$this->tpl->parseCurrentBlock();
	}

	private function populateUpperNextButtonBlockLeadingToSummary()
	{
		$this->tpl->setCurrentBlock( "next" );
		$this->tpl->setVariable( "BTN_NEXT", $this->lng->txt( "question_summary" ) . " &gt;&gt;" );
		$this->tpl->parseCurrentBlock();
	}

	private function populateCancelButtonBlock()
	{
		$this->tpl->setCurrentBlock( "cancel_test" );
		$this->tpl->setVariable( "TEXT_CANCELTEST", $this->lng->txt( "cancel_test" ) );
		$this->tpl->setVariable( "TEXT_ALTCANCELTEXT", $this->lng->txt( "cancel_test" ) );
		$this->tpl->setVariable( "TEXT_TITLECANCELTEXT", $this->lng->txt( "cancel_test" ) );
		$this->tpl->setVariable( "HREF_IMGCANCELTEST",
								 $this->ctrl->getLinkTargetByClass( get_class( $this ), "outIntroductionPage"
								 ) . "&cancelTest=true"
		);
		$this->tpl->setVariable( "HREF_CANCELTEXT",
								 $this->ctrl->getLinkTargetByClass( get_class( $this ), "outIntroductionPage"
								 ) . "&cancelTest=true"
		);
		$this->tpl->setVariable( "IMAGE_CANCEL", ilUtil::getImagePath( "cancel.png" ) );
		$this->tpl->parseCurrentBlock();
	}

	private function populateSummaryButtons()
	{
		$this->populateUpperSummaryButtonBlock();
		$this->populateLowerSummaryButtonBlock();
	}

	private function populateLowerSummaryButtonBlock()
	{
		$this->tpl->setCurrentBlock( "summary_bottom" );
		$this->tpl->setVariable( "BTN_SUMMARY", $this->lng->txt( "question_summary" ) );
		$this->tpl->parseCurrentBlock();
	}

	private function populateUpperSummaryButtonBlock()
	{
		$this->tpl->setCurrentBlock( "summary" );
		$this->tpl->setVariable( "BTN_SUMMARY", $this->lng->txt( "question_summary" ) );
		$this->tpl->parseCurrentBlock();
	}

	private function populatePostponeButtons()
	{
		$this->populateUpperPostponeButtonBlock();
		$this->populateLowerPostponeButtonBlock();
	}

	private function populateLowerPostponeButtonBlock()
	{
		$this->tpl->setCurrentBlock( "postpone_bottom" );
		$this->tpl->setVariable( "BTN_POSTPONE", $this->lng->txt( "postpone" ) );
		$this->tpl->parseCurrentBlock();
	}

	private function populateUpperPostponeButtonBlock()
	{
		$this->tpl->setCurrentBlock( "postpone" );
		$this->tpl->setVariable( "BTN_POSTPONE", $this->lng->txt( "postpone" ) );
		$this->tpl->parseCurrentBlock();
	}

	private function isFirstPageInSequence($sequence)
	{
		return $sequence == $this->object->getTestSequence()->getFirstSequence();
	}

	private function populatePreviousButtonLeadingToQuestion()
	{
		$this->populateUpperPreviousButtonBlockLeadingToQuestion();
		$this->populateLowerPreviousButtonBlockLeadingToQuestion();
	}

	private function populateLowerPreviousButtonBlockLeadingToQuestion()
	{
		$this->tpl->setCurrentBlock( "prev_bottom" );
		$this->tpl->setVariable( "BTN_PREV", "&lt;&lt; " . $this->lng->txt( "save_previous" ) );
		$this->tpl->parseCurrentBlock();
	}

	private function populateUpperPreviousButtonBlockLeadingToQuestion()
	{
		$this->tpl->setCurrentBlock( "prev" );
		$this->tpl->setVariable( "BTN_PREV", "&lt;&lt; " . $this->lng->txt( "save_previous" ) );
		$this->tpl->parseCurrentBlock();
	}

	private function populatePreviousButtonsLeadingToIntroduction()
	{
		$this->populateUpperPreviousButtonBlockLeadingToIntroduction();
		$this->populateLowerPreviousButtonBlockLeadingToIntroduction();
	}

	private function populateLowerPreviousButtonBlockLeadingToIntroduction()
	{
		$this->tpl->setCurrentBlock( "prev_bottom" );
		$this->tpl->setVariable( "BTN_PREV", "&lt;&lt; " . $this->lng->txt( "save_introduction" ) );
		$this->tpl->parseCurrentBlock();
	}

	private function populateUpperPreviousButtonBlockLeadingToIntroduction()
	{
		$this->tpl->setCurrentBlock( "prev" );
		$this->tpl->setVariable( "BTN_PREV", "&lt;&lt; " . $this->lng->txt( "save_introduction" ) );
		$this->tpl->parseCurrentBlock();
	}

	private function populateSpecificFeedbackBlock($question_gui)
	{
		$this->tpl->setCurrentBlock( "specific_feedback" );
		$this->tpl->setVariable( "SPECIFIC_FEEDBACK",
								 $question_gui->getSpecificFeedbackOutput(
									 $this->object->getTestSession()->getActiveId(),
									 NULL
								 )
		);
		$this->tpl->parseCurrentBlock();
	}

	private function populateGenericFeedbackBlock($question_gui)
	{
		$this->tpl->setCurrentBlock( "answer_feedback" );
		$this->tpl->setVariable( "ANSWER_FEEDBACK",
								 $question_gui->getAnswerFeedbackOutput( $this->object->getTestSession()->getActiveId(),
																		 NULL
								 )
		);
		$this->tpl->parseCurrentBlock();
	}

	private function populateScoreBlock($reachedPoints, $maxPoints)
	{
		$this->tpl->setCurrentBlock( "solution_output" );
		$this->tpl->setVariable( "RECEIVED_POINTS_INFORMATION",
								 sprintf( $this->lng->txt( "you_received_a_of_b_points" ), $reachedPoints, $maxPoints )
		);
		$this->tpl->parseCurrentBlock();
	}

	private function populateSolutionBlock($solutionoutput)
	{
		$this->tpl->setCurrentBlock( "solution_output" );
		$this->tpl->setVariable( "CORRECT_SOLUTION", $this->lng->txt( "tst_best_solution_is" ) );
		$this->tpl->setVariable( "QUESTION_FEEDBACK", $solutionoutput );
		$this->tpl->parseCurrentBlock();
	}

	private function showSideList()
	{
		global $ilUser;
		$show_side_list = $ilUser->getPref( 'side_list_of_questions' );
		$this->tpl->setCurrentBlock( 'view_sidelist' );
		$this->tpl->setVariable( 'IMAGE_SIDELIST',
								 ($show_side_list) ? ilUtil::getImagePath( 'view_remove.png'
								 ) : ilUtil::getImagePath( 'view_choose.png' )
		);
		$this->tpl->setVariable( 'TEXT_SIDELIST',
								 ($show_side_list) ? $this->lng->txt( 'tst_hide_side_list'
								 ) : $this->lng->txt( 'tst_show_side_list' )
		);
		$this->tpl->parseCurrentBlock();
		if ($show_side_list)
		{
			$this->tpl->addCss( ilUtil::getStyleSheetLocation( "output", "ta_split.css", "Modules/Test" ), "screen" );
			$this->outQuestionSummary( false );
		}
	}

	private function populateSyntaxStyleBlock()
	{
		$this->tpl->setCurrentBlock( "SyntaxStyle" );
		$this->tpl->setVariable( "LOCATION_SYNTAX_STYLESHEET",
								 ilObjStyleSheet::getSyntaxStylePath()
		);
		$this->tpl->parseCurrentBlock();
	}

	private function populateContentStyleBlock()
	{
		include_once("./Services/Style/classes/class.ilObjStyleSheet.php");
		$this->tpl->setCurrentBlock( "ContentStyle" );
		$this->tpl->setVariable( "LOCATION_CONTENT_STYLESHEET",
								 ilObjStyleSheet::getContentStylePath( 0 )
		);
		$this->tpl->parseCurrentBlock();
	}

	/**
* Displays a password protection page when a test password is set
*
* @access public
*/
	function showPasswordProtectionPage()
	{
		$template = new ilTemplate("tpl.il_as_tst_password_protection.html", TRUE, TRUE, "Modules/Test");
		$template->setVariable("FORMACTION", $this->ctrl->getFormAction($this, "checkPassword"));
		$template->setVariable("PASSWORD_INTRODUCTION", $this->lng->txt("tst_password_introduction"));
		$template->setVariable("TEXT_PASSWORD", $this->lng->txt("tst_password"));
		$template->setVariable("SUBMIT", $this->lng->txt("submit"));
		$this->tpl->setVariable($this->getContentBlockName(), $template->get());
	}
	
/**
* Check the password, a user entered for test access
*
* @access public
*/
	function checkPassword()
	{
		if (strcmp($this->object->getPassword(), $_POST["password"]) == 0)
		{
			global $ilUser;
			if ($_SESSION["AccountId"] != ANONYMOUS_USER_ID)
			{
				$ilUser->setPref("tst_password_".$this->object->getTestId(), $this->object->getPassword());
				$ilUser->writePref("tst_password_".$this->object->getTestId(), $this->object->getPassword());
			}
			else
			{
				$_SESSION['tst_password_'.$this->object->getTestId()] = $this->object->getPassword();
			}
			$this->ctrl->redirect($this, "start");
		}
		else
		{
			ilUtil::sendFailure($this->lng->txt("tst_password_entered_wrong_password"), true);
			$this->ctrl->redirectByClass("ilobjtestgui", "infoScreen"); 
		}
	}
	
/**
* Sets a session variable with the test access code for an anonymous test user
*
* Sets a session variable with the test access code for an anonymous test user
*
* @access public
*/
	function setAnonymousId()
	{
		if ($_SESSION["AccountId"] == ANONYMOUS_USER_ID)
		{
			$this->object->setAccessCodeSession($_POST["anonymous_id"]);
		}
		$this->ctrl->redirectByClass("ilobjtestgui", "infoScreen");
	}

/**
* Start a test for the first time
*
* Start a test for the first time. This method contains a lock
* to prevent multiple submissions by the start test button
*
* @access public
*/
	function start()
	{
		if (strcmp($_SESSION["lock"], $_POST["lock"]) != 0)
		{
			$_SESSION["lock"] = $_POST["lock"];
			$this->handleStartCommands();
			$this->ctrl->redirect($this, "startTest");
		}
		else
		{
			$this->ctrl->redirectByClass("ilobjtestgui", "redirectToInfoScreen");
		}
	}

/**
* Start a test for the first time after a redirect
*
* @access public
*/
	function startTest()
	{
		if ($this->object->checkMaximumAllowedUsers() == FALSE)
		{
			return $this->showMaximumAllowedUsersReachedMessage();
		}
		if ($_SESSION["AccountId"] == ANONYMOUS_USER_ID)
		{
			$this->object->setAccessCodeSession($this->object->createNewAccessCode());
		}
		else
		{
			$this->object->unsetAccessCodeSession();
		}
		if (strlen($this->object->getPassword()))
		{
			global $ilUser;
			global $rbacsystem;
			
			$pwd = '';
			if( $_SESSION["AccountId"] != ANONYMOUS_USER_ID )
			{
				$pwd = $ilUser->getPref("tst_password_".$this->object->getTestId());
			}
			elseif( isset($_SESSION['tst_password_'.$this->object->getTestId()]) )
			{
				$pwd = $_SESSION['tst_password_'.$this->object->getTestId()];
			}
			
			if ((strcmp($pwd, $this->object->getPassword()) != 0) && (!$rbacsystem->checkAccess("write", $this->object->getRefId())))
			{
				return $this->showPasswordProtectionPage();
			}
		}
		if ($_SESSION["AccountId"] == ANONYMOUS_USER_ID)
		{
			$this->ctrl->redirect($this, "displayCode");
		}
		else
		{
			$this->ctrl->setParameter($this, "activecommand", "start");
			$this->ctrl->redirect($this, "redirectQuestion");
		}
	}
	
	function displayCode()
	{
		$this->tpl->addBlockFile($this->getContentBlockName(), "adm_content", "tpl.il_as_tst_anonymous_code_presentation.html", "Modules/Test");
		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("TEXT_ANONYMOUS_CODE_CREATED", $this->lng->txt("tst_access_code_created"));
		$this->tpl->setVariable("TEXT_ANONYMOUS_CODE", $this->object->getAccessCodeSession());
		$this->tpl->setVariable("FORMACTION", $this->ctrl->getFormAction($this));
		$this->tpl->setVariable("CONTINUE", $this->lng->txt("continue_work"));
		$this->tpl->parseCurrentBlock();
	}
	
	function codeConfirmed()
	{
		$this->ctrl->setParameter($this, "activecommand", "start");
		$this->ctrl->redirect($this, "redirectQuestion");
	}

/**
* Resume a test at the last position
*
* Resume a test at the last position
*
* @access public
*/
	function resume()
	{
		if ($this->object->checkMaximumAllowedUsers() == FALSE)
		{
			return $this->showMaximumAllowedUsersReachedMessage();
		}
		$this->handleStartCommands();
		$this->ctrl->setParameter($this, "activecommand", "resume");
		$this->ctrl->redirect($this, "redirectQuestion");
	}

/**
* Handles some form parameters on starting and resuming a test
*/
	public function handleStartCommands()
	{
		global $ilUser;

		if ($_POST["chb_javascript"])
		{
			$ilUser->writePref("tst_javascript", 1);
		}
		else
		{
			$ilUser->writePref("tst_javascript", 0);
		}
		
		// hide previous results
		if ($this->object->getNrOfTries() != 1)
		{
			if ($this->object->getUsePreviousAnswers() == 1)
			{
				if ($_POST["chb_use_previous_answers"])
				{
					$ilUser->writePref("tst_use_previous_answers", 1);
				}
				else
				{ 
					$ilUser->writePref("tst_use_previous_answers", 0);
				}
			}
		}
/*		if ($this->object->getTestType() == TYPE_ONLINE_TEST)
		{
			global $ilias;
			$ilias->auth->setIdle(0, false);					
		}*/
	}
	
/**
* Called when a user answered a question to perform a redirect after POST.
* This is called for security reasons to prevent users sending a form twice.
*
* @access public
*/
	function redirectQuestion()
	{
		global $ilUser;

		// check the test restrictions to access the test in case one
		// of the test navigation commands was called by an external script
		// e.g. $ilNavigationHistory
		$executable = $this->object->isExecutable($ilUser->getId());
		if (!$executable["executable"])
		{
			ilUtil::sendInfo($executable["errormessage"], TRUE);
			$this->ctrl->redirectByClass("ilobjtestgui", "infoScreen");
		}
		switch ($_GET["activecommand"])
		{
			case "next":
				$this->sequence = $this->calculateSequence();
				if ($this->sequence === FALSE)
				{
					if ($this->object->getListOfQuestionsEnd())
					{
						
						$allObligationsAnswered = ilObjTest::allObligationsAnswered(
								$this->object->getTestSession()->getTestId(),
								$this->object->getTestSession()->getActiveId(),
								$this->object->getTestSession()->getPass()
						);

						if( $this->object->areObligationsEnabled() && !$allObligationsAnswered )
						{
							$this->ctrl->redirect($this, "outQuestionSummaryWithObligationsInfo");
						}
						
						$this->outQuestionSummary();
					}
					else
					{
						$this->ctrl->redirect($this, "finishTest");
					}
				}
				else
				{
					$this->object->getTestSession()->setLastSequence($this->sequence);
					$this->object->getTestSession()->saveToDb();
					$this->outTestPage();
				}
				break;
			case "previous":
				$this->sequence = $this->calculateSequence();
				$this->object->getTestSession()->setLastSequence($this->sequence);
				$this->object->getTestSession()->saveToDb();
				if ($this->sequence === FALSE)
				{
					$this->ctrl->redirect($this, "outIntroductionPage");
				}
				else
				{
					$this->outTestPage();
				}
				break;
			case "postpone":
				$this->sequence = $this->calculateSequence();
				$nextSequence = $this->object->getTestSequence()->getNextSequence($this->sequence);
				$this->object->getTestSequence()->postponeSequence($this->sequence);
				$this->object->getTestSequence()->saveToDb();
				$this->object->getTestSession()->setLastSequence($nextSequence);
				$this->object->getTestSession()->saveToDb();
				$this->sequence = $nextSequence;
				$this->outTestPage();
				break;
			case "setmarked":
				$this->sequence = $this->calculateSequence();	
				$this->object->getTestSession()->setLastSequence($this->sequence);
				$this->object->getTestSession()->saveToDb();
				$q_id  = $this->object->getTestSequence()->getQuestionForSequence($_GET["sequence"]);
				$this->object->setQuestionSetSolved(1, $q_id, $ilUser->getId());
				$this->outTestPage();
				break;
			case "resetmarked":
				$this->sequence = $this->calculateSequence();	
				$this->object->getTestSession()->setLastSequence($this->sequence);
				$this->object->getTestSession()->saveToDb();
				$q_id  = $this->object->getTestSequence()->getQuestionForSequence($_GET["sequence"]);
				$this->object->setQuestionSetSolved(0, $q_id, $ilUser->getId());
				$this->outTestPage();
				break;
			case "directfeedback":
				$this->sequence = $this->calculateSequence();	
				$this->object->getTestSession()->setLastSequence($this->sequence);
				$this->object->getTestSession()->saveToDb();
				$this->outTestPage();
				break;
			case "selectImagemapRegion":
				$this->sequence = $this->calculateSequence();	
				$this->object->getTestSession()->setLastSequence($this->sequence);
				$this->object->getTestSession()->saveToDb();
				$this->outTestPage();
				break;
			case "summary":
				$this->ctrl->redirect($this, "outQuestionSummary");
				break;
			case "summary_obligations":
				$this->ctrl->redirect($this, "outQuestionSummaryWithObligationsInfo");
				break;
			case "summary_obligations_only":
				$this->ctrl->redirect($this, "outObligationsOnlySummary");
				break;
			case "start":
				$_SESSION['tst_pass_finish'] = 0;
				$this->object->createTestSession();
				$active_id = $this->object->getTestSession()->getActiveId();
				
				assQuestion::_updateTestPassResults(
						$active_id, $this->object->getTestSession()->getPass(), $this->object->areObligationsEnabled()
				);
				
				$this->ctrl->setParameter($this, "active_id", $active_id);
				$shuffle = $this->object->getShuffleQuestions();
				if ($this->object->isRandomTest())
				{
					$this->object->generateRandomQuestions($this->object->getTestSession()->getActiveId());
					$this->object->loadQuestions();
					$shuffle = FALSE; // shuffle is already done during the creation of the random questions
				}
				$this->object->createTestSequence($active_id, 0, $shuffle);
				$active_time_id = $this->object->startWorkingTime($this->object->getTestSession()->getActiveId(), $this->object->getTestSession()->getPass());
				$_SESSION["active_time_id"] = $active_time_id;
				if ($this->object->getListOfQuestionsStart())
				{
					$this->ctrl->setParameter($this, "activecommand", "summary");
					$this->ctrl->redirect($this, "redirectQuestion");
				}
				else
				{
					$this->ctrl->setParameter($this, "sequence", $this->sequence);
					$this->ctrl->setParameter($this, "activecommand", "gotoquestion");
					$this->ctrl->saveParameter($this, "tst_javascript");
					$this->ctrl->redirect($this, "redirectQuestion");
				}
				break;
			case "resume":
				$_SESSION['tst_pass_finish'] = 0;
				$active_id = $this->object->getTestSession()->getActiveId();
				$this->ctrl->setParameter($this, "active_id", $active_id);

				if ($this->object->isRandomTest())
				{
					if (!$this->object->hasRandomQuestionsForPass($active_id, $this->object->getTestSession()->getPass()))
					{
						// create a new set of random questions
						$this->object->generateRandomQuestions($active_id, $this->object->getTestSession()->getPass());
					}
				}
				$shuffle = $this->object->getShuffleQuestions();
				if ($this->object->isRandomTest())
				{
					$shuffle = FALSE;
				}
				$this->object->createTestSequence($active_id, $this->object->getTestSession()->getPass(), $shuffle);

				$this->sequence = $this->object->getTestSession()->getLastSequence();
				$active_time_id = $this->object->startWorkingTime($active_id, $this->object->getTestSession()->getPass());
				$_SESSION["active_time_id"] = $active_time_id;
				if ($this->object->getListOfQuestionsStart())
				{
					$this->ctrl->setParameter($this, "activecommand", "summary");
					$this->ctrl->redirect($this, "redirectQuestion");
				}
				else
				{
					$this->ctrl->setParameter($this, "sequence", $this->sequence);
					$this->ctrl->setParameter($this, "activecommand", "gotoquestion");
					$this->ctrl->saveParameter($this, "tst_javascript");
					$this->ctrl->redirect($this, "redirectQuestion");
				}
				break;
				
			case "back":
			case "gotoquestion":
			default:
				$_SESSION['tst_pass_finish'] = 0;
				if (array_key_exists("tst_javascript", $_GET))
				{
					$ilUser->writePref("tst_javascript", $_GET["tst_javascript"]);
				}
				$this->sequence = $this->calculateSequence();	
				if (strlen($_GET['gotosequence'])) $this->sequence = $_GET['gotosequence'];
				$this->object->getTestSession()->setLastSequence($this->sequence);
				$this->object->getTestSession()->saveToDb();
				$this->outTestPage();
				break;
		}
	}
	
/**
* Calculates the sequence to determine the next question
*
* @access public
*/
	function calculateSequence() 
	{
		$sequence = $_GET["sequence"];
		if (!$sequence) $sequence = $this->object->getTestSequence()->getFirstSequence();
		if (array_key_exists("save_error", $_GET))
		{
			if ($_GET["save_error"] == 1)
			{
				return $sequence;
			}
		}
		switch ($_GET["activecommand"])
		{
			case "next":
				$sequence = $this->object->getTestSequence()->getNextSequence($sequence);
				break;
			case "previous":
				$sequence = $this->object->getTestSequence()->getPreviousSequence($sequence);
				break;
		}
		return $sequence;
	}
	
	function redirectAfterAutosave()
	{
		$this->tpl->addBlockFile($this->getContentBlockName(), "adm_content", "tpl.il_as_tst_redirect_autosave.html", "Modules/Test");	
		$this->tpl->setVariable("TEXT_REDIRECT", $this->lng->txt("redirectAfterSave"));
		$this->tpl->setCurrentBlock("HeadContent");
		$this->tpl->setVariable("CONTENT_BLOCK", "<meta http-equiv=\"refresh\" content=\"5; url=" . $this->ctrl->getLinkTarget($this, "redirectBack") . "\">");
		$this->tpl->parseCurrentBlock();
	}
	
	function autosave()
	{
		global $ilLog;
		$result = "";
		if (is_array($_POST) && count($_POST) > 0)
		{
			$res = $this->saveQuestionSolution(TRUE);
			if ($res)
			{
				$result = $this->lng->txt("autosave_success");
			}
			else
			{
				$result = $this->lng->txt("autosave_failed");
			}
		}
		if (!$this->canSaveResult())
		{
			// this was the last action in the test, saving is no longer allowed
			$result = $this->ctrl->getLinkTarget($this, "redirectAfterAutosave", "", true);
		}
		echo $result;
		exit;
	}
	
	/**
	* Toggle side list
	*/
	public function togglesidelist()
	{
		global $ilUser;

		$show_side_list = $ilUser->getPref('side_list_of_questions');
		$ilUser->writePref('side_list_of_questions', !$show_side_list);
		$this->saveQuestionSolution();
		$this->ctrl->redirect($this, "redirectQuestion");
	}
	
/**
* Go to the next question
*
* Go to the next question
*
* @access public
*/
	function next()
	{
		$this->saveQuestionSolution();
		$this->ctrl->setParameter($this, "activecommand", "next");
		$this->ctrl->redirect($this, "redirectQuestion");
	}
	
/**
* Go to the previous question
*
* Go to the previous question
*
* @access public
*/
	function previous()
	{
		$this->saveQuestionSolution();
		$this->ctrl->setParameter($this, "activecommand", "previous");
		$this->ctrl->redirect($this, "redirectQuestion");
	}
	
/**
* Postpone a question to the end of the test
*
* Postpone a question to the end of the test
*
* @access public
*/
	function postpone()
	{
		$this->saveQuestionSolution();
		$this->ctrl->setParameter($this, "activecommand", "postpone");
		$this->ctrl->redirect($this, "redirectQuestion");
	}

/**
* Show the question summary in online exams
*
* Show the question summary in online exams
*
* @access public
*/
	function summary()
	{
		$this->saveQuestionSolution();
		if ($this->saveResult == FALSE)
		{
			$this->ctrl->setParameter($this, "activecommand", "");
			$this->ctrl->redirect($this, "redirectQuestion");
		}
		else
		{
			$this->ctrl->setParameter($this, "activecommand", "summary");
			$this->ctrl->redirect($this, "redirectQuestion");
		}
	}

	function summaryWithoutSaving()
	{
		$this->ctrl->setParameter($this, "activecommand", "summary");
		$this->ctrl->redirect($this, "redirectQuestion");
	}
	
/**
* Set a question solved
*
* Set a question solved
*
* @access public
*/
	function setmarked()
	{
		$this->saveQuestionSolution();
		$this->ctrl->setParameter($this, "activecommand", "setmarked");
		$this->ctrl->redirect($this, "redirectQuestion");
	}

/**
* Set a question unsolved
*
* Set a question unsolved
*
* @access public
*/
	function resetmarked()
	{
		$this->saveQuestionSolution();
		$this->ctrl->setParameter($this, "activecommand", "resetmarked");
		$this->ctrl->redirect($this, "redirectQuestion");
	}
	
/**
* The direct feedback button was hit to show an instant feedback
*
* The direct feedback button was hit to show an instant feedback
*
* @access public
*/
	function directfeedback()
	{
		$this->saveQuestionSolution();
		$this->ctrl->setParameter($this, "activecommand", "directfeedback");
		$this->ctrl->redirect($this, "redirectQuestion");
	}
	
/**
* Select an image map region in an image map question
*
* Select an image map region in an image map question
*
* @access public
*/
	function selectImagemapRegion()
	{
		$this->saveQuestionSolution();
		$activecommand = "selectImagemapRegion";
		if (array_key_exists('cmd', $_POST))
		{
			$activecommand = key($_POST["cmd"]);
		}
		if (preg_match("/^gotoquestion_(\\d+)$/", $activecommand, $matches))
		{
			$activecommand = "gotoquestion";
			if (strlen($matches[1]))
			{
				$this->ctrl->setParameter($this, 'gotosequence', $matches[1]);
			}
		}
		if (strcmp($activecommand, "togglesidelist") == 0)
		{
			$this->togglesidelist();
		}
		else
		{
			$this->ctrl->setParameter($this, "activecommand", $activecommand);
			$this->ctrl->redirect($this, "redirectQuestion");
		}
	}
	
/**
* Go to the question with the active sequence
*
* Go to the question with the active sequence
*
* @access public
*/
	function gotoQuestion()
	{
		if (is_array($_POST) && count($_POST) > 0) $this->saveQuestionSolution();
		$this->ctrl->setParameter($this, "sequence", $_GET["sequence"]);
		$this->ctrl->setParameter($this, "activecommand", "gotoquestion");
		$this->ctrl->saveParameter($this, "tst_javascript");
		if (strlen($_GET['qst_selection'])) $_SESSION['qst_selection'] = $_GET['qst_selection'];
		$this->ctrl->redirect($this, "redirectQuestion");
	}
	
/**
* Go back to the last active question from the summary
*
* Go back to the last active question from the summary
*
* @access public
*/
	function backFromSummary()
	{
		$this->ctrl->setParameter($this, "activecommand", "back");
		$this->ctrl->redirect($this, "redirectQuestion");
	}

/**
* The final submission of a test was confirmed
*
* The final submission of a test was confirmed
*
* @access public
*/
	function confirmFinish()
	{
		$this->finishTest(false);
	}
	
/**
* Confirmation of the tests final submission
*
* Confirmation of the tests final submission
*
* @access public
*/
	function confirmFinishTest()
	{
		global $ilUser;
		
		$template = new ilTemplate("tpl.il_as_tst_finish_confirmation.html", TRUE, TRUE, "Modules/Test");
		$template->setVariable("FINISH_QUESTION", $this->lng->txt("tst_finish_confirmation_question"));
		$template->setVariable("BUTTON_CONFIRM", $this->lng->txt("tst_finish_confirm_button"));
		if ($this->object->canShowSolutionPrintview($ilUser->getId()))
		{
			$template->setVariable("BUTTON_CANCEL", $this->lng->txt("tst_finish_confirm_list_of_answers_button"));
		}
		else
		{
			$template->setVariable("BUTTON_CANCEL", $this->lng->txt("tst_finish_confirm_cancel_button"));
		}
		$template->setVariable("FORMACTION", $this->ctrl->getFormAction($this));
		$this->tpl->setVariable($this->getContentBlockName(), $template->get());
	}
	
/**
* Finish the test
*
* Finish the test
*
* @access public
*/
	function finishTest($confirm = true)
	{
		global $ilUser;
		global $ilias;
		global $ilAuth;
		
		unset($_SESSION["tst_next"]);
		
		$active_id = $this->object->getTestSession()->getActiveId();
		$actualpass = $this->object->_getPass($active_id);
		
		$allObligationsAnswered = ilObjTest::allObligationsAnswered($this->object->getTestSession()->getTestId(), $active_id, $actualpass);
		
		if( $this->object->areObligationsEnabled() && !$allObligationsAnswered )
		{
			if( $this->object->getListOfQuestions() )
			{
				$_GET['activecommand'] = 'summary_obligations';
			}
			else
			{
				$_GET['activecommand'] = 'summary_obligations_only';
			}
			
			$this->redirectQuestion();
			return;
		}
		
		if (($actualpass == $this->object->getNrOfTries() - 1) && (!$confirm))
		{
			$this->object->setActiveTestSubmitted($ilUser->getId());
			$ilAuth->setIdle(ilSession::getIdleValue(), false);
			$ilAuth->setExpire(0);
			switch ($this->object->getMailNotification())
			{
				case 1:
					$this->object->sendSimpleNotification($active_id);
					break;
				case 2:
					$this->object->sendAdvancedNotification($active_id);
					break;
			}
		}
		
		if (($confirm) && ($actualpass == $this->object->getNrOfTries() - 1))
		{
			if ($this->object->canShowSolutionPrintview($ilUser->getId()))
			{
				$template = new ilTemplate("tpl.il_as_tst_finish_navigation.html", TRUE, TRUE, "Modules/Test");
				$template->setVariable("BUTTON_FINISH", $this->lng->txt("btn_next"));
				$template->setVariable("BUTTON_CANCEL", $this->lng->txt("btn_previous"));
				
				$template_top = new ilTemplate("tpl.il_as_tst_list_of_answers_topbuttons.html", TRUE, TRUE, "Modules/Test");
				$template_top->setCurrentBlock("button_print");
				$template_top->setVariable("BUTTON_PRINT", $this->lng->txt("print"));
				$template_top->parseCurrentBlock();

				$this->showListOfAnswers($active_id, NULL, $template_top->get(), $template->get());
				return;
			}
			else
			{
				// show confirmation page
				return $this->confirmFinishTest();
			}
		}

		if (!$_SESSION['tst_pass_finish'])
		{
			if (!$_SESSION['tst_pass_finish']) $_SESSION['tst_pass_finish'] = 1;
			if ($this->object->getMailNotificationType() == 1)
			{
				switch ($this->object->getMailNotification())
				{
					case 1:
						$this->object->sendSimpleNotification($active_id);
						break;
					case 2:
						$this->object->sendAdvancedNotification($active_id);
						break;
				}
			}
			$this->object->getTestSession()->increaseTestPass();
		}
		$this->redirectBack();
	}
	
	public function redirectBack()
	{
		if (!$_GET["skipfinalstatement"])
		{
			if ($this->object->getShowFinalStatement())
			{
				$this->ctrl->redirect($this, "showFinalStatement");
			}
		}
		if($_GET['crs_show_result'])
		{
			$this->ctrl->redirectByClass("ilobjtestgui", "backToCourse");
		}

		if (!$this->object->canViewResults()) 
		{
			$this->outIntroductionPage();
		}
		else
		{
			$this->ctrl->redirectByClass("ilTestEvaluationGUI", "outUserResultsOverview");
		}
	}
	
	/*
	* Presents the final statement of a test
	*/
	public function showFinalStatement()
	{
		$template = new ilTemplate("tpl.il_as_tst_final_statement.html", TRUE, TRUE, "Modules/Test");
		$this->ctrl->setParameter($this, "crs_show_result", $_GET['crs_show_result']);
		$this->ctrl->setParameter($this, "skipfinalstatement", 1);
		$template->setVariable("FORMACTION", $this->ctrl->getFormAction($this, "redirectBack"));
		$template->setVariable("FINALSTATEMENT", $this->object->getFinalStatement());
		$template->setVariable("BUTTON_CONTINUE", $this->lng->txt("btn_next"));
		$this->tpl->setVariable($this->getContentBlockName(), $template->get());
	}
	
	public function getKioskHead()
	{
		global $ilUser;
		
		$template = new ilTemplate('tpl.il_as_tst_kiosk_head.html', true, true, 'Modules/Test');
		if ($this->object->getShowKioskModeTitle())
		{
			$template->setCurrentBlock("kiosk_show_title");
			$template->setVariable("TEST_TITLE", $this->object->getTitle());
			$template->parseCurrentBlock();
		}
		if ($this->object->getShowKioskModeParticipant())
		{
			$template->setCurrentBlock("kiosk_show_participant");
			$template->setVariable("PARTICIPANT_NAME", $this->lng->txt("login_as") . " " . $ilUser->getFullname());
			$template->parseCurrentBlock();
		}
		return $template->get();
	}
	
/**
* Outputs the question of the active sequence
*/
	function outTestPage()
	{
		global $rbacsystem, $ilUser;

		$this->tpl->addBlockFile($this->getContentBlockName(), "adm_content", "tpl.il_as_tst_output.html", "Modules/Test");	
		if (!$rbacsystem->checkAccess("read", $this->object->getRefId())) 
		{
			// only with read access it is possible to run the test
			$this->ilias->raiseError($this->lng->txt("cannot_execute_test"),$this->ilias->error_obj->MESSAGE);
		}
		
		if ($this->isMaxProcessingTimeReached())
		{
			$this->maxProcessingTimeReached();
			return;
		}
		
		if ($this->object->endingTimeReached())
		{
			$this->endingTimeReached();
			return;
		}
			
		if ($this->object->getKioskMode())
		{
			ilUtil::sendInfo();
			$head = $this->getKioskHead();
			if (strlen($head))
			{
				$this->tpl->setCurrentBlock("kiosk_options");
				$this->tpl->setVariable("KIOSK_HEAD", $head);
				$this->tpl->parseCurrentBlock();
			}
		}

		if ($this->object->getEnableProcessingTime())
		{
			$this->outProcessingTime($this->object->getTestSession()->getActiveId());
		}

		$this->tpl->setVariable("FORM_TIMESTAMP", time());
		
		$this->tpl->setVariable("PAGETITLE", "- " . $this->object->getTitle());
				
		$postpone = false;
		if ($this->object->getSequenceSettings() == TEST_POSTPONE)
		{
			$postpone = true;
		}
		$directfeedback = 0;
		if (strcmp($_GET["activecommand"], "directfeedback") == 0)
		{
			$directfeedback = 1;
		}
		
		$this->outWorkingForm($this->sequence, $this->object->getTestId(), $postpone, $directfeedback, $show_summary);
	}

/**
* check access restrictions like client ip, partipating user etc.
*
* check access restrictions like client ip, partipating user etc.
*
* @access public
*/
	function checkOnlineTestAccess() 
	{
		global $ilUser;
		
		// check if user is invited to participate
		$user = $this->object->getInvitedUsers($ilUser->getId());
		if (!is_array ($user) || count($user)!=1)
		{
				ilUtil::sendInfo($this->lng->txt("user_not_invited"), true);
				$this->ctrl->redirectByClass("ilobjtestgui", "backToRepository");
		}
			
		$user = array_pop($user);
		// check if client ip is set and if current remote addr is equal to stored client-ip			
		if (strcmp($user["clientip"],"")!=0 && strcmp($user["clientip"],$_SERVER["REMOTE_ADDR"])!=0)
		{
			ilUtil::sendInfo($this->lng->txt("user_wrong_clientip"), true);
			$this->ctrl->redirectByClass("ilobjtestgui", "backToRepository");
		}		
	}	

	
/**
 * test accessible returns true if the user can perform the test
 */
	function isTestAccessible() 
	{		
		return 	!$this->isNrOfTriesReached() 				
			 	and	 !$this->isMaxProcessingTimeReached()
			 	and  $this->object->startingTimeReached()
			 	and  !$this->object->endingTimeReached();
	}

/**
 * nr of tries exceeded
 */
	function isNrOfTriesReached() 
	{
		return $this->object->hasNrOfTriesRestriction() && $this->object->isNrOfTriesReached($this->object->getTestSession()->getPass());	
	}
	
/**
* Output of the learners view of an existing test pass
*
* Output of the learners view of an existing test pass
*
* @access public
*/
	function passDetails()
	{
		if (array_key_exists("pass", $_GET) && (strlen($_GET["pass"]) > 0))
		{
			$this->ctrl->saveParameter($this, "pass");
			$this->ctrl->saveParameter($this, "active_id");
			$this->outTestResults(false, $_GET["pass"]);
		}
		else
		{
			$this->outTestResults(false);
		}
	}
	
	/**
	 * handle endingTimeReached
	 * @private
	 */
	
	function endingTimeReached() 
	{
		ilUtil::sendInfo(sprintf($this->lng->txt("detail_ending_time_reached"), ilFormat::ftimestamp2datetimeDB($this->object->getEndingTime())));
		$this->object->getTestSession()->increasePass();
		$this->object->getTestSession()->setLastSequence(0);
		$this->object->getTestSession()->saveToDb();
		if (!$this->object->canViewResults()) 
		{
			$this->outIntroductionPage();
		}
		else
		{
			$this->ctrl->redirectByClass("ilTestEvaluationGUI", "outUserResultsOverview");
		}
	}
	
/**
* Outputs a message when the maximum processing time is reached
*
* Outputs a message when the maximum processing time is reached
*
* @access public
*/
	function maxProcessingTimeReached()
	{
		$this->outIntroductionPage();
	}		

	/**
	* confirm submit results
	* if confirm then results are submitted and the screen will be redirected to the startpage of the test
	* @access public
	*/
	function confirmSubmitAnswers() 
	{
		$this->tpl->addBlockFile($this->getContentBlockName(), "adm_content", "tpl.il_as_tst_submit_answers_confirm.html", "Modules/Test");
		$this->tpl->setCurrentBlock("adm_content");
		if ($this->object->isTestFinished($this->object->getTestSession()->getActiveId()))
		{
			$this->tpl->setCurrentBlock("not_submit_allowed");
			$this->tpl->setVariable("TEXT_ALREADY_SUBMITTED", $this->lng->txt("tst_already_submitted"));
			$this->tpl->setVariable("BTN_OK", $this->lng->txt("tst_show_answer_sheet"));
		} else 
		{
			$this->tpl->setCurrentBlock("submit_allowed");
			$this->tpl->setVariable("TEXT_CONFIRM_SUBMIT_RESULTS", $this->lng->txt("tst_confirm_submit_answers"));
			$this->tpl->setVariable("BTN_OK", $this->lng->txt("tst_submit_results"));
		}
		$this->tpl->setVariable("BTN_BACK", $this->lng->txt("back"));		
		$this->tpl->setVariable("FORMACTION", $this->ctrl->getFormAction($this, "finalSubmission"));
		$this->tpl->parseCurrentBlock();
	}
	
	function outProcessingTime($active_id) 
	{
		global $ilUser;

		$starting_time = $this->object->getStartingTimeOfUser($active_id);
		$processing_time = $this->object->getProcessingTimeInSeconds();
		$processing_time_minutes = floor($processing_time / 60);
		$processing_time_seconds = $processing_time - $processing_time_minutes * 60;
		$str_processing_time = "";
		if ($processing_time_minutes > 0)
		{
			$str_processing_time = $processing_time_minutes . " " . ($processing_time_minutes == 1 ? $this->lng->txt("minute") : $this->lng->txt("minutes"));
		}
		if ($processing_time_seconds > 0)
		{
			if (strlen($str_processing_time) > 0) $str_processing_time .= " " . $this->lng->txt("and") . " ";
			$str_processing_time .= $processing_time_seconds . " " . ($processing_time_seconds == 1 ? $this->lng->txt("second") : $this->lng->txt("seconds"));
		}
		$time_left = $starting_time + $processing_time - mktime();
		$time_left_minutes = floor($time_left / 60);
		$time_left_seconds = $time_left - $time_left_minutes * 60;
		$str_time_left = "";
		if ($time_left_minutes > 0)
		{
			$str_time_left = $time_left_minutes . " " . ($time_left_minutes == 1 ? $this->lng->txt("minute") : $this->lng->txt("minutes"));
		}
		if ($time_left < 300)
		{
			if ($time_left_seconds > 0)
			{
				if (strlen($str_time_left) > 0) $str_time_left .= " " . $this->lng->txt("and") . " ";
				$str_time_left .= $time_left_seconds . " " .  ($time_left_seconds == 1 ? $this->lng->txt("second") : $this->lng->txt("seconds"));
			}
		}
		$date = getdate($starting_time);
		$formattedStartingTime = ilDatePresentation::formatDate(new ilDateTime($date,IL_CAL_FKT_GETDATE));
		/*
		$formattedStartingTime = ilFormat::formatDate(
			$date["year"]."-".
			sprintf("%02d", $date["mon"])."-".
			sprintf("%02d", $date["mday"])." ".
			sprintf("%02d", $date["hours"]).":".
			sprintf("%02d", $date["minutes"]).":".
			sprintf("%02d", $date["seconds"])
		);
		*/
		$datenow = getdate();
		$this->tpl->setCurrentBlock("enableprocessingtime");
		$this->tpl->setVariable("USER_WORKING_TIME", 
			sprintf(
				$this->lng->txt("tst_time_already_spent"),
				$formattedStartingTime,
				$str_processing_time
			)
		);
		$this->tpl->setVariable("USER_REMAINING_TIME", sprintf($this->lng->txt("tst_time_already_spent_left"), $str_time_left));
		$this->tpl->parseCurrentBlock();
		$template = new ilTemplate("tpl.workingtime.js.html", TRUE, TRUE, TRUE);
		$template->setVariable("STRING_MINUTE", $this->lng->txt("minute"));
		$template->setVariable("STRING_MINUTES", $this->lng->txt("minutes"));
		$template->setVariable("STRING_SECOND", $this->lng->txt("second"));
		$template->setVariable("STRING_SECONDS", $this->lng->txt("seconds"));
		$template->setVariable("STRING_TIMELEFT", $this->lng->txt("tst_time_already_spent_left"));
		$template->setVariable("AND", strtolower($this->lng->txt("and")));
		$template->setVariable("YEAR", $date["year"]);
		$template->setVariable("MONTH", $date["mon"]-1);
		$template->setVariable("DAY", $date["mday"]);
		$template->setVariable("HOUR", $date["hours"]);
		$template->setVariable("MINUTE", $date["minutes"]);
		$template->setVariable("SECOND", $date["seconds"]);
		if (preg_match("/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/", $this->object->getEndingTime(), $matches))
		{
			$template->setVariable("ENDYEAR", $matches[1]);
			$template->setVariable("ENDMONTH", $matches[2]-1);
			$template->setVariable("ENDDAY", $matches[3]);
			$template->setVariable("ENDHOUR", $matches[4]);
			$template->setVariable("ENDMINUTE", $matches[5]);
			$template->setVariable("ENDSECOND", $matches[6]);
		}
		$template->setVariable("YEARNOW", $datenow["year"]);
		$template->setVariable("MONTHNOW", $datenow["mon"]-1);
		$template->setVariable("DAYNOW", $datenow["mday"]);
		$template->setVariable("HOURNOW", $datenow["hours"]);
		$template->setVariable("MINUTENOW", $datenow["minutes"]);
		$template->setVariable("SECONDNOW", $datenow["seconds"]);
		$template->setVariable("PTIME_M", $processing_time_minutes);
		$template->setVariable("PTIME_S", $processing_time_seconds);
		
		$this->tpl->setCurrentBlock("HeadContent");
		$this->tpl->setVariable("CONTENT_BLOCK", $template->get());
		$this->tpl->parseCurrentBlock();
	}
	
	/**
	 * Output of a summary of all test questions for test participants
	 */
	public function outQuestionSummary($fullpage = true, $contextFinishTest = false, $obligationsNotAnswered = false, $obligationsFilter = false) 
	{
		if( $fullpage )
		{
			$this->tpl->addBlockFile($this->getContentBlockName(), "adm_content", "tpl.il_as_tst_question_summary.html", "Modules/Test");
		}
		
		if( $obligationsNotAnswered )
		{
			ilUtil::sendFailure($this->lng->txt('not_all_obligations_answered'));
		}
		
		$active_id = $this->object->getTestSession()->getActiveId();
		$result_array = & $this->object->getTestSequence()->getSequenceSummary($obligationsFilter);		
		$marked_questions = array();
		
		if( $this->object->getKioskMode() && $fullpage )
		{
			$head = $this->getKioskHead();
			if( strlen($head) )
			{
				$this->tpl->setCurrentBlock("kiosk_options");
				$this->tpl->setVariable("KIOSK_HEAD", $head);
				$this->tpl->parseCurrentBlock();
			}
		}
		
		if( $this->object->getShowMarker() )
		{
			include_once "./Modules/Test/classes/class.ilObjTest.php";
			$marked_questions = ilObjTest::_getSolvedQuestions($active_id);
		}
		
		$data = array();
		
		foreach( $result_array as $key => $value )
		{
			$this->ctrl->setParameter($this, "sequence", $value["sequence"]);
			
			$href = $this->ctrl->getLinkTargetByClass(get_class($this), "gotoQuestion");
			
			$this->tpl->setVariable("VALUE_QUESTION_TITLE", "<a href=\"".$this->ctrl->getLinkTargetByClass(get_class($this), "gotoQuestion")."\">" . $this->object->getQuestionTitle($value["title"]) . "</a>");
			
			$this->ctrl->setParameter($this, "sequence", $_GET["sequence"]);
			
			$description = "";
			if( $this->object->getListOfQuestionsDescription() )
			{
				$description = $value["description"];
			}
			
			$points = "";
			if( !$this->object->getTitleOutput() )
			{
				$points = $value["points"]."&nbsp;".$this->lng->txt("points_short");
			}
			
			$marked = false;
			if( count($marked_questions) )
			{
				if( array_key_exists($value["qid"], $marked_questions) )
				{
					$obj = $marked_questions[$value["qid"]];
					if( $obj["solved"] == 1 )
					{
						$marked = true;
					}
				} 
			}
			
			array_push($data, array(
				'order' => $value["nr"],
				'href' => $href,
				'title' => $this->object->getQuestionTitle($value["title"]),
				'description' => $description,
				'worked_through' => ($value["worked_through"]) ? true : false,
				'postponed' => ($value["postponed"]) ? $this->lng->txt("postponed") : '',
				'points' => $points,
				'marked' => $marked,
				'sequence' => $value["sequence"],
				'obligatory' => $value['obligatory']
			));
		}
		
		$this->ctrl->setParameter($this, "sequence", $_GET["sequence"]);
		
		if( $fullpage )
		{
			include_once "./Modules/Test/classes/tables/class.ilListOfQuestionsTableGUI.php";
			$table_gui = new ilListOfQuestionsTableGUI(
					$this, 'backFromSummary', !$this->object->getTitleOutput(), $this->object->getShowMarker(),
					$obligationsNotAnswered, $obligationsFilter
			);
			
			$table_gui->setData($data);

			$this->tpl->setVariable('TABLE_LIST_OF_QUESTIONS', $table_gui->getHTML());	
			
			if( $this->object->getEnableProcessingTime() )
			{
				$this->outProcessingTime($active_id);
			}
		}
		else
		{
			$template = new ilTemplate('tpl.il_as_tst_list_of_questions_short.html', true, true, 'Modules/Test');
			
			foreach( $data as $row )
			{
				if( strlen($row['description']) )
				{
					$template->setCurrentBlock('description');
					$template->setVariable("DESCRIPTION", $row['description']);
					$template->parseCurrentBlock();
				}
				
				$active = ($row['sequence'] == $this->sequence) ? ' active' : '';
				
				$template->setCurrentBlock('item');
				$template->setVariable('CLASS', ($row['walked_through']) ? ('answered'.$active) : ('unanswered'.$active));
				$template->setVariable('ITEM', ilUtil::prepareFormOutput($row['title']));
				$template->setVariable('SEQUENCE', $row['sequence']);
				$template->parseCurrentBlock();
			}
			
			$template->setVariable('LIST_OF_QUESTIONS', $this->lng->txt('list_of_questions'));
			
			$this->tpl->setVariable('LIST_OF_QUESTIONS', $template->get());
		}
	}
	
	public function outQuestionSummaryWithObligationsInfo()
	{
		return $this->outQuestionSummary(true, true, true, false);
	}
	
	public function outObligationsOnlySummary()
	{
		return $this->outQuestionSummary(true, true, true, true);
	}
	
	function showMaximumAllowedUsersReachedMessage()
	{
		$this->tpl->addBlockFile($this->getContentBlockName(), "adm_content", "tpl.il_as_tst_max_allowed_users_reached.html", "Modules/Test");
		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("MAX_ALLOWED_USERS_MESSAGE", sprintf($this->lng->txt("tst_max_allowed_users_message"), $this->object->getAllowedUsersTimeGap()));
		$this->tpl->setVariable("MAX_ALLOWED_USERS_HEADING", sprintf($this->lng->txt("tst_max_allowed_users_heading"), $this->object->getAllowedUsersTimeGap()));
		$this->tpl->setVariable("BACK_TO_INTRODUCTION", $this->lng->txt("tst_results_back_introduction"));
		$this->tpl->setVariable("FORMACTION", $this->ctrl->getFormAction($this));
		$this->tpl->parseCurrentBlock();
	}
	
	function backConfirmFinish()
	{
		global $ilUser;
		if ($this->object->canShowSolutionPrintview($ilUser->getId()))
		{
			$template = new ilTemplate("tpl.il_as_tst_finish_navigation.html", TRUE, TRUE, "Modules/Test");
			$template->setVariable("BUTTON_FINISH", $this->lng->txt("btn_next"));
			$template->setVariable("BUTTON_CANCEL", $this->lng->txt("btn_previous"));
			
			$template_top = new ilTemplate("tpl.il_as_tst_list_of_answers_topbuttons.html", TRUE, TRUE, "Modules/Test");
			$template_top->setCurrentBlock("button_print");
			$template_top->setVariable("BUTTON_PRINT", $this->lng->txt("print"));
			$template_top->parseCurrentBlock();
			$active_id = $this->object->getTestSession()->getActiveId();
			return $this->showListOfAnswers($active_id, NULL, $template_top->get(), $template->get());
		}
		else
		{
			$this->ctrl->redirect($this, 'gotoQuestion');
		}
	}
	
	function finishListOfAnswers()
	{
		$this->confirmFinishTest();
	}
	
	/**
	* Creates an output of the solution of an answer compared to the correct solution
	*
	* @access public
	*/
	function outCorrectSolution()
	{
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_tst_correct_solution.html", "Modules/Test");

		include_once("./Services/Style/classes/class.ilObjStyleSheet.php");
		$this->tpl->setCurrentBlock("ContentStyle");
		$this->tpl->setVariable("LOCATION_CONTENT_STYLESHEET", ilObjStyleSheet::getContentStylePath(0));
		$this->tpl->parseCurrentBlock();

		$this->tpl->setCurrentBlock("SyntaxStyle");
		$this->tpl->setVariable("LOCATION_SYNTAX_STYLESHEET", ilObjStyleSheet::getSyntaxStylePath());
		$this->tpl->parseCurrentBlock();

		$this->tpl->addCss(ilUtil::getStyleSheetLocation("output", "test_print.css", "Modules/Test"), "print");
		if ($this->object->getShowSolutionAnswersOnly())
		{
			$this->tpl->addCss(ilUtil::getStyleSheetLocation("output", "test_print_hide_content.css", "Modules/Test"), "print");
		}

		$this->tpl->setCurrentBlock("adm_content");
		$solution = $this->getCorrectSolutionOutput($_GET["evaluation"], $_GET["active_id"], $_GET["pass"]);
		$this->tpl->setVariable("OUTPUT_SOLUTION", $solution);
		$this->tpl->setVariable("TEXT_BACK", $this->lng->txt("back"));
		$this->ctrl->saveParameter($this, "pass");
		$this->ctrl->saveParameter($this, "active_id");
		$this->tpl->setVariable("URL_BACK", $this->ctrl->getLinkTarget($this, "outUserResultsOverview"));
		$this->tpl->parseCurrentBlock();
	}

	/**
	* Creates an output of the list of answers for a test participant during the test
	* (only the actual pass will be shown)
	*
	* @param integer $active_id Active id of the participant
	* @param integer $pass Test pass of the participant
	* @param boolean $testnavigation Deceides wheather to show a navigation for tests or not
	* @access public
	*/
	function showListOfAnswers($active_id, $pass = NULL, $top_data = "", $bottom_data = "")
	{
		global $ilUser;

		$this->tpl->addBlockFile($this->getContentBlockName(), "adm_content", "tpl.il_as_tst_finish_list_of_answers.html", "Modules/Test");

		$result_array =& $this->object->getTestResult($active_id, $pass);

		$counter = 1;
		// output of questions with solutions
		foreach ($result_array as $question_data)
		{
			$question = $question_data["qid"];
			if (is_numeric($question))
			{
				$this->tpl->setCurrentBlock("printview_question");
				$question_gui = $this->object->createQuestionGUI("", $question);
				$template = new ilTemplate("tpl.il_as_qpl_question_printview.html", TRUE, TRUE, "Modules/TestQuestionPool");
				$template->setVariable("COUNTER_QUESTION", $counter.". ");
				$template->setVariable("QUESTION_TITLE", $question_gui->object->getTitle());
				
				$show_question_only = ($this->object->getShowSolutionAnswersOnly()) ? TRUE : FALSE;
				$result_output = $question_gui->getSolutionOutput($active_id, $pass, FALSE, FALSE, $show_question_only, $this->object->getShowSolutionFeedback());
				$template->setVariable("SOLUTION_OUTPUT", $result_output);
				$this->tpl->setVariable("QUESTION_OUTPUT", $template->get());
				$this->tpl->parseCurrentBlock();
				$counter ++;
			}
		}

		$this->tpl->addCss(ilUtil::getStyleSheetLocation("output", "test_print.css", "Modules/Test"), "print");
		if ($this->object->getShowSolutionAnswersOnly())
		{
			$this->tpl->addCss(ilUtil::getStyleSheetLocation("output", "test_print_hide_content.css", "Modules/Test"), "print");
		}
		if (strlen($top_data))
		{
			$this->tpl->setCurrentBlock("top_data");
			$this->tpl->setVariable("TOP_DATA", $top_data);
			$this->tpl->parseCurrentBlock();
		}
		
		if (strlen($bottom_data))
		{
			$this->tpl->setCurrentBlock("bottom_data");
			$this->tpl->setVariable("FORMACTION", $this->ctrl->getFormAction($this));
			$this->tpl->setVariable("BOTTOM_DATA", $bottom_data);
			$this->tpl->parseCurrentBlock();
		}
		
		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("TXT_ANSWER_SHEET", $this->lng->txt("tst_list_of_answers"));
		$user_data = $this->getResultsUserdata($active_id, TRUE);
		$signature = $this->getResultsSignature();
		$this->tpl->setVariable("USER_DETAILS", $user_data);
		$this->tpl->setVariable("SIGNATURE", $signature);
		$this->tpl->setVariable("TITLE", $this->object->getTitle());
		$this->tpl->setVariable("TXT_TEST_PROLOG", $this->lng->txt("tst_your_answers"));
		$invited_user =& $this->object->getInvitedUsers($ilUser->getId());
		$pagetitle = $this->object->getTitle() . " - " . $this->lng->txt("clientip") . 
			": " . $invited_user[$ilUser->getId()]["clientip"] . " - " . 
			$this->lng->txt("matriculation") . ": " . 
			$invited_user[$ilUser->getId()]["matriculation"];
		$this->tpl->setVariable("PAGETITLE", $pagetitle);
		$this->tpl->parseCurrentBlock();
	}
	
	/**
	* Returns the name of the current content block (depends on the kiosk mode setting)
	*
	* @return string The name of the content block
	* @access public
	*/
	private function getContentBlockName()
	{
		if ($this->object->getKioskMode())
		{
			$this->tpl->setBodyClass("kiosk");
			$this->tpl->setAddFooter(FALSE);
			return "CONTENT";
		}
		else
		{
			return "ADM_CONTENT";
		}
	}

	function outUserResultsOverview()
	{
		$this->ctrl->redirectByClass("iltestevaluationgui", "outUserResultsOverview");
	}

	function outUserListOfAnswerPasses()
	{
		$this->ctrl->redirectByClass("iltestevaluationgui", "outUserListOfAnswerPasses");
	}

	/**
	 * Go to requested hint list
	 *
	 * @access private
	 */
	private function showRequestedHintList()
	{
		$this->saveQuestionSolution();
		
		require_once 'Modules/TestQuestionPool/classes/class.ilAssQuestionHintRequestGUI.php';
		$this->ctrl->redirectByClass('ilAssQuestionHintRequestGUI', ilAssQuestionHintRequestGUI::CMD_SHOW_LIST);
	}
	
	/**
	 * Go to hint request confirmation
	 *
	 * @access private
	 */
	private function confirmHintRequest()
	{
		$this->saveQuestionSolution();
		
		require_once 'Modules/TestQuestionPool/classes/class.ilAssQuestionHintRequestGUI.php';
		$this->ctrl->redirectByClass('ilAssQuestionHintRequestGUI', ilAssQuestionHintRequestGUI::CMD_CONFIRM_REQUEST);
	}
	
	/**
	 * renders the elements for the question related navigation
	 * 
	 * @access private
	 * @global ilTemplate $tpl
	 * @global ilLanguage $lng
	 * @param assQuestionGUI $questionGUI 
	 */
	private function fillQuestionRelatedNavigation(assQuestionGUI $questionGUI)
	{
		global $tpl, $lng;
		
		$parseQuestionRelatedNavigation = false;
		
		switch( 1 )
		{
			case $this->object->getSpecificAnswerFeedback():
			case $this->object->getGenericAnswerFeedback():
			case $this->object->getAnswerFeedbackPoints():
			case $this->object->getInstantFeedbackSolution():
			
				$tpl->setCurrentBlock("direct_feedback");
				$tpl->setVariable("TEXT_DIRECT_FEEDBACK", $lng->txt("check"));
				$tpl->parseCurrentBlock();

				$parseQuestionRelatedNavigation = true;
		}
		
		if( $this->object->isOfferingQuestionHintsEnabled() )
		{
			require_once 'Modules/TestQuestionPool/classes/class.ilAssQuestionHintTracking.php';
			
			$questionId = $questionGUI->object->getId();
			$activeId = $this->object->getTestSession()->getActiveId();
			$pass = $this->object->getTestSession()->getPass();
			
			$requestsExist = ilAssQuestionHintTracking::requestsExist($questionId, $activeId, $pass);
			$requestsPossible = ilAssQuestionHintTracking::requestsPossible($questionId, $activeId, $pass);
			
			if( $requestsPossible )
			{
				if( $requestsExist )
				{
					$buttonText = $lng->txt("button_request_next_question_hint");
				}
				else
				{
					$buttonText = $lng->txt("button_request_question_hint");
				}

				$tpl->setCurrentBlock("button_request_next_question_hint");
				$tpl->setVariable("CMD_REQUEST_NEXT_QUESTION_HINT", 'confirmHintRequest');
				$tpl->setVariable("TEXT_REQUEST_NEXT_QUESTION_HINT", $buttonText);
				$tpl->parseCurrentBlock();

				$parseQuestionRelatedNavigation = true;
			}

			if( $requestsExist )
			{
				$tpl->setCurrentBlock("button_show_requested_question_hints");
				$tpl->setVariable("CMD_SHOW_REQUESTED_QUESTION_HINTS", 'showRequestedHintList');
				$tpl->setVariable("TEXT_SHOW_REQUESTED_QUESTION_HINTS", $lng->txt("button_show_requested_question_hints"));
				$tpl->parseCurrentBlock();

				$parseQuestionRelatedNavigation = true;
			}
		}
		
		if( $parseQuestionRelatedNavigation )
		{
			$tpl->setCurrentBlock("question_related_navigation");
			$tpl->parseCurrentBlock();
		}
	}
}
