<?php
/*
	+-----------------------------------------------------------------------------+
	| ILIAS open source                                                           |
	+-----------------------------------------------------------------------------+
	| Copyright (c) 1998-2001 ILIAS open source, University of Cologne            |
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
* Class ilRbacAdmin 
*  Core functions for role based access control.
*  Creation and maintenance of Relations.
*  The main relations of Rbac are user <-> role (UR) assignment relation and the permission <-> role (PR) assignment relation.
*  This class contains methods to 'create' and 'delete' instances of the (UR) relation e.g.: assignUser(), deassignUser()
*  Required methods for the PR relation are grantPermission(), revokePermission()
*
* @author Stefan Meyer <meyer@leifos.com>
* @version $Id: class.ilRbacAdmin.php 32886 2012-01-30 10:20:03Z smeyer $
*
* @ingroup ServicesAccessControl
*/
class ilRbacAdmin
{
	/**
	* Constructor
	* @access	public
	*/
	function ilRbacAdmin()
	{
		global $ilDB,$ilErr,$ilias;

		// set db & error handler
		(isset($ilDB)) ? $this->ilDB =& $ilDB : $this->ilDB =& $ilias->db;
		
		if (!isset($ilErr))
		{
			$ilErr = new ilErrorHandling();
			$ilErr->setErrorHandling(PEAR_ERROR_CALLBACK,array($ilErr,'errorHandler'));
		}
		else
		{
			$this->ilErr =& $ilErr;
		}
	}

	/**
	* deletes a user from rbac_ua
	*  all user <-> role relations are deleted
	* @access	public
	* @param	integer	user_id
	* @return	boolean	true on success
	*/
	function removeUser($a_usr_id)
	{
		global $ilDB;
		
		if (!isset($a_usr_id))
		{
			$message = get_class($this)."::removeUser(): No usr_id given!";
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}

		$query = "DELETE FROM rbac_ua WHERE usr_id = ".$ilDB->quote($a_usr_id,'integer');
		$res = $ilDB->manipulate($query);
		
		return true;
	}

	/**
	* Deletes a role and deletes entries in object_data, rbac_pa, rbac_templates, rbac_ua, rbac_fa
	* @access	public
	* @param	integer		obj_id of role (role_id)
	* @param	integer		ref_id of role folder (ref_id)
	* @return	boolean     true on success
	*/
	function deleteRole($a_rol_id,$a_ref_id)
	{
		global $lng,$ilDB;

		if (!isset($a_rol_id) or !isset($a_ref_id))
		{
			$message = get_class($this)."::deleteRole(): Missing parameter! role_id: ".$a_rol_id." ref_id of role folder: ".$a_ref_id;
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}

		// exclude system role from rbac
		if ($a_rol_id == SYSTEM_ROLE_ID)
		{
			$this->ilErr->raiseError($lng->txt("msg_sysrole_not_deletable"),$this->ilErr->MESSAGE);
		}

		include_once('Services/LDAP/classes/class.ilLDAPRoleGroupMapping.php');
		$mapping = ilLDAPRoleGroupMapping::_getInstance();
		$mapping->deleteRole($a_rol_id); 


		// TODO: check assigned users before deletion
		// This is done in ilObjRole. Should be better moved to this place?
		
		// delete user assignements
		$query = "DELETE FROM rbac_ua ".
			 "WHERE rol_id = ".$ilDB->quote($a_rol_id,'integer');
		$res = $ilDB->manipulate($query);
		
		// delete permission assignments
		$query = "DELETE FROM rbac_pa ".
			 "WHERE rol_id = ".$ilDB->quote($a_rol_id,'integer')." ";
		$res = $ilDB->manipulate($query);
		
		//delete rbac_templates and rbac_fa
		$this->deleteLocalRole($a_rol_id);
		
		return true;
	}

	/**
	* Deletes a template from role folder and deletes all entries in rbac_templates, rbac_fa
 	* @access	public
	* @param	integer		object_id of role template
	* @return	boolean
	*/
	function deleteTemplate($a_obj_id)
	{
		global $ilDB;
		
		if (!isset($a_obj_id))
		{
			$message = get_class($this)."::deleteTemplate(): No obj_id given!";
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}

		$query = 'DELETE FROM rbac_templates '.
			 'WHERE rol_id = '.$ilDB->quote($a_obj_id,'integer');
		$res = $ilDB->manipulate($query);

		$query = 'DELETE FROM rbac_fa '.
			'WHERE rol_id = '.$ilDB->quote($a_obj_id,'integer');
		$res = $ilDB->manipulate($query);

		return true;
	}

