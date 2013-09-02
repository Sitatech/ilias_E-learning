<?php
/*
	+-----------------------------------------------------------------------------+
	| ILIAS open source                                                           |
	+-----------------------------------------------------------------------------+
	| Copyright (c) 1998-2006 ILIAS open source, University of Cologne            |
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

include_once('Services/PrivacySecurity/classes/class.ilPrivacySettings.php');
include_once('Services/Membership/classes/class.ilMemberAgreement.php');
include_once('Modules/Course/classes/Export/class.ilCourseUserData.php');
include_once('Modules/Course/classes/Export/class.ilCourseDefinedFieldDefinition.php');

/** 
* 
* @author Stefan Meyer <meyer@leifos.com>
* @version $Id$
* 
* 
* @ilCtrl_Calls ilMemberAgreementGUI: 
* @ingroup ModulesCourse
*/
class ilMemberAgreementGUI
{
	private $ref_id;
	private $obj_id;
	private $type;
	
	private $db;
	private $ctrl;
	private $lng;
	private $tpl; 
	
	private $privacy;
	private $agreement;
	
	/**
	 * Constructor
	 *
	 * @access public
	 * 
	 */
	public function __construct($a_ref_id)
	{
		global $ilDB,$ilCtrl,$lng,$tpl,$ilUser,$ilObjDataCache;
		
		$this->ref_id = $a_ref_id;
	 	$this->obj_id = $ilObjDataCache->lookupObjId($this->ref_id);
		$this->type = ilObject::_lookupType($this->obj_id);
	 	$this->ctrl = $ilCtrl;
	 	$this->tpl = $tpl;
	 	$this->lng = $lng;
	 	$this->lng->loadLanguageModule('ps');
	 	
	 	$this->privacy = ilPrivacySettings::_getInstance();
	 	$this->agreement = new ilMemberAgreement($ilUser->getId(),$this->obj_id);
	 	$this->init();
	}
	
	/**
	 * Execute Command
	 *
	 * @access public
	 * 
	 */
	public function executeCommand()
	{
	 	$next_class = $this->ctrl->getNextClass($this);
		$cmd = $this->ctrl->getCmd();

		switch($next_class)
		{
			default:
				if(!$cmd or $cmd == 'view')
				{
					$cmd = 'showAgreement';
				}
				$this->$cmd();
				break;
		}	 	
	}
	
	/**
	 * show agreement
	 *
	 * @access private
	 * 
	 */
	private function showAgreement($send_info = true)
	{
		
		$this->tpl->addBlockFile('ADM_CONTENT','adm_content','tpl.crs_user_agreement.html','Modules/Course');
		$this->tpl->setVariable('FORMACTION',$this->ctrl->getFormAction($this));
		
		if($send_info)
		{
			$this->sendInfoMessage();
		}
		$this->showCourseDefinedFields();
		
		include_once('Services/PrivacySecurity/classes/class.ilExportFieldsInfo.php');
		$fields_info = ilExportFieldsInfo::_getInstanceByType(ilObject::_lookupType($this->obj_id));
		
		foreach($fields_info->getExportableFields() as $field)
		{
			$this->tpl->setCurrentBlock('field');
			$this->tpl->setVariable('FIELD_NAME',$this->lng->txt($field));
			$this->tpl->parseCurrentBlock();
		}
		
		$this->tpl->setVariable('AGREEMENT_HEADER',$this->lng->txt($this->type.'_agreement_header'));
		$this->tpl->setVariable('TXT_AGREEMENT',$this->lng->txt($this->type.'_user_agreement'));
		$this->tpl->setVariable('TXT_INFO_AGREEMENT',$this->lng->txt($this->type.'_info_agreement'));
		if($this->privacy->confirmationRequired($this->type) or ilCourseDefinedFieldDefinition::_hasFields($this->obj_id))
		{
			$this->tpl->setCurrentBlock('agreement');
			$this->tpl->setVariable('CHECK_AGREE',ilUtil::formCheckbox(0,'agreed',1));
			$this->tpl->setVariable('INFO_AGREE',$this->lng->txt($this->type.'_info_agree'));
			$this->tpl->setVariable('TXT_AGREE',$this->lng->txt($this->type.'_agree'));
			$this->tpl->parseCurrentBlock();
		}
		$this->tpl->setVariable('TXT_SAVE',$this->lng->txt('save'));
	}
	
	/**
	 * Save
	 *
	 * @access private
	 * @param
	 * 
	 */
	private function save()
	{
		if(!$this->checkCourseDefinedFields())
	 	{
	 		ilUtil::sendFailure($this->lng->txt('fill_out_all_required_fields'));
	 		$this->showAgreement(false);
	 		return false;
	 	}
	 	if(!$this->checkAgreement())
	 	{
	 		ilUtil::sendFailure($this->lng->txt($this->type.'_agreement_required'));
	 		$this->showAgreement(false);
	 		return false;
	 	}
	 	$this->agreement->setAccepted(true);
	 	$this->agreement->setAcceptanceTime(time());
	 	$this->agreement->save();
	 	
	 	$this->ctrl->returnToParent($this);
	}
	
