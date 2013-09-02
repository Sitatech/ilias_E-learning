<?php
/*
	+-----------------------------------------------------------------------------+
	| ILIAS open source                                                           |
	+-----------------------------------------------------------------------------+
	| Copyright (c) 1998-2001 ILIAS open source, University of Cologne            |
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


/**
* 
*
* @author Stefan Meyer <meyer@leifos.com>
* @version $Id: class.ilCronLinkCheck.php 33502 2012-03-03 14:14:30Z akill $
*
* @package ilias
*/

class ilCronLinkCheck
{
	function ilCronLinkCheck()
	{
		global $ilLog,$ilDB;

		$this->log =& $ilLog;
		$this->db =& $ilDB;
	}

	function check()
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
}
?>
