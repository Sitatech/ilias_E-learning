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

/**
* Test sequence handler
*
* This class manages the sequence settings for a given user
*
* @author		Helmut Schottmüller <helmut.schottmueller@mac.com>
* @version	$Id: class.ilTestSequence.php 36176 2012-08-13 15:11:43Z bheyser $
* @ingroup ModulesTest
*/
class ilTestSequence
{
	/**
	* An array containing the sequence data
	*
	* @var array
	*/
	var $sequencedata;

	/**
	* The mapping of the sequence numbers to the questions
	*
	* @var array
	*/
	var $questions;

	/**
	* The active id of the sequence data
	*
	* @var integer
	*/
	var $active_id;

	/**
	* The pass of the current sequence
	*
	* @var integer
	*/
	var $pass;

	/**
	* Indicates wheather the active test is a random test or not
	*
	* @var boolean
	*/
	var $isRandomTest;

	/**
	* ilTestSequence constructor
	*
	* The constructor takes possible arguments an creates an instance of 
	* the ilTestSequence object.
	*
	* @param object $a_object A reference to the test container object
	* @access public
	*/
	function ilTestSequence($active_id, $pass, $randomtest)
	{
		$this->active_id = $active_id;
		$this->pass = $pass;
		$this->isRandomTest = $randomtest;
		$this->sequencedata = array(
				"sequence" => array(),
				"postponed" => array(),
				"hidden" => array()
			);
		$this->loadFromDb();
		$this->loadQuestions();
	}
	
	function getActiveId()
	{
		return $this->active_id;
	}
	
	function createNewSequence($max, $shuffle)
	{
		$newsequence = array();
		if ($max > 0)
		{
			for ($i = 1; $i <= $max; $i++)
			{
				array_push($newsequence, $i);
			}
			if ($shuffle) $newsequence = $this->pcArrayShuffle($newsequence);
		}
		$this->sequencedata["sequence"] = $newsequence;
	}
	
	/**
	* Loads the question mapping
	*
	* @access private
	*/
	private function loadQuestions()
	{
		global $ilDB;

		$this->questions = array();
		if ($this->isRandomTest)
		{
			$result = $ilDB->queryF("SELECT tst_test_rnd_qst.* FROM tst_test_rnd_qst, qpl_questions WHERE tst_test_rnd_qst.active_fi = %s AND qpl_questions.question_id = tst_test_rnd_qst.question_fi AND tst_test_rnd_qst.pass = %s ORDER BY sequence",
				array('integer','integer'),
				array($this->active_id, $this->pass)
			);
			// The following is a fix for random tests prior to ILIAS 3.8. If someone started a random test in ILIAS < 3.8, there
			// is only one test pass (pass = 0) in tst_test_rnd_qst while with ILIAS 3.8 there are questions for every test pass.
			// To prevent problems with tests started in an older version and continued in ILIAS 3.8, the first pass should be taken if
			// no questions are present for a newer pass.
			if ($result->numRows() == 0)
			{
				$result = $ilDB->queryF("SELECT tst_test_rnd_qst.* FROM tst_test_rnd_qst, qpl_questions WHERE tst_test_rnd_qst.active_fi = %s AND qpl_questions.question_id = tst_test_rnd_qst.question_fi AND tst_test_rnd_qst.pass = 0 ORDER BY sequence",
					array('integer'),
					array($this->active_id)
				);
			}
		}
		else
		{
			$result = $ilDB->queryF("SELECT tst_test_question.* FROM tst_test_question, qpl_questions, tst_active WHERE tst_active.active_id = %s AND tst_test_question.test_fi = tst_active.test_fi AND qpl_questions.question_id = tst_test_question.question_fi ORDER BY tst_test_question.sequence",
				array('integer'),
				array($this->active_id)
			);
		}
		$index = 1;
		while ($data = $ilDB->fetchAssoc($result))
		{
			$this->questions[$index++] = $data["question_fi"];
		}
	}
	
	/**
	* Loads the sequence data for a given active id
	*
	* @return string The filesystem path of the certificate
	* @access private
	*/
	private function loadFromDb()
	{
		global $ilDB;
		$result = $ilDB->queryF("SELECT * FROM tst_sequence WHERE active_fi = %s AND pass = %s", 
			array('integer','integer'),
			array($this->active_id, $this->pass)
		);
		if ($result->numRows())
		{
			$row = $ilDB->fetchAssoc($result);
			$this->sequencedata = array(
				"sequence" => unserialize($row["sequence"]),
				"postponed" => unserialize($row["postponed"]),
				"hidden" => unserialize($row["hidden"])
			);
			if (!is_array($this->sequencedata["sequence"])) $this->sequencedata["sequence"] = array();
			if (!is_array($this->sequencedata["postponed"])) $this->sequencedata["postponed"] = array();
			if (!is_array($this->sequencedata["hidden"])) $this->sequencedata["hidden"] = array();
		}
	}
	
