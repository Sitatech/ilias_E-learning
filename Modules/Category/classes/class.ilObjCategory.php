<?php

/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once "./Services/Container/classes/class.ilContainer.php";


/** @defgroup ModulesCategory Modules/Category
 */

/**
* Class ilObjCategory
* 
* @author Stefan Meyer <meyer@leifos.com> 
* @version $Id: class.ilObjCategory.php 40448 2013-03-08 10:02:02Z jluetzen $
*
* @ingroup ModulesCategory
*/
class ilObjCategory extends ilContainer
{
	/**
	* Constructor
	* @access	public
	* @param	integer	reference_id or object_id
	* @param	boolean	treat the id as reference_id (true) or object_id (false)
	*/
	function ilObjCategory($a_id = 0,$a_call_by_reference = true)
	{
		$this->type = "cat";
		$this->ilContainer($a_id,$a_call_by_reference);
	}

	/**
	* delete category and all related data	
	*
	* @access	public
	* @return	boolean	true if all object data were removed; false if only a references were removed
	*/
	function delete()
	{
		global $ilDB,$ilAppEventHandler;
		
		// always call parent delete function first!!
		if (!parent::delete())
		{
			return false;
		}
		
		// put here category specific stuff
		include_once('./Services/User/classes/class.ilObjUserFolder.php');
		ilObjUserFolder::_updateUserFolderAssignment($this->ref_id,USER_FOLDER_ID);		

		$query = "DELETE FROM object_translation WHERE obj_id = ".$ilDB->quote($this->getId(),'integer');
		$res = $ilDB->manipulate($query);
		
		$ilAppEventHandler->raise('Modules/Category',
			'delete',
			array('object' => $this,
				'obj_id' => $this->getId()));
		
		return true;
	}
	
	/**
	* get all translations from this category
	* 
	* @access	public
	* @return	array 
	*/
	function getTranslations()
	{
		global $ilDB;
		
		$q = "SELECT * FROM object_translation WHERE obj_id = ".
			$ilDB->quote($this->getId(),'integer')." ORDER BY lang_default DESC";
		$r = $this->ilias->db->query($q);
		
		$num = 0;

		while ($row = $r->fetchRow(DB_FETCHMODE_OBJECT))
		{
			$data["Fobject"][$num]= array("title"	=> $row->title,
										  "desc"	=> $row->description,
										  "lang"	=> $row->lang_code
										  );
		$num++;
		}

		// first entry is always the default language
		$data["default_language"] = 0;

		return $data ? $data : array();	
	}
	
	// remove all Translations of current category
	function removeTranslations()
	{
		global $ilDB;
		
		$query = "DELETE FROM object_translation WHERE obj_id= ".
			$ilDB->quote($this->getId(),'integer');
		$res = $ilDB->manipulate($query);
	}

	// remove translations of current category
	function deleteTranslation($a_lang)
	{
		global $ilDB;

		$query = "DELETE FROM object_translation WHERE obj_id= ".
			$ilDB->quote($this->getId(),'integer')." AND lang_code = ".
			$ilDB->quote($a_lang, 'text');
		$res = $ilDB->manipulate($query);
	}
	
	// add a new translation to current category
	function addTranslation($a_title,$a_desc,$a_lang,$a_lang_default)
	{
		global $ilDB;
		
		if (empty($a_title))
		{
			$a_title = "NO TITLE";
		}

		$query = "INSERT INTO object_translation ".
			 "(obj_id,title,description,lang_code,lang_default) ".
			 "VALUES ".
			 "(".$ilDB->quote($this->getId(),'integer').",".
			 	$ilDB->quote($a_title,'text').",".$ilDB->quote($a_desc,'text').",".
				$ilDB->quote($a_lang,'text').",".$ilDB->quote($a_lang_default,'integer').")";
		$res = $ilDB->manipulate($query);

		return true;
	}
	
	// update a translation to current category
	function updateTranslation($a_title,$a_desc,$a_lang,$a_lang_default)
	{
		global $ilDB, $ilLog;
		
		if (empty($a_title))
		{
			$a_title = "NO TITLE";
		}

		$query = "UPDATE object_translation ".
			 "SET title = ". $ilDB->quote($a_title,'text').",".
				  "description = ".$ilDB->quote($a_desc,'text').",".
				  "lang_code = ".$ilDB->quote($a_lang,'text') . ",". 
				  "lang_default = ".$ilDB->quote($a_lang_default,'integer')." ".
			 "WHERE ".
			 " obj_id = ".$ilDB->quote($this->getId(),'integer');
		$res = $ilDB->manipulate($query);

		return true;
	}
	
	/**
	 * Clone course (no member data)
	 *
	 * @access public
	 * @param int target ref_id
	 * @param int copy id
	 * 
	 */
	public function cloneObject($a_target_id,$a_copy_id = 0)
	{
		global $ilDB,$ilUser;
		
	 	$new_obj = parent::cloneObject($a_target_id,$a_copy_id);

		/*	 done in class.ilContainer	
	 	include_once('./Services/Container/classes/class.ilContainerSortingSettings.php');
	 	ilContainerSortingSettings::_cloneSettings($this->getId(),$new_obj->getId());
	 	*/
	 	$first = true;
	 	$translations = $this->getTranslations();
	 	if(is_array($translations['Fobject']))
	 	{
		 	foreach($translations['Fobject'] as $num => $translation)
		 	{
		 		$new_obj->addTranslation($translation['title'],$translation['desc'],$translation['lang'],$first);
		 		
		 		if($first)
		 		{
		 			$first = false;
		 		}
		 	}
	 	}
	 	
		// clone icons
		$new_obj->saveIcons($this->getBigIconPath(),
			$this->getSmallIconPath(),
			$this->getTinyIconPath());
	 	
	 	
	 	return $new_obj;
	}
	
	/**
	* Add additional information to sub item, e.g. used in
	* courses for timings information etc.
	*/
	function addAdditionalSubItemInformation(&$a_item_data)
	{		
		include_once './Services/Object/classes/class.ilObjectActivation.php';
		ilObjectActivation::addAdditionalSubItemInformation($a_item_data);
	}
	
} // END class.ilObjCategory
?>