<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
include_once "./Modules/Test/classes/inc.AssessmentConstants.php";

/**
 * Class for error text questions
 *
 * @extends		assQuestion
 * 
 * @author		Helmut Schottmüller <helmut.schottmueller@mac.com> 
 * @author		Björn Heyser <bheyser@databay.de>
 * @version		$Id: class.assErrorText.php 42952 2013-06-25 09:56:50Z bheyser $
 * 
 * @ingroup		ModulesTestQuestionPool
 */
class assErrorText extends assQuestion
{
	protected $errortext;
	protected $textsize;
	protected $errordata;
	protected $points_wrong;
	
	/**
	* assErorText constructor
	*
	* The constructor takes possible arguments an creates an instance of the assOrderingHorizontal object.
	*
	* @param string $title A title string to describe the question
	* @param string $comment A comment string to describe the question
	* @param string $author A string containing the name of the questions author
	* @param integer $owner A numerical ID to identify the owner/creator
	* @param string $question The question string of the single choice question
	* @see assQuestion:__construct()
	*/
	function __construct(
		$title = "",
		$comment = "",
		$author = "",
		$owner = -1,
		$question = ""
	)
	{
		parent::__construct($title, $comment, $author, $owner, $question);
		$this->errortext = "";
		$this->textsize = 100.0;
		$this->errordata = array();
	}
	