	/**
	* Saves the sequence data for a given pass to the database
	*
	* @access public
	*/
	public function saveToDb()
	{
		global $ilDB;
		
		$postponed = NULL;
		if ((is_array($this->sequencedata["postponed"])) && (count($this->sequencedata["postponed"])))
		{
			$postponed = serialize($this->sequencedata["postponed"]);
		}
		$hidden = NULL;
		if ((is_array($this->sequencedata["hidden"])) && (count($this->sequencedata["hidden"])))
		{
			$hidden = serialize($this->sequencedata["hidden"]);
		}
		
		$affectedRows = $ilDB->manipulateF("DELETE FROM tst_sequence WHERE active_fi = %s AND pass = %s",
			array('integer','integer'),
			array($this->active_id, $this->pass)
		);
		
		$affectedRows = $ilDB->insert("tst_sequence", array(
			"active_fi" => array("integer", $this->active_id),
			"pass" => array("integer", $this->pass),
			"sequence" => array("clob", serialize($this->sequencedata["sequence"])),
			"postponed" => array("text", $postponed),
			"hidden" => array("text", $hidden),
			"tstamp" => array("integer", time())
		));
	}
	
	function postponeQuestion($question_id)
	{
		if (!$this->isPostponedQuestion($question_id))
		{
			array_push($this->sequencedata["postponed"], intval($question_id));
		}
	}
	
	function hideQuestion($question_id)
	{
		if (!$this->isHiddenQuestion($question_id))
		{
			array_push($this->sequencedata["hidden"], intval($question_id));
		}
	}
	
	function isPostponedQuestion($question_id)
	{
		if (!is_array($this->sequencedata["postponed"])) return FALSE;
		if (!in_array($question_id, $this->sequencedata["postponed"]))
		{
			return FALSE;
		}
		else
		{
			return TRUE;
		}
	}
	
	function isHiddenQuestion($question_id)
	{
		if (!is_array($this->sequencedata["hidden"])) return FALSE;
		if (!in_array($question_id, $this->sequencedata["hidden"]))
		{
			return FALSE;
		}
		else
		{
			return TRUE;
		}
	}
	
	function isPostponedSequence($sequence)
	{
		if (!array_key_exists($sequence, $this->questions)) return FALSE;
		if (!is_array($this->sequencedata["postponed"])) return FALSE;
		if (!in_array($this->questions[$sequence], $this->sequencedata["postponed"]))
		{
			return FALSE;
		}
		else
		{
			return TRUE;
		}
	}
	
	function isHiddenSequence($sequence)
	{
		if (!array_key_exists($sequence, $this->questions)) return FALSE;
		if (!is_array($this->sequencedata["hidden"])) return FALSE;
		if (!in_array($this->questions[$sequence], $this->sequencedata["hidden"]))
		{
			return FALSE;
		}
		else
		{
			return TRUE;
		}
	}
	
	function postponeSequence($sequence)
	{
		if (!$this->isPostponedSequence($sequence))
		{
			if (array_key_exists($sequence, $this->questions))
			{
				if (!is_array($this->sequencedata["postponed"])) $this->sequencedata["postponed"] = array();
				array_push($this->sequencedata["postponed"], intval($this->questions[$sequence]));
			}
		}
	}
	
	function hideSequence($sequence)
	{
		if (!$this->isHiddenSequence($sequence))
		{
			if (array_key_exists($sequence, $this->questions))
			{
				if (!is_array($this->sequencedata["hidden"])) $this->sequencedata["hidden"] = array();
				array_push($this->sequencedata["hidden"], intval($this->questions[$sequence]));
			}
		}
	}
	
	function getPositionOfSequence($sequence)
	{
		$correctedsequence = $this->getCorrectedSequence();
		$sequencekey = array_search($sequence, $correctedsequence);
		if ($sequencekey !== FALSE)
		{
			return $sequencekey + 1;
		}
		else
		{
			return "";
		}
	}
	
	function getUserQuestionCount()
	{
		return count($this->getCorrectedSequence());
	}
	
	function getOrderedSequence()
	{
		return array_keys($this->questions);
	}
	
	function getOrderedSequenceQuestions()
	{
		return $this->questions;
	}
	
	function getUserSequence()
	{
		return $this->getCorrectedSequence(TRUE);
	}

	function getUserSequenceQuestions()
	{
		$seq = $this->getCorrectedSequence(TRUE);
		$found = array();
		foreach ($seq as $sequence)
		{
			array_push($found, $this->getQuestionForSequence($sequence));
		}
		return $found;
	}

