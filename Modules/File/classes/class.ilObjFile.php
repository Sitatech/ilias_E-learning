<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once "Services/Object/classes/class.ilObject2.php";
include_once('Modules/File/classes/class.ilFSStorageFile.php');

/** @defgroup ModulesFile Modules/File
 */

/**
* Class ilObjFile
*
* @author Sascha Hofmann <shofmann@databay.de> 
* @version $Id: class.ilObjFile.php 33990 2012-03-29 11:24:28Z jluetzen $
*
* @ingroup ModulesFile
*/
class ilObjFile extends ilObject2
{
	var $filename;
	var $filetype;
	var $filemaxsize = "20000000";	// not used yet
	var $raise_upload_error;
	var $mode = "object";
	
	private $file_storage = null;


	/**
	* Constructor
	* @access	public
	* @param	integer	reference_id or object_id
	* @param	boolean	treat the id as reference_id (true) or object_id (false)
	*/
	function __construct($a_id = 0,$a_call_by_reference = true)
	{
		$this->version = 0;
		$this->raise_upload_error = true;
		parent::__construct($a_id,$a_call_by_reference);
		
		if($this->getId())
		{
			$this->initFileStorage();
		}
	}

	function initType()
	{
		$this->type = "file";
	}

	/**
	* create object
	* 
	* @param bool upload mode (if enabled no entries in file_data will be done)
	*/
	protected function doCreate($a_upload = false, $a_prevent_meta_data_creation = false)
	{
		//BEGIN WebDAV Move Property creation into a method of its own.
		$this->createProperties($a_upload);
		//END WebDAV Move Property creation into a method of its own.	
	}
	
	//BEGIN WebDAV: Move Property creation into a method of its own.
	/**
	 * The basic properties of a file object are stored in table object_data.
	 * This is not sufficient for a file object. Therefore we create additional
	 * properties in table file_data.
	 * This method has been put into a separate operation, to allow a WebDAV Null resource
	 * (class.ilObjNull.php) to become a file object.
	 */
	function createProperties($a_upload = false, $a_prevent_meta_data_creation = false)
	{
		global $ilDB,$tree;
		
		// Create file directory
		$this->initFileStorage();
		$this->file_storage->create();
		
		if($a_upload)
		{
			return true;
		}
		
		// not upload mode
		require_once("./Services/History/classes/class.ilHistory.php");
		ilHistory::_createEntry($this->getId(), "create", $this->getFileName().",1");
		$this->addNewsNotification("file_created");


		require_once("./Services/News/classes/class.ilNewsItem.php");
		$default_visibility = ilNewsItem::_getDefaultVisibilityForRefId($_GET['ref_id']);
		if ($default_visibility == "public")
		{
			ilBlockSetting::_write("news", "public_notifications",
				1, 0, $this->getId());
		}

		$q = "INSERT INTO file_data (file_id,file_name,file_type,file_size,version,f_mode) "
			."VALUES (".$ilDB->quote($this->getId() ,'integer').","
			.$ilDB->quote($this->getFileName() ,'text').","
			.$ilDB->quote($this->getFileType() ,'text').","
			.$ilDB->quote((int) $this->getFileSize() ,'integer').","
			.$ilDB->quote(1 ,'integer').",".$ilDB->quote($this->getMode() ,'text').")";
		$res = $ilDB->manipulate($q);
		
		// no meta data handling for file list files
		if ($this->getMode() != "filelist" && !$a_prevent_meta_data_creation)
		{
			$this->createMetaData();
		}
	}
	//END WebDAV: Move Property creation into a method of its own.
	
	/**
	* create file object meta data
	*/
	protected function doCreateMetaData()
	{
		// add technical section with file size and format
		$md_obj =& new ilMD($this->getId(),0,$this->getType());
		$technical = $md_obj->addTechnical();
		$technical->setSize($this->getFileSize());
		$technical->save();
		$format = $technical->addFormat();
		$format->setFormat($this->getFileType());
		$format->save();
		$technical->update();
	}

