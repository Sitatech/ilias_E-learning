<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
* Test session handler
*
* This class manages the test session for a participant
*
* @author		Helmut Schottmüller <helmut.schottmueller@mac.com>
* @version	$Id: class.ilTestSession.php 34181 2012-04-16 07:56:29Z bheyser $
* @ingroup ModulesTest
*/
class ilTestSession
{
	/**
	* The unique identifier of the test session
	*
	* @var integer
	*/
	var $active_id;

	/**
	* The user id of the participant
	*
	* @var integer
	*/
	var $user_id;

	/**
	* The anonymous id of the participant
	*
	* @var integer
	*/
	var $anonymous_id;

	/**
	* The database id of the test
	*
	* @var integer
	*/
	var $test_id;

	/**
	* The last sequence of the participant
	*
	* @var integer
	*/
	var $lastsequence;

	/**
	* Indicates if the test was submitted already
	*
	* @var boolean
	*/
	var $submitted;

	/**
	* The timestamp of the last session
	*
	* @var boolean
	*/
	var $tstamp;

	/**
	* The timestamp of the test submission
	*
	* @var string
	*/
	var $submittedTimestamp;

	/**
	* ilTestSession constructor
	*
	* The constructor takes possible arguments an creates an instance of 
	* the ilTestSession object.
	*
	* @access public
	*/
	function ilTestSession($active_id = "")
	{
		$this->active_id = 0;
		$this->user_id = 0;
		$this->anonymous_id = 0;
		$this->test_id = 0;
		$this->lastsequence = 0;
		$this->submitted = FALSE;
		$this->submittedTimestamp = "";
		$this->pass = 0;
		$this->ref_id = 0;
		$this->tstamp = 0;
		if ($active_id > 0)
		{
			$this->loadFromDb($active_id);
		}
	}

	/**
	 * Set Ref id
	 *
	 * @param	integer	Ref id
	 */
	function setRefId($a_val)
	{
		$this->ref_id = $a_val;
	}

	/**
	 * Get Ref id
	 *
	 * @return	integer	Ref id
	 */
	function getRefId()
	{
		return $this->ref_id;
	}
	
	private function activeIDExists($user_id, $test_id)
	{
		global $ilDB;

		if ($_SESSION["AccountId"] != ANONYMOUS_USER_ID)
		{
			$result = $ilDB->queryF("SELECT * FROM tst_active WHERE user_fi = %s AND test_fi = %s",
				array('integer','integer'),
				array($user_id, $test_id)
			);
			if ($result->numRows())
			{
				$row = $ilDB->fetchAssoc($result);
				$this->active_id = $row["active_id"];
				$this->active_id = $row["active_id"];
				$this->user_id = $row["user_fi"];
				$this->anonymous_id = $row["anonymous_id"];
				$this->test_id = $row["test_fi"];
				$this->lastsequence = $row["lastindex"];
				$this->pass = $row["tries"];
				$this->submitted = ($row["submitted"]) ? TRUE : FALSE;
				$this->submittedTimestamp = $row["submittimestamp"];
				$this->tstamp = $row["tstamp"];
				return true;
			}
		}
		return false;
	}
	
