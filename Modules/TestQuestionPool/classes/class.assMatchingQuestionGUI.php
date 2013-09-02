<?php

/* Copyright (c) 1998-2011 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once "./Modules/TestQuestionPool/classes/class.assQuestionGUI.php";
include_once "./Modules/Test/classes/inc.AssessmentConstants.php";

/**
* Matching question GUI representation
*
* The assMatchingQuestionGUI class encapsulates the GUI representation
* for matching questions.
*
* @author		Helmut Schottmüller <helmut.schottmueller@mac.com>
* @author		Björn Heyser <bheyser@databay.de>
* @version	$Id: class.assMatchingQuestionGUI.php 42780 2013-06-18 14:56:58Z bheyser $
* @ingroup ModulesTestQuestionPool
*/
class assMatchingQuestionGUI extends assQuestionGUI
{
	/**
	* assMatchingQuestionGUI constructor
	*
	* The constructor takes possible arguments an creates an instance of the assMatchingQuestionGUI object.
	*
	* @param integer $id The database id of a image map question object
	* @access public
	*/
	function __construct($id = -1)
	{
		parent::__construct();
		include_once "./Modules/TestQuestionPool/classes/class.assMatchingQuestion.php";
		$this->object = new assMatchingQuestion();
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
			include_once "./Services/AdvancedEditing/classes/class.ilObjAdvancedEditing.php";
			$questiontext = $_POST["question"];
			$this->object->setQuestion($questiontext);
			if (!$this->getSelfAssessmentEditingMode())
			{
				$this->object->setShuffle($_POST["shuffle"]);
			}
			else
			{
				$this->object->setShuffle(1);
			}
			$this->object->setThumbGeometry($_POST["thumb_geometry"]);
			$this->object->setElementHeight($_POST["element_height"]);
			if ($this->getSelfAssessmentEditingMode())
			{
				$this->object->setNrOfTries($_POST['nr_of_tries']);
			}
			// adding estimated working time
			$this->object->setEstimatedWorkingTime(
				$_POST["Estimated"]["hh"],
				$_POST["Estimated"]["mm"],
				$_POST["Estimated"]["ss"]
			);

			// Delete all existing answers and create new answers from the form data
			$this->object->flushMatchingPairs();
			$this->object->flushTerms();
			$this->object->flushDefinitions();
			$saved = false;

			// add terms
			include_once "./Modules/TestQuestionPool/classes/class.assAnswerMatchingTerm.php";
			foreach ($_POST['terms']['answer'] as $index => $answer)
			{
				$filename = $_POST['terms']['imagename'][$index];
				if (strlen($_FILES['terms']['name']['image'][$index]))
				{
					// upload the new file
					$name = $_FILES['terms']['name']['image'][$index];
					if ($this->object->setImageFile($_FILES['terms']['tmp_name']['image'][$index], $this->object->getEncryptedFilename($name)))
					{
						$filename = $this->object->getEncryptedFilename($name);
					}
					else
					{
						$filename = "";
					}
				}
				$this->object->addTerm(new assAnswerMatchingTerm($answer, $filename, $_POST['terms']['identifier'][$index]));
			}
			// add definitions
			include_once "./Modules/TestQuestionPool/classes/class.assAnswerMatchingDefinition.php";
			foreach ($_POST['definitions']['answer'] as $index => $answer)
			{
				$filename = $_POST['definitions']['imagename'][$index];
				if (strlen($_FILES['definitions']['name']['image'][$index]))
				{
					// upload the new file
					$name = $_FILES['definitions']['name']['image'][$index];
					if ($this->object->setImageFile($_FILES['definitions']['tmp_name']['image'][$index], $this->object->getEncryptedFilename($name)))
					{
						$filename = $this->object->getEncryptedFilename($name);
					}
					else
					{
						$filename = "";
					}
				}
				$this->object->addDefinition(new assAnswerMatchingDefinition($answer, $filename, $_POST['definitions']['identifier'][$index]));
			}

			// add matching pairs
			if (is_array($_POST['pairs']['points']))
			{
				include_once "./Modules/TestQuestionPool/classes/class.assAnswerMatchingPair.php";
				foreach ($_POST['pairs']['points'] as $index => $points)
				{
					$term_id = $_POST['pairs']['term'][$index];
					$definition_id = $_POST['pairs']['definition'][$index];
					$this->object->addMatchingPair($this->object->getTermWithIdentifier($term_id), $this->object->getDefinitionWithIdentifier($definition_id), $points);
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
	* Upload an image
	*/
	public function uploadterms()
	{
		$this->writePostData(true);
		$position = key($_POST['cmd']['uploadterms']);
		$this->editQuestion();
	}

	/**
	* Remove an image
	*/
	public function removeimageterms()
	{
		$this->writePostData(true);
		$position = key($_POST['cmd']['removeimageterms']);
		$filename = $_POST['terms']['imagename'][$position];
		$this->object->removeTermImage($position);
		$this->editQuestion();
	}

	/**
	* Upload an image
	*/
	public function uploaddefinitions()
	{
		$this->writePostData(true);
		$position = key($_POST['cmd']['uploaddefinitions']);
		$this->editQuestion();
	}

	/**
	* Remove an image
	*/
	public function removeimagedefinitions()
	{
		$this->writePostData(true);
		$position = key($_POST['cmd']['removeimagedefinitions']);
		$filename = $_POST['definitions']['imagename'][$position];
		$this->object->removeDefinitionImage($position);
		$this->editQuestion();
	}

	public function addterms()
	{
		$this->writePostData();
		$position = key($_POST["cmd"]["addterms"]);
		$this->object->insertTerm($position+1);
		$this->editQuestion();
	}

	public function removeterms()
	{
		$this->writePostData();
		$position = key($_POST["cmd"]["removeterms"]);
		$this->object->deleteTerm($position);
		$this->editQuestion();
	}

	public function adddefinitions()
	{
		$this->writePostData();
		$position = key($_POST["cmd"]["adddefinitions"]);
		$this->object->insertDefinition($position+1);
		$this->editQuestion();
	}

	public function removedefinitions()
	{
		$this->writePostData();
		$position = key($_POST["cmd"]["removedefinitions"]);
		$this->object->deleteDefinition($position);
		$this->editQuestion();
	}

	public function addpairs()
	{
		$this->writePostData();
		$position = key($_POST["cmd"]["addpairs"]);
		$this->object->insertMatchingPair($position+1);
		$this->editQuestion();
	}

	public function removepairs()
	{
		$this->writePostData();
		$position = key($_POST["cmd"]["removepairs"]);
		$this->object->deleteMatchingPair($position);
		$this->editQuestion();
	}

	/**
	* Creates an output of the edit form for the question
	*
	* @access public
	*/
	function editQuestion($checkonly = FALSE)
	{
		$save = $this->isSaveCommand();
		$this->getQuestionTemplate();

		include_once("./Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this));
		$form->setTitle($this->outQuestionType());
		$form->setMultipart(true);
		$form->setTableWidth("100%");
		$form->setId("matching");

		// Edit mode
		$hidden = new ilHiddenInputGUI("matching_type");
		$hidden->setValue($matchingtype);
		$form->addItem($hidden);
		
		// title, author, description, question, working time (assessment mode)
		$this->addBasicQuestionFormProperties($form);

		if (!$this->getSelfAssessmentEditingMode())
		{
			// shuffle
			$shuffle = new ilSelectInputGUI($this->lng->txt("shuffle_answers"), "shuffle");
			$shuffle_options = array(
				0 => $this->lng->txt("no"),
				1 => $this->lng->txt("matching_shuffle_terms_definitions"),
				2 => $this->lng->txt("matching_shuffle_terms"),
				3 => $this->lng->txt("matching_shuffle_definitions")
			);
			$shuffle->setOptions($shuffle_options);
			$shuffle->setValue($this->object->getShuffle());
			$shuffle->setRequired(FALSE);
			$form->addItem($shuffle);

			$element_height = new ilNumberInputGUI($this->lng->txt("element_height"), "element_height");
			$element_height->setValue($this->object->getElementHeight());
			$element_height->setRequired(false);
			$element_height->setMaxLength(6);
			$element_height->setMinValue(20);
			$element_height->setSize(6);
			$element_height->setInfo($this->lng->txt("element_height_info"));
			$form->addItem($element_height);

			$geometry = new ilNumberInputGUI($this->lng->txt("thumb_geometry"), "thumb_geometry");
			$geometry->setValue($this->object->getThumbGeometry());
			$geometry->setRequired(true);
			$geometry->setMaxLength(6);
			$geometry->setMinValue(20);
			$geometry->setSize(6);
			$geometry->setInfo($this->lng->txt("thumb_geometry_info"));
			$form->addItem($geometry);
		}

		// Definitions
		include_once "./Modules/TestQuestionPool/classes/class.ilMatchingWizardInputGUI.php";
		$definitions = new ilMatchingWizardInputGUI($this->lng->txt("definitions"), "definitions");
		if ($this->getSelfAssessmentEditingMode()) $definitions->setHideImages(true);
		$definitions->setRequired(true);
		$definitions->setQuestionObject($this->object);
		$definitions->setTextName($this->lng->txt('definition_text'));
		$definitions->setImageName($this->lng->txt('definition_image'));
		include_once "./Modules/TestQuestionPool/classes/class.assAnswerMatchingDefinition.php";
		if (!count($this->object->getDefinitions())) $this->object->addDefinition(new assAnswerMatchingDefinition());
		$definitionvalues = $this->object->getDefinitions();
		$definitions->setValues($definitionvalues);
		$form->addItem($definitions);
		
		// Terms
		include_once "./Modules/TestQuestionPool/classes/class.ilMatchingWizardInputGUI.php";
		$terms = new ilMatchingWizardInputGUI($this->lng->txt("terms"), "terms");
		if ($this->getSelfAssessmentEditingMode()) $terms->setHideImages(true);
		$terms->setRequired(true);
		$terms->setQuestionObject($this->object);
		$terms->setTextName($this->lng->txt('term_text'));
		$terms->setImageName($this->lng->txt('term_image'));
		include_once "./Modules/TestQuestionPool/classes/class.assAnswerMatchingTerm.php";
		if (!count($this->object->getTerms())) $this->object->addTerm(new assAnswerMatchingTerm());
		$termvalues = $this->object->getTerms();
		$terms->setValues($termvalues);
		$form->addItem($terms);
		
		// Matching Pairs
		include_once "./Modules/TestQuestionPool/classes/class.ilMatchingPairWizardInputGUI.php";
		$pairs = new ilMatchingPairWizardInputGUI($this->lng->txt('matching_pairs'), 'pairs');
		$pairs->setRequired(true);
		$pairs->setTerms($this->object->getTerms());
		$pairs->setDefinitions($this->object->getDefinitions());
		include_once "./Modules/TestQuestionPool/classes/class.assAnswerMatchingPair.php";
		if (count($this->object->getMatchingPairs()) == 0)
		{
			$this->object->addMatchingPair(new assAnswerMatchingPair($termvalues[0], $definitionvalues[0], 0));
		}
		$pairs->setPairs($this->object->getMatchingPairs());
		$form->addItem($pairs);

		$this->addQuestionFormCommandButtons($form);

		$errors = false;
	
		if ($save)
		{
			$form->setValuesByPost();
			$errors = !$form->checkInput();
			$form->setValuesByPost(); // again, because checkInput now performs the whole stripSlashes handling and we need this if we don't want to have duplication of backslashes
			if ((!$errors) && (count($terms->getValues()) < (count($definitions->getValues())))
				&& !$this->getSelfAssessmentEditingMode())
			{
				$errors = true;
				$terms->setAlert($this->lng->txt("msg_number_of_terms_too_low"));
				ilUtil::sendFailure($this->lng->txt('form_input_not_valid'));
			}
			if ($errors) $checkonly = false;
		}

		if (!$checkonly) $this->tpl->setVariable("QUESTION_DATA", $form->getHTML());
		return $errors;
	}

	function outQuestionForTest($formaction, $active_id, $pass = NULL, $is_postponed = FALSE, $user_post_solution = FALSE)
	{
		$test_output = $this->getTestOutput($active_id, $pass, $is_postponed, $user_post_solution); 
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
		// generate the question output
		include_once "./Services/UICore/classes/class.ilTemplate.php";
		$template = new ilTemplate("tpl.il_as_qpl_matching_output_solution.html", TRUE, TRUE, "Modules/TestQuestionPool");
		$solutiontemplate = new ilTemplate("tpl.il_as_tst_solution_output.html",TRUE, TRUE, "Modules/TestQuestionPool");
		
		$solutions = array();
		if (($active_id > 0) && (!$show_correct_solution))
		{
			include_once "./Modules/Test/classes/class.ilObjTest.php";
			$solutions =& $this->object->getSolutionValues($active_id, $pass);
			$solution_script .= "";
		}
		else
		{
			foreach ($this->object->getMatchingPairs() as $pair)
			{
				if( $pair->points <= 0 )
				{
					continue;
				}
				
				$solutions[] = array(
					"value1" => $pair->term->identifier,
					"value2" => $pair->definition->identifier,
					'points' => $pair->points
				);
			}
		}

		$i = 0;
		foreach ($solutions as $solution)
		{
			$definition = $this->object->getDefinitionWithIdentifier($solution['value2']);
			$term = $this->object->getTermWithIdentifier($solution['value1']);
			$points = $solution['points'];

			if (is_object($definition))
			{
				if (strlen($definition->picture))
				{
					$template->setCurrentBlock('definition_image');
					$template->setVariable('ANSWER_IMAGE_URL', $this->object->getImagePathWeb() . $this->object->getThumbPrefix() . $definition->picture);
					$template->setVariable('ANSWER_IMAGE_ALT', (strlen($definition->text)) ? ilUtil::prepareFormOutput($definition->text) : ilUtil::prepareFormOutput($definition->picture));
					$template->setVariable('ANSWER_IMAGE_TITLE', (strlen($definition->text)) ? ilUtil::prepareFormOutput($definition->text) : ilUtil::prepareFormOutput($definition->picture));
					$template->setVariable('URL_PREVIEW', $this->object->getImagePathWeb() . $definition->picture);
					$template->setVariable("TEXT_PREVIEW", $this->lng->txt('preview'));
					$template->setVariable("IMG_PREVIEW", ilUtil::getImagePath('enlarge.png'));
					$template->setVariable("TEXT_DEFINITION", (strlen($definition->text)) ? $this->lng->txt('definition') . ' ' . ($i+1) . ': ' . ilUtil::prepareFormOutput($definition->text) : $this->lng->txt('definition') . ' ' . ($i+1));
					$template->parseCurrentBlock();
				}
				else
				{
					$template->setCurrentBlock('definition_text');
					$template->setVariable("DEFINITION", $this->object->prepareTextareaOutput($definition->text, TRUE));
					$template->parseCurrentBlock();
				}
			}
			if (is_object($term))
			{
				if (strlen($term->picture))
				{
					$template->setCurrentBlock('term_image');
					$template->setVariable('ANSWER_IMAGE_URL', $this->object->getImagePathWeb() . $this->object->getThumbPrefix() . $term->picture);
					$template->setVariable('ANSWER_IMAGE_ALT', (strlen($term->text)) ? ilUtil::prepareFormOutput($term->text) : ilUtil::prepareFormOutput($term->picture));
					$template->setVariable('ANSWER_IMAGE_TITLE', (strlen($term->text)) ? ilUtil::prepareFormOutput($term->text) : ilUtil::prepareFormOutput($term->picture));
					$template->setVariable('URL_PREVIEW', $this->object->getImagePathWeb() . $term->picture);
					$template->setVariable("TEXT_PREVIEW", $this->lng->txt('preview'));
					$template->setVariable("TEXT_TERM", (strlen($term->text)) ? $this->lng->txt('term') . ' ' . ($i+1) . ': ' . ilUtil::prepareFormOutput($term->text) : $this->lng->txt('term') . ' ' . ($i+1));
					$template->setVariable("IMG_PREVIEW", ilUtil::getImagePath('enlarge.png'));
					$template->parseCurrentBlock();
				}
				else
				{
					$template->setCurrentBlock('term_text');
					$template->setVariable("TERM", $this->object->prepareTextareaOutput($term->text, TRUE));
					$template->parseCurrentBlock();
				}
				$i++;
			}
			if (($active_id > 0) && (!$show_correct_solution))
			{
				if ($graphicalOutput)
				{
					// output of ok/not ok icons for user entered solutions
					$ok = FALSE;
					foreach ($this->object->getMatchingPairs() as $pair)
					{
						if (is_object($term)) if (($pair->definition->identifier == $definition->identifier) && ($pair->term->identifier == $term->identifier)) $ok = true;
					}
					if ($ok)
					{
						$template->setCurrentBlock("icon_ok");
						$template->setVariable("ICON_OK", ilUtil::getImagePath("icon_ok.png"));
						$template->setVariable("TEXT_OK", $this->lng->txt("answer_is_right"));
						$template->parseCurrentBlock();
					}
					else
					{
						$template->setCurrentBlock("icon_ok");
						$template->setVariable("ICON_NOT_OK", ilUtil::getImagePath("icon_not_ok.png"));
						$template->setVariable("TEXT_NOT_OK", $this->lng->txt("answer_is_wrong"));
						$template->parseCurrentBlock();
					}
				}
			}

			if ($result_output)
			{
				$resulttext = ($points == 1) ? "(%s " . $this->lng->txt("point") . ")" : "(%s " . $this->lng->txt("points") . ")"; 
				$template->setCurrentBlock("result_output");
				$template->setVariable("RESULT_OUTPUT", sprintf($resulttext, $points));
				$template->parseCurrentBlock();
			}

			$template->setCurrentBlock("row");
			if ($this->object->getEstimatedElementHeight() > 0)
			{
				$template->setVariable("ELEMENT_HEIGHT", " style=\"height: " . $this->object->getEstimatedElementHeight() . "px;\"");
			}
			$template->setVariable("TEXT_MATCHES", $this->lng->txt("matches"));
			$template->parseCurrentBlock();
		}

		$questiontext = $this->object->getQuestion();
		if ($show_question_text==true)
		{
			$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($questiontext, TRUE));
		}
		
		$questionoutput = $template->get();
		
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

	public function getPreviewJS($show_question_only = FALSE)
	{
		global $ilUser;
		
		// generate the question output
		include_once "./Services/UICore/classes/class.ilTemplate.php";
		$template = new ilTemplate("tpl.il_as_qpl_matching_output_js.html", TRUE, TRUE, "Modules/TestQuestionPool");

		$jsswitch = "";
		if (strcmp($this->ctrl->getCmd(), 'preview') == 0)
		{
			if (array_key_exists('js', $_GET))
			{
				$ilUser->writePref('tst_javascript', $_GET['js']);
			}
			$jstemplate = new ilTemplate("tpl.il_as_qpl_javascript_switch.html", TRUE, TRUE, "Modules/TestQuestionPool");
			if ($ilUser->getPref("tst_javascript") == 1)
			{
				$jstemplate->setVariable("JAVASCRIPT_IMAGE", ilUtil::getImagePath("javascript_disable.png"));
				$jstemplate->setVariable("JAVASCRIPT_IMAGE_ALT", $this->lng->txt("disable_javascript"));
				$jstemplate->setVariable("JAVASCRIPT_IMAGE_TITLE", $this->lng->txt("disable_javascript"));
				$this->ctrl->setParameterByClass($this->ctrl->getCmdClass(), "js", "0");
				$jstemplate->setVariable("JAVASCRIPT_URL", $this->ctrl->getLinkTargetByClass($this->ctrl->getCmdClass(), $this->ctrl->getCmd()));
			}
			else
			{
				$jstemplate->setVariable("JAVASCRIPT_IMAGE", ilUtil::getImagePath("javascript.png"));
				$jstemplate->setVariable("JAVASCRIPT_IMAGE_ALT", $this->lng->txt("enable_javascript"));
				$jstemplate->setVariable("JAVASCRIPT_IMAGE_TITLE", $this->lng->txt("enable_javascript"));
				$this->ctrl->setParameterByClass($this->ctrl->getCmdClass(), "js", "1");
				$jstemplate->setVariable("JAVASCRIPT_URL", $this->ctrl->getLinkTargetByClass($this->ctrl->getCmdClass(), $this->ctrl->getCmd()));
			}
			$jsswitch = $jstemplate->get();
			if ($ilUser->getPref('tst_javascript')) $this->object->setOutputType(OUTPUT_JAVASCRIPT);
		}
		
		// shuffle output
		$terms = $this->object->getTerms();
		$definitions = $this->object->getDefinitions();
		switch ($this->object->getShuffle())
		{
			case 1:
				$terms = $this->object->pcArrayShuffle($terms);
				$definitions = $this->object->pcArrayShuffle($definitions);
				break;
			case 2:
				$terms = $this->object->pcArrayShuffle($terms);
				break;
			case 3:
				$definitions = $this->object->pcArrayShuffle($definitions);
				break;
		}

		include_once "./Services/YUI/classes/class.ilYuiUtil.php";
		ilYuiUtil::initDragDrop();

		// create definitions
		$counter = 0;
		foreach ($definitions as $definition)
		{
			if (strlen($definition->picture))
			{
				$template->setCurrentBlock("definition_picture");
				$template->setVariable("DEFINITION_ID", $definition->identifier);
				$template->setVariable("IMAGE_HREF", $this->object->getImagePathWeb() . $definition->picture);
				$thumbweb = $this->object->getImagePathWeb() . $this->object->getThumbPrefix() . $definition->picture;
				$thumb = $this->object->getImagePath() . $this->object->getThumbPrefix() . $definition->picture;
				if (!@file_exists($thumb)) $this->object->rebuildThumbnails();
				$template->setVariable("THUMBNAIL_HREF", $thumbweb);
				$template->setVariable("THUMB_ALT", $this->lng->txt("image"));
				$template->setVariable("THUMB_TITLE", $this->lng->txt("image"));
				$template->setVariable("TEXT_DEFINITION", (strlen($definition->text)) ? $this->object->prepareTextareaOutput($definition->text, TRUE) : '');
				$template->setVariable("TEXT_PREVIEW", $this->lng->txt('preview'));
				$template->setVariable("IMG_PREVIEW", ilUtil::getImagePath('enlarge.png'));
				$template->parseCurrentBlock();
			}
			else
			{
				$template->setCurrentBlock("definition_text");
				$template->setVariable("DEFINITION", $this->object->prepareTextareaOutput($definition->text, TRUE));
				$template->parseCurrentBlock();
			}

			$template->setCurrentBlock("droparea");
			$template->setVariable("ID_DROPAREA", $definition->identifier);
			$template->setVariable("QUESTION_ID", $this->object->getId());
			if ($this->object->getEstimatedElementHeight() > 0)
			{
				$template->setVariable("ELEMENT_HEIGHT", " style=\"height: " . $this->object->getEstimatedElementHeight() . "px;\"");
			}
			$template->parseCurrentBlock();

			$template->setCurrentBlock("init_dropareas");
			$template->setVariable("COUNTER", $counter++);
			$template->setVariable("ID_DROPAREA", $definition->identifier);
			$template->parseCurrentBlock();
		}


		// create terms
		$counter = 0;
		foreach ($terms as $term)
		{
			if (strlen($term->picture))
			{
				$template->setCurrentBlock("term_picture");
				$template->setVariable("TERM_ID", $term->identifier);
				$template->setVariable("IMAGE_HREF", $this->object->getImagePathWeb() . $term->picture);
				$thumbweb = $this->object->getImagePathWeb() . $this->object->getThumbPrefix() . $term->picture;
				$thumb = $this->object->getImagePath() . $this->object->getThumbPrefix() . $term->picture;
				if (!@file_exists($thumb)) $this->object->rebuildThumbnails();
				$template->setVariable("THUMBNAIL_HREF", $thumbweb);
				$template->setVariable("THUMB_ALT", $this->lng->txt("image"));
				$template->setVariable("THUMB_TITLE", $this->lng->txt("image"));
				$template->setVariable("TEXT_PREVIEW", $this->lng->txt('preview'));
				$template->setVariable("TEXT_TERM", (strlen($term->text)) ? $this->object->prepareTextareaOutput($term->text, TRUE) : '');
				$template->setVariable("IMG_PREVIEW", ilUtil::getImagePath('enlarge.png'));
				$template->parseCurrentBlock();
			}
			else
			{
				$template->setCurrentBlock("term_text");
				$template->setVariable("TERM_TEXT", $this->object->prepareTextareaOutput($term->text, TRUE));
				$template->parseCurrentBlock();
			}
			$template->setCurrentBlock("draggable");
			$template->setVariable("ID_DRAGGABLE", $term->identifier);
			if ($this->object->getEstimatedElementHeight() > 0)
			{
				$template->setVariable("ELEMENT_HEIGHT", " style=\"height: " . $this->object->getEstimatedElementHeight() . "px;\"");
			}
			$template->parseCurrentBlock();

			$template->setCurrentBlock("init_draggables");
			$template->setVariable("COUNTER", $counter++);
			$template->setVariable("ID_DRAGGABLE", $term->identifier);
			$template->parseCurrentBlock();
		}

		$template->setVariable("RESET_BUTTON", $this->lng->txt("reset_terms"));

		$this->tpl->setVariable("LOCATION_ADDITIONAL_STYLESHEET", ilUtil::getStyleSheetLocation("output", "test_javascript.css", "Modules/TestQuestionPool"));

		$questiontext = $this->object->getQuestion();
		$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($questiontext, TRUE));
		$questionoutput = $jsswitch . $template->get();
		if (!$show_question_only)
		{
			// get page object output
			$questionoutput = $this->getILIASPage($questionoutput);
		}
		return $questionoutput;
	}
	
	public function getPreview($show_question_only = FALSE)
	{
		global $ilUser;
		
		// generate the question output
		include_once "./Services/UICore/classes/class.ilTemplate.php";
		$template = new ilTemplate("tpl.il_as_qpl_matching_output.html", TRUE, TRUE, "Modules/TestQuestionPool");

		$jsswitch = "";
		if (strcmp($this->ctrl->getCmd(), 'preview') == 0)
		{
			if (array_key_exists('js', $_GET))
			{
				$ilUser->writePref('tst_javascript', $_GET['js']);
			}
			$jstemplate = new ilTemplate("tpl.il_as_qpl_javascript_switch.html", TRUE, TRUE, "Modules/TestQuestionPool");
			if ($ilUser->getPref("tst_javascript") == 1)
			{
				$jstemplate->setVariable("JAVASCRIPT_IMAGE", ilUtil::getImagePath("javascript_disable.png"));
				$jstemplate->setVariable("JAVASCRIPT_IMAGE_ALT", $this->lng->txt("disable_javascript"));
				$jstemplate->setVariable("JAVASCRIPT_IMAGE_TITLE", $this->lng->txt("disable_javascript"));
				$this->ctrl->setParameterByClass($this->ctrl->getCmdClass(), "js", "0");
				$jstemplate->setVariable("JAVASCRIPT_URL", $this->ctrl->getLinkTargetByClass($this->ctrl->getCmdClass(), $this->ctrl->getCmd()));
			}
			else
			{
				$jstemplate->setVariable("JAVASCRIPT_IMAGE", ilUtil::getImagePath("javascript.png"));
				$jstemplate->setVariable("JAVASCRIPT_IMAGE_ALT", $this->lng->txt("enable_javascript"));
				$jstemplate->setVariable("JAVASCRIPT_IMAGE_TITLE", $this->lng->txt("enable_javascript"));
				$this->ctrl->setParameterByClass($this->ctrl->getCmdClass(), "js", "1");
				$jstemplate->setVariable("JAVASCRIPT_URL", $this->ctrl->getLinkTargetByClass($this->ctrl->getCmdClass(), $this->ctrl->getCmd()));
			}
			$jsswitch = $jstemplate->get();
			if ($ilUser->getPref('tst_javascript')) $this->object->setOutputType(OUTPUT_JAVASCRIPT);
		}
		
		if ($this->object->getOutputType() == OUTPUT_JAVASCRIPT)
		{
			return $this->getPreviewJS($show_question_only);
		}
		
		// shuffle output
		$terms = $this->object->getTerms();
		$definitions = $this->object->getDefinitions();
		switch ($this->object->getShuffle())
		{
			case 1:
				$terms = $this->object->pcArrayShuffle($terms);
				$definitions = $this->object->pcArrayShuffle($definitions);
				break;
			case 2:
				$terms = $this->object->pcArrayShuffle($terms);
				break;
			case 3:
				$definitions = $this->object->pcArrayShuffle($definitions);
				break;
		}

		for ($i = 0; $i < count($definitions); $i++)
		{
			$definition = $definitions[$i];
			if (is_object($definition))
			{
				if (strlen($definition->picture))
				{
					$template->setCurrentBlock('definition_image');
					$template->setVariable('ANSWER_IMAGE_URL', $this->object->getImagePathWeb() . $this->object->getThumbPrefix() . $definition->picture);
					$template->setVariable('ANSWER_IMAGE_ALT', (strlen($definition->text)) ? ilUtil::prepareFormOutput($definition->text) : ilUtil::prepareFormOutput($definition->picture));
					$template->setVariable('ANSWER_IMAGE_TITLE', (strlen($definition->text)) ? ilUtil::prepareFormOutput($definition->text) : ilUtil::prepareFormOutput($definition->picture));
					$template->setVariable('URL_PREVIEW', $this->object->getImagePathWeb() . $definition->picture);
					$template->setVariable("TEXT_PREVIEW", $this->lng->txt('preview'));
					$template->setVariable("IMG_PREVIEW", ilUtil::getImagePath('enlarge.png'));
					$template->setVariable("TEXT_DEFINITION", (strlen($definition->text)) ? $this->lng->txt('definition') . ' ' . ($i+1) . ': ' . $this->object->prepareTextareaOutput($definition->text, TRUE) : $this->lng->txt('definition') . ' ' . ($i+1));
					$template->parseCurrentBlock();
				}
				else
				{
					$template->setCurrentBlock('definition_text');
					$template->setVariable("DEFINITION", $this->object->prepareTextareaOutput($definition->text, TRUE));
					$template->parseCurrentBlock();
				}
			}

			$template->setCurrentBlock('option');
			$template->setVariable("VALUE_OPTION", 0);
			$template->setVariable("TEXT_OPTION", ilUtil::prepareFormOutput($this->lng->txt('please_select')));
			$template->parseCurrentBlock();
			$j = 1;
			foreach ($terms as $term)
			{
				$template->setCurrentBlock('option');
				$template->setVariable("VALUE_OPTION", $term->identifier);
				$template->setVariable("TEXT_OPTION", (strlen($term->text)) ? $this->lng->txt('term') . ' ' . ($j) . ': ' . ilUtil::prepareFormOutput($term->text) : $this->lng->txt('term') . ' ' . ($j));
				$template->parseCurrentBlock();
				$j++;
			}
			
			$template->setCurrentBlock('row');
			$template->setVariable("TEXT_MATCHES", $this->lng->txt("matches"));
			if ($this->object->getEstimatedElementHeight() > 0)
			{
				$template->setVariable("ELEMENT_HEIGHT", " style=\"height: " . $this->object->getEstimatedElementHeight() . "px;\"");
			}
			$template->setVariable("QUESTION_ID", $this->object->getId());
			$template->setVariable("DEFINITION_ID", $definition->identifier);
			$template->parseCurrentBlock();
		}

		$i = 0;
		foreach ($terms as $term)
		{
			if (strlen($term->picture))
			{
				$template->setCurrentBlock('term_image');
				$template->setVariable('ANSWER_IMAGE_URL', $this->object->getImagePathWeb() . $this->object->getThumbPrefix() . $term->picture);
				$template->setVariable('ANSWER_IMAGE_ALT', (strlen($term->text)) ? ilUtil::prepareFormOutput($term->text) : ilUtil::prepareFormOutput($term->picture));
				$template->setVariable('ANSWER_IMAGE_TITLE', (strlen($term->text)) ? ilUtil::prepareFormOutput($term->text) : ilUtil::prepareFormOutput($term->picture));
				$template->setVariable('URL_PREVIEW', $this->object->getImagePathWeb() . $term->picture);
				$template->setVariable("TEXT_PREVIEW", $this->lng->txt('preview'));
				$template->setVariable("TEXT_TERM", (strlen($term->text)) ? $this->lng->txt('term') . ' ' . ($i+1) . ': ' . $this->object->prepareTextareaOutput($term->text, TRUE) : $this->lng->txt('term') . ' ' . ($i+1));
				$template->setVariable("IMG_PREVIEW", ilUtil::getImagePath('enlarge.png'));
				$template->parseCurrentBlock();
			}
			else
			{
				$template->setCurrentBlock('term_text');
				$template->setVariable("TERM", $this->object->prepareTextareaOutput($term->text, TRUE));
				$template->parseCurrentBlock();
			}
			$template->touchBlock('terms');
			$i++;
		}

		$questiontext = $this->object->getQuestion();
		$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($questiontext, TRUE));
		$template->setVariable("TEXT_TERMS", ilUtil::prepareFormOutput($this->lng->txt('available_terms')));
		$template->setVariable('TEXT_SELECTION', ilUtil::prepareFormOutput($this->lng->txt('selection')));
		$questionoutput = $jsswitch . $template->get();
		if (!$show_question_only)
		{
			// get page object output
			$questionoutput = $this->getILIASPage($questionoutput);
		}
		return $questionoutput;
	}

	protected function sortDefinitionsBySolution($solution)
	{
		$neworder = array();
		foreach ($solution as $solution_values)
		{
			$id = $solution_values['value2'];
			array_push($neworder, $this->object->getDefinitionWithIdentifier($id));
		}
		return $neworder;
	}

	function getTestOutputJS($active_id, $pass = NULL, $is_postponed = FALSE, $user_post_solution = FALSE)
	{
		// generate the question output
		include_once "./Services/UICore/classes/class.ilTemplate.php";
		$template = new ilTemplate("tpl.il_as_qpl_matching_output_js.html", TRUE, TRUE, "Modules/TestQuestionPool");

		if ($active_id)
		{
			$solutions = NULL;
			include_once "./Modules/Test/classes/class.ilObjTest.php";
			if (!ilObjTest::_getUsePreviousAnswers($active_id, true))
			{
				if (is_null($pass)) $pass = ilObjTest::_getPass($active_id);
			}
			if (is_array($user_post_solution)) 
			{ 
				$solutions = array();
				foreach ($user_post_solution['matching'][$this->object->getId()] as $definition => $term)
				{
					array_push($solutions, array("value1" => $term, "value2" => $definition));
				}
			}
			else
			{ 
				$solutions =& $this->object->getSolutionValues($active_id, $pass);
			}

			foreach ($solutions as $idx => $solution_value)
			{
				if ($this->object->getOutputType() == OUTPUT_JAVASCRIPT)
				{
					if (($solution_value["value2"] > -1) && ($solution_value["value1"] > -1))
					{
						$template->setCurrentBlock("restoreposition");
						$template->setVariable("TERM_ID", $solution_value["value1"]);
						$template->setVariable("PICTURE_DEFINITION_ID", $solution_value["value2"]);
						$template->parseCurrentBlock();
					}
				}
			}
		}

		// shuffle output
		$terms = $this->object->getTerms();
		$definitions = $this->object->getDefinitions();
		switch ($this->object->getShuffle())
		{
			case 1:
				$terms = $this->object->pcArrayShuffle($terms);
				if (count($solutions))
				{
					$definitions = $this->sortDefinitionsBySolution($solutions);
				}
				else
				{
					$definitions = $this->object->pcArrayShuffle($definitions);
				}
				break;
			case 2:
				$terms = $this->object->pcArrayShuffle($terms);
				break;
			case 3:
				if (count($solutions))
				{
					$definitions = $this->sortDefinitionsBySolution($solutions);
				}
				else
				{
					$definitions = $this->object->pcArrayShuffle($definitions);
				}
				break;
		}

		include_once "./Services/YUI/classes/class.ilYuiUtil.php";
		ilYuiUtil::initDragDrop();

		// create definitions
		$counter = 0;
		foreach ($definitions as $definition)
		{
			if (strlen($definition->picture))
			{
				$template->setCurrentBlock("definition_picture");
				$template->setVariable("DEFINITION_ID", $definition->identifier);
				$template->setVariable("IMAGE_HREF", $this->object->getImagePathWeb() . $definition->picture);
				$thumbweb = $this->object->getImagePathWeb() . $this->object->getThumbPrefix() . $definition->picture;
				$thumb = $this->object->getImagePath() . $this->object->getThumbPrefix() . $definition->picture;
				if (!@file_exists($thumb)) $this->object->rebuildThumbnails();
				$template->setVariable("THUMBNAIL_HREF", $thumbweb);
				$template->setVariable("THUMB_ALT", $this->lng->txt("image"));
				$template->setVariable("THUMB_TITLE", $this->lng->txt("image"));
				$template->setVariable("TEXT_DEFINITION", (strlen($definition->text)) ? ilUtil::prepareFormOutput($definition->text) : '');
				$template->setVariable("TEXT_PREVIEW", $this->lng->txt('preview'));
				$template->setVariable("IMG_PREVIEW", ilUtil::getImagePath('enlarge.png'));
				$template->parseCurrentBlock();
			}
			else
			{
				$template->setCurrentBlock("definition_text");
				$template->setVariable("DEFINITION", $this->object->prepareTextareaOutput($definition->text, true));
				$template->parseCurrentBlock();
			}

			$template->setCurrentBlock("droparea");
			$template->setVariable("ID_DROPAREA", $definition->identifier);
			$template->setVariable("QUESTION_ID", $this->object->getId());
			if ($this->object->getEstimatedElementHeight() > 0)
			{
				$template->setVariable("ELEMENT_HEIGHT", " style=\"height: " . $this->object->getEstimatedElementHeight() . "px;\"");
			}
			$template->parseCurrentBlock();

			$template->setCurrentBlock("init_dropareas");
			$template->setVariable("COUNTER", $counter++);
			$template->setVariable("ID_DROPAREA", $definition->identifier);
			$template->parseCurrentBlock();
		}


		// create terms
		$counter = 0;
		foreach ($terms as $term)
		{
			if (strlen($term->picture))
			{
				$template->setCurrentBlock("term_picture");
				$template->setVariable("TERM_ID", $term->identifier);
				$template->setVariable("IMAGE_HREF", $this->object->getImagePathWeb() . $term->picture);
				$thumbweb = $this->object->getImagePathWeb() . $this->object->getThumbPrefix() . $term->picture;
				$thumb = $this->object->getImagePath() . $this->object->getThumbPrefix() . $term->picture;
				if (!@file_exists($thumb)) $this->object->rebuildThumbnails();
				$template->setVariable("THUMBNAIL_HREF", $thumbweb);
				$template->setVariable("THUMB_ALT", $this->lng->txt("image"));
				$template->setVariable("THUMB_TITLE", $this->lng->txt("image"));
				$template->setVariable("TEXT_PREVIEW", $this->lng->txt('preview'));
				$template->setVariable("TEXT_TERM", (strlen($term->text)) ? ilUtil::prepareFormOutput($term->text) : '');
				$template->setVariable("IMG_PREVIEW", ilUtil::getImagePath('enlarge.png'));
				$template->parseCurrentBlock();
			}
			else
			{
				$template->setCurrentBlock("term_text");
				$template->setVariable("TERM_TEXT", $this->object->prepareTextareaOutput($term->text, true));
				$template->parseCurrentBlock();
			}
			$template->setCurrentBlock("draggable");
			$template->setVariable("ID_DRAGGABLE", $term->identifier);
			if ($this->object->getEstimatedElementHeight() > 0)
			{
				$template->setVariable("ELEMENT_HEIGHT", " style=\"height: " . $this->object->getEstimatedElementHeight() . "px;\"");
			}
			$template->parseCurrentBlock();

			$template->setCurrentBlock("init_draggables");
			$template->setVariable("COUNTER", $counter++);
			$template->setVariable("ID_DRAGGABLE", $term->identifier);
			$template->parseCurrentBlock();
		}

		$template->setVariable("RESET_BUTTON", $this->lng->txt("reset_terms"));

		$this->tpl->setVariable("LOCATION_ADDITIONAL_STYLESHEET", ilUtil::getStyleSheetLocation("output", "test_javascript.css", "Modules/TestQuestionPool"));

		$questiontext = $this->object->getQuestion();
		$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($questiontext, TRUE));
		$questionoutput = $template->get();
		$pageoutput = $this->outQuestionPage("", $is_postponed, $active_id, $questionoutput);
		return $pageoutput;
	}

	function getTestOutput($active_id, $pass = NULL, $is_postponed = FALSE, $user_post_solution = FALSE)
	{
		if ($this->object->getOutputType() == OUTPUT_JAVASCRIPT)
		{
			return $this->getTestOutputJS($active_id, $pass, $is_postponed, $user_post_solution);
		}
		// generate the question output
		include_once "./Services/UICore/classes/class.ilTemplate.php";
		$template = new ilTemplate("tpl.il_as_qpl_matching_output.html", TRUE, TRUE, "Modules/TestQuestionPool");
		
		if ($active_id)
		{
			$solutions = NULL;
			include_once "./Modules/Test/classes/class.ilObjTest.php";
			if (!ilObjTest::_getUsePreviousAnswers($active_id, true))
			{
				if (is_null($pass)) $pass = ilObjTest::_getPass($active_id);
			}
			if (is_array($user_post_solution)) 
			{ 
				$solutions = array();
				foreach ($user_post_solution['matching'][$this->object->getId()] as $definition => $term)
				{
					array_push($solutions, array("value1" => $term, "value2" => $definition));
				}
			}
			else
			{ 
				$solutions =& $this->object->getSolutionValues($active_id, $pass);
			}
		}

		
		// shuffle output
		$terms = $this->object->getTerms();
		$definitions = $this->object->getDefinitions();
		switch ($this->object->getShuffle())
		{
			case 1:
				$terms = $this->object->pcArrayShuffle($terms);
				if (count($solutions))
				{
					$definitions = $this->sortDefinitionsBySolution($solutions);
				}
				else
				{
					$definitions = $this->object->pcArrayShuffle($definitions);
				}
				break;
			case 2:
				$terms = $this->object->pcArrayShuffle($terms);
				break;
			case 3:
				if (count($solutions))
				{
					$definitions = $this->sortDefinitionsBySolution($solutions);
				}
				else
				{
					$definitions = $this->object->pcArrayShuffle($definitions);
				}
				break;
		}
		$maxcount = max(count($terms), count($definitions));
		for ($i = 0; $i < count($definitions); $i++)
		{
			$definition = $definitions[$i];
			if (is_object($definition))
			{
				if (strlen($definition->picture))
				{
					$template->setCurrentBlock('definition_image');
					$template->setVariable('ANSWER_IMAGE_URL', $this->object->getImagePathWeb() . $this->object->getThumbPrefix() . $definition->picture);
					$template->setVariable('ANSWER_IMAGE_ALT', (strlen($definition->text)) ? ilUtil::prepareFormOutput($definition->text) : ilUtil::prepareFormOutput($definition->picture));
					$template->setVariable('ANSWER_IMAGE_TITLE', (strlen($definition->text)) ? ilUtil::prepareFormOutput($definition->text) : ilUtil::prepareFormOutput($definition->picture));
					$template->setVariable('URL_PREVIEW', $this->object->getImagePathWeb() . $definition->picture);
					$template->setVariable("TEXT_PREVIEW", $this->lng->txt('preview'));
					$template->setVariable("IMG_PREVIEW", ilUtil::getImagePath('enlarge.png'));
					$template->setVariable("TEXT_DEFINITION", (strlen($definition->text)) ? $this->lng->txt('definition') . ' ' . ($i+1) . ': ' . ilUtil::prepareFormOutput($definition->text) : $this->lng->txt('definition') . ' ' . ($i+1));
					$template->parseCurrentBlock();
				}
				else
				{
					$template->setCurrentBlock('definition_text');
					$template->setVariable("DEFINITION", $this->object->prepareTextareaOutput($definition->text, true));
					$template->parseCurrentBlock();
				}
			}

			$template->setCurrentBlock('option');
			$template->setVariable("VALUE_OPTION", 0);
			$template->setVariable("TEXT_OPTION", ilUtil::prepareFormOutput($this->lng->txt('please_select')));
			$template->parseCurrentBlock();
			$j = 1;
			foreach ($terms as $term)
			{
				$template->setCurrentBlock('option');
				$template->setVariable("VALUE_OPTION", $term->identifier);
				$template->setVariable("TEXT_OPTION", (strlen($term->text)) ? $this->lng->txt('term') . ' ' . ($j) . ': ' . ilUtil::prepareFormOutput($term->text) : $this->lng->txt('term') . ' ' . ($j));
				foreach ($solutions as $solution)
				{
					if ($solution["value1"] == $term->identifier && $solution["value2"] == $definition->identifier)
					{
						$template->setVariable("SELECTED_OPTION", " selected=\"selected\"");
					}
				}
				$template->parseCurrentBlock();
				$j++;
			}
			
			$template->setCurrentBlock('row');
			$template->setVariable("TEXT_MATCHES", $this->lng->txt("matches"));
			if ($this->object->getEstimatedElementHeight() > 0)
			{
				$template->setVariable("ELEMENT_HEIGHT", " style=\"height: " . $this->object->getEstimatedElementHeight() . "px;\"");
			}
			$template->setVariable("QUESTION_ID", $this->object->getId());
			$template->setVariable("DEFINITION_ID", $definition->identifier);
			$template->parseCurrentBlock();
		}

		$i = 0;
		foreach ($terms as $term)
		{
			if (strlen($term->picture))
			{
				$template->setCurrentBlock('term_image');
				$template->setVariable('ANSWER_IMAGE_URL', $this->object->getImagePathWeb() . $this->object->getThumbPrefix() . $term->picture);
				$template->setVariable('ANSWER_IMAGE_ALT', (strlen($term->text)) ? ilUtil::prepareFormOutput($term->text) : ilUtil::prepareFormOutput($term->picture));
				$template->setVariable('ANSWER_IMAGE_TITLE', (strlen($term->text)) ? ilUtil::prepareFormOutput($term->text) : ilUtil::prepareFormOutput($term->picture));
				$template->setVariable('URL_PREVIEW', $this->object->getImagePathWeb() . $term->picture);
				$template->setVariable("TEXT_PREVIEW", $this->lng->txt('preview'));
				$template->setVariable("TEXT_TERM", (strlen($term->text)) ? $this->lng->txt('term') . ' ' . ($i+1) . ': ' . ilUtil::prepareFormOutput($term->text) : $this->lng->txt('term') . ' ' . ($i+1));
				$template->setVariable("IMG_PREVIEW", ilUtil::getImagePath('enlarge.png'));
				$template->parseCurrentBlock();
			}
			else
			{
				$template->setCurrentBlock('term_text');
				$template->setVariable("TERM", $this->object->prepareTextareaOutput($term->text, true));
				$template->parseCurrentBlock();
			}
			$template->touchBlock('terms');
			$i++;
		}

		$questiontext = $this->object->getQuestion();
		$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($questiontext, TRUE));
		$template->setVariable("TEXT_TERMS", ilUtil::prepareFormOutput($this->lng->txt('available_terms')));
		$template->setVariable('TEXT_SELECTION', ilUtil::prepareFormOutput($this->lng->txt('selection')));

		$questiontext = $this->object->getQuestion();
		$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($questiontext, TRUE));
		$questionoutput = $template->get();
		$pageoutput = $this->outQuestionPage("", $is_postponed, $active_id, $questionoutput);
		return $pageoutput;

	}

	/**
	* check input fields
	*/
	function checkInput()
	{
		if ((!$_POST["title"]) or (!$_POST["author"]) or (!$_POST["question"]))
		{
			return false;
		}
		return true;
	}


	/**
	* Saves the feedback for a single choice question
	*/
	function saveFeedback()
	{
		include_once "./Services/AdvancedEditing/classes/class.ilObjAdvancedEditing.php";
		$errors = $this->feedback(true);
		$this->object->saveFeedbackGeneric(0, $_POST["feedback_incomplete"]);
		$this->object->saveFeedbackGeneric(1, $_POST["feedback_complete"]);
		foreach ($this->object->getMatchingPairs() as $index => $answer)
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
				array("editQuestion", "save", "saveEdit", "removeimageterms", "uploadterms", "removeimagedefinitions", "uploaddefinitions",
					"addpairs", "removepairs", "addterms", "removeterms", "adddefinitions", "removedefinitions", "originalSyncForm"),
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
			foreach ($this->object->getMatchingPairs() as $index => $answer)
			{
				$caption = $ordinal = $index+1;
				$caption .= '. <br />"' . $answer->term->text . '" =&gt; ';
				$caption .= '"' . $answer->definition->text . '"';
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

		foreach ($this->object->getMatchingPairs() as $idx => $ans)
		{
			$feedback .= '<tr><td><b><i>' . $ans->definition->text . '</i></b></td><td>'. $this->lng->txt("matches") . '&nbsp;';
			$feedback .= '</td><td><b><i>' . $ans->term->text . '</i></b></td><td>&nbsp;</td><td>';
			$feedback .= $this->object->getFeedbackSingleAnswer($idx) . '</td> </tr>';
		}

		$feedback .= '</tbody></table>';
		return $this->object->prepareTextareaOutput($feedback, TRUE);
	}
}