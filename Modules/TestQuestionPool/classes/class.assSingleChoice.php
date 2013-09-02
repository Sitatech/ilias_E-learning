<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
include_once "./Modules/Test/classes/inc.AssessmentConstants.php";

/**
 * Class for single choice questions
 *
 * assSingleChoice is a class for single choice questions.
 *
 * @extends assQuestion
 * 
 * @author		Helmut Schottmüller <helmut.schottmueller@mac.com> 
 * @author		Björn Heyser <bheyser@databay.de>
 * @version		$Id: class.assSingleChoice.php 38336 2012-11-22 13:30:20Z bheyser $
 * 
 * @ingroup		ModulesTestQuestionPool
 */
class assSingleChoice extends assQuestion
{
	/**
	* The given answers of the single choice question
	*
	* $answers is an array of the given answers of the single choice question
	*
	* @var array
	*/
	var $answers;

	/**
	* Output type
	*
	* This is the output type for the answers of the single choice question. You can select
	* OUTPUT_ORDER(=0) or OUTPUT_RANDOM (=1). The default output type is OUTPUT_ORDER
	*
	* @var integer
	*/
	var $output_type;

	/**
	* Thumbnail size
	*
	* @var integer
	*/
	protected $thumb_size;

	/**
	* assSingleChoice constructor
	*
	* The constructor takes possible arguments an creates an instance of the assSingleChoice object.
	*
	* @param string $title A title string to describe the question
	* @param string $comment A comment string to describe the question
	* @param string $author A string containing the name of the questions author
	* @param integer $owner A numerical ID to identify the owner/creator
	* @param string $question The question string of the single choice question
	* @param integer $output_type The output order of the single choice answers
	* @access public
	* @see assQuestion:assQuestion()
	*/
	function __construct(
		$title = "",
		$comment = "",
		$author = "",
		$owner = -1,
		$question = "",
		$output_type = OUTPUT_ORDER
	)
	{
		parent::__construct($title, $comment, $author, $owner, $question);
		$this->thumb_size = 150;
		$this->output_type = $output_type;
		$this->answers = array();
		$this->shuffle = 1;
	}