	protected function beforeMDUpdateListener($a_element)
	{
		// Check file extension
		// Removing the file extension is not allowed
		include_once 'Services/MetaData/classes/class.ilMD.php';
		$md = new ilMD($this->getId(),0, $this->getType());
		if(!is_object($md_gen = $md->getGeneral()))
		{
			return false;
		}
		$title = $this->checkFileExtension($this->getFileName(), $md_gen->getTitle());
		$md_gen->setTitle($title);
		$md_gen->update();
		return true;
	}

	protected function doMDUpdateListener($a_element)
	{
		// handling for technical section 
		include_once 'Services/MetaData/classes/class.ilMD.php';
//echo "-".$a_element."-";
		switch($a_element)
		{
			case 'Technical':

				// Update Format (size is not stored in db)
				$md = new ilMD($this->getId(),0, $this->getType());
				if(!is_object($md_technical = $md->getTechnical()))
				{
					return false;
				}

				foreach($md_technical->getFormatIds() as $id)
				{
					$md_format = $md_technical->getFormat($id);
					ilObjFile::_writeFileType($this->getId(),$md_format->getFormat());
					$this->setFileType($md_format->getFormat());
					break;
				}

				break;
		}
		return true;
	}


	function getDirectory($a_version = 0)
	{
		$version_subdir = "";

		if ($a_version)
		{
			// BEGIN WebDAV Avoid double slash before version subdirectory
			$version_subdir = sprintf("%03d", $a_version);
			// END WebDAV Avoid  double slash before version subdirectory
		}
		
		if(!is_object($this->file_storage))
		{
			$this->initFileStorage();
		}
		
		return $this->file_storage->getAbsolutePath().'/'.$version_subdir;
	}

	function createDirectory()
	{
		ilUtil::makeDirParents($this->getDirectory());
	}
	
	function raiseUploadError($a_raise = true)
	{
		$this->raise_upload_error = $a_raise;
	}

	function getUploadFile($a_upload_file, $a_filename)
	{
		$this->setVersion($this->getVersion() + 1);

		if (@!is_dir($this->getDirectory($this->getVersion())))
		{
			ilUtil::makeDirParents($this->getDirectory($this->getVersion()));
		}

		$file = $this->getDirectory($this->getVersion())."/".$a_filename;
		//move_uploaded_file($a_upload_file, $file);
		ilUtil::moveUploadedFile($a_upload_file, $a_filename, $file, $this->raise_upload_error);
	}

	/**
	* replace file with new file
	*/
	function replaceFile($a_upload_file, $a_filename)
	{
		$this->getUploadFile($a_upload_file, $a_filename);
		
		require_once("./Services/History/classes/class.ilHistory.php");
		ilHistory::_createEntry(
			$this->getId(),
			"replace",
			$a_filename.",".$this->getVersion()
		);
		$this->setFilename($a_filename);
		$this->addNewsNotification("file_updated");
	}
	
	
	public function addFileVersion($a_upload_file,$a_filename)
	{
		$this->getUploadFile($a_upload_file, $a_filename);
		
		require_once("./Services/History/classes/class.ilHistory.php");
		ilHistory::_createEntry(
			$this->getId(),
			"new_version",
			$a_filename.",".$this->getVersion()
		);
		$this->setFilename($a_filename);
		$this->addNewsNotification("file_updated");
				
	}


	/**
	* copy file
	*/
	function copy($a_source,$a_destination)
	{
		return copy($a_source,$this->getDirectory()."/".$a_destination);
	}
	
	/**
	* clear data directory
	*/
	function clearDataDirectory()
	{
		ilUtil::delDir($this->getDirectory());
		$this->createDirectory();
	}
	
	public function deleteVersions()
	{
		global $ilDB;
		
		$ilDB->manipulate("UPDATE file_data SET version = 1 WHERE file_id = ".$ilDB->quote($this->getId() ,'integer'));
		$this->setVersion(0);
		$this->clearDataDirectory();
		
		require_once("./Services/History/classes/class.ilHistory.php");
		ilHistory::_removeEntriesForObject($this->getId());
		
	}