	protected function getCorrectedSequence($with_hidden_questions = FALSE)
	{
		$correctedsequence = $this->sequencedata["sequence"];
		if (!$with_hidden_questions)
		{
			if (is_array($this->sequencedata["hidden"]))
			{
				foreach ($this->sequencedata["hidden"] as $question_id)
				{
					$foundsequence = array_search($question_id, $this->questions);
					if ($foundsequence !== FALSE)
					{
						$sequencekey = array_search($foundsequence, $correctedsequence);
						if ($sequencekey !== FALSE)
						{
							unset($correctedsequence[$sequencekey]);
						}
					}
				}
			}
		}
		if (is_array($this->sequencedata["postponed"]))
		{
			foreach ($this->sequencedata["postponed"] as $question_id)
			{
				$foundsequence = array_search($question_id, $this->questions);
				if ($foundsequence !== FALSE)
				{
					$sequencekey = array_search($foundsequence, $correctedsequence);
					if ($sequencekey !== FALSE)
					{
						unset($correctedsequence[$sequencekey]);
						array_push($correctedsequence, $foundsequence);
					}
				}
			}
		}
		return array_values($correctedsequence);
	}
	
	function getSequenceForQuestion($question_id)
	{
		return array_search($question_id, $this->questions);
	}
	
	function getFirstSequence()
	{
		$correctedsequence = $this->getCorrectedSequence();
		if (count($correctedsequence))
		{
			return reset($correctedsequence);
		}
		else
		{
			return FALSE;
		}
	}
	
	function getLastSequence()
	{
		$correctedsequence = $this->getCorrectedSequence();
		if (count($correctedsequence))
		{
			return end($correctedsequence);
		}
		else
		{
			return FALSE;
		}
	}
	
	function getNextSequence($sequence)
	{
		$correctedsequence = $this->getCorrectedSequence();
		$sequencekey = array_search($sequence, $correctedsequence);
		if ($sequencekey !== FALSE)
		{
			$nextsequencekey = $sequencekey + 1;
			if (array_key_exists($nextsequencekey, $correctedsequence))
			{
				return $correctedsequence[$nextsequencekey];
			}
		}
		return FALSE;
	}
	
	function getPreviousSequence($sequence)
	{
		$correctedsequence = $this->getCorrectedSequence();
		$sequencekey = array_search($sequence, $correctedsequence);
		if ($sequencekey !== FALSE)
		{
			$prevsequencekey = $sequencekey - 1;
			if (($prevsequencekey >= 0) && (array_key_exists($prevsequencekey, $correctedsequence)))
			{
				return $correctedsequence[$prevsequencekey];
			}
		}
		return FALSE;
	}
	
	/**
	* Shuffles the values of a given array
	*
	* Shuffles the values of a given array
	*
	* @param array $array An array which should be shuffled
	* @access public
	*/
	function pcArrayShuffle($array)
	{
		$keys = array_keys($array);
		shuffle($keys);
		$result = array();
		foreach ($keys as $key)
		{
			$result[$key] = $array[$key];
		}
		return $result;
	}

	function getQuestionForSequence($sequence)
	{
		if ($sequence < 1) return FALSE;
		if (array_key_exists($sequence, $this->questions))
		{
			return $this->questions[$sequence];
		}
		else
		{
			return FALSE;
		}
	}
	
	function &getSequenceSummary($obligationsFilter = false)
	{
		$correctedsequence = $this->getCorrectedSequence();
		$result_array = array();
		include_once "./Modules/Test/classes/class.ilObjTest.php";
		$solved_questions = ilObjTest::_getSolvedQuestions($this->active_id);
		$key = 1;
		foreach ($correctedsequence as $sequence)
		{
			$question =& ilObjTest::_instanciateQuestion($this->getQuestionForSequence($sequence));
			if (is_object($question))
			{
				$worked_through = $question->_isWorkedThrough($this->active_id, $question->getId(), $this->pass);
				$solved  = 0;
				if (array_key_exists($question->getId(), $solved_questions))
				{
					$solved =  $solved_questions[$question->getId()]["solved"];
				}
				$is_postponed = $this->isPostponedQuestion($question->getId());

				$row = array(
					"nr" => "$key",
					"title" => $question->getTitle(),
					"qid" => $question->getId(),
					"visited" => $worked_through,
					"solved" => (($solved)?"1":"0"),
					"description" => $question->getComment(),
					"points" => $question->getMaximumPoints(),
					"worked_through" => $worked_through,
					"postponed" => $is_postponed,
					"sequence" => $sequence,
					"obligatory" => ilObjTest::isQuestionObligatory($question->getId()),
					'isAnswered' => $question->isAnswered($this->active_id, $this->pass)
				);
				
				if( !$obligationsFilter || $row['obligatory'] )
				{
					array_push($result_array, $row);
				}
				
				$key++;
			}
		}
		return $result_array;
	}
	
	function getPass()
	{
		return $this->pass;
	}
	
	function setPass($pass)
	{
		$this->pass = $pass;
	}
	
	function hasSequence()
	{
		if ((is_array($this->sequencedata["sequence"])) && (count($this->sequencedata["sequence"]) > 0))
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

	function hasHiddenQuestions()
	{
		if ((is_array($this->sequencedata["hidden"])) && (count($this->sequencedata["hidden"]) > 0))
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
	function clearHiddenQuestions()
	{
		$this->sequencedata["hidden"] = array();
	}
}

?>
