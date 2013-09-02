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

/**
* Handle callback from payment system. Currently only relevant
* for ePay.
*
* @author Jesper G�dvad <jesper@ilias.dk>
* @version $Id$
* @since ILIAS 4.0.2
* 
* @ingroup ServicesPayment
*/

chdir(dirname(__FILE__));
chdir('../../..');
require_once 'Services/Authentication/classes/class.ilAuthFactory.php';
ilAuthFactory::setContext(ilAuthFactory::CONTEXT_CRON);
require_once 'Services/Payment/classes/class.ilPurchase.php';
$usr_id = $_REQUEST['ilUser'];
$pay_method = $_REQUEST['pay_method'];
try
{
  $buy = new ilPurchase( $usr_id, $pay_method);
  $buy->purchase($_REQUEST['tid']);
}
catch (Exception $e)
{
  die($e->getMessage());
}
?>