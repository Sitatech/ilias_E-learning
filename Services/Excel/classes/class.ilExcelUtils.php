<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/*
* Utilities for Microsoft Excel Import/Export
*
* @author Helmut Schottmüller <ilias@aurealis.de>
* @version $Id: class.ilExcelUtils.php 24199 2010-06-11 08:09:26Z jluetzen $
*
*/

class ilExcelUtils
{
	function _convert_text($a_text, $a_target = "has been removed")
	{
		return $a_text;

		/* utf-8 is supported
		$a_text = preg_replace("/<[^>]*?>/", "", $a_text);
		return utf8_decode($a_text);
		 */
	}

} // END class.ilExcelUtils.php
?>