	function increaseTestPass()
	{
		global $ilDB, $ilLog;
		
		$this->increasePass();
		$this->setLastSequence(0);
		$submitted = ($this->isSubmitted()) ? 1 : 0;
		// there has to be at least 10 seconds between new test passes (to ensure that noone double clicks the finish button and increases the test pass by more than 1)
		if (time() - $_SESSION['tst_last_increase_pass'] > 10)
		{
			$_SESSION['tst_last_increase_pass'] = time();
			$this->tstamp = time();
			if ($this->active_id > 0)
			{
				$affectedRows = $ilDB->manipulateF("UPDATE tst_active SET lastindex = %s, tries = %s, submitted = %s, submittimestamp = %s, tstamp = %s WHERE active_id = %s",
					array('integer', 'integer', 'integer', 'timestamp', 'integer', 'integer'),
					array(
						$this->getLastSequence(),
						$this->getPass(),
						$submitted,
						(strlen($this->getSubmittedTimestamp())) ? $this->getSubmittedTimestamp() : NULL,
						time(),
						$this->getActiveId()
					)
				);
				
				// update learning progress
				include_once("./Modules/Test/classes/class.ilObjTestAccess.php");
				include_once("./Services/Tracking/classes/class.ilLPStatusWrapper.php");
				ilLPStatusWrapper::_updateStatus(ilObjTestAccess::_lookupObjIdForTestId($this->getTestId()),
					ilObjTestAccess::_getParticipantId($this->active_id));
			}
			else
			{
				if (!$this->activeIDExists($this->getUserId(), $this->getTestId()))
				{
					$anonymous_id = ($this->getAnonymousId()) ? $this->getAnonymousId() : NULL;
					$next_id = $ilDB->nextId('tst_active');
					$affectedRows = $ilDB->manipulateF("INSERT INTO tst_active (active_id, user_fi, anonymous_id, test_fi, lastindex, tries, submitted, submittimestamp, tstamp) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)",
						array('integer', 'integer', 'text', 'integer', 'integer', 'integer', 'integer', 'timestamp', 'integer'),
						array(
							$next_id,
							$this->getUserId(),
							$anonymous_id,
							$this->getTestId(),
							$this->getLastSequence(),
							$this->getPass(),
							$submitted,
							(strlen($this->getSubmittedTimestamp())) ? $this->getSubmittedTimestamp() : NULL,
							time()
						)
					);
					$this->active_id = $next_id;

					// update learning progress
					include_once("./Modules/Test/classes/class.ilObjTestAccess.php");
					include_once("./Services/Tracking/classes/class.ilLPStatusWrapper.php");
					ilLPStatusWrapper::_updateStatus(ilObjTestAccess::_lookupObjIdForTestId($this->getTestId()),
						$this->getUserId());
				}
			}
		}
	}
	
	function saveToDb()
	{
		global $ilDB, $ilLog;
		
		$submitted = ($this->isSubmitted()) ? 1 : 0;
		if ($this->active_id > 0)
		{
			$affectedRows = $ilDB->manipulateF("UPDATE tst_active SET lastindex = %s, tries = %s, submitted = %s, submittimestamp = %s, tstamp = %s WHERE active_id = %s",
				array('integer', 'integer', 'integer', 'timestamp', 'integer', 'integer'),
				array(
					$this->getLastSequence(),
					$this->getPass(),
					$submitted,
					(strlen($this->getSubmittedTimestamp())) ? $this->getSubmittedTimestamp() : NULL,
					time()-10,
					$this->getActiveId()
				)
			);

			// update learning progress
			include_once("./Modules/Test/classes/class.ilObjTestAccess.php");
			include_once("./Services/Tracking/classes/class.ilLPStatusWrapper.php");
			ilLPStatusWrapper::_updateStatus(ilObjTestAccess::_lookupObjIdForTestId($this->getTestId()),
				ilObjTestAccess::_getParticipantId($this->getActiveId()));
		}
		else
		{
			if (!$this->activeIDExists($this->getUserId(), $this->getTestId()))
			{
				$anonymous_id = ($this->getAnonymousId()) ? $this->getAnonymousId() : NULL;
				$next_id = $ilDB->nextId('tst_active');
				$affectedRows = $ilDB->manipulateF("INSERT INTO tst_active (active_id, user_fi, anonymous_id, test_fi, lastindex, tries, submitted, submittimestamp, tstamp) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)",
					array('integer', 'integer', 'text', 'integer', 'integer', 'integer', 'integer', 'timestamp', 'integer'),
					array(
						$next_id,
						$this->getUserId(),
						$anonymous_id,
						$this->getTestId(),
						$this->getLastSequence(),
						$this->getPass(),
						$submitted,
						(strlen($this->getSubmittedTimestamp())) ? $this->getSubmittedTimestamp() : NULL,
						time()-10
					)
				);
				$this->active_id = $next_id;

				// update learning progress
				include_once("./Modules/Test/classes/class.ilObjTestAccess.php");
				include_once("./Services/Tracking/classes/class.ilLPStatusWrapper.php");
				ilLPStatusWrapper::_updateStatus(ilObjTestAccess::_lookupObjIdForTestId($this->getTestId()),
					$this->getUserId());
			}
		}
		
		include_once("./Services/Tracking/classes/class.ilLearningProgress.php");
		ilLearningProgress::_tracProgress($this->getUserId(),
										  ilObjTestAccess::_lookupObjIdForTestId($this->getTestId()),
										  $this->getRefId(),
										  'tst');
	}
	
