<?php

/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once("./Services/UIComponent/Explorer/classes/class.ilExplorer.php");

/*
* Explorer View for Bookmarks
*
* @author Alex Killing <alex.killing@gmx.de>
* @author Manfred Thaler <manfred.thaler@endo7.com>
* @version $Id: class.ilBookmarkExplorer.php 35228 2012-06-26 22:27:00Z akill $
* @ingroup ServicesBookmarks
*
*/
class ilBookmarkExplorer extends ilExplorer
{
	/**
	 * user_id
	 * @var int uid
	 * @access private
	 */
	var $user_id;

	/**
	 * id of root folder
	 * @var int root folder id
	 * @access private
	 */
	var $root_id;

	/**
	 * allowed object types
	 * @var array object types
	 * @access private
	 */
	var $allowed_types;

	/**
	* Constructor
	* @access	public
	* @param	string	scriptname
	* @param    int user_id
	*/
	function ilBookmarkExplorer($a_target,$a_user_id)
	{
		parent::ilExplorer($a_target);
		$this->tree = new ilTree($a_user_id);
		$this->tree->setTableNames('bookmark_tree','bookmark_data');
		$this->root_id = $this->tree->readRootId();
		$this->user_id = $a_user_id;
		$this->allowed_types= array ('bmf','dum');
		$this->enablesmallmode = false;
	}

		/**
	* Set Enable Small Mode.
	*
	* @param	boolean	$a_enablesmallmode	Enable Small Mode
	*/
	function setEnableSmallMode($a_enablesmallmode)
	{
		$this->enablesmallmode = $a_enablesmallmode;
	}

	/**
	* Get Enable Small Mode.
	*
	* @return	boolean	Enable Small Mode
	*/
	function getEnableSmallMode()
	{
		return $this->enablesmallmode;
	}


	/**
	* Overwritten method from class.Explorer.php to avoid checkAccess selects
	* recursive method
	* @access	public
	* @param	integer		parent_node_id where to start from (default=0, 'root')
	* @param	integer		depth level where to start (default=1)
	* @return	string
	*/
	function setOutput($a_parent, $a_depth = 1)
	{
		global $lng;
		static $counter = 0;

		if ($objects =  $this->tree->getChilds($a_parent,"type DESC,title"))
		{
//			var_dump("<pre>",$objects,"</pre");
			$tab = ++$a_depth - 2;

			if($a_depth < 4)
			{
				for($i=0;$i<count($objects);++$i)
				{
					//$objects[$i]["title"] = $lng->txt("mail_".$objects[$i]["title"]);
					//$objects[$i]["title"] = "TEEST";
				}
			}
			
			foreach ($objects as $key => $object)
			{
				if (!in_array($object["type"],$this->allowed_types))
				{
					continue;
				}
				
				//ask for FILTER
				if ($object["child"] != $this->root_id)
				{
					//$data = $this->tree->getParentNodeData($object["child"]);
					$parent_index = $this->getIndex($object);
				}
				// Store targets for Bookmarks
				if ($object["type"]=='bm') {
					$this->bm_targets[$object["child"]]=$object["target"];
				};
								
				if($this->root && !$counter)
				{
					$counter++;
					$object["title"] = $this->root;
					$a_depth++;
				}
				
				$this->format_options["$counter"]["parent"] = $object["parent"];
				$this->format_options["$counter"]["child"] = $object["child"];
				$this->format_options["$counter"]["title"] = $object["title"];
				$this->format_options["$counter"]["description"] = $object["description"];
				$this->format_options["$counter"]["type"] = $object["type"];
				$this->format_options["$counter"]["depth"] = $tab;
				$this->format_options["$counter"]["container"] = false;
				$this->format_options["$counter"]["visible"]	  = true;

				// Create prefix array
				for ($i = 0; $i < $tab; ++$i)
				{
					$this->format_options["$counter"]["tab"][] = 'blank';
				}
				// only if parent is expanded and visible, object is visible
				if ($object["child"] != $this->root_id  and ((!in_array($object["parent"],$this->expanded) && !$this->expand_all)
														  or !$this->format_options["$parent_index"]["visible"]))
				{
					$this->format_options["$counter"]["visible"] = false;
				}

				// if object exists parent is container
				if ($object["child"] != $this->root_id)
				{
					$this->format_options["$parent_index"]["container"] = true;

					if(!$this->expand_all)
					{
						if (in_array($object["parent"],$this->expanded))
						{
							$this->format_options["$parent_index"]["tab"][($tab-2)] = 'minus';
						}
						else
						{
							$this->format_options["$parent_index"]["tab"][($tab-2)] = 'plus';
						}
					}
				}

				++$counter;

				// Recursive
				$this->setOutput($object["child"],$a_depth);
			} //foreach
		} //if
	} //function