	/**
	* Returns true, if a single choice question is complete for use
	*
	* @return boolean True, if the single choice question is complete for use, otherwise false
	*/
	public function isComplete()
	{
		if (strlen($this->title) and ($this->author) and ($this->question) and ($this->getMaximumPoints() > 0))
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* Saves a the object to the database
	*
	*/
	public function saveToDb($original_id = "")
	{
		global $ilDB;

		$this->saveQuestionDataToDb($original_id);

		// save additional data
		$affectedRows = $ilDB->manipulateF("DELETE FROM " . $this->getAdditionalTableName() . " WHERE question_fi = %s", 
			array("integer"),
			array($this->getId())
		);

		$affectedRows = $ilDB->manipulateF("INSERT INTO " . $this->getAdditionalTableName() . " (question_fi, errortext, textsize, points_wrong) VALUES (%s, %s, %s, %s)", 
			array("integer", "text", "float", "float"),
			array(
				$this->getId(),
				$this->getErrorText(),
				$this->getTextSize(),
				$this->getPointsWrong()
			)
		);
	
		$affectedRows = $ilDB->manipulateF("DELETE FROM qpl_a_errortext WHERE question_fi = %s",
			array('integer'),
			array($this->getId())
		);

		$sequence = 0;
		foreach ($this->errordata as $object)
		{
			$next_id = $ilDB->nextId('qpl_a_errortext');
			$affectedRows = $ilDB->manipulateF("INSERT INTO qpl_a_errortext (answer_id, question_fi, text_wrong, text_correct, points, sequence) VALUES (%s, %s, %s, %s, %s, %s)",
				array('integer','integer','text','text','float', 'integer'),
				array(
					$next_id,
					$this->getId(),
					$object->text_wrong,
					$object->text_correct,
					$object->points,
					$sequence++
				)
			);
		}
		
		parent::saveToDb();
	}

	/**
	* Loads the object from the database
	*
	* @param object $db A pear DB object
	* @param integer $question_id A unique key which defines the multiple choice test in the database
	*/
	public function loadFromDb($question_id)
	{
		global $ilDB;

		$result = $ilDB->queryF("SELECT qpl_questions.*, " . $this->getAdditionalTableName() . ".* FROM qpl_questions LEFT JOIN " . $this->getAdditionalTableName() . " ON " . $this->getAdditionalTableName() . ".question_fi = qpl_questions.question_id WHERE qpl_questions.question_id = %s",
			array("integer"),
			array($question_id)
		);
		if ($result->numRows() == 1)
		{
			$data = $ilDB->fetchAssoc($result);
			$this->setId($question_id);
			$this->setObjId($data["obj_fi"]);
			$this->setTitle($data["title"]);
			$this->setComment($data["description"]);
			$this->setOriginalId($data["original_id"]);
			$this->setNrOfTries($data['nr_of_tries']);
			$this->setAuthor($data["author"]);
			$this->setPoints($data["points"]);
			$this->setOwner($data["owner"]);
			include_once("./Services/RTE/classes/class.ilRTE.php");
			$this->setQuestion(ilRTE::_replaceMediaObjectImageSrc($data["question_text"], 1));
			$this->setErrorText($data["errortext"]);
			$this->setTextSize($data["textsize"]);
			$this->setPointsWrong($data["points_wrong"]);
			$this->setEstimatedWorkingTime(substr($data["working_time"], 0, 2), substr($data["working_time"], 3, 2), substr($data["working_time"], 6, 2));
		}

		$result = $ilDB->queryF("SELECT * FROM qpl_a_errortext WHERE question_fi = %s ORDER BY sequence ASC",
			array('integer'),
			array($question_id)
		);
		include_once "./Modules/TestQuestionPool/classes/class.assAnswerErrorText.php";
		if ($result->numRows() > 0)
		{
			while ($data = $ilDB->fetchAssoc($result))
			{
				array_push($this->errordata, new assAnswerErrorText($data["text_wrong"], $data["text_correct"], $data["points"]));
			}
		}

		parent::loadFromDb($question_id);
	}

	/**
	* Duplicates the object
	*/
	public function duplicate($for_test = true, $title = "", $author = "", $owner = "", $testObjId = null)
	{
		if ($this->id <= 0)
		{
			// The question has not been saved. It cannot be duplicated
			return;
		}
		// duplicate the question in database
		$this_id = $this->getId();
		
		if( (int)$testObjId > 0 )
		{
			$thisObjId = $this->getObjId();
		}
		
		$clone = $this;
		include_once ("./Modules/TestQuestionPool/classes/class.assQuestion.php");
		$original_id = assQuestion::_getOriginalId($this->id);
		$clone->id = -1;
		
		if( (int)$testObjId > 0 )
		{
			$clone->setObjId($testObjId);
		}
		
		if ($title)
		{
			$clone->setTitle($title);
		}

		if ($author)
		{
			$clone->setAuthor($author);
		}
		if ($owner)
		{
			$clone->setOwner($owner);
		}

		if ($for_test)
		{
			$clone->saveToDb($original_id);
		}
		else
		{
			$clone->saveToDb();
		}
		// copy question page content
		$clone->copyPageOfQuestion($this_id);
		// copy XHTML media objects
		$clone->copyXHTMLMediaObjectsOfQuestion($this_id);
		// duplicate the generic feedback
		$clone->duplicateGenericFeedback($this_id);
		// duplicate the specific feedback
		$clone->duplicateSpecificFeedback($this_id);

		$clone->onDuplicate($this_id);
		return $clone->id;
	}

	/**
	* Copies an object
	*/
	public function copyObject($target_questionpool, $title = "")
	{
		if ($this->id <= 0)
		{
			// The question has not been saved. It cannot be duplicated
			return;
		}
		// duplicate the question in database
		$clone = $this;
		include_once ("./Modules/TestQuestionPool/classes/class.assQuestion.php");
		$original_id = assQuestion::_getOriginalId($this->id);
		$clone->id = -1;
		$source_questionpool = $this->getObjId();
		$clone->setObjId($target_questionpool);
		if ($title)
		{
			$clone->setTitle($title);
		}
		$clone->saveToDb();

		// copy question page content
		$clone->copyPageOfQuestion($original_id);
		// copy XHTML media objects
		$clone->copyXHTMLMediaObjectsOfQuestion($original_id);
		// duplicate the generic feedback
		$clone->duplicateGenericFeedback($original_id);
		// duplicate the specific feedback
		$clone->duplicateSpecificFeedback($original_id);

		$clone->onCopy($this->getObjId(), $this->getId());

		return $clone->id;
	}

	/**
	* Returns the maximum points, a learner can reach answering the question
	*
	* @see $points
	*/
	public function getMaximumPoints()
	{
		$maxpoints = 0.0;
		foreach ($this->errordata as $object)
		{
			if ($object->points > 0) $maxpoints += $object->points;
		}
		return $maxpoints;
	}

	/**
	 * Returns the points, a learner has reached answering the question.
	 * The points are calculated from the given answers.
	 * 
	 * @access public
	 * @param integer $active_id
	 * @param integer $pass
	 * @param boolean $returndetails (deprecated !!)
	 * @return integer/array $points/$details (array $details is deprecated !!)
	 */
	public function calculateReachedPoints($active_id, $pass = NULL, $returndetails = FALSE)
	{
		if( $returndetails )
		{
			throw new ilTestException('return details not implemented for '.__METHOD__);
		}
		
		global $ilDB;
		
		$found_values = array();
		if (is_null($pass))
		{
			$pass = $this->getSolutionMaxPass($active_id);
		}
		$result = $ilDB->queryF("SELECT value1 FROM tst_solutions WHERE active_fi = %s AND question_fi = %s AND pass = %s",
			array('integer','integer','integer'),
			array($active_id, $this->getId(), $pass)
		);
		while ($row = $ilDB->fetchAssoc($result))
		{
			array_push($found_values, $row['value1']);
		}
		$points = $this->getPointsForSelectedPositions($found_values);
		return $points;
	}
	
	/*
	* Change the selection during a test when a user selects/deselects a word without using javascript
	*/
	public function toggleSelection($position, $active_id, $pass = null)
	{
		global $ilDB;
		global $ilUser;

		if (is_null($pass))
		{
			include_once "./Modules/Test/classes/class.ilObjTest.php";
			$pass = ilObjTest::_getPass($active_id);
		}

		$affectedRows = $ilDB->manipulateF("DELETE FROM tst_solutions WHERE active_fi = %s AND question_fi = %s AND pass = %s AND value1 = %s",
			array('integer','integer','integer', 'text'),
			array($active_id, $this->getId(), $pass, $position)
		);
		if ($affectedRows == 0)
		{
			$next_id = $ilDB->nextId('tst_solutions');
			$affectedRows = $ilDB->insert("tst_solutions", array(
				"solution_id" => array("integer", $next_id),
				"active_fi" => array("integer", $active_id),
				"question_fi" => array("integer", $this->getId()),
				"value1" => array("clob", $position),
				"value2" => array("clob", null),
				"pass" => array("integer", $pass),
				"tstamp" => array("integer", time())
			));
		}
		include_once ("./Modules/Test/classes/class.ilObjAssessmentFolder.php");
		if (ilObjAssessmentFolder::_enabledAssessmentLogging())
		{
			$this->logAction($this->lng->txtlng("assessment", "log_user_entered_values", ilObjAssessmentFolder::_getLogLanguage()), $active_id, $this->getId());
		}
		$this->calculateReachedPoints($active_id, $pass);
	}
	
	/**
	 * Saves the learners input of the question to the database.
	 * 
	 * @access public
	 * @param integer $active_id Active id of the user
	 * @param integer $pass Test pass
	 * @return boolean $status
	 */
	public function saveWorkingData($active_id, $pass = NULL)
	{
		global $ilDB;
		global $ilUser;

		if (is_null($pass))
		{
			include_once "./Modules/Test/classes/class.ilObjTest.php";
			$pass = ilObjTest::_getPass($active_id);
		}

		$affectedRows = $ilDB->manipulateF("DELETE FROM tst_solutions WHERE active_fi = %s AND question_fi = %s AND pass = %s",
			array('integer','integer','integer'),
			array($active_id, $this->getId(), $pass)
		);

		$entered_values = false;
		if (strlen($_POST["qst_" . $this->getId()]))
		{
			$selected = split(",", $_POST["qst_" . $this->getId()]);
			foreach ($selected as $position)
			{
				$next_id = $ilDB->nextId('tst_solutions');
				$affectedRows = $ilDB->insert("tst_solutions", array(
					"solution_id" => array("integer", $next_id),
					"active_fi" => array("integer", $active_id),
					"question_fi" => array("integer", $this->getId()),
					"value1" => array("clob", $position),
					"value2" => array("clob", null),
					"pass" => array("integer", $pass),
					"tstamp" => array("integer", time())
				));
			}
			$entered_values = true;
		}
		if ($entered_values)
		{
			include_once ("./Modules/Test/classes/class.ilObjAssessmentFolder.php");
			if (ilObjAssessmentFolder::_enabledAssessmentLogging())
			{
				$this->logAction($this->lng->txtlng("assessment", "log_user_entered_values", ilObjAssessmentFolder::_getLogLanguage()), $active_id, $this->getId());
			}
		}
		else
		{
			include_once ("./Modules/Test/classes/class.ilObjAssessmentFolder.php");
			if (ilObjAssessmentFolder::_enabledAssessmentLogging())
			{
				$this->logAction($this->lng->txtlng("assessment", "log_user_not_entered_values", ilObjAssessmentFolder::_getLogLanguage()), $active_id, $this->getId());
			}
		}
		
		return true;
	}

	/**
	 * Reworks the allready saved working data if neccessary
	 *
	 * @access protected
	 * @param integer $active_id
	 * @param integer $pass
	 * @param boolean $obligationsAnswered
	 */
	protected function reworkWorkingData($active_id, $pass, $obligationsAnswered)
	{
		// nothing to rework!
	}

	/**
	* Returns the question type of the question
	*
	* @return integer The question type of the question
	*/
	public function getQuestionType()
	{
		return "assErrorText";
	}
	
	/**
	* Returns the name of the additional question data table in the database
	*
	* @return string The additional table name
	*/
	public function getAdditionalTableName()
	{
		return "qpl_qst_errortext";
	}
	
	/**
	* Returns the name of the answer table in the database
	*
	* @return string The answer table name
	*/
	public function getAnswerTableName()
	{
		return "qpl_a_errortext";
	}

	/**
	* Collects all text in the question which could contain media objects
	* which were created with the Rich Text Editor
	*/
	public function getRTETextWithMediaObjects()
	{
		$text = parent::getRTETextWithMediaObjects();
		return $text;
	}

	/**
	* Creates an Excel worksheet for the detailed cumulated results of this question
	*
	* @param object $worksheet Reference to the parent excel worksheet
	* @param object $startrow Startrow of the output in the excel worksheet
	* @param object $active_id Active id of the participant
	* @param object $pass Test pass
	* @param object $format_title Excel title format
	* @param object $format_bold Excel bold format
	* @param array $eval_data Cumulated evaluation data
	*/
	public function setExportDetailsXLS(&$worksheet, $startrow, $active_id, $pass, &$format_title, &$format_bold)
	{
		include_once ("./Services/Excel/classes/class.ilExcelUtils.php");
		$worksheet->writeString($startrow, 0, ilExcelUtils::_convert_text($this->lng->txt($this->getQuestionType())), $format_title);
		$worksheet->writeString($startrow, 1, ilExcelUtils::_convert_text($this->getTitle()), $format_title);
		
		$i= 0;
		$selections = array();
		$solutions =& $this->getSolutionValues($active_id, $pass);
		if (is_array($solutions))
		{
			foreach ($solutions as $solution)
			{
				array_push($selections, $solution['value1']);
			}
			$errortext_value = join(",", $selections);
		}
		$errortext = $this->createErrorTextExport($selections);
		$errortext = preg_replace("/#HREF\d+/is", "javascript:void(0);", $errortext);
		$i++;
		$worksheet->writeString($startrow+$i, 0, ilExcelUtils::_convert_text($errortext));
		$i++;
		return $startrow + $i + 1;
	}
	
	/**
	* Creates a question from a QTI file
	*
	* Receives parameters from a QTI parser and creates a valid ILIAS question object
	*
	* @param object $item The QTI item object
	* @param integer $questionpool_id The id of the parent questionpool
	* @param integer $tst_id The id of the parent test if the question is part of a test
	* @param object $tst_object A reference to the parent test object
	* @param integer $question_counter A reference to a question counter to count the questions of an imported question pool
	* @param array $import_mapping An array containing references to included ILIAS objects
	*/
	public function fromXML(&$item, &$questionpool_id, &$tst_id, &$tst_object, &$question_counter, &$import_mapping)
	{
		include_once "./Modules/TestQuestionPool/classes/import/qti12/class.assErrorTextImport.php";
		$import = new assErrorTextImport($this);
		$import->fromXML($item, $questionpool_id, $tst_id, $tst_object, $question_counter, $import_mapping);
	}
	
	/**
	* Returns a QTI xml representation of the question and sets the internal
	* domxml variable with the DOM XML representation of the QTI xml representation
	*
	* @return string The QTI xml representation of the question
	*/
	public function toXML($a_include_header = true, $a_include_binary = true, $a_shuffle = false, $test_output = false, $force_image_references = false)
	{
		include_once "./Modules/TestQuestionPool/classes/export/qti12/class.assErrorTextExport.php";
		$export = new assErrorTextExport($this);
		return $export->toXML($a_include_header, $a_include_binary, $a_shuffle, $test_output, $force_image_references);
	}

	/**
	* Returns the best solution for a given pass of a participant
	*
	* @return array An associated array containing the best solution
	*/
	public function getBestSolution($active_id, $pass)
	{
		$user_solution = array();
		return $user_solution;
	}
	
	public function getErrorsFromText($a_text = "")
	{
		if (strlen($a_text) == 0) $a_text = $this->getErrorText();
		preg_match_all("/#([^\s]+)/is", $a_text, $matches);
		if (is_array($matches[1]))
		{
			return $matches[1];
		}
		else
		{
			return array();
		}
	}
	
	public function setErrorData($a_data)
	{
		include_once "./Modules/TestQuestionPool/classes/class.assAnswerErrorText.php";
		$temp = $this->errordata;
		$this->errordata = array();
		foreach ($a_data as $error)
		{
			$text_correct = "";
			$points = 0.0;
			foreach ($temp as $object)
			{
				if (strcmp($object->text_wrong, $error) == 0)
				{
					$text_correct = $object->text_correct;
					$points = $object->points;
					continue;
				}
			}
			array_push($this->errordata, new assAnswerErrorText($error, $text_correct, $points));
		}
	}
	
	public function createErrorTextOutput($selections = null, $graphicalOutput = false, $correct_solution = false)
	{
		$counter = 0;
		$errorcounter = 0;
		include_once "./Services/Utilities/classes/class.ilStr.php";
		if (!is_array($selections)) $selections = array();
		$textarray = preg_split("/[\n\r]+/", $this->getErrorText());
		foreach ($textarray as $textidx => $text)
		{
			$items = preg_split("/\s+/", $text);
			foreach ($items as $idx => $item)
			{
				if (strpos($item, '#') === 0)
				{
					$item = ilStr::substr($item, 1, ilStr::strlen($item));
					if ($correct_solution)
					{
						$errorobject = $this->errordata[$errorcounter];
						if (is_object($errorobject))
						{
							$item = strlen($errorobject->text_correct) ? $errorobject->text_correct : '&nbsp;';
						}
						$errorcounter++;
					}
				}
				$class = '';
				$img = '';
				if (in_array($counter, $selections))
				{
					$class = ' class="sel"';
					if ($graphicalOutput)
					{
						if ($this->getPointsForSelectedPositions(array($counter)) >= 0)
						{
							$img = ' <img src="' . ilUtil::getImagePath("icon_ok.png") . '" alt="' . $this->lng->txt("answer_is_right") . '" title="' . $this->lng->txt("answer_is_right") . '" /> ';
						}
						else
						{
							$img = ' <img src="' . ilUtil::getImagePath("icon_not_ok.png") . '" alt="' . $this->lng->txt("answer_is_wrong") . '" title="' . $this->lng->txt("answer_is_wrong") . '" /> ';
						}
					}
				}
				$items[$idx] = '<a' . $class . ' href="#HREF' . $idx . '" onclick="javascript: return false;">' . ($item == '&nbsp;' ? $item : ilUtil::prepareFormOutput($item)) . '</a>' . $img;
				$counter++;
			}
			$textarray[$textidx] = '<p>' . join($items, " ") . '</p>';
		}
		return join($textarray, "\n");
	}

	public function createErrorTextExport($selections = null)
	{
		$counter = 0;
		$errorcounter = 0;
		include_once "./Services/Utilities/classes/class.ilStr.php";
		if (!is_array($selections)) $selections = array();
		$textarray = preg_split("/[\n\r]+/", $this->getErrorText());
		foreach ($textarray as $textidx => $text)
		{
			$items = preg_split("/\s+/", $text);
			foreach ($items as $idx => $item)
			{
				if (strpos($item, '#') === 0)
				{
					$item = ilStr::substr($item, 1, ilStr::strlen($item));
					if ($correct_solution)
					{
						$errorobject = $this->errordata[$errorcounter];
						if (is_object($errorobject))
						{
							$item = $errorobject->text_correct;
						}
						$errorcounter++;
					}
				}
				$word = "";
				if (in_array($counter, $selections))
				{
					$word .= '#';
				}
				$word .= ilUtil::prepareFormOutput($item);
				if (in_array($counter, $selections))
				{
					$word .= '#';
				}
				$items[$idx] = $word;
				$counter++;
			}
			$textarray[$textidx] = join($items, " ");
		}
		return join($textarray, "\n");
	}
	
	public function getBestSelection($withPositivePointsOnly = true)
	{
		$words = array();
		$counter = 0;
		$errorcounter = 0;
		$textarray = preg_split("/[\n\r]+/", $this->getErrorText());
		foreach ($textarray as $textidx => $text)
		{
			$items = preg_split("/\s+/", $text);
			foreach ($items as $word)
			{
				$points = $this->getPointsWrong();
				$isErrorItem = false;
				if (strpos($word, '#') === 0)
				{
					$errorobject = $this->errordata[$errorcounter];
					if (is_object($errorobject))
					{
						$points = $errorobject->points;
						$isErrorItem = true;
					}
					$errorcounter++;
				}
				$words[$counter] = array("word" => $word, "points" => $points, "isError" => $isErrorItem);
				$counter++;
			}
		}
		$selections = array();
		foreach ($words as $idx => $word)
		{
			if (!$withPositivePointsOnly && $word['isError'] || $withPositivePointsOnly && $word['points'] > 0)
			{
				array_push($selections, $idx);
			}
		}
		return $selections;
	}
	
	protected function getPointsForSelectedPositions($positions)
	{
		$words = array();
		$counter = 0;
		$errorcounter = 0;
		$textarray = preg_split("/[\n\r]+/", $this->getErrorText());
		foreach ($textarray as $textidx => $text)
		{
			$items = preg_split("/\s+/", $text);
			foreach ($items as $word)
			{
				$points = $this->getPointsWrong();
				if (strpos($word, '#') === 0)
				{
					$errorobject = $this->errordata[$errorcounter];
					if (is_object($errorobject))
					{
						$points = $errorobject->points;
					}
					$errorcounter++;
				}
				$words[$counter] = array("word" => $word, "points" => $points);
				$counter++;
			}
		}
		$total = 0;
		foreach ($positions as $position)
		{
			$total += $words[$position]['points'];
		}
		return $total;
	}
	
	/**
	* Flush error data
	*/
	public function flushErrorData()
	{
		$this->errordata = array();
	}
	
	public function addErrorData($text_wrong, $text_correct, $points)
	{
		include_once "./Modules/TestQuestionPool/classes/class.assAnswerErrorText.php";
		array_push($this->errordata, new assAnswerErrorText($text_wrong, $text_correct, $points));
	}
	
	/**
	* Get error data
	*
	* @return array Error data
	*/
	public function getErrorData()
	{
		return $this->errordata;
	}
	
	/**
	* Get error text
	*
	* @return string Error text
	*/
	public function getErrorText()
	{
		return $this->errortext;
	}
	
	/**
	* Set error text
	*
	* @param string $a_value Error text
	*/
	public function setErrorText($a_value)
	{
		$this->errortext = $a_value;
	}
	
	/**
	* Set text size in percent
	*
	* @return double Text size in percent
	*/
	public function getTextSize()
	{
		return $this->textsize;
	}
	
	/**
	* Set text size in percent
	*
	* @param double $a_value text size in percent
	*/
	public function setTextSize($a_value)
	{
		// in self-assesment-mode value should always be set (and must not be null)
		if($a_value === null)
		{
			$a_value = 100;
		}
		$this->textsize = $a_value;
	}
	
	/**
	* Get wrong points
	*
	* @return double Points for wrong selection
	*/
	public function getPointsWrong()
	{
		return $this->points_wrong;
	}
	
	/**
	* Set wrong points
	*
	* @param double $a_value Points for wrong selection
	*/
	public function setPointsWrong($a_value)
	{
		$this->points_wrong = $a_value;
	}
	
	/**
	* Object getter
	*/
	public function __get($value)
	{
		switch ($value)
		{
			case "errortext":
				return $this->getErrorText();
				break;
			case "textsize":
				return $this->getTextSize();
				break;
			case "points_wrong":
				return $this->getPointsWrong();
				break;
			default:
				return parent::__get($value);
				break;
		}
	}

	/**
	* Object setter
	*/
	public function __set($key, $value)
	{
		switch ($key)
		{
			case "errortext":
				$this->setErrorText($value);
				break;
			case "textsize":
				$this->setTextSize($value);
				break;
			case "points_wrong":
				$this->setPointsWrong($value);
				break;
			default:
				parent::__set($key, $value);
				break;
		}
	}


	/**
	* Returns a JSON representation of the question
	*/
	public function toJSON()
	{
		include_once("./Services/RTE/classes/class.ilRTE.php");
		$result = array();
		$result['id'] = (int) $this->getId();
		$result['type'] = (string) $this->getQuestionType();
		$result['title'] = (string) $this->getTitle();
		$result['question'] =  $this->formatSAQuestion($this->getQuestion());
		$result['text'] =  (string) ilRTE::_replaceMediaObjectImageSrc($this->getErrorText(), 0);
		$result['nr_of_tries'] = (int) $this->getNrOfTries();
		$result['shuffle'] = (bool) $this->getShuffle();
		$result['feedback'] = array(
			"onenotcorrect" => ilRTE::_replaceMediaObjectImageSrc($this->getFeedbackGeneric(0), 0),
			"allcorrect" => ilRTE::_replaceMediaObjectImageSrc($this->getFeedbackGeneric(1), 0)
			);

		$answers = array();
		foreach ($this->getErrorData() as $idx => $answer_obj)
		{
			array_push($answers, array(
				"answertext_wrong" => (string) $answer_obj->text_wrong,
				"answertext_correct" => (string) $answer_obj->text_correct,
				"points" => (float)$answer_obj->points,
				"order" => (int)$idx+1
			));
		}
		$result['correct_answers'] = $answers;

		$answers = array();
		$textarray = preg_split("/[\n\r]+/", $this->getErrorText());
		foreach ($textarray as $textidx => $text)
		{
			$items = preg_split("/\s+/", trim($text));
			foreach ($items as $idx => $item)
			{
				if(substr($item, 0, 1) == "#")
				{
					$item = substr($item, 1);
				}
				array_push($answers, array(
					"answertext" => (string) ilUtil::prepareFormOutput($item),
					"order" => $textidx."_".($idx+1)
				));
			}
			if($textidx != sizeof($textarray)-1)
			{
				array_push($answers, array(
						"answertext" => "###",
						"order" => $textidx."_".($idx+2)
					));
			}
		}
		$result['answers'] = $answers;

		$mobs = ilObjMediaObject::_getMobsOfObject("qpl:html", $this->getId());
		$result['mobs'] = $mobs;

		return json_encode($result);
	}
	
	/**
	* Saves feedback for a single selected answer to the database
	*
	* @param integer $answer_index The index of the answer
	* @param string $feedback Feedback text
	* @access public
	*/
	function saveFeedbackSingleAnswer($answer_index, $feedback)
	{
		global $ilDB;
		
		$affectedRows = $ilDB->manipulateF("DELETE FROM qpl_fb_errortext WHERE question_fi = %s AND answer = %s",
			array('integer','integer'),
			array($this->getId(), $answer_index)
		);
		if (strlen($feedback))
		{
			include_once("./Services/RTE/classes/class.ilRTE.php");
			$next_id = $ilDB->nextId('qpl_fb_errortext');
			$affectedRows = $ilDB->manipulateF("INSERT INTO qpl_fb_errortext (feedback_id, question_fi, answer, feedback, tstamp) VALUES (%s, %s, %s, %s, %s)",
				array('integer','integer','integer','text','integer'),
				array(
					$next_id,
					$this->getId(),
					$answer_index,
					ilRTE::_replaceMediaObjectImageSrc($feedback, 0),
					time()
				)
			);
		}
	}
	
	/**
	* Returns the feedback for a single selected answer
	*
	* @param integer $answer_index The index of the answer
	* @return string Feedback text
	* @access public
	*/
	function getFeedbackSingleAnswer($answer_index)
	{
		global $ilDB;
		
		$feedback = "";
		$result = $ilDB->queryF("SELECT * FROM qpl_fb_errortext WHERE question_fi = %s AND answer = %s",
			array('integer','integer'),
			array($this->getId(), $answer_index)
		);
		if ($result->numRows())
		{
			$row = $ilDB->fetchAssoc($result);
			include_once("./Services/RTE/classes/class.ilRTE.php");
			$feedback = ilRTE::_replaceMediaObjectImageSrc($row["feedback"], 1);
		}
		return $feedback;
	}

	/**
	 * Duplicates the answer specific feedback
	 *
	 * @param integer $original_id The database ID of the original question
	 * @access public
	 */
	function duplicateSpecificFeedback($original_id)
	{
		global $ilDB;

		$feedback = "";
		$result = $ilDB->queryF("SELECT * FROM qpl_fb_errortext WHERE question_fi = %s",
								array('integer'),
								array($original_id)
		);
		if ($result->numRows())
		{
			while ($row = $ilDB->fetchAssoc($result))
			{
				$next_id = $ilDB->nextId('qpl_fb_errortext');
				$affectedRows = $ilDB->manipulateF("INSERT INTO qpl_fb_errortext (feedback_id, question_fi, answer, feedback, tstamp) VALUES (%s, %s, %s, %s, %s)",
												   array('integer','integer','integer','text','integer'),
												   array(
													   $next_id,
													   $this->getId(),
													   $row["answer"],
													   $row["feedback"],
													   time()
												   )
				);
			}
		}
	}

	protected function deleteFeedbackSpecific($question_id)
	{
		global $ilDB;
		$ilDB->manipulateF(
			'DELETE 
			FROM qpl_fb_errortext 
			WHERE question_fi = %s',
			array('integer'),
			array($question_id)
		);
	}
}
