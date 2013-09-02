<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once("./Services/FileSystem/classes/class.ilFileData.php");

/**
* This class handles all operations on files for the exercise object
*
* @author	Stefan Meyer <meyer@leifos.com>
* @version $Id: class.ilFileDataExercise.php 33510 2012-03-04 11:59:59Z akill $
*
* @ingroup	ModulesExercise
*/
class ilFileDataExercise extends ilFileData
{
	/**
	* obj_id
	* @var integer obj_id of exercise object
	* @access private
	*/
	var $obj_id;

	/**
	* path of exercise directory
	* @var string path
	* @access private
	*/
	var $exercise_path;

	/**
	* Constructor
	* call base constructors
	* checks if directory is writable and sets the optional obj_id
	* @param integereger obj_id
	* @access	public
	*/
	function ilFileDataExercise($a_obj_id = 0)
	{
die ("ilFileDataExercise is deprecated.");
		define('EXERCISE_PATH','exercise');
		parent::ilFileData();
		$this->exercise_path = parent::getPath()."/".EXERCISE_PATH;

		// IF DIRECTORY ISN'T CREATED CREATE IT
		if(!$this->__checkPath())
		{
			$this->__initDirectory();
		}
		$this->obj_id = $a_obj_id;
	}

	function getObjId()
	{
		return $this->obj_id;
	}

	/**
	* get exercise path
	* @access	public
	* @return string path
	*/
	function getExercisePath()
	{
		return $this->exercise_path;
	}

	function getFiles()
	{
		$files = array();
		$dp = opendir($this->exercise_path);

		while($file = readdir($dp))
		{
			if(is_dir($file))
			{
				continue;
			}
			list($obj_id,$rest) = split('_',$file,2);
			if($obj_id == $this->obj_id)
			{
				if(!is_dir($this->exercise_path.'/'.$file))
				{
					$files[] = array(
						'name'     => $rest,
						'size'     => filesize($this->exercise_path.'/'.$file),
						'ctime'    => ilFormat::formatDate(date('Y-m-d H:i:s',filectime($this->exercise_path.'/'.$file))),
						'fullpath' => $this->exercise_path.'/'.$file);
				}
			}
		}
		closedir($dp);
		return $files;
	}

	function ilClone($a_new_obj_id)
	{
		foreach($this->getFiles() as $file)
		{
			@copy($this->getExercisePath()."/".$this->obj_id.'_'.$file["name"],
				  $this->getExercisePath()."/".$a_new_obj_id.'_'.$file["name"]);
		}
		return true;
	}
	function delete()
	{
		foreach($this->getFiles() as $file)
		{
			$this->unlinkFile($file["name"]);
		}

		$delivered_file_path = $this->getExercisePath() . "/" . $this->obj_id . "/";
		if (is_dir($delivered_file_path))
		{
			include_once "./Services/Utilities/classes/class.ilUtil.php";
			ilUtil::delDir($delivered_file_path);
		}

		return true;
	}
	
	/**
	* store uploaded file in filesystem
	* @param array HTTP_POST_FILES
	* @param boolean $is_unziped true if uploaded file is unziped from archive
	* @access	public
	* @return bool
	*/
	function storeUploadedFile($a_http_post_file, $secure_filename = false, $is_unziped = false)
	{
		// TODO:
		// CHECK UPLOAD LIMIT
		//
		$filename = $a_http_post_file['name'];
		if ($secure_filename)
		{
			// replace whitespaces with underscores
			$filename = preg_replace("/\s/", "_", $filename);
			// remove all special characters
			$filename = preg_replace("/[^_a-zA-Z0-9\.]/", "", $filename);
		}
		if(isset($a_http_post_file) && $a_http_post_file['size'])
		{
			// CHECK IF FILE WITH SAME NAME EXISTS
			$this->__rotateFiles($this->getExercisePath().'/'.$this->obj_id.'_'.$filename);
			//move_uploaded_file($a_http_post_file['tmp_name'],$this->getExercisePath().'/'.$this->obj_id.'_'.
			//				   $filename);
			
			if (!$is_unziped)
			{
				ilUtil::moveUploadedFile($a_http_post_file['tmp_name'], $a_http_post_file['name'],
				$this->getExercisePath().'/'.$this->obj_id.'_'.$filename);
			}
			else
			{
				// ######## Warning, there is no check whether the unziped files are virus free or not
				rename($a_http_post_file['tmp_name'],
				$this->getExercisePath().'/'.$this->obj_id.'_'.$filename);
			}

		}
		return true;
	}


