<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once("Services/Table/classes/class.ilTable2GUI.php");


/**
 * TableGUI class for plugins listing
 *
 * @author Alex Killing <alex.killing@gmx.de>
 * @version $Id$
 *
 * @ingroup ServicesComponent
 */
class ilPluginsTableGUI extends ilTable2GUI
{
	function ilPluginsTableGUI($a_parent_obj, $a_parent_cmd = "",
		$a_c_type, $a_c_name, $a_slot_id)
	{
		global $ilCtrl, $lng;
		
		include_once("./Services/Component/classes/class.ilPluginSlot.php");
		$this->slot = new ilPluginSlot($a_c_type, $a_c_name, $a_slot_id);
		
		parent::__construct($a_parent_obj, $a_parent_cmd);
		
		//$this->addColumn($lng->txt("cmps_module"));
		$this->addColumn($lng->txt("cmps_plugin"));
		$this->addColumn($lng->txt("cmps_basic_files"));
		$this->addColumn($lng->txt("cmps_languages"));
		$this->addColumn($lng->txt("cmps_database"));
		
		$this->setEnableHeader(true);
		$this->setFormAction($ilCtrl->getFormAction($a_parent_obj));
		$this->setRowTemplate("tpl.table_row_plugin.html",
			"Services/Component");
		$this->getPlugins();
		//$this->setDefaultOrderField("subdir");
		$this->setLimit(10000);
		
		// save options command
		//$this->addCommandButton("saveOptions", $lng->txt("cmps_save_options"));

		$this->setTitle($lng->txt("cmps_plugins"));
	}
	
	/**
	* Get pages for list.
	*/
	function getPlugins()
	{
		$plugins = $this->slot->getPluginsInformation();
//var_dump($plugins);
		$this->setData($plugins);
	}
	