	/**
	* read file properties
	*/
	protected function doRead()
	{
		global $ilDB;
		
		$q = "SELECT * FROM file_data WHERE file_id = ".$ilDB->quote($this->getId() ,'integer');
		$r = $this->ilias->db->query($q);
		$row = $r->fetchRow(DB_FETCHMODE_OBJECT);

		$this->setFileName($row->file_name);
		$this->setFileType($row->file_type);
		$this->setFileSize($row->file_size);
		$this->setVersion($row->version);
		$this->setMode($row->f_mode);
		
		$this->initFileStorage();
	}

	protected function beforeUpdate()
	{
		// no meta data handling for file list files
		if ($this->getMode() != "filelist")
		{
			$this->updateMetaData();
		}
		
		return true;
	}

	/**
	* update file
	*/
	protected function doUpdate()
	{
		global $ilDB, $ilLog;
		
		//$ilLog->write(__METHOD__.' File type: '.$this->getFileType());
		
		$q = "UPDATE file_data SET file_name = ".$ilDB->quote($this->getFileName() ,'text').
			", file_type = ".$ilDB->quote($this->getFiletype() ,'text')." ".
			", file_size = ".$ilDB->quote((int) $this->getFileSize() ,'integer')." ".
			", version = ".$ilDB->quote($this->getVersion() ,'integer')." ".
			", f_mode = ".$ilDB->quote($this->getMode() ,'text')." ".
			"WHERE file_id = ".$ilDB->quote($this->getId() ,'integer');
		$res = $ilDB->manipulate($q);
		
		return true;
	}
	
	/**
	* update meta data
	*/
	protected function doUpdateMetaData()
	{
		// add technical section with file size and format
		$md_obj =& new ilMD($this->getId(),0,$this->getType());
		if(!is_object($technical = $md_obj->getTechnical()))
		{
			$technical = $md_obj->addTechnical();
			$technical->save();
		}
		$technical->setSize($this->getFileSize());
		
		$format_ids = $technical->getFormatIds();
		if (count($format_ids) > 0)
		{
			$format = $technical->getFormat($format_ids[0]);
			$format->setFormat($this->getFileType());
			$format->update();
		}
		else
		{
			$format = $technical->addFormat();
			$format->setFormat($this->getFileType());
			$format->save();
		}
		$technical->update();
	}

	/**
	* set filename
	*/
	function setFileName($a_name)
	{
		$this->filename = $a_name;
	}

	function getFileName()
	{
		return $this->filename;
	}

	function setFileType($a_type)
	{
		global $ilLog;
		
		
		$this->filetype = $a_type;
	}

	function getFileType()
	{
		return $this->filetype;
	}

	function setFileSize($a_size)
	{
		$this->filesize = $a_size;
	}

	function getFileSize()
	{
		return $this->filesize;
	}
	/**
	* Gets the disk usage of the object in bytes.
    *
	* @access	public
	* @return	integer		the disk usage in bytes
	*/
	function getDiskUsage()
	{
	    require_once("./Modules/File/classes/class.ilObjFileAccess.php");
		return ilObjFileAccess::_lookupDiskUsage($this->id);
	}


	// END PATCH WebDAV Encapsulate file access in ilObjFile class.
	function getFile($a_hist_entry_id = null)
	{
		if (is_null($a_hist_entry_id))
		{
			$file = $this->getDirectory($this->getVersion())."/".$this->getFileName();
		}
		else
		{
			require_once("./Services/History/classes/class.ilHistory.php");
			$entry = ilHistory::_getEntryByHistoryID($a_hist_entry_id);
			
			if ($entry === false)
			{
				return false;
			}

			$data = explode(",",$entry["info_params"]);
			
			// bugfix: first created file had no version number
			// this is a workaround for all files created before the bug was fixed
			if (empty($data[1]))
			{
				$data[1] = "1";
			}

			$file = $this->getDirectory($data[1])."/".$data[0];
		}
		return $file;
	}
	// END PATCH WebDAV Encapsulate file access in ilObjFile class.
	
	function setVersion($a_version)
	{
		$this->version = $a_version;
	}

	function getVersion()
	{
		return $this->version;
	}
	
	/**
	* mode is object or filelist
	*
	* @param	string		$a_mode		mode
	*/
	function setMode($a_mode)
	{
		$this->mode = $a_mode;
	}