	/**
	* Deletes a local role and entries in rbac_fa and rbac_templates
	* @access	public
	* @param	integer	object_id of role
	* @param	integer	ref_id of role folder (optional)
	* @return	boolean true on success
	*/
	function deleteLocalRole($a_rol_id,$a_ref_id = 0)
	{
		global $ilDB;
		
		if (!isset($a_rol_id))
		{
			$message = get_class($this)."::deleteLocalRole(): Missing parameter! role_id: '".$a_rol_id."'";
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}
		
		// exclude system role from rbac
		if ($a_rol_id == SYSTEM_ROLE_ID)
		{
			return true;
		}

		if ($a_ref_id != 0)
		{
			$clause = 'AND parent = '.$ilDB->quote($a_ref_id,'integer').' ';
		}
		
		$query = 'DELETE FROM rbac_fa '.
			 'WHERE rol_id = '.$ilDB->quote($a_rol_id,'integer').' '.
			 $clause;
		$res = $ilDB->manipulate($query);

		$query = 'DELETE FROM rbac_templates '.
			 'WHERE rol_id = '.$ilDB->quote($a_rol_id,'integer').' '.
			 $clause;
		$res = $ilDB->manipulate($query);
		return true;
	}


	/**
	* Assigns an user to a role. Update of table rbac_ua
	* TODO: remove deprecated 3rd parameter sometime
	* @access	public
	* @param	integer	object_id of role
	* @param	integer	object_id of user
	* @param	boolean	true means default role (optional
	* @return	boolean
	*/
	function assignUser($a_rol_id,$a_usr_id,$a_default = false)
	{
		global $ilDB,$rbacreview;
		
		if (!isset($a_rol_id) or !isset($a_usr_id))
		{
			$message = get_class($this)."::assignUser(): Missing parameter! role_id: ".$a_rol_id." usr_id: ".$a_usr_id;
			#$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}
		
		// check if already assigned user id and role_id
		$alreadyAssigned = $rbacreview->isAssigned($a_usr_id,$a_rol_id);	
		
		// enhanced: only if we haven't had this role for this user
		if (!$alreadyAssigned) 
		{
			$query = "INSERT INTO rbac_ua (usr_id, rol_id) ".
			 "VALUES (".$ilDB->quote($a_usr_id,'integer').",".$ilDB->quote($a_rol_id,'integer').")";
			$res = $ilDB->manipulate($query);
		
			include_once 'Services/AccessControl/classes/class.ilRoleDesktopItem.php';
	
			$role_desk_item_obj =& new ilRoleDesktopItem($a_rol_id);
			
			if(is_object($tmp_user = ilObjectFactory::getInstanceByObjId($a_usr_id,false)))
			{
				foreach($role_desk_item_obj->getAll() as $item_data)
				{
					if(!$tmp_user->isDesktopItem($item_data['item_id'],$item_data['item_type']))
					{
						$tmp_user->addDesktopItem($item_data['item_id'],$item_data['item_type']);
					}
				}
			}
		}
		
		include_once('Services/LDAP/classes/class.ilLDAPRoleGroupMapping.php');
		$mapping = ilLDAPRoleGroupMapping::_getInstance();
		$mapping->assign($a_rol_id,$a_usr_id); 
		
		return true;
	}

	/**
	* Deassigns a user from a role. Update of table rbac_ua
	* @access	public
	* @param	integer	object id of role
	* @param	integer	object id of user
	* @return	boolean	true on success
	*/
	function deassignUser($a_rol_id,$a_usr_id)
	{
		global $ilDB;
		
		if (!isset($a_rol_id) or !isset($a_usr_id))
		{
			$message = get_class($this)."::deassignUser(): Missing parameter! role_id: ".$a_rol_id." usr_id: ".$a_usr_id;
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}

		$query = "DELETE FROM rbac_ua ".
			 "WHERE usr_id = ".$ilDB->quote($a_usr_id,'integer')." ".
			 "AND rol_id = ".$ilDB->quote($a_rol_id,'integer')." ";
		$res = $ilDB->manipulate($query);
		
		include_once('Services/LDAP/classes/class.ilLDAPRoleGroupMapping.php');
		$mapping = ilLDAPRoleGroupMapping::_getInstance();
		$mapping->deassign($a_rol_id,$a_usr_id); 
		
		return true;
	}

	/**
	* Grants a permission to an object and a specific role. Update of table rbac_pa
	* @access	public
	* @param	integer	object id of role
	* @param	array	array of operation ids
	* @param	integer	reference id of that object which is granted the permissions
	* @return	boolean
	*/
	function grantPermission($a_rol_id,$a_ops,$a_ref_id)
	{
		global $ilDB;
		
		if (!isset($a_rol_id) or !isset($a_ops) or !isset($a_ref_id))
		{
			$this->ilErr->raiseError(get_class($this)."::grantPermission(): Missing parameter! ".
							"role_id: ".$a_rol_id." ref_id: ".$a_ref_id." operations: ",$this->ilErr->WARNING);
		}

		if (!is_array($a_ops))
		{
			$this->ilErr->raiseError(get_class($this)."::grantPermission(): Wrong datatype for operations!",
									 $this->ilErr->WARNING);
		}
		
		/*
		if (count($a_ops) == 0)
		{
			return false;
		}
		*/
		// exclude system role from rbac
		if ($a_rol_id == SYSTEM_ROLE_ID)
		{
			return true;
		}
		
		// convert all values to integer
		foreach ($a_ops as $key => $operation)
		{
			$a_ops[$key] = (int) $operation;
		}

		// Serialization des ops_id Arrays
		$ops_ids = serialize($a_ops);
		
		$query = 'DELETE FROM rbac_pa '.
			'WHERE rol_id = %s '.
			'AND ref_id = %s';
		$res = $ilDB->queryF($query,array('integer','integer'),
			array($a_rol_id,$a_ref_id));
			
		if(!count($a_ops))
		{
			return false;
		}

		$query = "INSERT INTO rbac_pa (rol_id,ops_id,ref_id) ".
			 "VALUES ".
			 "(".$ilDB->quote($a_rol_id,'integer').",".$ilDB->quote($ops_ids,'text').",".$ilDB->quote($a_ref_id,'integer').")";
		$res = $ilDB->manipulate($query);

		return true;
	}

	/**
	* Revokes permissions of an object of one role. Update of table rbac_pa.
	* Revokes all permission for all roles for that object (with this reference).
	* When a role_id is given this applies only to that role
	* @access	public
	* @param	integer	reference id of object where permissions should be revoked
	* @param	integer	role_id (optional: if you want to revoke permissions of object only for a specific role)
	* @return	boolean
	*/
	function revokePermission($a_ref_id,$a_rol_id = 0,$a_keep_protected = true)
	{
		global $rbacreview,$log,$ilDB,$ilLog;

		if (!isset($a_ref_id))
		{
			$ilLog->logStack();
			$message = get_class($this)."::revokePermission(): Missing parameter! ref_id: ".$a_ref_id;
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}
#$log->write("ilRBACadmin::revokePermission(), 0");

		// bypass protected status of roles
		if ($a_keep_protected != true)
		{
			// exclude system role from rbac
			if ($a_rol_id == SYSTEM_ROLE_ID)
			{
				return true;
			}
	
			if ($a_rol_id)
			{
				$and1 = " AND rol_id = ".$ilDB->quote($a_rol_id,'integer')." ";
			}
			else
			{
				$and1 = "";
			}
	
			$query = "DELETE FROM rbac_pa ".
				 "WHERE ref_id = ".$ilDB->quote($a_ref_id,'integer').
				 $and1;
			
			$res = $ilDB->manipulate($query);
	
			return true;
		}
		
		// consider protected status of roles
	
		// in any case, get all roles in scope first
		$roles_in_scope = $rbacreview->getParentRoleIds($a_ref_id);

		if (!$a_rol_id)
		{
#$log->write("ilRBACadmin::revokePermission(), 1");

			$role_ids = array();
			
			foreach ($roles_in_scope as $role)
			{
				if ($role['protected'] == true)
				{
					continue;
				}
				
				$role_ids[] = $role['obj_id'];
			}
			
			// return if no role in array
			if (!$role_ids)
			{
				return true;
			}
			
			$query = 'DELETE FROM rbac_pa '.
				'WHERE '.$ilDB->in('rol_id',$role_ids,false,'integer').' '.
				'AND ref_id = '.$ilDB->quote($a_ref_id,'integer');
			$res = $ilDB->manipulate($query);
		}
		else
		{
#$log->write("ilRBACadmin::revokePermission(), 2");	
			// exclude system role from rbac
			if ($a_rol_id == SYSTEM_ROLE_ID)
			{
				return true;
			}
			
			// exclude protected permission settings from revoking
			if ($roles_in_scope[$a_rol_id]['protected'] == true)
			{
				return true;
			}

			$query = "DELETE FROM rbac_pa ".
				 "WHERE ref_id = ".$ilDB->quote($a_ref_id,'integer')." ".
				 "AND rol_id = ".$ilDB->quote($a_rol_id,'integer')." ";
			$res = $ilDB->manipulate($query);
		}

		return true;
	}
	
	/**
	 * Revoke subtree permissions
	 * @param object $a_ref_id
	 * @param object $a_role_id
	 * @return 
	 */
	public function revokeSubtreePermissions($a_ref_id,$a_role_id)
	{
		global $ilDB;
		
		$query = "DELETE FROM rbac_pa ".
			"WHERE ref_id IN ".
			"(SELECT child FROM tree WHERE ".
				"lft >= (SELECT lft FROM tree WHERE child = ".$ilDB->quote($a_ref_id,'integer')." ) AND ".
				"rgt <= (SELECT rgt FROM tree WHERE child = ".$ilDB->quote($a_ref_id,'integer')." ) ".
			") ".
			"AND rol_id = ".$ilDB->quote($a_role_id,'integer');
		
		$ilDB->manipulate($query);
		return true;
	}
	
	/**
	 * Delete all template permissions of subtree nodes
	 * @param object $a_ref_id
	 * @param object $a_rol_id
	 * @return 
	 */
	public function deleteSubtreeTemplates($a_ref_id,$a_rol_id)
	{
		global $ilDB;
		
		$query = "DELETE FROM rbac_templates ".
			"WHERE parent IN ".
			"(SELECT child FROM tree WHERE ".
				"lft >= (SELECT lft FROM tree WHERE child = ".$ilDB->quote($a_ref_id,'integer')." ) AND ".
				"rgt <= (SELECT rgt FROM tree WHERE child = ".$ilDB->quote($a_ref_id,'integer')." ) ".
			") ".
			"AND rol_id = ".$ilDB->quote($a_rol_id,'integer');
		
		$ilDB->manipulate($query);

		$query = "DELETE FROM rbac_fa ".
			"WHERE parent IN ".
			"(SELECT child FROM tree WHERE ".
				"lft >= (SELECT lft FROM tree WHERE child = ".$ilDB->quote($a_ref_id,'integer')." ) AND ".
				"rgt <= (SELECT rgt FROM tree WHERE child = ".$ilDB->quote($a_ref_id,'integer')." ) ".
			") ".
			"AND rol_id = ".$ilDB->quote($a_rol_id,'integer');
			
		
		$ilDB->manipulate($query);

		return true;
	}

	/**
	* Revokes permissions of a LIST of objects of ONE role. Update of table rbac_pa.
	* @access	public
	* @param	array	list of reference_ids to revoke permissions
	* @param	integer	role_id
	* @return	boolean
	*/
	function revokePermissionList($a_ref_ids,$a_rol_id)
	{
		global $ilDB;
		
		if (!isset($a_ref_ids) or !is_array($a_ref_ids))
		{
			$message = get_class($this)."::revokePermissionList(): Missing parameter or parameter is not an array! reference_list: ".var_dump($a_ref_ids);
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}

		if (!isset($a_rol_id))
		{
			$message = get_class($this)."::revokePermissionList(): Missing parameter! rol_id: ".$a_rol_id;
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}

		// exclude system role from rbac
		if ($a_rol_id == SYSTEM_ROLE_ID)
		{
			return true;
		}

		$query = "DELETE FROM rbac_pa ".
			 "WHERE ".$ilDB->in('ref_id',$a_ref_ids,false,'integer').' '.
			 "AND rol_id = ".$ilDB->quote($a_rol_id,'integer');
		$res = $ilDB->manipulate($query);

		return true;
	}
	
	/**
	 * Copies template permissions and permission of one role to another.
	 * 
	 * @access	public
	 * @param	integer		$a_source_id		role_id source
	 * @param	integer		$a_source_parent	parent_id source
	 * @param	integer		$a_dest_parent		parent_id destination
	 * @param	integer		$a_dest_id			role_id destination
	 * @return	boolean 
	 */
	public function copyRolePermissions($a_source_id,$a_source_parent,$a_dest_parent,$a_dest_id,$a_consider_protected = true)
	{
		global $tree,$rbacreview;
		
		// Copy template permissions
		$this->copyRoleTemplatePermissions($a_source_id,$a_source_parent,$a_dest_parent,$a_dest_id,$a_consider_protected);
		
		$source_obj = $tree->getParentId($a_source_parent);
		$target_obj = $tree->getParentId($a_dest_parent);
		$ops = $rbacreview->getRoleOperationsOnObject($a_source_id,$source_obj);

		$this->revokePermission($target_obj,$a_dest_id);
		$this->grantPermission($a_dest_id,$ops,$target_obj);
		return true;
	}

	/**
	* Copies template permissions of one role to another.
	* It's also possible to copy template permissions from/to RoleTemplateObject
	* @access	public
	* @param	integer		$a_source_id		role_id source
	* @param	integer		$a_source_parent	parent_id source
	* @param	integer		$a_dest_parent		parent_id destination
 	* @param	integer		$a_dest_id			role_id destination
	* @return	boolean 
	*/
	function copyRoleTemplatePermissions($a_source_id,$a_source_parent,$a_dest_parent,$a_dest_id,$a_consider_protected = true)
	{
		global $rbacreview,$ilDB;

		if (!isset($a_source_id) or !isset($a_source_parent) or !isset($a_dest_id) or !isset($a_dest_parent))
		{
			$message = __METHOD__.": Missing parameter! source_id: ".$a_source_id.
					   " source_parent_id: ".$a_source_parent.
					   " dest_id : ".$a_dest_id.
					   " dest_parent_id: ".$a_dest_parent;
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}
		
		// exclude system role from rbac
		if ($a_dest_id == SYSTEM_ROLE_ID)
		{
			return true;
		}

		// Read operations
		$query = 'SELECT * FROM rbac_templates '.
			 'WHERE rol_id = '.$ilDB->quote($a_source_id,'integer').' '.
			 'AND parent = '.$ilDB->quote($a_source_parent,'integer');
		$res = $ilDB->query($query);
		$operations = array();
		$rownum = 0;
		while ($row = $ilDB->fetchObject($res))
		{
			$operations[$rownum]['type'] = $row->type;
			$operations[$rownum]['ops_id'] = $row->ops_id;
			$rownum++;
		}

		// Delete target permissions
		$query = 'DELETE FROM rbac_templates WHERE rol_id = '.$ilDB->quote($a_dest_id,'integer').' '.
			'AND parent = '.$ilDB->quote($a_dest_parent,'integer');
		$res = $ilDB->manipulate($query);
		
		foreach($operations as $row => $op)
		{
			$query = 'INSERT INTO rbac_templates (rol_id,type,ops_id,parent) '.
				 'VALUES ('.
				 $ilDB->quote($a_dest_id,'integer').",".
				 $ilDB->quote($op['type'],'text').",".
				 $ilDB->quote($op['ops_id'],'integer').",".
				 $ilDB->quote($a_dest_parent,'integer').")";
			$ilDB->manipulate($query);
		}
		
		// copy also protection status if applicable
		if ($a_consider_protected == true)
		{
			if ($rbacreview->isProtected($a_source_parent,$a_source_id))
			{
				$this->setProtected($a_dest_parent,$a_dest_id,'y');
			}
		}

		return true;
	}
	/**
	* Copies the intersection of the template permissions of two roles to a
        * third role.
        *
	* @access	public
	* @param	integer		$a_source1_id		role_id source
	* @param	integer		$a_source1_parent	parent_id source
	* @param	integer		$a_source2_id		role_id source
	* @param	integer		$a_source2_parent	parent_id source
 	* @param	integer		$a_dest_id			role_id destination
	* @param	integer		$a_dest_parent		parent_id destination
	* @return	boolean 
	*/
	function copyRolePermissionIntersection($a_source1_id,$a_source1_parent,$a_source2_id,$a_source2_parent,$a_dest_parent,$a_dest_id)
	{
		global $rbacreview,$ilDB;
		
		if (!isset($a_source1_id) or !isset($a_source1_parent) 
		or !isset($a_source2_id) or !isset($a_source2_parent) 
                or !isset($a_dest_id) or !isset($a_dest_parent))
		{
			$message = get_class($this)."::copyRolePermissionIntersection(): Missing parameter! source1_id: ".$a_source1_id.
					   " source1_parent: ".$a_source1_parent.
					   " source2_id: ".$a_source2_id.
					   " source2_parent: ".$a_source2_parent.
					   " dest_id: ".$a_dest_id.
					   " dest_parent_id: ".$a_dest_parent;
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}
		
		// exclude system role from rbac
		if ($a_dest_id == SYSTEM_ROLE_ID)
		{
			return true;
		}
		
		if ($rbacreview->isProtected($a_source2_parent,$a_source2_id))
		{
			$GLOBALS['ilLog']->write(__METHOD__.': Role is protected');
			return true;
		}

		$query = "SELECT s1.type, s1.ops_id ".
                        "FROM rbac_templates s1, rbac_templates s2 ".
                        "WHERE s1.rol_id = ".$ilDB->quote($a_source1_id,'integer')." ".
                        "AND s1.parent = ".$ilDB->quote($a_source1_parent,'integer')." ".
                        "AND s2.rol_id = ".$ilDB->quote($a_source2_id,'integer')." ".
                        "AND s2.parent = ".$ilDB->quote($a_source2_parent,'integer')." ".
                        "AND s1.type = s2.type ".
                        "AND s1.ops_id = s2.ops_id";
		$res = $ilDB->query($query);
		$operations = array();
		$rowNum = 0;
		while($row = $res->fetchRow(DB_FETCHMODE_OBJECT))
		{
			$operations[$rowNum]['type'] = $row->type;
			$operations[$rowNum]['ops_id'] = $row->ops_id;

			$rowNum++;
		}

		// Delete template permissions of target
		$query = 'DELETE FROM rbac_templates WHERE rol_id = '.$ilDB->quote($a_dest_id,'integer').' '.
			'AND parent = '.$ilDB->quote($a_dest_parent,'integer');
		$res = $ilDB->manipulate($query);

		$query = 'INSERT INTO rbac_templates (rol_id,type,ops_id,parent) '.
			'VALUES (?,?,?,?)';
		$sta = $ilDB->prepareManip($query,array('integer','text','integer','integer'));
		foreach($operations as $key => $set)
		{
			$ilDB->execute($sta,array(
				$a_dest_id,
				$set['type'],
				$set['ops_id'],
				$a_dest_parent));
		}
		return true;
	}

	/**
	 *
	 * @global <type> $ilDB
	 * @param <type> $a_source1_id
	 * @param <type> $a_source1_parent
	 * @param <type> $a_source2_id
	 * @param <type> $a_source2_parent
	 * @param <type> $a_dest_id
	 * @param <type> $a_dest_parent
	 * @return <type> 
	 */
	public function copyRolePermissionUnion(
		$a_source1_id,
		$a_source1_parent,
		$a_source2_id,
		$a_source2_parent,
		$a_dest_id,
		$a_dest_parent)
	{
		global $ilDB, $rbacreview;

		$this->copyRoleTemplatePermissions(
			$a_source1_id,
			$a_source1_parent,
			$a_dest_parent,
			$a_dest_id
		);

		$s1_ops = $rbacreview->getAllOperationsOfRole($a_source1_id,$a_source1_parent);
		$s2_ops = $rbacreview->getAlloperationsOfRole($a_source2_id,$a_source2_parent);

		foreach($s2_ops as $type => $ops)
		{
			foreach($ops as $op)
			{
				if(!isset($s1_ops[$type]) or !in_array($op, $s1_ops[$type]))
				{
					$query = 'INSERT INTO rbac_templates (rol_id,type,ops_id,parent) '.
						'VALUES( '.
						$ilDB->quote($a_dest_id,'integer').', '.
						$ilDB->quote($type,'text').', '.
						$ilDB->quote($op,'integer').', '.
						$ilDB->quote($a_dest_parent,'integer').' '.
						')';
					$ilDB->manipulate($query);
				}
			}
		}
		return true;
	}

	
	/**
	* Deletes all entries of a template. If an object type is given for third parameter only
	* the entries for that object type are deleted
	* Update of table rbac_templates.
	* @access	public
	* @param	integer		object id of role
	* @param	integer		ref_id of role folder
	* @param	string		object type (optional)
	* @return	boolean
	*/
	function deleteRolePermission($a_rol_id,$a_ref_id,$a_type = false)
	{
		global $ilDB;
		
		if (!isset($a_rol_id) or !isset($a_ref_id))
		{
			$message = get_class($this)."::deleteRolePermission(): Missing parameter! role_id: ".$a_rol_id." ref_id: ".$a_ref_id;
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}

		// exclude system role from rbac
		if ($a_rol_id == SYSTEM_ROLE_ID)
		{
			return true;
		}
		
		if ($a_type !== false)
		{
			$and_type = " AND type=".$ilDB->quote($a_type,'text')." ";
		}

		$query = 'DELETE FROM rbac_templates '.
			 'WHERE rol_id = '.$ilDB->quote($a_rol_id,'integer').' '.
			 'AND parent = '.$ilDB->quote($a_ref_id,'integer').' '.
			 $and_type;
			 
		$res = $ilDB->manipulate($query);

		return true;
	}
	
	/**
	* Inserts template permissions in rbac_templates for an specific object type. 
	*  Update of table rbac_templates
	* @access	public
	* @param	integer		role_id
	* @param	string		object type
	* @param	array		operation_ids
	* @param	integer		ref_id of role folder object
	* @return	boolean
	*/
	function setRolePermission($a_rol_id,$a_type,$a_ops,$a_ref_id)
	{
		global $ilDB;
		
		if (!isset($a_rol_id) or !isset($a_type) or !isset($a_ops) or !isset($a_ref_id))
		{
			$message = get_class($this)."::setRolePermission(): Missing parameter!".
					   " role_id: ".$a_rol_id.
					   " type: ".$a_type.
					   " operations: ".$a_ops.
					   " ref_id: ".$a_ref_id;
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}

		if (!is_string($a_type) or empty($a_type))
		{
			$message = get_class($this)."::setRolePermission(): a_type is no string or empty!";
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}

		if (!is_array($a_ops) or empty($a_ops))
		{
			$message = get_class($this)."::setRolePermission(): a_ops is no array or empty!";
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}
		
		// exclude system role from rbac
		if ($a_rol_id == SYSTEM_ROLE_ID)
		{
			return true;
		}

		$query = 'INSERT INTO rbac_templates (rol_id,type,ops_id,parent) '.
			'VALUES (?,?,?,?)';
		$sta = $ilDB->prepareManip($query,array('integer','text','integer','integer'));
		foreach ($a_ops as $op)
		{
			$res = $ilDB->execute($sta,array(
				$a_rol_id,
				$a_type,
				$op,
				$a_ref_id
			));
		}

		return true;
	}

	/**
	* Assigns a role to an role folder
	* A role folder is an object to store roles.
	* Every role is assigned to minimum one role folder
	* If the inheritance of a role is stopped, a new role template will created, and the role is assigned to
	* minimum two role folders. All roles with stopped inheritance need the flag '$a_assign = false'
	*
	* @access	public
	* @param	integer		object id of role
	* @param	integer		ref_id of role folder
	* @param	string		assignable('y','n'); default: 'y'
	* @return	boolean
	*/
	function assignRoleToFolder($a_rol_id,$a_parent,$a_assign = "y")
	{
		global $ilDB,$rbacreview;
		
		if (!isset($a_rol_id) or !isset($a_parent))
		{
			$message = get_class($this)."::assignRoleToFolder(): Missing Parameter!".
					   " role_id: ".$a_rol_id.
					   " parent_id: ".$a_parent.
					   " assign: ".$a_assign;
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}

		// exclude system role from rbac
		if ($a_rol_id == SYSTEM_ROLE_ID)
		{
			return true;
		}
		
		// if a wrong value is passed, always set assign to "n"
		if ($a_assign != "y")
		{
			$a_assign = "n";
		}

		$query = sprintf('INSERT INTO rbac_fa (rol_id, parent, assign, protected) '.
			'VALUES (%s,%s,%s,%s)',
			$ilDB->quote($a_rol_id,'integer'),
			$ilDB->quote($a_parent,'integer'),
			$ilDB->quote($a_assign,'text'),
			$ilDB->quote('n','text'));
		$res = $ilDB->manipulate($query);

		return true;
	}

	/**
	* Assign an existing operation to an object
	*  Update of rbac_ta.
	* @access	public
	* @param	integer	object type
	* @param	integer	operation_id
	* @return	boolean
	*/
	function assignOperationToObject($a_type_id,$a_ops_id)
	{
		global $ilDB;
		
		if (!isset($a_type_id) or !isset($a_ops_id))
		{
			$message = get_class($this)."::assignOperationToObject(): Missing parameter!".
					   "type_id: ".$a_type_id.
					   "ops_id: ".$a_ops_id;
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}

		$query = "INSERT INTO rbac_ta (typ_id, ops_id) ".
			 "VALUES(".$ilDB->quote($a_type_id,'integer').",".$ilDB->quote($a_ops_id,'integer').")";
		$res = $ilDB->manipulate($query);
		return true;
	}

	/**
	* Deassign an existing operation from an object 
	* Update of rbac_ta
	* @access	public
	* @param	integer	object type
	* @param	integer	operation_id
	* @return	boolean
	*/
	function deassignOperationFromObject($a_type_id,$a_ops_id)
	{
		global $ilDB;
		
		if (!isset($a_type_id) or !isset($a_ops_id))
		{
			$message = get_class($this)."::deassignPermissionFromObject(): Missing parameter!".
					   "type_id: ".$a_type_id.
					   "ops_id: ".$a_ops_id;
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}

		$query = "DELETE FROM rbac_ta ".
			 "WHERE typ_id = ".$ilDB->quote($a_type_id,'integer')." ".
			 "AND ops_id = ".$ilDB->quote($a_ops_id,'integer');
		$res = $ilDB->manipulate($query);
	
		return true;
	}
	
	function setProtected($a_ref_id,$a_role_id,$a_value)
	{
		global $ilDB;
		
		// ref_id not used yet. protected permission acts 'global' for each role, 
		// regardless of any broken inheritance before
		$query = 'UPDATE rbac_fa '.
			'SET protected = '.$ilDB->quote($a_value,'text').' '.
			'WHERE rol_id = '.$ilDB->quote($a_role_id,'integer');
		$res = $ilDB->manipulate($query);
		return true;
	}
	
	/**
	 * Copy local roles
	 * This method creates a copy of all local role.
	 * Note: auto generated roles are excluded
	 *
	 * @access public
	 * @param int source id of object (not role folder)
	 * @param int target id of object
	 * 
	 */
	public function copyLocalRoles($a_source_id,$a_target_id)
	{
	 	global $rbacreview,$ilLog,$ilObjDataCache;
	 	
	 	$source_rolf = $rbacreview->getRoleFolderIdOfObject($a_source_id);
	 	$target_rolf = $rbacreview->getRoleFolderIdOfObject($a_target_id);

	 	if(!$source_rolf)
	 	{
	 		// Nothing to do
	 		return true;
	 	}
	 	$real_local = array();
	 	foreach($rbacreview->getRolesOfRoleFolder($source_rolf,false) as $role_data)
	 	{
	 		$title = $ilObjDataCache->lookupTitle($role_data);
	 		if(substr($title,0,3) == 'il_')
	 		{
	 			continue;
	 		}
	 		$real_local[] = $role_data;
	 	}
	 	if(!count($real_local))
	 	{
	 		return true;
	 	}
	 	// Create role folder
	 	if(!$target_rolf)
	 	{
	 		$tmp_obj = ilObjectFactory::getInstanceByRefId($a_target_id,false);
	 		if(!is_object($tmp_obj))
	 		{
	 			return false;
	 		}
	 		$rolf = $tmp_obj->createRoleFolder();
	 		$target_rolf = $rolf->getRefId();
	 		$ilLog->write(__METHOD__.': Created new role folder with id '.$rolf->getRefId());
	 	}
	 	foreach($real_local as $role)
	 	{
			include_once ("./Services/AccessControl/classes/class.ilObjRole.php");
	 		$orig = new ilObjRole($role);
	 		$orig->read();
	 		
	 		$ilLog->write(__METHOD__.': Start copying of role '.$orig->getTitle());
			$roleObj = new ilObjRole();
			$roleObj->setTitle($orig->getTitle());
			$roleObj->setDescription($orig->getDescription());
			$roleObj->setImportId($orig->getImportId());
			$roleObj->create();
			
			$this->assignRoleToFolder($roleObj->getId(),$target_rolf,"y");
			$this->copyRolePermissions($role,$source_rolf,$target_rolf,$roleObj->getId(),true);
	 		$ilLog->write(__METHOD__.': Added new local role, id '.$roleObj->getId());
	 	}
	 	
	}
	
	/**
	 * Adjust permissions of moved objects
	 * - Delete permissions of parent roles that do not exist in new context
	 * - Delete role templates of parent roles that do not exist in new context
	 * - Add permissions for parent roles that did not exist in old context
	 *
	 * @access public
	 * @param int ref id of moved object
	 * @param int ref_id of old parent
	 * 
	 */
	public function adjustMovedObjectPermissions($a_ref_id,$a_old_parent)
	{
		global $rbacreview,$tree,$ilLog;
		
		$new_parent = $tree->getParentId($a_ref_id);
		$old_context_roles = $rbacreview->getParentRoleIds($a_old_parent,false);
		$new_context_roles = $rbacreview->getParentRoleIds($new_parent,false);

		$for_addition = $for_deletion = array();
		foreach($new_context_roles as $new_role_id => $new_role)
		{
			if(!isset($old_context_roles[$new_role_id]))
			{
				$for_addition[$new_role_id] = $new_role;
			}
			elseif($new_role['parent'] != $old_context_roles[$new_role_id]['parent'])
			{
				// handle stopped inheritance
				$for_deletion[$new_role_id] = $new_role;
				$for_addition[$new_role_id] = $new_role;
			}
		}
		foreach($old_context_roles as $old_role_id => $old_role)
		{
			if(!isset($new_context_roles[$old_role_id]))
			{
				$for_deletion[$old_role_id] = $old_role;
			}
		}
		
		if(!count($for_deletion) and !count($for_addition))
		{
			return true;
		}

		include_once "Services/AccessControl/classes/class.ilRbacLog.php";
		$rbac_log_active = ilRbacLog::isActive();
		if($rbac_log_active)
		{
			$role_ids = array_unique(array_merge(array_keys($for_deletion), array_keys($for_addition)));
		}
		
		foreach($nodes = $tree->getSubTree($node_data = $tree->getNodeData($a_ref_id),true) as $node_data)
		{
			$node_id = $node_data['child'];

			if($rbac_log_active)
			{
				$log_old = ilRbacLog::gatherFaPa($node_id, $role_ids);
			}
			
			// If $node_data['type'] is not set, this means there is a tree entry without
			// object_reference and/or object_data entry
			// Continue in this case
			if(!$node_data['type'])
			{
				$ilLog->write(__METHOD__.': No type give. Choosing next tree entry.');
				continue;
			}
			
			if(!$node_id)
			{
				$ilLog->write(__METHOD__.': Missing subtree node_id');
				continue;
			}
			
			foreach($for_deletion as $role_id => $role_data)
			{
				if($rolf_id = $rbacreview->getRoleFolderIdOfObject($node_id))
				{
					$this->deleteLocalRole($role_id,$rolf_id);
				}
				$this->revokePermission($node_id,$role_id,false);
//var_dump("<pre>",'REVOKE',$role_id,$node_id,$rolf_id,"</pre>");
			}
			foreach($for_addition as $role_id => $role_data)
			{
				$this->grantPermission(
					$role_id,
					$ops = $rbacreview->getOperationsOfRole($role_id,$node_data['type'],$role_data['parent']),
					$node_id);
//var_dump("<pre>",'GRANT',$role_id,$ops,$role_id,$node_data['type'],$role_data['parent'],"</pre>");
			}

			if($rbac_log_active)
			{
				$log_new = ilRbacLog::gatherFaPa($node_id, $role_ids);
				$log = ilRbacLog::diffFaPa($log_old, $log_new);
				ilRbacLog::add(ilRbacLog::MOVE_OBJECT, $node_id, $log);
			}
		}

	}
} // END class.ilRbacAdmin
?>