	/**
	* store content as file in filesystem
	* @param   $filename Filename
	* @param   $content base64 decoded content
	* @access	public
	* @return bool
	*/
	function storeContentAsFile($filename, $content, $secure_filename = false)
	{
		// TODO:
		// CHECK UPLOAD LIMIT
		//
		if ($secure_filename)
		{
			// replace whitespaces with underscores
			$filename = preg_replace("/\s/", "_", $filename);
			// remove all special characters
			$filename = preg_replace("/[^_a-zA-Z0-9\.]/", "", $filename);
		}
		if(count($content) > 0 )
		{
			// CHECK IF FILE WITH SAME NAME EXISTS
			$filename = $this->getAbsolutePath($filename);
			$this->__rotateFiles($filename);
			file_put_contents($filename, $content);

			// check for virus
		    $vir = ilUtil::virusHandling($filename);
		    if (!$vir[0] ||$vir[1] != "")
		    {
			     unlink($filename);
			     return false;
		    }
			return true;
		}
		return false;
	}


	/**
	* Download all submitted files of all members.
	*
	* @param	$members		array of user names, key is user id
	*/
	function downloadAllDeliveredFiles($members)
	{
		require_once "./Services/Utilities/classes/class.ilUtil.php";
		global $lng, $ilObjDataCache;

		ksort($members);
		$savepath = $this->getExercisePath() . "/" . $this->obj_id . "/";
		$cdir = getcwd();


		// important check: if the directory does not exist
		// ILIAS stays in the current directory (echoing only a warning)
		// and the zip command below archives the whole ILIAS directory
		// (including the data directory) and sends a mega file to the user :-o
		if (!is_dir($savepath))
		{
			return;
		}
		// Safe mode fix
		chdir($this->getExercisePath());
		$zip = PATH_TO_ZIP;

		// check first, if we have enough free disk space to copy all files to temporary directory
		$tmpdir = ilUtil::ilTempnam();
		ilUtil::makeDir($tmpdir);
		chdir($tmpdir);


		$dirsize = 0;
		foreach ($members as $id => $object) {
			$directory = $savepath.DIRECTORY_SEPARATOR.$id;
			$dirsize += ilUtil::dirsize($directory);
		}
		if ($dirsize > disk_free_space($tmpdir)) {
			return -1;
		}

		// copy all member directories to the temporary folder
		// switch from id to member name and append the login if the member name is double
		// ensure that no illegal filenames will be created
		// remove timestamp from filename
		$cache = array();
		foreach ($members as $id => $user)
		{
			$sourcedir = $savepath.DIRECTORY_SEPARATOR.$id;
			if (!is_dir($sourcedir))
				continue;
			$userName = ilObjUser::_lookupName($id);
			$directory = ilUtil::getASCIIFilename(trim($userName["lastname"])."_".trim($userName["firstname"]));
			if (array_key_exists($directory, $cache))
			{
				// first try is to append the login;
				$directory = ilUtil::getASCIIFilename($directory."_".trim(ilObjUser::_lookupLogin($id)));
				if (array_key_exists($directory, $cache)) {
					// second and secure: append the user id as well.
					$directory .= "_".$id;
				}
			}

			$cache[$directory] = $directory;
			ilUtil::makeDir ($directory);
			$sourcefiles = scandir($sourcedir);
			foreach ($sourcefiles as $sourcefile) {
				if ($sourcefile == "." || $sourcefile == "..")
					continue;
				$targetfile = trim(basename($sourcefile));
				$pos = strpos($targetfile, "_");
				if ($pos === false)
				{
				} else
				{
					$targetfile= substr($targetfile, $pos + 1);
				}
				$targetfile = $directory.DIRECTORY_SEPARATOR.$targetfile;
				$sourcefile = $sourcedir.DIRECTORY_SEPARATOR.$sourcefile;

				if (!copy ($sourcefile, $targetfile))
				{
					//echo 'Could not copy '.$sourcefile.' to '.$targetfile;
					$this->ilias->raiseError('Could not copy '.basename($sourcefile)." to '".$targetfile."'.",
						$this->ilias->error_obj->MESSAGE);
				}
				else
				{
					// preserve time stamp
					touch($targetfile, filectime($sourcefile));
				}

			}
		}

		$tmpfile = ilUtil::ilTempnam();
		$tmpzipfile = $tmpfile . ".zip";
		// Safe mode fix
		$zipcmd = $zip." -r ".ilUtil::escapeShellArg($tmpzipfile)." .";
		exec($zipcmd);
		ilUtil::delDir($tmpdir);

		$exerciseTitle = $ilObjDataCache->lookupTitle($this->getObjId());
		ilUtil::deliverFile($tmpzipfile, (strlen($exerciseTitle) == 0? strtolower($lng->txt("excs")) : $exerciseTitle). ".zip");
		chdir($cdir);
		unlink($tmpfile);
		unlink($tmpzipfile);
	}