	/**
	* mode is object or filelist
	*
	* @return	string		mode
	*/
	function getMode()
	{
		return $this->mode;
	}
	
	function _writeFileType($a_id ,$a_format)
	{
		global $ilDB;
		
		$q = "UPDATE file_data SET ".
			" file_type = ".$ilDB->quote($a_format ,'text').
			" WHERE file_id = ".$ilDB->quote($a_id ,'integer');
		$res = $ilDB->manipulate($q);
		
	}

	function _lookupFileName($a_id)
	{
		global $ilDB;

		$q = "SELECT * FROM file_data WHERE file_id = ".$ilDB->quote($a_id ,'integer');
		$r = $ilDB->query($q);
		$row = $r->fetchRow(DB_FETCHMODE_OBJECT);

		return ilUtil::stripSlashes($row->file_name);
	}


	/** Lookups the file size of the file in bytes. */
	function _lookupFileSize($a_id)
	{
	    require_once("./Modules/File/classes/class.ilObjFileAccess.php");
		return ilObjFileAccess::_lookupFileSize($a_id);
	}
	
	/**
	* lookup version
	*/
	function _lookupVersion($a_id)
	{
		require_once("./Modules/File/classes/class.ilObjFileAccess.php");
		return ilObjFileAccess::_lookupVersion($a_id);
	}

	/**
	* Determine File Size
	*/
	function determineFileSize($a_hist_entry_id = null)
	{
		if (is_null($a_hist_entry_id))
		{
			$file = $this->getDirectory($this->getVersion())."/".$this->getFileName();
		}
		else
		{
			require_once("./Services/History/classes/class.ilHistory.php");
			$entry = ilHistory::_getEntryByHistoryID($a_hist_entry_id);
			
			if ($entry === false)
			{
				return false;
			}

			$data = explode(",",$entry["info_params"]);
			
			// bugfix: first created file had no version number
			// this is a workaround for all files created before the bug was fixed
			if (empty($data[1]))
			{
				$data[1] = "1";
			}
			$file = $this->getDirectory($data[1])."/".$data[0];
		}
		$this->setFileSize(filesize($file));
	}
	
	function sendFile($a_hist_entry_id = null)
	{	
		if (is_null($a_hist_entry_id))
		{
			$file = $this->getDirectory($this->getVersion())."/".$this->getFileName();

			// if not found lookup for file in file object's main directory for downward c	ompability
			if (@!is_file($file))
			{
				$file = $this->getDirectory()."/".$this->getFileName();
			}
		}
		else
		{
			require_once("./Services/History/classes/class.ilHistory.php");
			$entry = ilHistory::_getEntryByHistoryID($a_hist_entry_id);
			
			if ($entry === false)
			{
				echo "3";return false;
			}

			$data = explode(",",$entry["info_params"]);
			
			// bugfix: first created file had no version number
			// this is a workaround for all files created before the bug was fixed
			if (empty($data[1]))
			{
				$data[1] = "1";
			}

			$file = $this->getDirectory($data[1])."/".$data[0];
			
			// if not found lookup for file in file object's main directory for downward compability
			if (@!is_file($file))
			{
				$file = $this->getDirectory()."/".$data[0];
			}

			// BEGIN WebDAV removed duplicated code
			// END WebDAV removed duplicated code
		}

		if (@is_file($file))
		{
			global $ilClientIniFile;
			if ($ilClientIniFile->readVariable('file_access','download_with_uploaded_filename') != '1')
			{
				ilUtil::deliverFile($file, $this->getTitle(), $this->guessFileType($file), $this->isInline());
			}
			else
			{
				ilUtil::deliverFile($file, basename($file), $this->guessFileType($file), $this->isInline());
			}
			return true;
		}

		return false;
	}

