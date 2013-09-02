<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
include_once "./Modules/Test/classes/inc.AssessmentConstants.php";

/**
 * Class for cloze tests
 *
 * @extends assQuestion
 * 
 * @author		Helmut Schottmüller <helmut.schottmueller@mac.com> 
 * @author		Björn Heyser <bheyser@databay.de>
 * @version	$Id: class.assClozeTest.php 42740 2013-06-16 16:26:49Z bheyser $
 * 
 * @ingroup ModulesTestQuestionPool
 */
class assClozeTest extends assQuestion
{
	/**
	* The gaps of the cloze question
	*
	* $gaps is an array of the predefined gaps of the cloze question
	*
	* @var array
	*/
	var $gaps;

	/**
	* The start tag beginning a cloze gap
	*
	* The start tag is set to "*[" by default.
	*
	* @var string
	*/
	var $start_tag;

	/**
	* The end tag beginning a cloze gap
	*
	* The end tag is set to "]" by default.
	*
	* @var string
	*/
	var $end_tag;
	
	/**
	* The rating option for text gaps
	*
	* This could contain one of the following options:
	* - case insensitive text gaps
	* - case sensitive text gaps
	* - various levenshtein distances
	*
	* @var string
	*/
	var $textgap_rating;

	/**
	* Defines the scoring for "identical solutions"
	*
	* If the learner selects the same solution twice 
	* or more in different gaps, only the first choice 
	* will be scored if identical_scoring is 0.
	*
	* @var boolean
	*/
	var $identical_scoring;

	/**
	* The fixed text length for all text fields in the cloze question
	*
	* @var integer
	*/
	var $fixedTextLength;
	
