<?php
/*
	+-----------------------------------------------------------------------------+
	| ILIAS open source                                                           |
	+-----------------------------------------------------------------------------+
	| Copyright (c) 1998-2009 ILIAS open source, University of Cologne            |
	|                                                                             |
	| This program is free software; you can redistribute it and/or               |
	| modify it under the terms of the GNU General Public License                 |
	| as published by the Free Software Foundation; either version 2              |
	| of the License, or (at your option) any later version.                      |
	|                                                                             |
	| This program is distributed in the hope that it will be useful,             |
	| but WITHOUT ANY WARRANTY; without even the implied warranty of              |
	| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               |
	| GNU General Public License for more details.                                |
	|                                                                             |
	| You should have received a copy of the GNU General Public License           |
	| along with this program; if not, write to the Free Software                 |
	| Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA. |
	+-----------------------------------------------------------------------------+
*/


// include pear
//require_once("DB.php");

/**
* Cron job class
*
* @author Stefan Meyer <meyer@leifos.com>
* @version $Id: class.ilCron.php 33502 2012-03-03 14:14:30Z akill $
*/
class ilCron
{
	var $db;
	var $log;

	function ilCron(&$db)
	{
		define('DEBUG',1);
		define('SOCKET_TIMEOUT',5);

		$this->db = $db;
		
		$GLOBALS["ilDB"] = $this->db;
		include_once './Services/Administration/classes/class.ilSetting.php';
		$this->setting = new ilSetting("common", true);

	}

	function initLog($path,$file,$client)
	{
		include_once './Services/Logging/classes/class.ilLog.php';

		$this->log =& new ilLog($path,$file,$client);

		return true;
	}

	function txt($language,$key,$module = 'common')
	{
		include_once './Services/Language/classes/class.ilLanguage.php';
		return ilLanguage::_lookupEntry($language, $module, $key);
	}

	
	function start()
	{
		// add here other checks
		if($this->__readSetting('cron_user_check'))
		{
			$this->__checkUserAccounts();
		}
		if($this->__readSetting('cron_link_check'))
		{
			$this->__checkLinks();
		}
	}

	function __checkUserAccounts()
	{
		global $ilDB;
		
		$two_weeks_in_seconds = 60 * 60 * 24 * 14;

		$this->log->write('Cron: Start checkUserAccounts()');
		$query = "SELECT * FROM usr_data,usr_pref ".
			"WHERE time_limit_message = ".$ilDB->quote(0, "integer")." ".
			"AND time_limit_unlimited = ".$ilDB->quote(0, "integer")." ".
			"AND time_limit_from < ".$ilDB->quote(time(), "integer")." ".
			"AND time_limit_until > ".$ilDB->quote($two_weeks_in_seconds, "integer")." ".
			"AND usr_data.usr_id = usr_pref.usr_id ".
			"AND keyword = ".$ilDB->quote("language", "text");

		$res = $ilDB->query($query);

		while($row = $ilDB->fetchObject($res))
		{
			include_once './Services/Mail/classes/class.ilMimeMail.php';

			$data['expires'] = $row->time_limit_until;
			$data['email'] = $row->email;
			$data['login'] = $row->login;
			$data['usr_id'] = $row->usr_id;
			$data['language'] = $row->value;
			$data['owner'] = $row->time_limit_owner;

			// Get owner
			$query = "SELECT email FROM usr_data WHERE usr_id = ".$ilDB->quote($data['owner'], "integer");
			
			$res2 = $this->db->query($query);
			while($row = $res2->fetchRow(DB_FETCHMODE_OBJECT))
			{
				$from = $row->email;
			}

			// Send mail
			$mail =& new ilMimeMail();
			
			$mail->From($from);
			$mail->To($data['email']);
			$mail->Subject($this->txt($data['language'],'account_expires_subject'));
			$mail->Body($this->txt($data['language'],'account_expires_body')." ".strftime('%Y-%m-%d %R',$data['expires']));
			$mail->send();

			// set status 'mail sent'
			$query = "UPDATE usr_data SET time_limit_message = ".$ilDB->quote(1, "integer").
				" WHERE usr_id = ".$ilDB->quote($data['usr_id'], "integer");
			$ilDB->manipulate($query);
			
			// Send log message
			$this->log->write('Cron: (checkUserAccounts()) sent message to '.$data['login'].'.');
		}
		
	}


	function __checkLinks()
	{
		include_once'./Services/LinkChecker/classes/class.ilLinkChecker.php';

		$link_checker =& new ilLinkChecker($this->db);
		$link_checker->setMailStatus(true);

		$invalid = $link_checker->checkLinks();
		foreach($link_checker->getLogMessages() as $message)
		{
			$this->log->write($message);
		}

		return true;
	}

	function __readSetting($a_keyword)
	{
		return $this->setting->get($a_keyword);
/*		$query = "SELECT * FROM sett ings ".
			"WHERE keyword = '".$a_keyword."'";

		$res = $this->db->query($query);
		while($row = $res->fetchRow(DB_FETCHMODE_OBJECT))
		{
			return $row->value ? $row->value : 0;
		}
		return 0;	*/
	}
}
?>