	// BEGIN WebDAV: Get file extension, determine if file is inline, guess file type.
	/**
	 * Returns the extension of the file name converted to lower-case.
	 * e.g. returns 'pdf' for 'document.pdf'.
	 */
	function getFileExtension() {
		require_once 'Modules/File/classes/class.ilObjFileAccess.php';
		return ilObjFileAccess::_getFileExtension($this->getTitle());
	}
	/**
	 * Returns true, if this file should be displayed inline in a browser
	 * window. This is especially useful for PDF documents, HTML pages,
	 * and for images which are directly supported by the browser.
	 */
	function isInline() {
		require_once 'Modules/File/classes/class.ilObjFileAccess.php';
		return ilObjFileAccess::_isFileInline($this->getTitle());
	}
	/**
	 * Returns true, if this file should be hidden in the repository view.
	 */
	function isHidden() {
		require_once 'Modules/File/classes/class.ilObjFileAccess.php';
		return ilObjFileAccess::_isFileHidden($this->getTitle());
	}
	// END WebDAV: Get file extension, determine if file is inline, guess file type.
	
	/**
	 * Guesses the file type based on the current values returned by getFileType()
	 * and getFileExtension().
	 * If getFileType() returns 'application/octet-stream', the file extension is
	 * used to guess a more accurate file type.
	 */
	function guessFileType($a_file = "") {
		
		$path = pathinfo($a_file);
		if ($path["extension"] != "")
		{
			$filename = $path["basename"];
		}
		else
		{
			$filename = "dummy.".$this->getFileExtension();
		}
		include_once("./Services/Utilities/classes/class.ilMimeTypeUtil.php");
		$mime = ilMimeTypeUtil::getMimeType($a_file, $filename, $this->getFileType());
		return $mime;
		
/*
		$fileType = $this->getFileType();
		if (strlen($fileType) == 0) {	
			$fileType = 'application/octet-stream';
		}

		// Firefox browser assigns 'application/x-pdf' to PDF files, but
		// it can only handle them if the have the mime-type 'application/pdf'.
		if ($fileType == 'application/x-pdf')
		{
			$fileType = 'application/pdf';
		}

		if ($fileType == 'application/octet-stream')
		{
			$fileExtension = $this->getFileExtension();
			$mimeArray = array(
				'mpeg' => 'video/mpeg',
				'mp3' => 'audio/mpeg',
				'pdf' => 'application/pdf',
				'gif' => 'image/gif',
				'jpg' => 'image/jpg',
				'png' => 'image/png',
				'htm' => 'text/html',
				'html' => 'text/html',
				'wma' => 'video/x-ms-wma',
				'wmv' => 'video/x-ms-wmv',
				'swf' => 'application/x-shockwave-flash',
			);
			if (array_key_exists($fileExtension, $mimeArray))
			{
				$fileType = $mimeArray[$fileExtension];
			}
		}
		return $fileType;
*/
	}
	
	/**
	 * Clone
	 *
	 * @access public
	 * @param object clone
	 * @param int target id
	 * @param int copy id
	 * 
	 */
	protected function doCloneObject($a_new_obj,$a_target_id,$a_copy_id = 0)
	{
		global $ilDB;

	 	$a_new_obj->createDirectory();
	 	$this->cloneMetaData($a_new_obj);
	 	
	 	// Copy all file versions
	 	ilUtil::rCopy($this->getDirectory(),$a_new_obj->getDirectory());
	 	
	 	// object created now copy other settings
		$query = "INSERT INTO file_data (file_id,file_name,file_type,file_size,version,f_mode) VALUES (".
				$ilDB->quote($a_new_obj->getId() ,'integer').",".
				$ilDB->quote($this->getFileName() ,'text').",".
				$ilDB->quote($this->getFileType() ,'text').",".
				$ilDB->quote((int) $this->getFileSize() ,'integer').", ".
				$ilDB->quote($this->getVersion() ,'integer').", ".
				$ilDB->quote($this->getMode() ,'text').")";
		$res = $ilDB->manipulate($query);

		// copy history entries
		require_once("./Services/History/classes/class.ilHistory.php");
		ilHistory::_copyEntriesForObject($this->getId(),$a_new_obj->getId());
		
		// add news notification
		$a_new_obj->addNewsNotification("file_created");

	 	return $a_new_obj;
	}
	
	protected function beforeDelete()
	{
		global $ilDB;
		
		// check, if file is used somewhere
		$usages = $this->getUsages();
		if (count($usages) == 0)
		{
			return true;
		}
		return false;
	}

