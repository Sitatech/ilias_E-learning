<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once("./Services/Form/classes/class.ilCustomInputGUI.php");
include_once("./Services/Form/classes/class.ilSubEnabledFormPropertyGUI.php");

/**
 * Class ilDataCollectionDatatype
 *
 * @author Martin Studer <ms@studer-raimann.ch>
 * @author Marcel Raimann <mr@studer-raimann.ch>
 * @author Fabian Schmid <fs@studer-raimann.ch>
 * @author Oskar Truffer <ot@studer-raimann.ch>
 * @version $Id:
 *
 * @ingroup ModulesDataCollection
 *
 * @ilCtrl_Calls ilDataCollectionTreePickInputGUI : ilDataCollectionRecordEditGUI
 *
 */
class ilDataCollectionTreePickInputGUI extends ilCustomInputGUI{

	private $customHTML;
	/**
	 * @var ilTextInputGUI
	 */
	private $title_input;

	/**
	 * @var ilTextInputGUI
	 */
	private $search_input;

	public function __construct($title, $post_var){
		global $lng;
		parent::__construct($title, $post_var);
		$this->title_input = new ilTextInputGUI($this->getTitle(), "display_".$this->getPostVar());
		$this->title_input->setDisabled(true);
		$this->title_input->setInfo($lng->txt("dcl_ilias_refere	nce_info"));
		$this->search_input = new ilTextInputGUI($this->getTitle(), "search_".$this->getPostVar());
		$this->search_input->setDisabled(false);
		$this->search_input->setInfo($lng->txt('dcl_ilias_reference_info'));
		$this->hidden_input = new ilHiddenInputGUI($this->getPostVar());
	}

	public function getHtml(){
		global $ilCtrl;
		$tpl = new ilTemplate("tpl.dcl_tree.html", true, true, "Modules/DataCollection");
		$tpl->setVariable("FIELD_ID", $this->getPostVar());
		$tpl->setVariable("AJAX_LINK", $ilCtrl->getLinkTargetByClass("ildatacollectionrecordeditgui", "searchObjects"));
		$tpl->setVariable("LOADER_PATH", ilUtil::getImagePath("loader.gif"));

		return $this->title_input->getToolbarHTML()."<br /><br />".$this->search_input->getTableFilterHTML().$this->hidden_input->getToolbarHTML()." <a href='#' id='search_button_".$this->getPostVar()."'>Search</a>".$tpl->get();
	}

	public function setValueByArray($value){
		parent::setValueByArray($value);
		include_once './Services/Tree/classes/class.ilPathGUI.php';
		$path = new ilPathGUI();
		$reference = $value[$this->getPostVar()];
        if($reference){
		    $pathString = $path->getPath(ROOT_FOLDER_ID, $reference);
            $id = ilObject::_lookupObjId($reference);
            $this->title_input->setValue($pathString." > ".ilObject::_lookupTitle($id));
            $this->hidden_input->setValue($reference);
        }
	}

}

?>