	/**
	* overwritten method from base class
	* @access	public
	* @param	integer obj_id
	* @param	integer array options
	* @return	string
	*/
	function formatHeader($a_obj_id,$a_option)
	{
		global $lng, $ilias;

	}

	/**
	* set the expand option
	* this value is stored in a SESSION variable to save it different view (lo view, frm view,...)
	* @access	private
	* @param	string		pipe-separated integer
	*/
	function setExpand($a_node_id)
	{
		if ($a_node_id == "")
		{
			$a_node_id = $this->root_id;
		}

		// IF ISN'T SET CREATE SESSION VARIABLE
		if(!is_array($_SESSION["mexpand"]))
		{
			$_SESSION["mexpand"] = array();
		}
		// IF $_GET["expand"] is positive => expand this node
		if($a_node_id > 0 && !in_array($a_node_id,$_SESSION["mexpand"]))
		{
			array_push($_SESSION["mexpand"],$a_node_id);
		}
		// IF $_GET["expand"] is negative => compress this node
		if($a_node_id < 0)
		{
			$key = array_keys($_SESSION["mexpand"],-(int) $a_node_id);
			unset($_SESSION["mexpand"][$key[0]]);
		}
		$this->expanded = $_SESSION["mexpand"];
	}
	/**
	* overwritten method from base class
	* get link target
	*/
	function buildLinkTarget($a_node_id, $a_type)
	{
		if ($_REQUEST['bm_link'] && $a_type == 'bmf')
		{
			$link = $_SERVER['REQUEST_URI'];
			$link = ereg_replace('bmf_id=[0-9]*', 'bmf_id=' . $a_node_id, $link);
			return $link;
		}

		switch ($a_type) {
			case 'bm':
				// return stored Bookmark target;
				return htmlentities($this->bm_targets[$a_node_id]);
				break;
			default:
				$target = (strpos($this->target, "?") === false)
					? $this->target."?"
					: $this->target."&";
				return $target.$this->target_get."=".$a_node_id.$this->params_get;
		}
	}
	/**
	* overwritten method from base class
	* buid link target
	*/
	function buildFrameTarget($a_type, $a_child = 0, $a_obj_id = 0)
	{
		switch ($a_type) {
			case 'bm':
				// return _blank for Bookmarks;
				return '_blank';
				break;
			default:
				return '';
		}
	}

	/**
	* set the alowed object types
	* @access	private
	* @param	array		arraye of object types
	*/
	function setAllowedTypes($a_types)
	{
		$this->allowed_types = $a_types;
	}
	/**
	* set details mode
	* @access	public
	* @param	string		y or n
	*/
	function setShowDetails($s_details)
	{
		$this->show_details = $s_details;
	}

	/**
	* overwritten method from base class
	* buid decription
	*/
	function buildDescription($a_desc, $a_id, $a_type)
	{
		if ($this->show_details=='y' && !empty($a_desc))
		{
			return $a_desc;

		}
		else
		{
			return "";
		}
	}
	
	function getImageAlt($a_def, $a_type, $a_obj_id)
	{
		global $lng;
		
		return $lng->txt("icon")." ".$lng->txt($a_type);
	}
	
	function addRoot($a_caption)
	{
		$this->root = $a_caption;
	}
}
?>