	/**
	* Returns true, if a single choice question is complete for use
	*
	* @return boolean True, if the single choice question is complete for use, otherwise false
	* @access public
	*/
	function isComplete()
	{
		if (strlen($this->title) and ($this->author) and ($this->question) and (count($this->answers)) and ($this->getMaximumPoints() > 0))
		{
			foreach ($this->answers as $answer)
			{
				if ((strlen($answer->getAnswertext()) == 0) && (strlen($answer->getImage()) == 0)) return false;
			}
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* Saves a assSingleChoice object to a database
	*
	* @param object $db A pear DB object
	* @access public
	*/
	function saveToDb($original_id = "")
	{
		global $ilDB;

		$this->saveQuestionDataToDb($original_id);

		$oldthumbsize = 0;
		if ($this->isSingleline && ($this->getThumbSize()))
		{
			// get old thumbnail size
			$result = $ilDB->queryF("SELECT thumb_size FROM " . $this->getAdditionalTableName() . " WHERE question_fi = %s",
				array("integer"),
				array($this->getId())
			);
			if ($result->numRows() == 1)
			{
				$data = $ilDB->fetchAssoc($result);
				$oldthumbsize = $data['thumb_size'];
			}
		}
		if (!$this->isSingleline)
		{
			ilUtil::delDir($this->getImagePath());
		}

		// save additional data
		$affectedRows = $ilDB->manipulateF("DELETE FROM " . $this->getAdditionalTableName() . " WHERE question_fi = %s", 
			array("integer"),
			array($this->getId())
		);

		$affectedRows = $ilDB->manipulateF("INSERT INTO " . $this->getAdditionalTableName() . " (question_fi, shuffle, allow_images, thumb_size) VALUES (%s, %s, %s, %s)", 
			array("integer", "text", "text", "integer"),
			array(
				$this->getId(),
				$this->getShuffle(),
				($this->isSingleline) ? "0" : "1",
				(strlen($this->getThumbSize()) == 0) ? null : $this->getThumbSize()
			)
		);

		$affectedRows = $ilDB->manipulateF("DELETE FROM qpl_a_sc WHERE question_fi = %s",
			array('integer'),
			array($this->getId())
		);

		foreach ($this->answers as $key => $value)
		{
			$answer_obj = $this->answers[$key];
			$next_id = $ilDB->nextId('qpl_a_sc');
			$affectedRows = $ilDB->manipulateF("INSERT INTO qpl_a_sc (answer_id, question_fi, answertext, points, aorder, imagefile, tstamp) VALUES (%s, %s, %s, %s, %s, %s, %s)",
				array('integer','integer','text','float','integer','text','integer'),
				array(
					$next_id,
					$this->getId(),
					ilRTE::_replaceMediaObjectImageSrc($answer_obj->getAnswertext(), 0),
					$answer_obj->getPoints(),
					$answer_obj->getOrder(),
					$answer_obj->getImage(),
					time()
				)
			);
		}

		$this->rebuildThumbnails();
		
		parent::saveToDb($original_id);
	}
	
	/*
	* Rebuild the thumbnail images with a new thumbnail size
	*/
	protected function rebuildThumbnails()
	{
		if ($this->isSingleline && ($this->getThumbSize()))
		{
			foreach ($this->getAnswers() as $answer)
			{
				if (strlen($answer->getImage()))
				{
					$this->generateThumbForFile($this->getImagePath(), $answer->getImage());
				}
			}
		}
	}
	
	public function getThumbPrefix()
	{
		return "thumb.";
	}
	
	protected function generateThumbForFile($path, $file)
	{
		$filename = $path . $file;
		if (@file_exists($filename))
		{
			$thumbpath = $path . $this->getThumbPrefix() . $file;
			$path_info = @pathinfo($filename);
			$ext = "";
			switch (strtoupper($path_info['extension']))
			{
				case 'PNG':
					$ext = 'PNG';
					break;
				case 'GIF':
					$ext = 'GIF';
					break;
				default:
					$ext = 'JPEG';
					break;
			}
			ilUtil::convertImage($filename, $thumbpath, $ext, $this->getThumbSize());
		}
	}

	/**
	* Loads a assSingleChoice object from a database
	*
	* @param object $db A pear DB object
	* @param integer $question_id A unique key which defines the multiple choice test in the database
	* @access public
	*/
	function loadFromDb($question_id)
	{
		global $ilDB;

		$hasimages = 0;

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
			$this->setNrOfTries($data['nr_of_tries']);
			$this->setComment($data["description"]);
			$this->setOriginalId($data["original_id"]);
			$this->setAuthor($data["author"]);
			$this->setPoints($data["points"]);
			$this->setOwner($data["owner"]);
			include_once("./Services/RTE/classes/class.ilRTE.php");
			$this->setQuestion(ilRTE::_replaceMediaObjectImageSrc($data["question_text"], 1));
			$shuffle = (is_null($data['shuffle'])) ? true : $data['shuffle'];
			$this->setShuffle($shuffle);
			$this->setEstimatedWorkingTime(substr($data["working_time"], 0, 2), substr($data["working_time"], 3, 2), substr($data["working_time"], 6, 2));
			$this->setThumbSize($data['thumb_size']);
			$this->isSingleline = ($data['allow_images']) ? false : true;
			$this->lastChange = $data['tstamp'];
		}

		$result = $ilDB->queryF("SELECT * FROM qpl_a_sc WHERE question_fi = %s ORDER BY aorder ASC",
			array('integer'),
			array($question_id)
		);
		include_once "./Modules/TestQuestionPool/classes/class.assAnswerBinaryStateImage.php";
		if ($result->numRows() > 0)
		{
			while ($data = $ilDB->fetchAssoc($result))
			{
				$imagefilename = $this->getImagePath() . $data["imagefile"];
				if (!@file_exists($imagefilename))
				{
					$data["imagefile"] = "";
				}
				include_once("./Services/RTE/classes/class.ilRTE.php");
				$data["answertext"] = ilRTE::_replaceMediaObjectImageSrc($data["answertext"], 1);
				array_push($this->answers, new ASS_AnswerBinaryStateImage($data["answertext"], $data["points"], $data["aorder"], 1, $data["imagefile"]));
			}
		}

		parent::loadFromDb($question_id);
	}

	/**
	* Duplicates an assSingleChoiceQuestion
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
		// duplicate the images
		$clone->duplicateImages($this_id, $thisObjId);
		// duplicate the generic feedback
		$clone->duplicateGenericFeedback($this_id);
		// duplicate the answer specific feedback
		$clone->duplicateFeedbackAnswer($this_id);
		$clone->onDuplicate($this_id);

		return $clone->id;
	}

	/**
	* Copies an assSingleChoice object
	*
	* @access public
	*/
	function copyObject($target_questionpool, $title = "")
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
		// duplicate the image
		$clone->copyImages($original_id, $source_questionpool);
		// duplicate the generic feedback
		$clone->duplicateGenericFeedback($original_id);
		// duplicate the answer specific feedback
		$clone->duplicateFeedbackAnswer($original_id);
		$clone->onCopy($this->getObjId(), $this->getId());

		return $clone->id;
	}

