<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
include_once "./Modules/Test/classes/inc.AssessmentConstants.php";

/**
 * Class for horizontal ordering questions
 *
 * @extends assQuestion
 * 
 * @author		Helmut Schottmüller <helmut.schottmueller@mac.com> 
 * @author		Björn Heyser <bheyser@databay.de>
 * @version		$Id: class.assOrderingHorizontal.php 38336 2012-11-22 13:30:20Z bheyser $
 * 
 * @ingroup		ModulesTestQuestionPool
 */
class assOrderingHorizontal extends assQuestion
{
	protected $ordertext;
	protected $textsize;
	protected $separator = "::";
	protected $answer_separator = '{::}';
	
	/**
	* assOrderingHorizontal constructor
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
		$this->ordertext = "";
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
	* Saves a assOrderingHorizontal object to a database
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

		$affectedRows = $ilDB->manipulateF("INSERT INTO " . $this->getAdditionalTableName() . " (question_fi, ordertext, textsize) VALUES (%s, %s, %s)", 
			array("integer", "text", "float"),
			array(
				$this->getId(),
				$this->getOrderText(),
				($this->getTextSize() < 10) ? NULL : $this->getTextSize()
			)
		);
	
		parent::saveToDb();
	}

	/**
	* Loads a assOrderingHorizontal object from a database
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
			$this->setOrderText($data["ordertext"]);
			$this->setTextSize($data["textsize"]);
			$this->setEstimatedWorkingTime(substr($data["working_time"], 0, 2), substr($data["working_time"], 3, 2), substr($data["working_time"], 6, 2));
		}

		parent::loadFromDb($question_id);
	}

	/**
	* Duplicates an assOrderingHorizontal
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

		$clone->onDuplicate($this_id);
		return $clone->id;
	}

	/**
	* Copies an assOrderingHorizontal object
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
		return $this->getPoints();
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
		$result = $ilDB->queryF("SELECT * FROM tst_solutions WHERE active_fi = %s AND question_fi = %s AND pass = %s",
			array('integer','integer','integer'),
			array($active_id, $this->getId(), $pass)
		);
		$points = 0;
		$data = $ilDB->fetchAssoc($result);

		$data["value1"] = $this->splitAndTrimOrderElementText($data["value1"], $this->answer_separator);
		
		$data['value1'] = join($data['value1'], $this->answer_separator);
		
		if (strcmp($data["value1"], join($this->getOrderingElements(), $this->answer_separator)) == 0)
		{
			$points = $this->getPoints();
		}
		return $points;
	}
	
	/**
	 * Splits the answer string either by space(s) or the separator (eg. ::) and
	 * trims the resulting array elements.
	 * 
	 * @param string $in_string OrderElements 
	 * @param string $separator to be used for splitting.
	 * 
	 * @return array 
	 */
	private function splitAndTrimOrderElementText($in_string, $separator)
	{
		$result = array();
		include_once "./Services/Utilities/classes/class.ilStr.php";
		
		if (ilStr::strPos($in_string, $separator) === false)
		{
			$result = preg_split("/\\s+/", $in_string);
		}
		else
		{
			$result = split($separator, $in_string);
		}
		
		foreach ($result as $key => $value)
		{
			$result[$key] = trim($value);
		}
		
		return $result;
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
		if (strlen($_POST["orderresult"]))
		{
			$next_id = $ilDB->nextId('tst_solutions');
			$affectedRows = $ilDB->insert("tst_solutions", array(
				"solution_id" => array("integer", $next_id),
				"active_fi" => array("integer", $active_id),
				"question_fi" => array("integer", $this->getId()),
				"value1" => array("clob", $_POST['orderresult']),
				"value2" => array("clob", null),
				"pass" => array("integer", $pass),
				"tstamp" => array("integer", time())
			));
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

	/*
	* Move an element to the right during a test when a user selects/deselects a word without using javascript
	*/
	public function moveRight($position, $active_id, $pass = null)
	{
		global $ilDB;
		global $ilUser;

		if (is_null($pass))
		{
			include_once "./Modules/Test/classes/class.ilObjTest.php";
			$pass = ilObjTest::_getPass($active_id);
		}

		$solutions =& $this->getSolutionValues($active_id, $pass);
		$elements = array();
		if (count($solutions) == 1)
		{
			$elements = split("{::}", $solutions[0]["value1"]);
		}
		else
		{
			$elements = $_SESSION['qst_ordering_horizontal_elements'];
		}
		if (count($elements))
		{
			$affectedRows = $ilDB->manipulateF("DELETE FROM tst_solutions WHERE active_fi = %s AND question_fi = %s AND pass = %s",
				array('integer','integer','integer'),
				array($active_id, $this->getId(), $pass)
			);

			if ($position < count($elements)-1)
			{
				$temp = $elements[$position];
				$elements[$position] = $elements[$position+1];
				$elements[$position+1] = $temp;
			}
			$entered_values = false;
			$next_id = $ilDB->nextId('tst_solutions');
			$affectedRows = $ilDB->insert("tst_solutions", array(
				"solution_id" => array("integer", $next_id),
				"active_fi" => array("integer", $active_id),
				"question_fi" => array("integer", $this->getId()),
				"value1" => array("clob", join($elements, '{::}')),
				"value2" => array("clob", null),
				"pass" => array("integer", $pass),
				"tstamp" => array("integer", time())
			));
			$entered_values = true;
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

			$this->calculateReachedPoints($active_id, $pass);
		}
	}
	
	/**
	* Returns the question type of the question
	*
	* @return integer The question type of the question
	*/
	public function getQuestionType()
	{
		return "assOrderingHorizontal";
	}
	
	/**
	* Returns the name of the additional question data table in the database
	*
	* @return string The additional table name
	*/
	public function getAdditionalTableName()
	{
		return "qpl_qst_horder";
	}
	
	/**
	* Returns the name of the answer table in the database
	*
	* @return string The answer table name
	*/
	public function getAnswerTableName()
	{
		return "";
	}
	
	/**
	* Deletes datasets from answers tables
	*
	* @param integer $question_id The question id which should be deleted in the answers table
	*/
	public function deleteAnswers($question_id)
	{
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

		$solutionvalue = "";
		$solutions =& $this->getSolutionValues($active_id, $pass);
		$solutionvalue = str_replace("{::}", " ", $solutions[0]["value1"]);
		$i = 1;
		$worksheet->writeString($startrow+$i, 0, ilExcelUtils::_convert_text($solutionvalue));
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
		include_once "./Modules/TestQuestionPool/classes/import/qti12/class.assOrderingHorizontalImport.php";
		$import = new assOrderingHorizontalImport($this);
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
		include_once "./Modules/TestQuestionPool/classes/export/qti12/class.assOrderingHorizontalExport.php";
		$export = new assOrderingHorizontalExport($this);
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
	
	/**
	* Get ordering elements from order text
	*
	* @return array Ordering elements
	*/
	public function getOrderingElements()
	{
		return $this->splitAndTrimOrderElementText($this->getOrderText(), $this->separator);
	}
	
	/**
	* Get ordering elements from order text in random sequence
	*
	* @return array Ordering elements
	*/
	public function getRandomOrderingElements()
	{
		$elements = $this->getOrderingElements();
		shuffle($elements);
		return $elements;
	}
	
	/**
	* Get order text
	*
	* @return string Order text
	*/
	public function getOrderText()
	{
		return $this->ordertext;
	}
	
	/**
	* Set order text
	*
	* @param string $a_value Order text
	*/
	public function setOrderText($a_value)
	{
		$this->ordertext = $a_value;
	}
	
	/**
	* Get text size
	*
	* @return double Text size in percent
	*/
	public function getTextSize()
	{
		return $this->textsize;
	}
	
	/**
	* Set text size
	*
	* @param double $a_value Text size in percent
	*/
	public function setTextSize($a_value)
	{
		if ($a_value >= 10)
		{
			$this->textsize = $a_value;
		}
	}
	
	/**
	* Get order text separator
	*
	* @return string Separator
	*/
	public function getSeparator()
	{
		return $this->separator;
	}
	
	/**
	* Set order text separator
	*
	* @param string $a_value Separator
	*/
	public function setSeparator($a_value)
	{
		$this->separator = $a_value;
	}
	
	/**
	* Object getter
	*/
	public function __get($value)
	{
		switch ($value)
		{
			case "ordertext":
				return $this->getOrderText();
				break;
			case "textsize":
				return $this->getTextSize();
				break;
			case "separator":
				return $this->getSeparator();
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
			case "ordertext":
				$this->setOrderText($value);
				break;
			case "textsize":
				$this->setTextSize($value);
				break;
			case "separator":
				$this->setSeparator($value);
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
		$result['nr_of_tries'] = (int) $this->getNrOfTries();
		$result['shuffle'] = (bool) true;
		$result['points'] = (bool) $this->getPoints();
		$result['feedback'] = array(
			"onenotcorrect" => nl2br(ilRTE::_replaceMediaObjectImageSrc($this->getFeedbackGeneric(0), 0)),
			"allcorrect" => nl2br(ilRTE::_replaceMediaObjectImageSrc($this->getFeedbackGeneric(1), 0))
			);
		
		$arr = array();
		foreach ($this->getOrderingElements() as $order => $answer)
		{
			array_push($arr, array(
				"answertext" => (string) $answer,
				"order" => (int) $order+1
			));
		}
		$result['answers'] = $arr;

		$mobs = ilObjMediaObject::_getMobsOfObject("qpl:html", $this->getId());
		$result['mobs'] = $mobs;
	
		return json_encode($result);
	}
}

?>