	private function showCourseDefinedFields()
	{
		global $ilUser;
		
	 	include_once('Modules/Course/classes/Export/class.ilCourseDefinedFieldDefinition.php');
	 	include_once('Modules/Course/classes/Export/class.ilCourseUserData.php');

		if(!count($cdf_fields = ilCourseDefinedFieldDefinition::_getFields($this->obj_id)))
		{
			return true;
		}
		
		foreach($cdf_fields as $field_obj)
		{
			$course_user_data = new ilCourseUserData($ilUser->getId(),$field_obj->getId());
			
			switch($field_obj->getType())
			{
				case IL_CDF_TYPE_SELECT:
					$this->tpl->setCurrentBlock('sel_row');

					// Workaround for mantis 9868
					$options[0] = $this->lng->txt('links_select_one');
					foreach($field_obj->getValues() as $value)
					{
						$options[$field_obj->getId().'_'.$value] = $value;
					}
					$this->tpl->setVariable('SEL_SELECT',ilUtil::formSelect($field_obj->getId().'_'.$course_user_data->getValue(),
																			'cdf['.$field_obj->getId().']',
																			$options,
																			false,
																			true));
					break;
				case IL_CDF_TYPE_TEXT:
					$this->tpl->setCurrentBlock('txt_row');
					$this->tpl->setVariable('TXT_ROW_NAME',$field_obj->getId());
					$this->tpl->setVariable('TXT_ROW_VALUE',$course_user_data->getValue());
					break;
			}
			if($field_obj->isRequired())
			{
				$this->show_required_info = true;
				$this->tpl->touchBlock('cdf_required');
			}
			
			$this->tpl->setCurrentBlock('cdf_row');
			$this->tpl->setVariable('CDF_FIELD_NAME',$field_obj->getName());
			$this->tpl->parseCurrentBlock();
		}
		$this->tpl->setCurrentBlock('cdf');
		$this->tpl->setVariable('CDF_TXT',$this->lng->txt($this->type.'_ps_cdf_info'));
		$this->tpl->parseCurrentBlock();
	}
	
		/**
	 * Check required course fields
	 *
	 * @access private
	 * 
	 */
	private function checkCourseDefinedFields()
	{
		global $ilUser;
		
		include_once('Modules/Course/classes/Export/class.ilCourseDefinedFieldDefinition.php');
		include_once('Modules/Course/classes/Export/class.ilCourseUserData.php');
		
		$all_required = true;
		foreach(ilCourseDefinedFieldDefinition::_getFields($this->obj_id) as $field_obj)
		{
			$required_given = false;
			switch($field_obj->getType())
			{
				case IL_CDF_TYPE_SELECT:
					$tmp_values = ilUtil::stripSlashes($_POST['cdf'][$field_obj->getId()]);
					$tmp_values = explode('_', $tmp_values,2);
					
					
					if(isset($tmp_values[1]))
					{
						$tmp_value = isset($tmp_values[1]) ? $tmp_values[1] : '';
						$value = '';
						foreach((array) $field_obj->getValues() as $v)
						{
							if($v == $tmp_value)
							{
								$value = $tmp_value;
								$required_given = true;
								break;
							}
						}
					}
					break;
				
				case IL_CDF_TYPE_TEXT:
					$value = ilUtil::stripSlashes($_POST['cdf'][$field_obj->getId()]);
					if($value)
					{
						$required_given = true;
					}
					break;
			}
			$course_user_data = new ilCourseUserData($ilUser->getId(),$field_obj->getId());
			$course_user_data->setValue($value);
			$course_user_data->update();
			
			if($field_obj->isRequired() and !$required_given)
			{
				$all_required = false;
			}
		}	
		return $all_required;
	}
	
	
	/**
	 * Check Agreement
	 *
	 * @access private
	 * 
	 */
	private function checkAgreement()
	{
		global $ilUser;
		
	 	if($_POST['agreed'])
	 	{
	 		return true;
	 	}
		if(!$this->privacy->confirmationRequired($this->type) and !ilCourseDefinedFieldDefinition::_hasFields($this->obj_id))
		{
			return true;
		}
	 	return false;
	}
	
	
	
	/**
	 * Read setting
	 *
	 * @access private
	 * @param
	 * 
	 */
	private function init()
	{
		global $ilUser;
		
	 	$this->required_fullfilled = ilCourseUserData::_checkRequired($ilUser->getId(),$this->obj_id);
 		$this->agreement_required = $this->agreement->agreementRequired();
	}
	
	/**
	 * Send info message
	 *
	 * @access private
	 */
	private function sendInfoMessage()
	{
		$message = '';
		if($this->agreement_required)
		{
			$message = $this->lng->txt($this->type.'_ps_agreement_req_info');
		}
		if(!$this->required_fullfilled)
		{
			if(strlen($message))
			{
				$message .= '<br />';
			}
			$message .= $this->lng->txt($this->type.'_ps_required_info');
		}
		
		if(strlen($message))
		{
			ilUtil::sendFailure($message);
		}
	}
}


?>