	/**
	* assClozeTest constructor
	*
	* The constructor takes possible arguments an creates an instance of the assClozeTest object.
	*
	* @param string $title A title string to describe the question
	* @param string $comment A comment string to describe the question
	* @param string $author A string containing the name of the questions author
	* @param integer $owner A numerical ID to identify the owner/creator
	* @param string $cloze_text The question string of the cloze test
	* @access public
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
		$this->start_tag = "[gap]";
		$this->end_tag = "[/gap]";
		$this->gaps = array();
		$this->setClozeText($cloze_text);
		$this->fixedTextLength = "";
		$this->identical_scoring = 1;
	}

	/**
	* Returns TRUE, if a cloze test is complete for use
	*
	* @return boolean TRUE, if the cloze test is complete for use, otherwise FALSE
	* @access public
	*/
	function isComplete()
	{
		if (strlen($this->getTitle()) and ($this->getAuthor()) and ($this->getClozeText()) and (count($this->getGaps())) and ($this->getMaximumPoints() > 0))
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

	/**
	* Cleans cloze question text to remove attributes or tags from older ILIAS versions
	*
	* @param string $text The cloze question text
	* @return string The cleaned cloze question text
	* @access public
	*/
	function cleanQuestiontext($text)
	{
		$text = preg_replace("/\[gap[^\]]*?\]/", "[gap]", $text);
		$text = preg_replace("/\<gap([^>]*?)\>/", "[gap]", $text);
		$text = str_replace("</gap>", "[/gap]", $text);
		return $text;
	}
	
	/**
	* Loads a assClozeTest object from a database
	*
	* @param object $db A pear DB object
	* @param integer $question_id A unique key which defines the cloze test in the database
	* @access public
	*/
	function loadFromDb($question_id)
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
			$this->setNrOfTries($data['nr_of_tries']);
			$this->setObjId($data["obj_fi"]);
			$this->setTitle($data["title"]);
			$this->setComment($data["description"]);
			$this->setOriginalId($data["original_id"]);
			$this->setAuthor($data["author"]);
			$this->setPoints($data["points"]);
			$this->setOwner($data["owner"]);
			$this->setQuestion($this->cleanQuestiontext($data["question_text"]));
			$this->setFixedTextLength($data["fixed_textlen"]);
			$this->setIdenticalScoring(($data['tstamp'] == 0) ? true : $data["identical_scoring"]);
			// replacement of old syntax with new syntax
			include_once("./Services/RTE/classes/class.ilRTE.php");
			$this->question = ilRTE::_replaceMediaObjectImageSrc($this->question, 1);
			$this->setTextgapRating($data["textgap_rating"]);
			$this->setEstimatedWorkingTime(substr($data["working_time"], 0, 2), substr($data["working_time"], 3, 2), substr($data["working_time"], 6, 2));

			// open the cloze gaps with all answers
			include_once "./Modules/TestQuestionPool/classes/class.assAnswerCloze.php";
			include_once "./Modules/TestQuestionPool/classes/class.assClozeGap.php";
			$result = $ilDB->queryF("SELECT * FROM qpl_a_cloze WHERE question_fi = %s ORDER BY gap_id, aorder ASC",
				array("integer"),
				array($question_id)
			);
			if ($result->numRows() > 0)
			{
				$this->gaps = array();
				while ($data = $ilDB->fetchAssoc($result)) 
				{
					switch ($data["cloze_type"])
					{
						case CLOZE_TEXT:
							if (!array_key_exists($data["gap_id"], $this->gaps))
							{
								$this->gaps[$data["gap_id"]] = new assClozeGap(CLOZE_TEXT);
							}
							$answer = new assAnswerCloze(
								$data["answertext"],
								$data["points"],
								$data["aorder"]
							);
							$this->gaps[$data["gap_id"]]->addItem($answer);
							break;
						case CLOZE_SELECT:
							if (!array_key_exists($data["gap_id"], $this->gaps))
							{
								$this->gaps[$data["gap_id"]] = new assClozeGap(CLOZE_SELECT);
								$this->gaps[$data["gap_id"]]->setShuffle($data["shuffle"]);
							}
							$answer = new assAnswerCloze(
								$data["answertext"],
								$data["points"],
								$data["aorder"]
								);
							$this->gaps[$data["gap_id"]]->addItem($answer);
							break;
						case CLOZE_NUMERIC:
							if (!array_key_exists($data["gap_id"], $this->gaps))
							{
								$this->gaps[$data["gap_id"]] = new assClozeGap(CLOZE_NUMERIC);
							}
							$answer = new assAnswerCloze(
								$data["answertext"],
								$data["points"],
								$data["aorder"]
							);
							$answer->setLowerBound($data["lowerlimit"]);
							$answer->setUpperBound($data["upperlimit"]);
							$this->gaps[$data["gap_id"]]->addItem($answer);
							break;
					}
				}
			}
		}
		parent::loadFromDb($question_id);
	}

	/**
	* Saves a assClozeTest object to a database
	*
	* @param integer $original_id ID of the original question
	* @access public
	*/
	function saveToDb($original_id = "")
	{
		global $ilDB;

		$this->saveQuestionDataToDb($original_id);

		include_once "./Services/Math/classes/class.EvalMath.php";
		$eval = new EvalMath();
		$eval->suppress_errors = TRUE;

		// save additional data
		$affectedRows = $ilDB->manipulateF("DELETE FROM " . $this->getAdditionalTableName() . " WHERE question_fi = %s", 
			array("integer"),
			array($this->getId())
		);


		$affectedRows = $ilDB->manipulateF("INSERT INTO " . $this->getAdditionalTableName() . " (question_fi, textgap_rating, identical_scoring, fixed_textlen) VALUES (%s, %s, %s, %s)",
			array(
				"integer", 
				"text",
				"text",
				"integer"
			),
			array(
				$this->getId(),
				$this->getTextgapRating(),
				$this->getIdenticalScoring(),
				$this->getFixedTextLength() ? $this->getFixedTextLength() : NULL
			)
		);

		$affectedRows = $ilDB->manipulateF("DELETE FROM qpl_a_cloze WHERE question_fi = %s",
			array("integer"),
			array($this->getId())
		);
		
		foreach ($this->gaps as $key => $gap)
		{
			foreach ($gap->getItems() as $item)
			{
				$query = "";
				switch ($gap->getType())
				{
					case CLOZE_TEXT:
						$next_id = $ilDB->nextId('qpl_a_cloze');
						$affectedRows = $ilDB->manipulateF("INSERT INTO qpl_a_cloze (answer_id, question_fi, gap_id, answertext, points, aorder, cloze_type) VALUES (%s, %s, %s, %s, %s, %s, %s)",
							array(
								"integer",
								"integer", 
								"integer",
								"text",
								"float",
								"integer",
								"text"
							),
							array(
								$next_id,
								$this->getId(),
								$key,
								strlen($item->getAnswertext()) ? $item->getAnswertext() : "",
								$item->getPoints(),
								$item->getOrder(),
								$gap->getType()
							)
						);
						break;
					case CLOZE_SELECT:
						$next_id = $ilDB->nextId('qpl_a_cloze');
						$affectedRows = $ilDB->manipulateF("INSERT INTO qpl_a_cloze (answer_id, question_fi, gap_id, answertext, points, aorder, cloze_type, shuffle) VALUES (%s, %s, %s, %s, %s, %s, %s, %s)",
							array(
								"integer",
								"integer", 
								"integer",
								"text",
								"float",
								"integer",
								"text",
								"text"
							),
							array(
								$next_id,
								$this->getId(),
								$key,
								strlen($item->getAnswertext()) ? $item->getAnswertext() : "",
								$item->getPoints(),
								$item->getOrder(),
								$gap->getType(),
								($gap->getShuffle()) ? "1" : "0"
							)
						);
						break;
					case CLOZE_NUMERIC:
						$next_id = $ilDB->nextId('qpl_a_cloze');
						$affectedRows = $ilDB->manipulateF("INSERT INTO qpl_a_cloze (answer_id, question_fi, gap_id, answertext, points, aorder, cloze_type, lowerlimit, upperlimit) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)",
							array(
								"integer",
								"integer", 
								"integer",
								"text",
								"float",
								"integer",
								"text",
								"text",
								"text"
							),
							array(
								$next_id,
								$this->getId(),
								$key,
								strlen($item->getAnswertext()) ? $item->getAnswertext() : "",
								$item->getPoints(),
								$item->getOrder(),
								$gap->getType(),
								($eval->e($item->getLowerBound() !== FALSE) && strlen($item->getLowerBound()) > 0) ? $item->getLowerBound() : $item->getAnswertext(),
								($eval->e($item->getUpperBound() !== FALSE)  && strlen($item->getUpperBound()) > 0) ? $item->getUpperBound() : $item->getAnswertext()
							)
						);
						break;
				}
			}
		}
		parent::saveToDb($original_id);
	}

	/**
	* Returns the array of gaps
	*
	* @return array Array containing the gap objects of the cloze question gaps
	* @access public
	*/
	function getGaps()
	{
		return $this->gaps;
	}


	/**
	* Deletes all gaps without changing the cloze text
	*
	* @access public
	* @see $gaps
	*/
	function flushGaps() 
	{
		$this->gaps = array();
	}

	/**
	* Evaluates the text gap solutions from the cloze text. A single or multiple text gap solutions
	* could be entered using the following syntax in the cloze text:
	* solution1 [, solution2, ..., solutionN] enclosed in the text gap selector gap[]
	*
	* @param string $cloze_text The cloze text with all gaps and gap gaps
	* @access public
	* @see $cloze_text
	*/
	function setClozeText($cloze_text = "")
	{
		$this->gaps = array();
		$cloze_text = $this->cleanQuestiontext($cloze_text);
		$this->question = $cloze_text;
		$this->createGapsFromQuestiontext();
	}
	
	/**
	* Returns the cloze text
	*
	* @return string The cloze text string
	* @access public
	* @see $cloze_text
	*/
	function getClozeText() 
	{
		return $this->question;
	}

	/**
	* Returns the start tag of a cloze gap
	*
	* @return string The start tag of a cloze gap
	* @access public
	* @see $start_tag
	*/
	function getStartTag() 
	{
		return $this->start_tag;
	}

	/**
	* Sets the start tag of a cloze gap
	*
	* @param string $start_tag The start tag for a cloze gap
	* @access public
	* @see $start_tag
	*/
	function setStartTag($start_tag = "[gap]") 
	{
		$this->start_tag = $start_tag;
	}
	
	/**
	* Returns the end tag of a cloze gap
	*
	* @return string The end tag of a cloze gap
	* @access public
	* @see $end_tag
	*/
	function getEndTag() 
	{
		return $this->end_tag;
	}

	/**
	* Sets the end tag of a cloze gap
	*
	* @param string $end_tag The end tag for a cloze gap
	* @access public
	* @see $end_tag
	*/
	function setEndTag($end_tag = "[/gap]") 
	{
		$this->end_tag = $end_tag;
	}

	/**
	* Create gap entries by parsing the question text
	*
	* @access public
	* @see $gaps
	*/
	function createGapsFromQuestiontext()
	{
		include_once "./Modules/TestQuestionPool/classes/class.assClozeGap.php";
		include_once "./Modules/TestQuestionPool/classes/class.assAnswerCloze.php";
		$search_pattern = "|\[gap\](.*?)\[/gap\]|i";
		preg_match_all($search_pattern, $this->getClozeText(), $found);
		$this->gaps = array();
		if (count($found[0]))
		{
			foreach ($found[1] as $gap_index => $answers)
			{
				// create text gaps by default
				$gap = new assClozeGap(CLOZE_TEXT);
				$textparams = preg_split("/(?<!\\\\),/", $answers);
				foreach ($textparams as $key => $value)
				{
					$answer = new assAnswerCloze($value, 0, $key);
					$gap->addItem($answer);
				}
				$this->gaps[$gap_index] = $gap;
			}
		}
	}
	
	/**
	* Set the type of a gap with a given index
	*
	* @access private
	*/
	function setGapType($gap_index, $gap_type)
	{
		if (array_key_exists($gap_index, $this->gaps))
		{
			$this->gaps[$gap_index]->setType($gap_type);
		}
	}

	/**
	* Sets the shuffle state of a gap with a given index. The index of the first
	* gap is 0, the index of the second gap is 1 and so on.
	*
	* @param integer $index A nonnegative index of the n-th gap
	* @param integer $shuffle Turn shuffle on (=1) or off (=0)
	* @access public
	* @see $gaps
	*/
	function setGapShuffle($gap_index = 0, $shuffle = 1) 
	{
		if (array_key_exists($gap_index, $this->gaps))
		{
			$this->gaps[$gap_index]->setShuffle($shuffle);
		}
	}

	/**
	* Removes all answers from the gaps
	*
	* @access public
	* @see $gaps
	*/
	function clearGapAnswers() 
	{
		foreach ($this->gaps as $gap_index => $gap)
		{
			$this->gaps[$gap_index]->clearItems();
		}
	}

	/**
	* Returns the number of gaps
	*
	* @return integer The number of gaps
	* @access public
	* @see $gaps
	*/
	function getGapCount() 
	{
		if (is_array($this->gaps))
		{
			return count($this->gaps);
		}
		else
		{
			return 0;
		}
	}

	/**
	* Sets the answer text of a gap with a given index. The index of the first
	* gap is 0, the index of the second gap is 1 and so on.
	*
	* @param integer $gap_index A nonnegative index of the n-th gap
	* @param integer $order The order of the answer text
	* @param string $answer The answer text
	* @access public
	* @see $gaps
	*/
	function addGapAnswer($gap_index, $order, $answer) 
	{
		if (array_key_exists($gap_index, $this->gaps))
		{
			if ($this->gaps[$gap_index]->getType() == CLOZE_NUMERIC)
			{
				// only allow notation with "." for real numbers
				$answer = str_replace(",", ".", $answer);
			}
			$this->gaps[$gap_index]->addItem(new assAnswerCloze($answer, 0, $order));
		}
	}
	
	/**
	* Returns the gap at a given index
	*
	* @param integer $gap_index A nonnegative index of the n-th gap
	* @return object The gap of the given index
	* @access public
	* @see $gaps
	*/
	function getGap($gap_index = 0) 
	{
		if (array_key_exists($gap_index, $this->gaps))
		{
			return $this->gaps[$gap_index];
		}
		else
		{
			return NULL;
		}
	}

	/**
	* Sets the points of a gap with a given index and an answer with a given order. The index of the first
	* gap is 0, the index of the second gap is 1 and so on.
	*
	* @param integer $gap_index A nonnegative index of the n-th gap
	* @param integer $order The order of the answer text
	* @param string $answer The points of the answer
	* @access public
	* @see $gaps
	*/
	function setGapAnswerPoints($gap_index, $order, $points) 
	{
		if (array_key_exists($gap_index, $this->gaps))
		{
			$this->gaps[$gap_index]->setItemPoints($order, $points);
		}
	}

	/**
	* Adds a new answer text value to a text gap with a given index. The index of the first
	* gap is 0, the index of the second gap is 1 and so on.
	*
	* @param integer $gap_index A nonnegative index of the n-th gap
	* @access public
	* @see $gaps
	*/
	function addGapText($gap_index) 
	{
		if (array_key_exists($gap_index, $this->gaps))
		{
			include_once "./Modules/TestQuestionPool/classes/class.assAnswerCloze.php";
			$answer = new assAnswerCloze(
				"",
				0,
				$this->gaps[$gap_index]->getItemCount()
			);
			$this->gaps[$gap_index]->addItem($answer);
		}
	}
	
	/**
	* Adds a ClozeGap object at a given index
	*
	* @param object $gap The gap object
	* @param integer $index A nonnegative index of the n-th gap
	* @access public
	* @see $gaps
	*/
	function addGapAtIndex($gap, $index)
	{
		$this->gaps[$index] = $gap;
	}

	/**
	* Sets the lower bound of a gap with a given index and an answer with a given order. The index of the first
	* gap is 0, the index of the second gap is 1 and so on.
	*
	* @param integer $gap_index A nonnegative index of the n-th gap
	* @param integer $order The order of the answer text
	* @param string $answer The lower bound of the answer
	* @access public
	* @see $gaps
	*/
	function setGapAnswerLowerBound($gap_index, $order, $bound) 
	{
		if (array_key_exists($gap_index, $this->gaps))
		{
			$this->gaps[$gap_index]->setItemLowerBound($order, $bound);
		}
	}

	/**
	* Sets the upper bound of a gap with a given index and an answer with a given order. The index of the first
	* gap is 0, the index of the second gap is 1 and so on.
	*
	* @param integer $gap_index A nonnegative index of the n-th gap
	* @param integer $order The order of the answer text
	* @param string $answer The upper bound of the answer
	* @access public
	* @see $gaps
	*/
	function setGapAnswerUpperBound($gap_index, $order, $bound) 
	{
		if (array_key_exists($gap_index, $this->gaps))
		{
			$this->gaps[$gap_index]->setItemUpperBound($order, $bound);
		}
	}

	/**
	* Returns the maximum points, a learner can reach answering the question
	*
	* @access public
	* @see $points
	*/
	function getMaximumPoints() 
	{
		$points = 0;
		foreach ($this->gaps as $gap_index => $gap) 
		{
			if ($gap->getType() == CLOZE_TEXT) 
			{
				$gap_max_points = 0;
				foreach ($gap->getItems() as $item) 
				{
					if ($item->getPoints() > $gap_max_points)
					{
						$gap_max_points = $item->getPoints();
					}
				}
				$points += $gap_max_points;
			} 
			else if ($gap->getType() == CLOZE_SELECT)
			{
				$srpoints = 0;
				foreach ($gap->getItems() as $item) 
				{
					if ($item->getPoints() > $srpoints)
					{
						$srpoints = $item->getPoints();
					}
				}
				$points += $srpoints;
			}
			else if ($gap->getType() == CLOZE_NUMERIC)
			{
				$numpoints = 0;
				foreach ($gap->getItems() as $item)
				{
					if ($item->getPoints() > $numpoints)
					{
						$numpoints = $item->getPoints();
					}
				}
				$points += $numpoints;
			}
		}
		return $points;
	}

	/**
	* Duplicates an assClozeTest
	*
	* @access public
	*/
	function duplicate($for_test = true, $title = "", $author = "", $owner = "", $testObjId = null)
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

		return $clone->getId();
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

		$result = $ilDB->queryF("SELECT * FROM qpl_fb_cloze WHERE question_fi = %s",
								array('integer'),
								array($original_id)
		);
		if ($result->numRows())
		{
			while ($row = $ilDB->fetchAssoc($result))
			{
				$next_id = $ilDB->nextId('qpl_fb_cloze');
				$affectedRows = $ilDB->manipulateF("INSERT INTO qpl_fb_cloze (feedback_id, question_fi, answer, feedback, tstamp) VALUES (%s, %s, %s, %s, %s)",
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
	
	/**
	* Copies an assClozeTest object
	*
	* @access public
	*/
	function copyObject($target_questionpool, $title = "")
	{
		if ($this->getId() <= 0)
		{
			// The question has not been saved. It cannot be duplicated
			return;
		}
		$clone = $this;
		include_once ("./Modules/TestQuestionPool/classes/class.assQuestion.php");
		$original_id = assQuestion::_getOriginalId($this->getId());
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
		// duplicate specific feedback
		$clone->duplicateSpecificFeedback($original_id);

		$clone->onCopy($this->getObjId(), $this->getId());
		return $clone->getId();
	}

	/**
	* Updates the gap parameters in the cloze text from the form input
	*
	* @access private
	*/
	function updateClozeTextFromGaps()
	{
		$output = $this->getClozeText();
		foreach ($this->getGaps() as $gap_index => $gap)
		{
			$answers = array();
			foreach ($gap->getItemsRaw() as $item)
			{
				array_push($answers, str_replace(",", "\\,", $item->getAnswerText()));
			}
			$output = preg_replace("/\[gap\].*?\[\/gap\]/", "[_gap]" . $this->prepareTextareaOutput(join(",", $answers), true) . "[/_gap]", $output, 1);
		}
		$output = str_replace("_gap]", "gap]", $output);
		$this->question = $output;
	}
	
	/**
	* Deletes the answer text of a gap with a given index and an answer with a given order. The index of the first
	* gap is 0, the index of the second gap is 1 and so on.
	*
	* @param integer $gap_index A nonnegative index of the n-th gap
	* @param integer $answer_index The order of the answer text
	* @access public
	* @see $gaps
	*/
	function deleteAnswerText($gap_index, $answer_index) 
	{
		if (array_key_exists($gap_index, $this->gaps))
		{
			if ($this->gaps[$gap_index]->getItemCount() == 1)
			{
				// this is the last answer text => remove the gap
				$this->deleteGap($gap_index);
			}
			else
			{
				// remove the answer text
				$this->gaps[$gap_index]->deleteItem($answer_index);
				$this->updateClozeTextFromGaps();
			}
		}
	}

	/**
	* Deletes a gap with a given index. The index of the first
	* gap is 0, the index of the second gap is 1 and so on.
	*
	* @param integer $gap_index A nonnegative index of the n-th gap
	* @access public
	* @see $gaps
	*/
	function deleteGap($gap_index) 
	{
		if (array_key_exists($gap_index, $this->gaps))
		{
			$output = $this->getClozeText();
			foreach ($this->getGaps() as $replace_gap_index => $gap)
			{
				$answers = array();
				foreach ($gap->getItemsRaw() as $item)
				{
					array_push($answers, str_replace(",", "\\,", $item->getAnswerText()));
				}
				if ($replace_gap_index == $gap_index)
				{
					$output = preg_replace("/\[gap\].*?\[\/gap\]/", "", $output, 1);
				}
				else
				{
					$output = preg_replace("/\[gap\].*?\[\/gap\]/", "[_gap]" . join(",", $answers) . "[/_gap]", $output, 1);
				}
			}
			$output = str_replace("_gap]", "gap]", $output);
			$this->question = $output;
			unset($this->gaps[$gap_index]);
			$this->gaps = array_values($this->gaps);
		}
	}

	/**
	* Returns the points for a text gap and compares the given solution with
	* the entered solution using the text gap rating options.
	*
	* @param string $a_original The original (correct) text
	* @param string $a_entered The text entered by the user
	* @param integer $max_points The maximum number of points for the solution
	* @access public
	*/
	function getTextgapPoints($a_original, $a_entered, $max_points)
	{
		include_once "./Services/Utilities/classes/class.ilStr.php";
		$result = 0;
		$gaprating = $this->getTextgapRating();
		switch ($gaprating)
		{
			case TEXTGAP_RATING_CASEINSENSITIVE:
				if (strcmp(ilStr::strToLower($a_original), ilStr::strToLower($a_entered)) == 0) $result = $max_points;
				break;
			case TEXTGAP_RATING_CASESENSITIVE:
				if (strcmp($a_original, $a_entered) == 0) $result = $max_points;
				break;
			case TEXTGAP_RATING_LEVENSHTEIN1:
				if (levenshtein($a_original, $a_entered) <= 1) $result = $max_points;
				break;
			case TEXTGAP_RATING_LEVENSHTEIN2:
				if (levenshtein($a_original, $a_entered) <= 2) $result = $max_points;
				break;
			case TEXTGAP_RATING_LEVENSHTEIN3:
				if (levenshtein($a_original, $a_entered) <= 3) $result = $max_points;
				break;
			case TEXTGAP_RATING_LEVENSHTEIN4:
				if (levenshtein($a_original, $a_entered) <= 4) $result = $max_points;
				break;
			case TEXTGAP_RATING_LEVENSHTEIN5:
				if (levenshtein($a_original, $a_entered) <= 5) $result = $max_points;
				break;
		}
		return $result;
	}
	
	/**
	* Returns the points for a text gap and compares the given solution with
	* the entered solution using the text gap rating options.
	*
	* @param string $a_original The original (correct) text
	* @param string $a_entered The text entered by the user
	* @param integer $max_points The maximum number of points for the solution
	* @access public
	*/
	function getNumericgapPoints($a_original, $a_entered, $max_points, $lowerBound, $upperBound)
	{
		if( !is_numeric($a_entered) )
		{
			return 0;
		}
		
		include_once "./Services/Math/classes/class.EvalMath.php";
		$eval = new EvalMath();
		$eval->suppress_errors = TRUE;
		$result = 0;
		if (($eval->e($lowerBound) !== FALSE) && ($eval->e($upperBound) !== FALSE))
		{
			if (($eval->e($a_entered) >= $eval->e($lowerBound)) && ($eval->e($a_entered) <= $eval->e($upperBound))) $result = $max_points;
		}
		else if ($eval->e($lowerBound) !== FALSE)
		{
			if (($eval->e($a_entered) >= $eval->e($lowerBound)) && ($eval->e($a_entered) <= $eval->e($a_original))) $result = $max_points;
		}
		else if ($eval->e($upperBound) !== FALSE)
		{
			if (($eval->e($a_entered) >= $eval->e($a_original)) && ($eval->e($a_entered) <= $eval->e($upperBound))) $result = $max_points;
		}
		else
		{
			if ($eval->e($a_entered) == $eval->e($a_original)) $result = $max_points;
		}
		return $result;
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
		global $ilDB;
		
		$found_value1 = array();
		$found_value2 = array();
		$detailed = array();
		if (is_null($pass))
		{
			$pass = $this->getSolutionMaxPass($active_id);
		}
		$result = $ilDB->queryF("SELECT * FROM tst_solutions WHERE active_fi = %s AND question_fi = %s AND pass = %s",
			array(
				"integer", 
				"integer",
				"integer"
			),
			array(
				$active_id,
				$this->getId(),
				$pass
			)
		);
		$user_result = array();
		while ($data = $ilDB->fetchAssoc($result)) 
		{
			if (strcmp($data["value2"], "") != 0)
			{
				$user_result[$data["value1"]] = array(
					"gap_id" => $data["value1"],
					"value" => $data["value2"]
				);
			}
		}
		$points = 0;
		$counter = 0;
		$solution_values_text = array(); // for identical scoring checks
		$solution_values_select = array(); // for identical scoring checks
		$solution_values_numeric = array(); // for identical scoring checks
		foreach ($user_result as $gap_id => $value) 
		{
			if (array_key_exists($gap_id, $this->gaps))
			{
				switch ($this->gaps[$gap_id]->getType())
				{
					case CLOZE_TEXT:
						$gappoints = 0;
						for ($order = 0; $order < $this->gaps[$gap_id]->getItemCount(); $order++) 
						{
							$answer = $this->gaps[$gap_id]->getItem($order);
							$gotpoints = $this->getTextgapPoints($answer->getAnswertext(), $value["value"], $answer->getPoints());
							if ($gotpoints > $gappoints) $gappoints = $gotpoints;
						}
						if (!$this->getIdenticalScoring())
						{
							// check if the same solution text was already entered
							if ((in_array($value["value"], $solution_values_text)) && ($gappoints > 0))
							{
								$gappoints = 0;
							}
						}
						$points += $gappoints;
						$detailed[$gap_id] = array("points" =>$gappoints, "best" => ($this->getMaximumGapPoints($gap_id) == $gappoints) ? TRUE : FALSE, "positive" => ($gappoints > 0) ? TRUE : FALSE);
						array_push($solution_values_text, $value["value"]);
						break;
					case CLOZE_NUMERIC:
						$gappoints = 0;
						for ($order = 0; $order < $this->gaps[$gap_id]->getItemCount(); $order++) 
						{
							$answer = $this->gaps[$gap_id]->getItem($order);
							$gotpoints = $this->getNumericgapPoints($answer->getAnswertext(), $value["value"], $answer->getPoints(), $answer->getLowerBound(), $answer->getUpperBound());
							if ($gotpoints > $gappoints) $gappoints = $gotpoints;
						}
						if (!$this->getIdenticalScoring())
						{
							// check if the same solution value was already entered
							include_once "./Services/Math/classes/class.EvalMath.php";
							$eval = new EvalMath();
							$eval->suppress_errors = TRUE;
							$found_value = FALSE;
							foreach ($solution_values_numeric as $solval)
							{
								if ($eval->e($solval) == $eval->e($value["value"]))
								{
									$found_value = TRUE;
								}
							}
							if ($found_value && ($gappoints > 0))
							{
								$gappoints = 0;
							}
						}
						$points += $gappoints;
						$detailed[$gap_id] = array("points" =>$gappoints, "best" => ($this->getMaximumGapPoints($gap_id) == $gappoints) ? TRUE : FALSE, "positive" => ($gappoints > 0) ? TRUE : FALSE);
						array_push($solution_values_numeric, $value["value"]);
						break;
					case CLOZE_SELECT:
						if ($value["value"] >= 0)
						{
							for ($order = 0; $order < $this->gaps[$gap_id]->getItemCount(); $order++) 
							{
								$answer = $this->gaps[$gap_id]->getItem($order);
								if ($value["value"] == $answer->getOrder())
								{
									$answerpoints = $answer->getPoints();
									if (!$this->getIdenticalScoring())
									{
										// check if the same solution value was already entered
										if ((in_array($answer->getAnswertext(), $solution_values_select)) && ($answerpoints > 0))
										{
											$answerpoints = 0;
										}
									}
									$points += $answerpoints;
									$detailed[$gap_id] = array("points" =>$answerpoints, "best" => ($this->getMaximumGapPoints($gap_id) == $answerpoints) ? TRUE : FALSE, "positive" => ($answerpoints > 0) ? TRUE : FALSE);
									array_push($solution_values_select, $answer->getAnswertext());
								}
							}
						}
						break;
				}
			}
		}
		if ($returndetails)
		{
			return $detailed;
		}
		else
		{
			return $points;
		}
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
			array(
				"integer", 
				"integer",
				"integer"
			),
			array(
				$active_id,
				$this->getId(),
				$pass
			)
		);

		$entered_values = 0;
		foreach ($_POST as $key => $value) 
		{
			if (preg_match("/^gap_(\d+)/", $key, $matches)) 
			{ 
				$value = ilUtil::stripSlashes($value, FALSE);
				if (strlen($value))
				{
					$gap = $this->getGap($matches[1]);
					if (is_object($gap))
					{
						if (!(($gap->getType() == CLOZE_SELECT) && ($value == -1)))
						{
							if ($gap->getType() == CLOZE_NUMERIC)
							{
								$value = str_replace(",", ".", $value);
							}
							$next_id = $ilDB->nextId("tst_solutions");
							$affectedRows = $ilDB->insert("tst_solutions", array(
								"solution_id" => array("integer", $next_id),
								"active_fi" => array("integer", $active_id),
								"question_fi" => array("integer", $this->getId()),
								"value1" => array("clob", trim($matches[1])),
								"value2" => array("clob", trim($value)),
								"pass" => array("integer", $pass),
								"tstamp" => array("integer", time())
							));
							$entered_values++;
						}
					}
				}
			}
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
		
		return TRUE;
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
	* @access public
	*/
	function getQuestionType()
	{
		return "assClozeTest";
	}

	/**
	* Returns the rating option for text gaps
	*
	* @return string The rating option for text gaps
	* @see $textgap_rating
	* @access public
	*/
	function getTextgapRating()
	{
		return $this->textgap_rating;
	}

	/**
	* Sets the rating option for text gaps
	*
	* @param string $a_textgap_rating The rating option for text gaps
	* @see $textgap_rating
	* @access public
	*/
	function setTextgapRating($a_textgap_rating)
	{
		switch ($a_textgap_rating)
		{
			case TEXTGAP_RATING_CASEINSENSITIVE:
			case TEXTGAP_RATING_CASESENSITIVE:
			case TEXTGAP_RATING_LEVENSHTEIN1:
			case TEXTGAP_RATING_LEVENSHTEIN2:
			case TEXTGAP_RATING_LEVENSHTEIN3:
			case TEXTGAP_RATING_LEVENSHTEIN4:
			case TEXTGAP_RATING_LEVENSHTEIN5:
				$this->textgap_rating = $a_textgap_rating;
				break;
			default:
				$this->textgap_rating = TEXTGAP_RATING_CASEINSENSITIVE;
				break;
		}
	}

	/**
	* Returns the identical scoring status of the question
	*
	* @return boolean The identical scoring status
	* @see $identical_scoring
	* @access public
	*/
	function getIdenticalScoring()
	{
		return ($this->identical_scoring) ? 1 : 0;
	}

	/**
	* Sets the identical scoring option for cloze questions
	*
	* @param boolean $a_identical_scoring The identical scoring option for cloze questions
	* @see $identical_scoring
	* @access public
	*/
	function setIdenticalScoring($a_identical_scoring)
	{
		$this->identical_scoring = ($a_identical_scoring) ? 1 : 0;
	}

	/**
	* Returns the name of the additional question data table in the database
	*
	* @return string The additional table name
	* @access public
	*/
	function getAdditionalTableName()
	{
		return "qpl_qst_cloze";
	}

	/**
	* Returns the name of the answer table in the database
	*
	* @return string The answer table name
	* @access public
	*/
	function getAnswerTableName()
	{
		return "qpl_a_cloze";
	}
	
	/**
	* Sets a fixed text length for all text fields in the cloze question
	*
	* @param integer $a_text_len The text field length
	* @access public
	*/
	function setFixedTextLength($a_text_len)
	{
		$this->fixedTextLength = $a_text_len;
	}
	
	/**
	* Gets the fixed text length for all text fields in the cloze question
	*
	* @return integer The text field length
	* @access public
	*/
	function getFixedTextLength()
	{
		return $this->fixedTextLength;
	}

	/**
	* Returns the maximum points for a gap
	*
	* @param integer $gap_index The index of the gap
	* @return double The maximum points for the gap
	* @access public
	* @see $points
	*/
	function getMaximumGapPoints($gap_index) 
	{
		$points = 0;
		if (array_key_exists($gap_index, $this->gaps))
		{
			$gap =& $this->gaps[$gap_index];
			foreach ($gap->getItems() as $answer) 
			{
				if ($answer->getPoints() > $gap_max_points)
				{
					$gap_max_points = $answer->getPoints();
				}
			}
			$points += $gap_max_points;
		}
		return $points;
	}

	/**
	* Collects all text in the question which could contain media objects
	* which were created with the Rich Text Editor
	*/
	function getRTETextWithMediaObjects()
	{
		return parent::getRTETextWithMediaObjects();
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
	* @access public
	*/
	public function setExportDetailsXLS(&$worksheet, $startrow, $active_id, $pass, &$format_title, &$format_bold)
	{
		include_once ("./Services/Excel/classes/class.ilExcelUtils.php");
		$solution = $this->getSolutionValues($active_id, $pass);
		$worksheet->writeString($startrow, 0, ilExcelUtils::_convert_text($this->lng->txt($this->getQuestionType())), $format_title);
		$worksheet->writeString($startrow, 1, ilExcelUtils::_convert_text($this->getTitle()), $format_title);
		$i = 1;
		foreach ($this->getGaps() as $gap_index => $gap)
		{
			$worksheet->writeString($startrow + $i, 0, ilExcelUtils::_convert_text($this->lng->txt("gap") . " $i"), $format_bold);
			$checked = FALSE;
			foreach ($solution as $solutionvalue)
			{
				if ($gap_index == $solutionvalue["value1"])
				{
					switch ($gap->getType())
					{
						case CLOZE_SELECT:
							$worksheet->writeString($startrow + $i, 1, $gap->getItem($solutionvalue["value2"])->getAnswertext());
							break;
						case CLOZE_NUMERIC:
						case CLOZE_TEXT:
							$worksheet->writeString($startrow + $i, 1, $solutionvalue["value2"]);
							break;
					}
				}
			}
			$i++;
		}
		return $startrow + $i + 1;
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
		$result['shuffle'] = (bool) $this->getShuffle();
		$result['feedback'] = array(
			"onenotcorrect" => nl2br(ilRTE::_replaceMediaObjectImageSrc($this->getFeedbackGeneric(0), 0)),
			"allcorrect" => nl2br(ilRTE::_replaceMediaObjectImageSrc($this->getFeedbackGeneric(1), 0))
			);
		$gaps = array();
		foreach ($this->getGaps() as $key => $gap)
		{
			$items = array();
			foreach ($gap->getItems() as $item)
			{
				$jitem = array();
				$jitem['points'] = $item->getPoints();
				$jitem['value'] = $item->getAnswertext();
				$jitem['order'] = $item->getOrder();
				if ($gap->getType() == CLOZE_NUMERIC)
				{
					$jitem['lowerbound'] = $item->getLowerBound();
					$jitem['upperbound'] = $item->getUpperBound();
				}
				array_push($items, $jitem);
			}
			$jgap['shuffle'] = $gap->getShuffle();
			$jgap['type'] = $gap->getType();
			$jgap['item'] = $items;
			array_push($gaps, $jgap);
			
		}
		$result['gaps'] = $gaps;
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
		
		$affectedRows = $ilDB->manipulateF("DELETE FROM qpl_fb_cloze WHERE question_fi = %s AND answer = %s",
			array('integer','integer'),
			array($this->getId(), $answer_index)
		);
		if (strlen($feedback))
		{
			include_once("./Services/RTE/classes/class.ilRTE.php");
			$next_id = $ilDB->nextId('qpl_fb_cloze');
			$affectedRows = $ilDB->manipulateF("INSERT INTO qpl_fb_cloze (feedback_id, question_fi, answer, feedback, tstamp) VALUES (%s, %s, %s, %s, %s)",
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
		$result = $ilDB->queryF("SELECT * FROM qpl_fb_cloze WHERE question_fi = %s AND answer = %s",
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

	protected function deleteFeedbackSpecific($question_id)
	{
		global $ilDB;
		$ilDB->manipulateF(
			'DELETE 
			FROM qpl_fb_cloze 
			WHERE question_fi = %s',
			array('integer'),
			array($question_id)
		);
	}
}