	function loadTestSession($test_id, $user_id = "", $anonymous_id = "")
	{
		global $ilDB;
		global $ilUser;

		if (!$user_id)
		{
			$user_id = $ilUser->getId();
		}
		if (($_SESSION["AccountId"] == ANONYMOUS_USER_ID) && (strlen($_SESSION["tst_access_code"][$test_id])))
		{
			$result = $ilDB->queryF("SELECT * FROM tst_active WHERE user_fi = %s AND test_fi = %s AND anonymous_id = %s",
				array('integer','integer','text'),
				array($user_id, $test_id, $_SESSION["tst_access_code"][$test_id])
			);
		}
		else if (strlen($anonymous_id))
		{
			$result = $ilDB->queryF("SELECT * FROM tst_active WHERE user_fi = %s AND test_fi = %s AND anonymous_id = %s",
				array('integer','integer','text'),
				array($user_id, $test_id, $anonymous_id)
			);
		}
		else
		{
			if ($_SESSION["AccountId"] == ANONYMOUS_USER_ID)
			{
				return NULL;
			}
			$result = $ilDB->queryF("SELECT * FROM tst_active WHERE user_fi = %s AND test_fi = %s",
				array('integer','integer'),
				array($user_id, $test_id)
			);
		}
		if ($result->numRows())
		{
			$row = $ilDB->fetchAssoc($result);
			$this->active_id = $row["active_id"];
			$this->user_id = $row["user_fi"];
			$this->anonymous_id = $row["anonymous_id"];
			$this->test_id = $row["test_fi"];
			$this->lastsequence = $row["lastindex"];
			$this->pass = $row["tries"];
			$this->submitted = ($row["submitted"]) ? TRUE : FALSE;
			$this->submittedTimestamp = $row["submittimestamp"];
			$this->tstamp = $row["tstamp"];
		}
	}
	
	/**
	* Loads the session data for a given active id
	*
	* @param integer $active_id The database id of the test session
	* @access private
	*/
	private function loadFromDb($active_id)
	{
		global $ilDB;
		$result = $ilDB->queryF("SELECT * FROM tst_active WHERE active_id = %s", 
			array('integer'),
			array($active_id)
		);
		if ($result->numRows())
		{
			$row = $ilDB->fetchAssoc($result);
			$this->active_id = $row["active_id"];
			$this->user_id = $row["user_fi"];
			$this->anonymous_id = $row["anonymous_id"];
			$this->test_id = $row["test_fi"];
			$this->lastsequence = $row["lastindex"];
			$this->pass = $row["tries"];
			$this->submitted = ($row["submitted"]) ? TRUE : FALSE;
			$this->submittedTimestamp = $row["submittimestamp"];
			$this->tstamp = $row["tstamp"];
		}
	}
	
	function getActiveId()
	{
		return $this->active_id;
	}
	
	function setUserId($user_id)
	{
		$this->user_id = $user_id;
	}
	
	function getUserId()
	{
		return $this->user_id;
	}
	
	function setTestId($test_id)
	{
		$this->test_id = $test_id;
	}
	
	function getTestId()
	{
		return $this->test_id;
	}
	
	function setAnonymousId($anonymous_id)
	{
		$this->anonymous_id = $anonymous_id;
	}
	
	function getAnonymousId()
	{
		return $this->anonymous_id;
	}
	
	function setLastSequence($lastsequence)
	{
		$this->lastsequence = $lastsequence;
	}
	
	function getLastSequence()
	{
		return $this->lastsequence;
	}
	
	function setPass($pass)
	{
		$this->pass = $pass;
	}
	
	function getPass()
	{
		return $this->pass;
	}
	
	function increasePass()
	{
		$this->pass += 1;
	}
	
	function isSubmitted()
	{
		return $this->submitted;
	}
	
	function setSubmitted()
	{
		$this->submitted = TRUE;
	}
	
	function getSubmittedTimestamp()
	{
		return $this->submittedTimestamp;
	}
	
	function setSubmittedTimestamp()
	{
		$this->submittedTimestamp = strftime("%Y-%m-%d %H:%M:%S");
	}
}

?>