	/**
	* unlink files: expects an array of filenames e.g. array('foo','bar')
	* @param array filenames to delete
	* @access	public
	* @return string error message with filename that couldn't be deleted
	*/
	function unlinkFiles($a_filenames)
	{
		if(is_array($a_filenames))
		{
			foreach($a_filenames as $file)
			{
				if(!$this->unlinkFile($file))
				{
					return $file;
				}
			}
		}
		return '';
	}
	/**
	* unlink one uploaded file expects a filename e.g 'foo'
	* @param string filename to delete
	* @access	public
	* @return bool
	*/
	function unlinkFile($a_filename)
	{
		if(file_exists($this->exercise_path.'/'.$this->obj_id.'_'.$a_filename))
		{
			return unlink($this->exercise_path.'/'.$this->obj_id.'_'.$a_filename);
		}
	}
	/**
	* get absolute path of filename
	* @param string relative path
	* @access	public
	* @return string absolute path
	*/
	function getAbsolutePath($a_path)
	{
		return $this->exercise_path.'/'.$this->obj_id.'_'.$a_path;
	}

	/**
	* check if files exist
	* @param array filenames to check
	* @access	public
	* @return bool
	*/
	function checkFilesExist($a_files)
	{
		if($a_files)
		{
			foreach($a_files as $file)
			{
				if(!file_exists($this->exercise_path.'/'.$this->obj_id.'_'.$file))
				{
					return false;
				}
			}
			return true;
		}
		return true;
	}

	// PRIVATE METHODS
	function __checkPath()
	{
		if(!@file_exists($this->getExercisePath()))
		{
			return false;
		}
		$this->__checkReadWrite();

		return true;
	}
	/**
	* check if directory is writable
	* overwritten method from base class
	* @access	private
	* @return bool
	*/
	function __checkReadWrite()
	{
		if(is_writable($this->exercise_path) && is_readable($this->exercise_path))
		{
			return true;
		}
		else
		{
			$this->ilias->raiseError("Exercise directory is not readable/writable by webserver",$this->ilias->error_obj->FATAL);
		}
	}
	/**
	* init directory
	* overwritten method
	* @access	public
	* @return string path
	*/
	function __initDirectory()
	{
		if(is_writable($this->getPath()))
		{
			return ilUtil::makeDir($this->excercise_path = $this->getPath().'/'.EXERCISE_PATH);
		}
		return false;
	}
	/**
	* rotate files with same name
	* recursive method
	* @param string filename
	* @access	private
	* @return bool
	*/
	function __rotateFiles($a_path)
	{
		if(file_exists($a_path))
		{
			$this->__rotateFiles($a_path.".old");
			return rename($a_path,$a_path.'.old');
		}
		return true;
	}
}