	/**
	* Standard Version of Fill Row. Most likely to
	* be overwritten by derived class.
	*/
	protected function fillRow($a_set)
	{
		global $lng, $ilCtrl, $ilDB;
		
		$ilCtrl->setParameter($this->parent_obj, "ctype", $_GET["ctype"]);
		$ilCtrl->setParameter($this->parent_obj, "cname", $_GET["cname"]);
		$ilCtrl->setParameter($this->parent_obj, "slot_id", $_GET["slot_id"]);
		$ilCtrl->setParameter($this->parent_obj, "pname", $a_set["name"]);
		
		// dbupdate
		$file = ilPlugin::getDBUpdateScriptName($_GET["ctype"], $_GET["cname"],
			ilPluginSlot::lookupSlotName($_GET["ctype"], $_GET["cname"], $_GET["slot_id"]),
			$a_set["name"]);

		if (@is_file($file))
		{
			include_once("./Services/Component/classes/class.ilPluginDBUpdate.php");
			$dbupdate = new ilPluginDBUpdate($_GET["ctype"], $_GET["cname"],
				$_GET["slot_id"], $a_set["name"], $ilDB, true, "");

			// update command
/*			if ($dbupdate->getFileVersion() > $dbupdate->getCurrentVersion())
			{
				$this->tpl->setCurrentBlock("db_update_cmd");
				$this->tpl->setVariable("TXT_UPDATE_DB",
					$lng->txt("cmps_update_db"));
				$this->tpl->setVariable("HREF_UPDATE_DB",
					$ilCtrl->getLinkTarget($this->parent_obj, "updatePluginDB"));
				$this->tpl->parseCurrentBlock();
			}
*/
			
			// db version
			$this->tpl->setCurrentBlock("db_versions");
			$this->tpl->setVariable("TXT_CURRENT_VERSION",
				$lng->txt("cmps_current_version"));
			$this->tpl->setVariable("VAL_CURRENT_VERSION",
				$dbupdate->getCurrentVersion());
			$this->tpl->setVariable("TXT_FILE_VERSION",
				$lng->txt("cmps_file_version"));
			$this->tpl->setVariable("VAL_FILE_VERSION",
				$dbupdate->getFileVersion());
			$this->tpl->parseCurrentBlock();

			$this->tpl->setCurrentBlock("db_update");
			$this->tpl->setVariable("DB_UPDATE_FILE",
				"dbupdate.php");
		}
		else
		{
			$this->tpl->setCurrentBlock("db_update");
			$this->tpl->setVariable("DB_UPDATE_FILE",
				$lng->txt("cmps_no_db_update_file_available"));
		}
		$this->tpl->parseCurrentBlock();
		
		
		// language files
		$langs = ilPlugin::getAvailableLangFiles($this->slot->getPluginsDirectory()."/".
			$a_set["name"]."/lang");
		if (count($langs) == 0)
		{
			$this->tpl->setCurrentBlock("lang");
			$this->tpl->setVariable("VAL_LANG_FILE",
				$lng->txt("cmps_no_language_file_available"));
			$this->tpl->parseCurrentBlock();
		}
		foreach($langs as $lang)
		{
			$this->tpl->setCurrentBlock("lang");
			$this->tpl->setVariable("VAL_LANG_FILE",
				$lang["file"]);
			$this->tpl->parseCurrentBlock();
		}

		// activation button
		if ($a_set["activation_possible"])
		{
			$this->tpl->setCurrentBlock("activate");
			$this->tpl->setVariable("HREF_ACTIVATE",
				$ilCtrl->getLinkTarget($this->parent_obj, "activatePlugin"));
			$this->tpl->setVariable("TXT_ACTIVATE",
				$lng->txt("cmps_activate"));
			$this->tpl->parseCurrentBlock();
		}
		
		// deactivation/refresh languages button
		if ($a_set["is_active"])
		{
			// deactivate button
			$this->tpl->setCurrentBlock("deactivate");
			$this->tpl->setVariable("HREF_DEACTIVATE",
				$ilCtrl->getLinkTarget($this->parent_obj, "deactivatePlugin"));
			$this->tpl->setVariable("TXT_DEACTIVATE",
				$lng->txt("cmps_deactivate"));
			$this->tpl->parseCurrentBlock();
			
			// refresh languages button
			if (count($langs) > 0)
			{
				$this->tpl->setCurrentBlock("refresh_langs");
				$this->tpl->setVariable("HREF_REFRESH_LANGS",
					$ilCtrl->getLinkTarget($this->parent_obj, "refreshLanguages"));
				$this->tpl->setVariable("TXT_REFRESH_LANGS",
					$lng->txt("cmps_refresh"));
				$this->tpl->parseCurrentBlock();
			}

			// configure button
			if (ilPlugin::hasConfigureClass($this->slot->getPluginsDirectory(), $a_set["name"]) &&
				$ilCtrl->checkTargetClass(ilPlugin::getConfigureClassName($a_set["name"])))
			{
				$this->tpl->setCurrentBlock("configure");
				$this->tpl->setVariable("HREF_CONFIGURE",
					$ilCtrl->getLinkTargetByClass(strtolower(ilPlugin::getConfigureClassName($a_set["name"])), "configure"));
				$this->tpl->setVariable("TXT_CONFIGURE",
					$lng->txt("cmps_configure"));
				$this->tpl->parseCurrentBlock();
			}
		}

		// update button
		if ($a_set["needs_update"])
		{
			$this->tpl->setCurrentBlock("update");
			$this->tpl->setVariable("HREF_UPDATE",
				$ilCtrl->getLinkTarget($this->parent_obj, "updatePlugin"));
			$this->tpl->setVariable("TXT_UPDATE",
				$lng->txt("cmps_update"));
			$this->tpl->parseCurrentBlock();
		}
		
		if (strlen($a_set["responsible"]))
		{
			$responsibles = explode('/', $a_set["responsible_mail"]);
			$first_handled = false;
			foreach($responsibles as $responsible)
			{
				if(!strlen($responsible = trim($responsible)))
				{
					continue;
				}
				
				if($first_handled)
				{
					$this->tpl->touchBlock('plugin_responsible_sep');
				}
				
				$this->tpl->setCurrentBlock("plugin_responsible");
				$this->tpl->setVariable("VAL_PLUGIN_RESPONSIBLE_MAIL", $responsible);
				$this->tpl->parseCurrentBlock();
				$first_handled = true;
			}

			$this->tpl->setCurrentBlock("responsible_mail");
			$this->tpl->parseCurrentBlock();
			
			$this->tpl->setCurrentBlock("responsible");
			$this->tpl->setVariable("TXT_RESPONSIBLE", $lng->txt("cmps_responsible"));
			$this->tpl->setVariable("VAL_PLUGIN_RESPONSIBLE", $a_set["responsible"]);
			$this->tpl->parseCurrentBlock();
		}

		$this->tpl->setVariable("VAL_PLUGIN_NAME", $a_set["name"]);
		$this->tpl->setVariable("VAL_PLUGIN_ID", $a_set["id"]);
		$this->tpl->setVariable("TXT_PLUGIN_NAME", $lng->txt("cmps_name"));
		$this->tpl->setVariable("TXT_PLUGIN_ID", $lng->txt("cmps_id"));
		$this->tpl->setVariable("TXT_PLUGIN_VERSION", $lng->txt("cmps_version"));
		$this->tpl->setVariable("TXT_PHP_FILE", "plugin.php");
		$this->tpl->setVariable("TXT_CLASS_FILE", $lng->txt("cmps_class_file"));
		$this->tpl->setVariable("VAL_CLASS_FILE", $a_set["class_file"]);
		$this->tpl->setVariable("TXT_VERSION", $lng->txt("cmps_version"));
		$this->tpl->setVariable("VAL_PLUGIN_VERSION", $a_set["version"]);
		$this->tpl->setVariable("TXT_ILIAS_MIN", $lng->txt("cmps_ilias_min_version"));
		$this->tpl->setVariable("VAL_ILIAS_MIN", $a_set["ilias_min_version"]);
		$this->tpl->setVariable("TXT_ILIAS_MAX", $lng->txt("cmps_ilias_max_version"));
		$this->tpl->setVariable("VAL_ILIAS_MAX", $a_set["ilias_max_version"]);
		$this->tpl->setVariable("TXT_STATUS", $lng->txt("cmps_status"));
		
		if ($a_set["is_active"])
		{
			$this->tpl->setVariable("VAL_STATUS", $lng->txt("cmps_active"));
		}
		else
		{
			$r = ($a_set["inactive_reason"] != "")
				? " (".$a_set["inactive_reason"].")"
				: "";
				
			$this->tpl->setVariable("VAL_STATUS", $lng->txt("cmps_inactive").$r);
		}

		if ($a_set["plugin_php_file_status"])
		{
			$this->tpl->setVariable("VAL_PLUGIN_PHP_FILE_STATUS", $lng->txt("cmps_available"));
		}
		else
		{
			$this->tpl->setVariable("VAL_PLUGIN_PHP_FILE_STATUS", $lng->txt("cmps_missing"));
		}
		if ($a_set["class_file_status"])
		{
			$this->tpl->setVariable("VAL_CLASS_FILE_STATUS", $lng->txt("cmps_available"));
		}
		else
		{
			$this->tpl->setVariable("VAL_CLASS_FILE_STATUS", $lng->txt("cmps_missing"));
		}
	}

}
?>
