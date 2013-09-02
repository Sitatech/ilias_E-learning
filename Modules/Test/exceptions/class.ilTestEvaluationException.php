<?php

require_once('Modules/Test/exceptions/class.ilTestException.php');

/**
 * Test Evaluation Exception
 *
 * @author		Björn Heyser <bheyser@databay.de>
 * @version		$Id: class.ilTestEvaluationException.php 36373 2012-08-21 11:17:39Z bheyser $
 * 
 * @ingroup ModulesTest
 */
class ilTestEvaluationException extends ilTestException
{
	/**
	 * ilTestException Constructor
	 *
	 * @access public
	 * 
	 */
	public function __construct($a_message,$a_code = 0)
	{
	 	parent::__construct($a_message,$a_code);
	}
}

