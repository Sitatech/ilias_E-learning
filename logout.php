<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
* logout script for ilias
*
* @author Sascha Hofmann <shofmann@databay.de>
* @version $Id: logout.php 33447 2012-03-01 14:00:01Z jluetzen $
*
* @package ilias-core
*/

require_once("Services/Init/classes/class.ilInitialisation.php");
ilInitialisation::initILIAS();

$ilCtrl->initBaseClass("ilStartUpGUI");
$ilCtrl->setCmd("showLogout");
$ilCtrl->setTargetScript("ilias.php");
$ilCtrl->callBaseClass();
$ilBench->save();

exit;
?>