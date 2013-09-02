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
* @author Werner Randelshofer, Hochschule Luzern, werner.randelshofer@hslu.ch
* @version $Id: class.ilCronDiskQuotaCheck.php 25400 2010-08-27 08:54:44Z mjansen $
*
* @package ilias
*/
class ilCronDiskQuotaCheck
{
	function ilCronDiskQuotaCheck()
	{
		global $ilLog,$ilDB;

		$this->log =& $ilLog;
		$this->db =& $ilDB;
	}

	function updateDiskUsageStatistics()
	{
		require_once'./Services/WebDAV/classes/class.ilDiskQuotaChecker.php';
		ilDiskQuotaChecker::_updateDiskUsageReport();
		return true;
	}
	function sendReminderMails()
	{
		require_once'./Services/WebDAV/classes/class.ilDiskQuotaChecker.php';
		ilDiskQuotaChecker::_sendReminderMails();
		return true;
	}
	function sendSummaryMails()
	{
		require_once'./Services/WebDAV/classes/class.ilDiskQuotaChecker.php';
		ilDiskQuotaChecker::_sendSummaryMails();
		return true;
	}
}
?>