	/**
	* Gets the single choice output type which is either OUTPUT_ORDER (=0) or OUTPUT_RANDOM (=1).
	*
	* @return integer The output type of the assSingleChoice object
	* @access public
	* @see $output_type
	*/
	function getOutputType()
	{
		return $this->output_type;
	}

	/**
	* Sets the output type of the assSingleChoice object
	*
	* @param integer $output_type A nonnegative integer value specifying the output type. It is OUTPUT_ORDER (=0) or OUTPUT_RANDOM (=1).
	* @access public
	* @see $response
	*/
	function setOutputType($output_type = OUTPUT_ORDER)
	{
		$this->output_type = $output_type;
	}

	/**
	* Adds a possible answer for a single choice question. A ASS_AnswerBinaryStateImage object will be
	* created and assigned to the array $this->answers.
	*
	* @param string $answertext The answer text
	* @param double $points The points for selecting the answer (even negative points can be used)
	* @param boolean $state Defines the answer as correct (TRUE) or incorrect (FALSE)
	* @param integer $order A possible display order of the answer
	* @param double $points The points for not selecting the answer (even negative points can be used)
	* @access public
	* @see $answers
	* @see ASS_AnswerBinaryStateImage
	*/
	function addAnswer(
		$answertext = "",
		$points = 0.0,
		$order = 0,
		$answerimage = ""
	)
	{
		include_once "./Modules/TestQuestionPool/classes/class.assAnswerBinaryStateImage.php";
		if (array_key_exists($order, $this->answers))
		{
			// insert answer
			$answer = new ASS_AnswerBinaryStateImage($answertext, $points, $order, 1, $answerimage);
			$newchoices = array();
			for ($i = 0; $i < $order; $i++)
			{
				array_push($newchoices, $this->answers[$i]);
			}
			array_push($newchoices, $answer);
			for ($i = $order; $i < count($this->answers); $i++)
			{
				$changed = $this->answers[$i];
				$changed->setOrder($i+1);
				array_push($newchoices, $changed);
			}
			$this->answers = $newchoices;
		}
		else
		{
			// add answer
			$answer = new ASS_AnswerBinaryStateImage($answertext, $points, count($this->answers), 1, $answerimage);
			array_push($this->answers, $answer);
		}
	}

	/**
	* Returns the number of answers
	*
	* @return integer The number of answers of the multiple choice question
	* @access public
	* @see $answers
	*/
	function getAnswerCount()
	{
		return count($this->answers);
	}

	/**
	* Returns an answer with a given index. The index of the first
	* answer is 0, the index of the second answer is 1 and so on.
	*
	* @param integer $index A nonnegative index of the n-th answer
	* @return object ASS_AnswerBinaryStateImage-Object containing the answer
	* @access public
	* @see $answers
	*/
	function getAnswer($index = 0)
	{
		if ($index < 0) return NULL;
		if (count($this->answers) < 1) return NULL;
		if ($index >= count($this->answers)) return NULL;

		return $this->answers[$index];
	}

