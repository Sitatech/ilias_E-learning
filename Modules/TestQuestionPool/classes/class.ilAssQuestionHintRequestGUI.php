<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Modules/TestQuestionPool/classes/class.ilAssQuestionHintAbstractGUI.php';
require_once 'Modules/TestQuestionPool/classes/class.ilAssQuestionHintTracking.php';

/**
 * GUI class for management/output of hint requests during test session
 *
 * @author		Björn Heyser <bheyser@databay.de>
 * @version		$Id: class.ilAssQuestionHintRequestGUI.php 38295 2012-11-21 11:47:21Z bheyser $
 * 
 * @package		Modules/TestQuestionPool
 * 
 * @ilCtrl_Calls ilAssQuestionHintRequestGUI: ilAssQuestionHintsTableGUI
 * @ilCtrl_Calls ilAssQuestionHintRequestGUI: ilConfirmationGUI, ilPropertyFormGUI
 */
class ilAssQuestionHintRequestGUI extends ilAssQuestionHintAbstractGUI
{
	/**
	 * command constants
	 */
	const CMD_SHOW_LIST			= 'showList';
	const CMD_SHOW_HINT			= 'showHint';
	const CMD_CONFIRM_REQUEST	= 'confirmRequest';
	const CMD_PERFORM_REQUEST	= 'performRequest';
	const CMD_BACK_TO_QUESTION	= 'backToQuestion';
	
	/**
	 * @var ilTestOutputGUI
	 */
	protected $testOutputGUI = null;
	
	/**
	 * @var ilTestSession
	 */
	protected $testSession = null;
	
	/**
	 * Constructor
	 *
	 * @param	ilTestOutputGUI $testOutputGUI
	 * @param	ilTestSession $testSession
	 * @param	assQuestionGUI $questionGUI 
	 */
	public function __construct(ilTestOutputGUI $testOutputGUI, ilTestSession $testSession, assQuestionGUI $questionGUI)
	{
		$this->testOutputGUI = $testOutputGUI;
		$this->testSession = $testSession;
		
		parent::__construct($questionGUI);
	}
	
	/**
	 * Execute Command
	 * 
	 * @access	public
	 * @global	ilCtrl	$ilCtrl
	 * @return	mixed 
	 */
	public function executeCommand()
	{
		global $ilCtrl;
		
		$cmd = $ilCtrl->getCmd(self::CMD_SHOW_LIST);
		$nextClass = $ilCtrl->getNextClass($this);

		switch($nextClass)
		{
			default:
				
				$cmd .= 'Cmd';
				return $this->$cmd();
				break;
		}
	}
	
	/**
	 * shows the list of allready requested hints
	 * 
	 * @access	private
	 */
	private function showListCmd()
	{
		global $ilCtrl, $tpl;
		
		require_once 'Modules/TestQuestionPool/classes/class.ilAssQuestionHintsTableGUI.php';

		$questionHintList = ilAssQuestionHintTracking::getRequestedHintsList(
				$this->questionOBJ->getId(), $this->testSession->getActiveId(), $this->testSession->getPass()
		);

		$table = new ilAssQuestionHintsTableGUI(
				$this->questionOBJ, $questionHintList, $this, self::CMD_SHOW_LIST
		);

		$this->populateContent( $ilCtrl->getHtml($table) );
	}
	
	/**
	 * shows an allready requested hint
	 * 
	 * @access	private
	 * @global	ilCtrl $ilCtrl
	 * @global	ilTemplate $tpl
	 * @global	ilLanguage $lng
	 */
	private function showHintCmd()
	{
		global $ilCtrl, $tpl, $lng;
		
		if( !isset($_GET['hintId']) || !(int)$_GET['hintId'] )
		{
			throw new ilTestException('no hint id given');
		}
		
		$isRequested = ilAssQuestionHintTracking::isRequested(
				(int)$_GET['hintId'], $this->testSession->getActiveId(), $this->testSession->getPass()
		);
		
		if( !$isRequested )
		{
			throw new ilTestException('hint with given id is not yet requested for given testactive and testpass');
		}
		
		$questionHint = ilAssQuestionHint::getInstanceById((int)$_GET['hintId']);
		
		require_once 'Services/Utilities/classes/class.ilUtil.php';
		require_once 'Services/Form/classes/class.ilPropertyFormGUI.php';
		require_once 'Services/Form/classes/class.ilNonEditableValueGUI.php';

		// build form
		
		$form = new ilPropertyFormGUI();
		
		$form->setFormAction($ilCtrl->getFormAction($this));
		
		$form->setTableWidth('100%');

		$form->setTitle(sprintf(
				$lng->txt('tst_question_hints_form_header_edit'),
				$questionHint->getIndex(),
				$this->questionOBJ->getTitle()
		));
		
		$form->addCommandButton(self::CMD_BACK_TO_QUESTION, $lng->txt('tst_question_hints_back_to_question'));
		
		$numExistingRequests = ilAssQuestionHintTracking::getNumExistingRequests(
				$this->questionOBJ->getId(), $this->testSession->getActiveId(), $this->testSession->getPass()
		);
				
		if($numExistingRequests > 1)
		{
			$form->addCommandButton(self::CMD_SHOW_LIST, $lng->txt('button_show_requested_question_hints'));
		}
		
		// form input: hint text
		
		$nonEditableHintText = new ilNonEditableValueGUI($lng->txt('tst_question_hints_form_label_hint_text'), 'hint_text', true);
		$nonEditableHintText->setValue(	ilUtil::prepareTextareaOutput($questionHint->getText(), true) );
		$form->addItem($nonEditableHintText);
		
		// form input: hint points
		
		$nonEditableHintPoints = new ilNonEditableValueGUI($lng->txt('tst_question_hints_form_label_hint_points'), 'hint_points');
		$nonEditableHintPoints->setValue($questionHint->getPoints());
		$form->addItem($nonEditableHintPoints);
		
		$this->populateContent( $ilCtrl->getHtml($form) );
	}
	