	protected function doDelete()
	{
		global $ilDB;
		
		// delete file data entry
		$q = "DELETE FROM file_data WHERE file_id = ".$ilDB->quote($this->getId() ,'integer');
		$this->ilias->db->query($q);

		// delete history entries
		require_once("./Services/History/classes/class.ilHistory.php");
		ilHistory::_removeEntriesForObject($this->getId());

		// delete entire directory and its content
		if (@is_dir($this->getDirectory()))
		{
			ilUtil::delDir($this->getDirectory());
		}

		// delete meta data
		if ($this->getMode() != "filelist")
		{
			$this->deleteMetaData();
		}
	}

	/**
	* export files of object to target directory
	* note: target directory must be the export target directory,
	* "/objects/il_<inst>_file_<file_id>/..." will be appended to this directory
	*
	* @param	string		$a_target_dir		target directory
	*/
	function export($a_target_dir)
	{
		$subdir = "il_".IL_INST_ID."_file_".$this->getId();
		ilUtil::makeDir($a_target_dir."/objects/".$subdir);

		$filedir = $this->getDirectory($this->getVersion());
		
		if (@!is_dir($filedir))
		{
			$filedir = $this->getDirectory();
		}
		
		ilUtil::rCopy($filedir, $a_target_dir."/objects/".$subdir);
	}

	/**
	* static delete all usages of
	*/
	function _deleteAllUsages($a_type, $a_id, $a_usage_hist_nr = 0)
	{
		global $ilDB;
		
		$ilDB->manipulate("DELETE FROM file_usage WHERE usage_type = ".
			$ilDB->quote($a_type, "text").
			" AND usage_id = ".$ilDB->quote((int) $a_id, "integer").
			" AND usage_hist_nr = ".$ilDB->quote((int) $a_usage_hist_nr, "integer"));
	}

	/**
	* save usage
	*/
	function _saveUsage($a_mob_id, $a_type, $a_id, $a_usage_hist_nr = 0)
	{
		global $ilDB;
		
		$ilDB->manipulate("DELETE FROM file_usage WHERE usage_type = ".
			$ilDB->quote((string) $a_type, "text").
			" AND usage_id = ".$ilDB->quote((int) $a_id, "integer").
			" AND usage_hist_nr = ".$ilDB->quote((int) $a_usage_hist_nr, "integer").
			" AND id = ".$ilDB->quote((int) $a_mob_id, "integer"));

		$ilDB->manipulate("INSERT INTO file_usage (id, usage_type, usage_id, usage_hist_nr) VALUES".
			" (".$ilDB->quote((int) $a_mob_id, "integer").",".
			$ilDB->quote((string) $a_type, "text").",".
			$ilDB->quote((int) $a_id, "integer").",".
			$ilDB->quote((int) $a_usage_hist_nr, "integer").")");
	}

	/**
	* get all usages of file object
	*/
	function getUsages()
	{
		global $ilDB;

		// get usages in learning modules
		$q = "SELECT * FROM file_usage WHERE id = ".$ilDB->quote($this->getId(), "integer");
		$us_set = $ilDB->query($q);
		$ret = array();
		while($us_rec = $ilDB->fetchAssoc($us_set))
		{
			$ret[] = array("type" => $us_rec["usage_type"],
				"id" => $us_rec["usage_id"],
				"hist_nr" => $us_rec["usage_hist_nr"]);
		}

		return $ret;
	}

	/**
	* get all files of an object
	*
	* @param	string		$a_type		object type (e.g. "lm:pg")
	* @param	int			$a_id		object id
	*
	* @return	array		array of file ids
	*/
	function _getFilesOfObject($a_type, $a_id, $a_usage_hist_nr = 0)
	{
		global $ilDB;

		// get usages in learning modules
		$q = "SELECT * FROM file_usage WHERE ".
			"usage_id = ".$ilDB->quote((int) $a_id, "integer")." AND ".
			"usage_type = ".$ilDB->quote((string) $a_type, "text")." AND ".
			"usage_hist_nr = ".$ilDB->quote((int) $a_usage_hist_nr, "integer");
		$file_set = $ilDB->query($q);
		$ret = array();
		while($file_rec = $ilDB->fetchAssoc($file_set))
		{
			$ret[$file_rec["id"]] = $file_rec["id"];
		}

		return $ret;
	}