	/**
	* Deletes an answer with a given index. The index of the first
	* answer is 0, the index of the second answer is 1 and so on.
	*
	* @param integer $index A nonnegative index of the n-th answer
	* @access public
	* @see $answers
	*/
	function deleteAnswer($index = 0)
	{
		if ($index < 0) return;
		if (count($this->answers) < 1) return;
		if ($index >= count($this->answers)) return;
		$answer = $this->answers[$index];
		if (strlen($answer->getImage())) $this->deleteImage($answer->getImage());
		unset($this->answers[$index]);
		$this->answers = array_values($this->answers);
		for ($i = 0; $i < count($this->answers); $i++)
		{
			if ($this->answers[$i]->getOrder() > $index)
			{
				$this->answers[$i]->setOrder($i);
			}
		}
	}

	/**
	* Deletes all answers
	*
	* @access public
	* @see $answers
	*/
	function flushAnswers()
	{
		$this->answers = array();
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
		foreach ($this->answers as $key => $value) 
		{
			if ($value->getPoints() > $points)
			{
				$points = $value->getPoints();
			}
		}
		return $points;
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
		while ($data = $ilDB->fetchAssoc($result))
		{
			if (strcmp($data["value1"], "") != 0)
			{
				array_push($found_values, $data["value1"]);
			}
		}
		$points = 0;
		foreach ($this->answers as $key => $answer)
		{
			if (count($found_values) > 0) 
			{
				if (in_array($key, $found_values))
				{
					$points += $answer->getPoints();
				}
			}
		}

		return $points;
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
		$entered_values = 0;

		$result = $ilDB->queryF("SELECT solution_id FROM tst_solutions WHERE active_fi = %s AND question_fi = %s AND pass = %s",
			array('integer','integer','integer'),
			array($active_id, $this->getId(), $pass)
		);
		$row = $ilDB->fetchAssoc($result);
		$update = $row["solution_id"];
		
		if ($update)
		{
			if (strlen($_POST["multiple_choice_result"]))
			{
				$affectedRows = $ilDB->update("tst_solutions", array(
					"value1" => array("clob", $_POST["multiple_choice_result"]),
					"tstamp" => array("integer", time())
				), array(
				"solution_id" => array("integer", $update)
				));
				$entered_values++;
			}
			else
			{
				$affectedRows = $ilDB->manipulateF("DELETE FROM tst_solutions WHERE solution_id = %s",
					array('integer'),
					array($update)
				);
			}
		}
		else
		{
			if (strlen($_POST["multiple_choice_result"]))
			{
				$next_id = $ilDB->nextId('tst_solutions');
				$affectedRows = $ilDB->insert("tst_solutions", array(
					"solution_id" => array("integer", $next_id),
					"active_fi" => array("integer", $active_id),
					"question_fi" => array("integer", $this->getId()),
					"value1" => array("clob", $_POST['multiple_choice_result']),
					"value2" => array("clob", null),
					"pass" => array("integer", $pass),
					"tstamp" => array("integer", time())
				));
				$entered_values++;
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
	* Synchronizes the single answer feedback with an original question
	*
	* @access public
	*/
	function syncFeedbackSingleAnswers()
	{
		global $ilDB;

		$feedback = "";

		// delete generic feedback of the original
		$affectedRows = $ilDB->manipulateF("DELETE FROM qpl_fb_sc WHERE question_fi = %s",
			array('integer'),
			array($this->original_id)
		);
			
		// get generic feedback of the actual question
		$result = $ilDB->queryF("SELECT * FROM qpl_fb_sc WHERE question_fi = %s",
			array('integer'),
			array($this->getId())
		);

		// save generic feedback to the original
		if ($result->numRows())
		{
			while ($row = $ilDB->fetchAssoc($result))
			{
				$next_id = $ilDB->nextId('qpl_fb_sc');
				$affectedRows = $ilDB->manipulateF("INSERT INTO qpl_fb_sc (feedback_id, question_fi, answer, feedback, tstamp) VALUES (%s, %s, %s, %s, %s)",
					array('integer','integer','integer','text','integer'),
					array(
						$next_id,
						$this->original_id,
						$row["answer"],
						$row["feedback"],
						time()
					)
				);
			}
		}
	}

	function syncWithOriginal()
	{
		if ($this->getOriginalId())
		{
			$this->syncFeedbackSingleAnswers();
			$this->syncImages();
			parent::syncWithOriginal();
		}
	}

	/**
	* Returns the question type of the question
	*
	* @return integer The question type of the question
	* @access public
	*/
	function getQuestionType()
	{
		return "assSingleChoice";
	}
	
	/**
	* Returns the name of the additional question data table in the database
	*
	* @return string The additional table name
	* @access public
	*/
	function getAdditionalTableName()
	{
		return "qpl_qst_sc";
	}
	
	/**
	* Returns the name of the answer table in the database
	*
	* @return string The answer table name
	* @access public
	*/
	function getAnswerTableName()
	{
		return "qpl_a_sc";
	}
	
	/**
	* Sets the image file and uploads the image to the object's image directory.
	*
	* @param string $image_filename Name of the original image file
	* @param string $image_tempfilename Name of the temporary uploaded image file
	* @return integer An errorcode if the image upload fails, 0 otherwise
	* @access public
	*/
	function setImageFile($image_filename, $image_tempfilename = "")
	{
		$result = 0;
		if (!empty($image_tempfilename))
		{
			$image_filename = str_replace(" ", "_", $image_filename);
			$imagepath = $this->getImagePath();
			if (!file_exists($imagepath))
			{
				ilUtil::makeDirParents($imagepath);
			}
			//if (!move_uploaded_file($image_tempfilename, $imagepath . $image_filename))
			if (!ilUtil::moveUploadedFile($image_tempfilename, $image_filename, $imagepath.$image_filename))
			{
				$result = 2;
			}
			else
			{
				include_once "./Services/MediaObjects/classes/class.ilObjMediaObject.php";
				$mimetype = ilObjMediaObject::getMimeType($imagepath . $image_filename);
				if (!preg_match("/^image/", $mimetype))
				{
					unlink($imagepath . $image_filename);
					$result = 1;
				}
				else
				{
					// create thumbnail file
					if ($this->isSingleline && ($this->getThumbSize()))
					{
						$this->generateThumbForFile($imagepath, $image_filename);
					}
				}
			}
		}
		return $result;
	}
	
	/**
	* Deletes an image file
	*
	* @param string $image_filename Name of the image file to delete
	* @access private
	*/
	function deleteImage($image_filename)
	{
		$imagepath = $this->getImagePath();
		@unlink($imagepath . $image_filename);
		$thumbpath = $imagepath . $this->getThumbPrefix() . $image_filename;
		@unlink($thumbpath);
	}

	function duplicateImages($question_id, $objectId = null)
	{
		global $ilLog;
		$imagepath = $this->getImagePath();
		$imagepath_original = str_replace("/$this->id/images", "/$question_id/images", $imagepath);
		
		if( (int)$objectId > 0 )
		{
			$imagepath_original = str_replace("/$this->obj_id/", "/$objectId/", $imagepath_original);
		}
		
		foreach ($this->answers as $answer)
		{
			$filename = $answer->getImage();
			if (strlen($filename))
			{
				if (!file_exists($imagepath))
				{
					ilUtil::makeDirParents($imagepath);
				}
				if (!@copy($imagepath_original . $filename, $imagepath . $filename))
				{
					$ilLog->write("image could not be duplicated!!!!", $ilLog->ERROR);
					$ilLog->write("object: " . print_r($this, TRUE), $ilLog->ERROR);
				}
				if (@file_exists($imagepath_original. $this->getThumbPrefix(). $filename))
				{
					if (!@copy($imagepath_original . $this->getThumbPrefix() . $filename, $imagepath . $this->getThumbPrefix() . $filename))
					{
						$ilLog->write("image thumbnail could not be duplicated!!!!", $ilLog->ERROR);
						$ilLog->write("object: " . print_r($this, TRUE), $ilLog->ERROR);
					}
				}
			}
		}
	}

	function copyImages($question_id, $source_questionpool)
	{
		global $ilLog;
		$imagepath = $this->getImagePath();
		$imagepath_original = str_replace("/$this->id/images", "/$question_id/images", $imagepath);
		$imagepath_original = str_replace("/$this->obj_id/", "/$source_questionpool/", $imagepath_original);
		foreach ($this->answers as $answer)
		{
			$filename = $answer->getImage();
			if (strlen($filename))
			{
				if (!file_exists($imagepath))
				{
					ilUtil::makeDirParents($imagepath);
				}
				if (!@copy($imagepath_original . $filename, $imagepath . $filename))
				{
					$ilLog->write("image could not be duplicated!!!!", $ilLog->ERROR);
					$ilLog->write("object: " . print_r($this, TRUE), $ilLog->ERROR);
				}
				if (@file_exists($imagepath_original. $this->getThumbPrefix(). $filename))
				{
					if (!@copy($imagepath_original . $this->getThumbPrefix() . $filename, $imagepath . $this->getThumbPrefix() . $filename))
					{
						$ilLog->write("image thumbnail could not be duplicated!!!!", $ilLog->ERROR);
						$ilLog->write("object: " . print_r($this, TRUE), $ilLog->ERROR);
					}
				}
			}
		}
	}
	
	/**
	* Sync images of a MC question on synchronisation with the original question
	**/
	protected function syncImages()
	{
		global $ilLog;
		$question_id = $this->getOriginalId();
		$imagepath = $this->getImagePath();
		$imagepath_original = str_replace("/$this->id/images", "/$question_id/images", $imagepath);
		ilUtil::delDir($imagepath_original);
		foreach ($this->answers as $answer)
		{
			$filename = $answer->getImage();
			if (strlen($filename))
			{
				if (@file_exists($imagepath . $filename))
				{
					if (!file_exists($imagepath))
					{
						ilUtil::makeDirParents($imagepath);
					}
					if (!file_exists($imagepath_original))
					{
						ilUtil::makeDirParents($imagepath_original);
					}
					if (!@copy($imagepath . $filename, $imagepath_original . $filename))
					{
						$ilLog->write("image could not be duplicated!!!!", $ilLog->ERROR);
						$ilLog->write("object: " . print_r($this, TRUE), $ilLog->ERROR);
					}
				}
				if (@file_exists($imagepath . $this->getThumbPrefix() . $filename))
				{
					if (!@copy($imagepath . $this->getThumbPrefix() . $filename, $imagepath_original . $this->getThumbPrefix() . $filename))
					{
						$ilLog->write("image thumbnail could not be duplicated!!!!", $ilLog->ERROR);
						$ilLog->write("object: " . print_r($this, TRUE), $ilLog->ERROR);
					}
				}
			}
		}
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

		$affectedRows = $ilDB->manipulateF("DELETE FROM qpl_fb_sc WHERE question_fi = %s AND answer = %s",
			array('integer','integer'),
			array($this->getId(), $answer_index)
		);
		if (strlen($feedback))
		{
			include_once("./Services/RTE/classes/class.ilRTE.php");
			$next_id = $ilDB->nextId('qpl_fb_sc');
			$affectedRows = $ilDB->manipulateF("INSERT INTO qpl_fb_sc (feedback_id, question_fi, answer, feedback, tstamp) VALUES (%s, %s, %s, %s, %s)",
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
		$result = $ilDB->queryF("SELECT * FROM qpl_fb_sc WHERE question_fi = %s AND answer = %s",
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
	function duplicateFeedbackAnswer($original_id)
	{
		global $ilDB;

		$feedback = "";
		$result = $ilDB->queryF("SELECT * FROM qpl_fb_sc WHERE question_fi = %s",
			array('integer'),
			array($original_id)
		);
		if ($result->numRows())
		{
			while ($row = $ilDB->fetchAssoc($result))
			{
				$next_id = $ilDB->nextId('qpl_fb_sc');
				$affectedRows = $ilDB->manipulateF("INSERT INTO qpl_fb_sc (feedback_id, question_fi, answer, feedback, tstamp) VALUES (%s, %s, %s, %s, %s)",
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
	* Collects all text in the question which could contain media objects
	* which were created with the Rich Text Editor
	*/
	function getRTETextWithMediaObjects()
	{
		$text = parent::getRTETextWithMediaObjects();
		foreach ($this->answers as $index => $answer)
		{
			$text .= $this->getFeedbackSingleAnswer($index);
			$answer_obj = $this->answers[$index];
			$text .= $answer_obj->getAnswertext();
		}
		return $text;
	}

	/**
	* Returns a reference to the answers array
	*/
	function &getAnswers()
	{
		return $this->answers;
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
		foreach ($this->getAnswers() as $id => $answer)
		{
			$worksheet->writeString($startrow + $i, 0, ilExcelUtils::_convert_text($answer->getAnswertext()), $format_bold);
			if ($id == $solution[0]["value1"])
			{
				$worksheet->write($startrow + $i, 1, 1);
			}
			else
			{
				$worksheet->write($startrow + $i, 1, 0);
			}
			$i++;
		}
		return $startrow + $i + 1;
	}

	public function getThumbSize()
	{
		return $this->thumb_size;
	}
	
	public function setThumbSize($a_size)
	{
		$this->thumb_size = $a_size;
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
			"onenotcorrect" => ilRTE::_replaceMediaObjectImageSrc($this->getFeedbackGeneric(0), 0),
			"allcorrect" => ilRTE::_replaceMediaObjectImageSrc($this->getFeedbackGeneric(1), 0)
			);

		$answers = array();
		$has_image = false;
		foreach ($this->getAnswers() as $key => $answer_obj)
		{
			if((string) $answer_obj->getImage())
			{
				$has_image = true;
			}
			array_push($answers, array(
				"answertext" => (string) $answer_obj->getAnswertext(),
				"points" => (float)$answer_obj->getPoints(),
				"order" => (int)$answer_obj->getOrder(),
				"image" => (string) $answer_obj->getImage(),
				"feedback" => ilRTE::_replaceMediaObjectImageSrc($this->getFeedbackSingleAnswer($key), 0)
			));
		}
		$result['answers'] = $answers;
		if($has_image)
		{
			$result['path'] = $this->getImagePathWeb();
			$result['thumb'] = $this->getThumbSize();
		}

		$mobs = ilObjMediaObject::_getMobsOfObject("qpl:html", $this->getId());
		$result['mobs'] = $mobs;

		return json_encode($result);
	}
	
	public function removeAnswerImage($index)
	{
		$answer = $this->answers[$index];
		if (is_object($answer))
		{
			$this->deleteImage($answer->getImage());
			$answer->setImage('');
		}
	}

	function createRandomSolution($active_id, $pass)
	{
		$value = rand(0, count($this->answers)-1);
		$_POST["multiple_choice_result"] = (strlen($value)) ? (string)$value : '0';
		$this->saveWorkingData($active_id, $pass);
	}

	function getMultilineAnswerSetting()
	{
		global $ilUser;

		$multilineAnswerSetting = $ilUser->getPref("tst_multiline_answers");
		if ($multilineAnswerSetting != 1)
		{
			$multilineAnswerSetting = 0;
		}
		return $multilineAnswerSetting;
	}
	
	function setMultilineAnswerSetting($a_setting = 0)
	{
		global $ilUser;
		$ilUser->writePref("tst_multiline_answers", $a_setting);
	}
	
	/**
	 * returns boolean wether the question
	 * is answered during test pass or not
	 * 
	 * (overwrites method in class assQuestion)
	 * 
	 * @param integer $active_id
	 * @param integer $pass
	 * @return boolean $answered
	 */
	public function isAnswered($active_id, $pass)
	{
		$answered = assQuestion::doesSolutionRecordsExist($active_id, $pass, $this->getId());
		
		return $answered;
	}
	
	/**
	 * returns boolean wether it is possible to set
	 * this question type as obligatory or not
	 * considering the current question configuration
	 * 
	 * (overwrites method in class assQuestion)
	 * 
	 * @param integer $questionId
	 * @return boolean $obligationPossible
	 */
	public static function isObligationPossible($questionId)
	{
		return true;
	}
}

?>