	/**
	 * shows a confirmation screen for a hint request
	 * 
	 * @access	private
	 * @global	ilCtrl $ilCtrl
	 * @global	ilTemplate $tpl
	 * @global	ilLanguage $lng
	 */
	private function confirmRequestCmd()
	{
		global $ilCtrl, $tpl, $lng;
		
		$nextRequestableHint = ilAssQuestionHintTracking::getNextRequestableHint(
				$this->questionOBJ->getId(), $this->testSession->getActiveId(), $this->testSession->getPass()
		);
		
		require_once 'Services/Utilities/classes/class.ilConfirmationGUI.php';
		
		$confirmation = new ilConfirmationGUI();
		
		$formAction = ilUtil::appendUrlParameterString(
				$ilCtrl->getFormAction($this), "hintId={$nextRequestableHint->getId()}"
		);
				
		$confirmation->setFormAction($formAction);
		
		$confirmation->setConfirm($lng->txt('tst_question_hints_confirm_request'), self::CMD_PERFORM_REQUEST);
		$confirmation->setCancel($lng->txt('tst_question_hints_cancel_request'), self::CMD_BACK_TO_QUESTION);
		
		$confirmation->setHeaderText(sprintf(
				$lng->txt('tst_question_hints_request_confirmation'),
				$nextRequestableHint->getIndex(),
				$nextRequestableHint->getPoints()
		));
		
		$this->populateContent( $ilCtrl->getHtml($confirmation) );
	}
	
	/**
	 * Performs a hint request and invokes the (re-)saving the question solution.
	 * Redirects to local showHint command
	 * 
	 * @access	private
	 * @global	ilCtrl $ilCtrl
	 */
	private function performRequestCmd()
	{
		global $ilCtrl;
		
		if( !isset($_GET['hintId']) || !(int)$_GET['hintId'] )
		{
			throw new ilTestException('no hint id given');
		}
		
		$nextRequestableHint = ilAssQuestionHintTracking::getNextRequestableHint(
				$this->questionOBJ->getId(), $this->testSession->getActiveId(), $this->testSession->getPass()
		);
		
		if( $nextRequestableHint->getId() != (int)$_GET['hintId'] )
		{
			throw new ilTestException('given hint id does not relate to the next requestable hint');
		}
		
		ilAssQuestionHintTracking::storeRequest(
				$nextRequestableHint, $this->questionOBJ->getId(),
				$this->testSession->getActiveId(), $this->testSession->getPass()
		);
		
		$this->testOutputGUI->saveQuestionSolution();
		
		$redirectTarget = ilUtil::appendUrlParameterString(
				$ilCtrl->getLinkTarget($this, self::CMD_SHOW_HINT, '', false, false), "hintId={$nextRequestableHint->getId()}"
		);
		
		ilUtil::redirect($redirectTarget);
	}
	
	/**
	 * gateway command method to jump back to test session output
	 * 
	 * @access	private
	 * @global	ilCtrl $ilCtrl
	 */
	private function backToQuestionCmd()
	{
		global $ilCtrl;
		
		$ilCtrl->redirectByClass('ilTestOutputGUI', 'redirectQuestion');
	}
	
	/**
	 * populates the rendered questin hint relating output content to global template
	 * depending on possibly active kiosk mode
	 * 
	 * @global ilTemplate $tpl
	 * @param string $content
	 */
	private function populateContent($content)
	{
		global $tpl;
		
		if( $this->testOutputGUI->object->getKioskMode() )
		{
			$tpl->setBodyClass('kiosk');
			$tpl->setAddFooter(false);

			$tpl->addBlockFile(
					'CONTENT', 'content', 'tpl.il_tst_question_hints_kiosk_page.html', 'Modules/TestQuestionPool'
			);
			
			$tpl->setVariable('KIOSK_HEAD', $this->testOutputGUI->getKioskHead());
			
			$tpl->setVariable('KIOSK_CONTENT', $content);
		}
		else
		{
			$tpl->setContent($content);
		}
	}
}