	// TODO: What is this function good for??
	function getXMLZip()
	{
		global $ilias;

		$zip = PATH_TO_ZIP;

		exec($zip.' '.ilUtil::escapeShellArg($this->getDirectory().'/'.$this->getFileName())." ".
			 ilUtil::escapeShellArg($this->getDirectory().'/'.'1.zip'));

		return $this->getDirectory().'/1.zip';
	}
	
	function addNewsNotification($a_lang_var)
	{
                // BEGIN WebDAV Suppress news notification for hidden files
                if ($this->isHidden()) {
                        return;
                }
                // END WebDAV Suppress news notification for hidden files
                
		global $ilUser;
		
		// Add Notification to news
		include_once("./Services/News/classes/class.ilNewsItem.php");
		include_once("./Modules/File/classes/class.ilObjFileAccess.php");
		$news_item = new ilNewsItem();
		$news_item->setContext($this->getId(), $this->getType());
		$news_item->setPriority(NEWS_NOTICE);
		$news_item->setTitle($a_lang_var);
		$news_item->setContentIsLangVar(true);
		if ($this->getDescription() != "")
		{
			$news_item->setContent(
				"<p>".
				$this->getDescription()."</p>");
		}
		$news_item->setUserId($ilUser->getId());
		$news_item->setVisibility(NEWS_USERS);
		$news_item->create();
	}
	
	/**
	 * init file storage object
	 *
	 * @access public
	 * 
	 */
	public function initFileStorage()
	{
	 	$this->file_storage = new ilFSStorageFile($this->getId());
	 	return true;
	}
	/**
	* storeUnzipedFile
	*
	* Stores Files unzipped from uploaded archive in filesystem
	*
	* @param string $a_upload_file
	* @param string	$a_filename
	*/

	function storeUnzipedFile($a_upload_file, $a_filename)
		{
			$this->setVersion($this->getVersion() + 1);

			if (@!is_dir($this->getDirectory($this->getVersion())))
			{
				ilUtil::makeDir($this->getDirectory($this->getVersion()));
			}

			$file = $this->getDirectory($this->getVersion())."/".$a_filename;
			//move_uploaded_file($a_upload_file, $file);
			rename($a_upload_file,  $file);
	}
	
	/**
	 * return absolute path for version
	 *
	 */
	public static function _lookupAbsolutePath ($obj_id, $a_version = null) 
	{
		$file_storage = new ilFSStorageFile($obj_id);
		$filename = ilObjFile::_lookupFileName($obj_id);
		$version_subdir = "";
		
		if (!is_numeric($a_version))
		{
			$a_version = ilObjFile::_lookupVersion ($obj_id);
		}
		$version_subdir = DIRECTORY_SEPARATOR.sprintf("%03d", $a_version);		
		return $file_storage->getAbsolutePath().$version_subdir.DIRECTORY_SEPARATOR.$filename;
	}
	
	/**
	 * Check if the file extension does still exist after an update of the title
	 * @return 
	 */
	public function checkFileExtension($new_filename,$new_title)
	{
		include_once './Modules/File/classes/class.ilObjFileAccess.php';
		$fileExtension = ilObjFileAccess::_getFileExtension($new_filename);
		$titleExtension = ilObjFileAccess::_getFileExtension($new_title);
		if ($titleExtension != $fileExtension && strlen($fileExtension) > 0)
		{
			// remove old extension
			$pi = pathinfo($this->getFileName());
			$suffix = $pi["extension"];
			if ($suffix != "")
			{ 
				if (substr($new_title,
					strlen($new_title) - strlen($suffix) - 1)
					== ".".$suffix)
				{
					$new_title = substr($new_title, 0, strlen($new_title) - strlen($suffix) - 1);
				}
			}
			$new_title .= '.'.$fileExtension;
		}
		return $new_title;
	}


} // END class.ilObjFile
?>
