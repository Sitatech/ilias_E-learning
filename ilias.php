<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
* ilias.php. main script.
*
* If you want to use this script your base class must be declared
* within modules.xml.
*
* @author Alex Killing <alex.killing@gmx.de>
* @version $Id: ilias.php 33447 2012-03-01 14:00:01Z jluetzen $
*
*/

require_once("Services/Init/classes/class.ilInitialisation.php");
ilInitialisation::initILIAS();

global $ilCtrl, $ilBench;

$ilCtrl->setTargetScript("ilias.php");
$ilCtrl->callBaseClass();
$ilBench->save();

?>