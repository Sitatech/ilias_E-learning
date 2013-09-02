<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Modules/TestQuestionPool/classes/class.ilAssQuestionHintList.php';

/**
 * abstract parent class for concrete question hint GUI classes
 *
 * @author		Björn Heyser <bheyser@databay.de>
 * @version		$Id: class.ilAssQuestionHintAbstractGUI.php 34191 2012-04-16 10:43:25Z bheyser $
 * 
 * @package		Modules/TestQuestionPool
 */
abstract class ilAssQuestionHintAbstractGUI
{
	/**
	 * gui instance of current question
	 * 
	 * @access	protected
	 * @var		assQuestionGUI
	 */
	protected $questionGUI = null;
	
	/**
	 * object instance of current question
	 * 
	 * @access	protected
	 * @var		assQuestion 
	 */
	protected $questionOBJ = null;
	
	/**
	 * Constructor
	 * 
	 * @access	public
	 * @param	assQuestionGUI	$questionGUI 
	 */
	public function __construct(assQuestionGUI $questionGUI)
	{
		$this->questionGUI = $questionGUI;
		$this->questionOBJ = $questionGUI->object;
	}
}
