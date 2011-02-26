<?php

/**
 *
 */
class DynamicTemplate extends Folder {
	static $db = array(
		/**
		 * A serialised form of the normalised manifest array, so we
		 * can render more quickly without having to reparse the manifest file.
		 */
		"ManifestCache" => "Text"
	);

	/**
	 * This determines the base of where dynamic templates are for the site.
	 * We have them under one folder so relative URLs within dynamic
	 * template assets may be renamed (e.g. so we can expand image references).
	 * The folder is relative to assets.
	 */
	public static $dynamic_template_folder = "dynamic_templates/";

	static function set_dynamic_template_folder($value) {
		self::$dynamic_template_folder = $value;
	}

	static function get_dynamic_template_folder() {
		return self::$dynamic_template_folder;
	}

	/**
	 * Given a file object that contains a bundle, extract the contents,
	 * verify it and if it's OK, create a DynamicTemplate object
	 * with the contents of the file in it.
	 * @param File		File object to be extracted
	 */
	static function extract_bundle($file, &$errors) {
		// Create the holder
		$holder = DataObject::get_one("Folder", "\"Filename\"='assets/" . self::$dynamic_template_folder . "'");
		if (!$holder) {
			if (!self::$dynamic_template_folder) {
				$errors = array("There is no dynamic template folder configured, see DynamicTemplate::set_dynamic_template_folder");
				return null;
			}
			$holder = Folder::findOrMake(self::$dynamic_template_folder);
		}

		if (!$template = self::extract_file($file, $holder, &$errors)) return null;

		// If the zip file contains a single directory, and it's not templates,
		// css or javascript, then move the contents of the folder in to replace
		// it. scandir returns . and .. as the first two entries.
		$files = scandir($template->getFullPath());
		if (count($files) == 3 &&
			is_dir($template->getFullPath() . ($file = $files[2])) &&
			$file != "templates" && $file != "css" && $file != "javascript") {
			$tempName = "___" . $file;
			rename($template->getFullPath() . $file, $template->getFullPath() . $tempName);
			
			$inner = scandir($template->getFullPath() . $tempName);
			foreach ($inner as $f) {
				if ($f == "." || $f == "..") continue;
				rename(
					$template->getFullPath() . $tempName . "/" . $f,
					$template->getFullPath() . $f
				);
			}
			rmdir($template->getFullPath() . $tempName);
		}

		// Resync the contents of the folder
		$template->syncChildren();

		return $template;
	}

	/**
	 * Helper to extract compressed file appropriately. If its a tarball, it
	 * uses Archive class. If it's a zip file, it uses Zip library, although
	 * this isn't always available.
	 * @param File $compressedFile			Path of file to be extracted
	 * @param Folder $holder				Folder object for the dynamic template holder directory.
	 * @param String $destinationFolder		Path of folder to extract it to.
	 * @param array $errors					Array ref where errors are written.
	 * @return DynamicTemplate				Returns a new DynamicTemplate object
	 *										on success, or null on failure.
	 */
	static function extract_file($compressedFile, $holder, &$errors) {
		$inputPath = $compressedFile->getFullPath();

		$extensions = array(
			"zip" => array(".zip"),
			"tarball" => array(".tgz", ".tar.gz", ".bz2")
		);
		$basename = basename($inputPath);
		$archiveType = "";
		$templateName = "";
		$extension = "";
		foreach ($extensions as $type => $extlist) {
			foreach ($extlist as $ext) {
				if (!$archiveType && substr($basename, -1 * strlen($ext)) == $ext) {
					$archiveType = $type;
					$templateName = substr($basename, 0, -1 * strlen($ext));
					$extension = $ext;
				}
			}
		}
		if (!$archiveType) return null; // not a file we can extract.
		if (!$templateName) {
			$errors[] = "There was a problem determining the name of the template";
			return null;
		}

		// Attempt to open the archive to determine if there are extraction
		// errors.
		switch ($archiveType) {
			case "zip":
				$zip = new ZipArchive;
				if ($zip->open($inputPath) !== TRUE) {
					$errors = array("Could not unzip file " . Director::baseFolder() . "/" . $file);
					return null;
				}
				break;
		}

		$template = new DynamicTemplate();
		$template->ParentID = $holder->ID;
		$template->Name = $templateName;
		$template->Title = $templateName;
		$template->write();
		if (!file_exists($template->getFullPath())) {
			mkdir($template->getFullPath(), Filesystem::$folder_create_mask);
		}

		switch ($archiveType) {
			case "zip":
				$zip->extractTo($template->getFullPath());
				$zip->close();
				break;
			case "tarball":
				// @todo could use TarballArchive, but it seems flakey.
				$modifiers = array(
					".gz" => "z",
					".tgz" => "z",
					".tar.gz" => "z",
					".bz2" => "j"
				);
				$modifier = $modifiers[$extension];
				$command = "tar -xv{$modifier}f {$inputPath} --directory " . $template->getFullPath();
				$output = `$command`;
				echo "Extracting bundle:<br/>" . $output;
				break;
		}

		return $template;
	}

	function validate() {
		return new ValidationResult(true);
	}

	/**
	 * Return the normalised manifest array for this template. We get it from
	 * the cache if its set, otherwise, calculate it and store it in the
	 * cache.
	 */	 
	function getManifest() {
		if (!$this->ManifestCache) {
			$manifest = $this->generateManifest();
			$this->flushManifest($manifest);
			return $manifest;
		}

		// return the deserialised manifest property.
		return unserialize($this->ManifestCache);
	}

	/**
	 * Given a manifest object, flush the dynamic template with this
	 * manifest. This only has effect if the manifest has been modified.
	 * The new manifest object is serialised into the cache for the
	 * template, as well as a new MANIFEST file being written.
	 */
	function flushManifest($manifest) {
		if ($manifest->getModified()) {
			$manifest->setModified(false);
			$this->ManifestCache = serialize($manifest);
			$this->write();

			$content = $manifest->generateFileContent();

			// File is relative to dynamic template folder
			$manifestPath = Director::baseFolder() . '/' . $this->Filename . 'MANIFEST';

			file_put_contents($manifestPath, $content);

			// If we just created the file, this will sync it to the DB.
			$this->syncChildren();
		}
	}

	/**
	 * Generate the normalised manifest array for this template. If there is
	 * a file within this folder called MANIFEST, then use that. Otherwise
	 * generate a default manifest based on what is present in this folder.
	 * Note: if there is a file present, but it doesn't parse, the default
	 * logic is used instead. The manifest is assumed to have been checked for
	 * errors at the time it's loaded, and should be dealt with then.
	 */
	function generateManifest() {
		// make sure that the contents of the folder are in sync first,
		// as we'll use the DB to find them.
		$this->syncChildren();
		$file = DataObject::get_one("File", "\"ParentID\"={$this->ID} and \"Name\"='MANIFEST'");
		if ($file) {
			$errors = null;
			$manifest = $this->loadManifestFile($file, &$errors);
			if (!$errors) return $manifest;
			echo "Errors parsing manifest file " . $file->Filename . "\n";
			print_r($errors);
			echo "\n";
		}

		// OK, there is no manifest file, so determine the manifest based on
		// what is present. It's not very smart, but in the simplest case is
		// probably what is wanted.
		$manifest = new DynamicTemplateManifest();
		$templates = $this->getFilesInDirByExt("templates", ".ss");
		if (count($templates) > 0) $manifest->addPath('index', $templates[0], 'main');
		$css = $this->getFilesInDirByExt("css", ".css");
		foreach ($css as $c) $manifest->addPath('index', $c);
		$js = $this->getFilesInDirByExt("javascript", ".js");
		foreach ($js as $j) $manifest->addPath('index', $j);

		return $manifest;
	}

	/**
	 * Look for a subfolder called $subdir, and for every file in the folder
	 * with an extension of $ext, add it's full path to the array that is
	 * returned.
	 */
	function getFilesInDirByExt($subdir, $ext) {
		$paths = array();
		
		$files = scandir($this->FullPath . $subdir);
 		foreach ($files as $file) {
			if ($file == "." || $file == "..") continue;
			if (substr($file, -1*strlen($ext)) != $ext) continue;
			$paths[] = $this->Filename . $subdir . "/" . $file;
		}
		return $paths;
	}

	/**
	 * Given a file object, load and parse its contents as a manifest file.
	 * If there are errors, they are create as an array of strings and returned
	 * in $errors, and null is returned.
	 * @param File $file		The file to parse
	 * @param mixed $errors		Errors are passed back here, or null if there
	 *							are none.
	 */
	function loadManifestFile($file, &$errors) {
		$manifest = new DynamicTemplateManifest();
		$errors = $manifest->loadFromFile($file);

		if (count($errors) == 0) return $manifest;
		return null;
	}

	/**
	 * Determine if this template applies to an item. If the dynamic template has any
	 * class constraints, then this will return true if the class matches the constraints
	 * and returns false if it doesn't. If the template has no constraints, it always
	 * returns true.
	 * @param mixed $item   If item is a string, it is treated as a class name. Otherwise
	 * 						it expects an object, and gets the class name from it.
	 * @return void
	 */
	function appliesTo($item) {
		$manifest = $this->getManifest();
		$class = (is_string($item)) ? $item : $item->ClassName;

		return $manifest->appliesToClass($class);
	}

	/**
	 * Return the FieldSet used to edit a dynamic template in the CMS.
	 */
	function getCMSFields() {
		$fileList = new DynamicTemplateFilesField(
			"Files",
			"Files", 
			$this
		);

		$titleField = ($this->ID && $this->ID != "root") ? new TextField("Title", _t('Folder.TITLE')) : new HiddenField("Title");
		
		// delete files button
		if( $this->canEdit() ) {
			$deleteButton = new InlineFormAction('deletemarked',_t('Folder.DELSELECTED','Delete selected files'), 'delete');
			$deleteButton->includeDefaultJS(false);
		} else {
			$deleteButton = new HiddenField('deletemarked');
		}

		// link file button
		if ($this->canEdit()) {
			$fileButtons = new CompositeField(
				$linkFileButton = new InlineFormAction('linkfile', _t('DynamicTemplate.LINKFILE', 'Link file from theme'), 'link'),
				$newFileButton = new InlineFormAction('newfile', _t('DynamicTemplate.NEWFILE', 'New file'), 'newfile')
			);
			$linkFileButton->includeDefaultJS(false);
			$newFileButton->includeDefaultJS(false);
		}
		else {
			$fileButtons = new HiddenField('linkfile');
		}

		$propButtons = new CompositeField();
		$propButtons->push($exportButton = new InlineFormAction('exporttemplate', _t('DynamicTemplate.EXPORTTEMPLATE', 'Export'), 'export'));
		$exportButton->includeDefaultJS(false);
		if ($this->canEdit()) {
			$propButtons->push($saveButton = new InlineFormAction('savetemplate', _t('DynamicTemplate.SAVETEMPLATE', 'Save'), 'save'));
			$saveButton->includeDefaultJS(false);
		}
		if ($this->canDelete()) {
			$propButtons->push($deleteButton = new InlineFormAction('deletetemplate', _t('DynamicTemplate.DELETETEMPLATE', 'Delete'), 'delete'));
			$deleteButton->includeDefaultJS(false);
		}
		$titleField = ($this->ID && $this->ID != "root") ? new TextField("Title", _t('Folder.TITLE')) : new HiddenField("Title");
		if (!$this->canEdit()) $titleField->setReadOnly(true);

		$fields = new FieldSet(
			new HiddenField("Name"),
			new TabSet("Root",
				new Tab("Properties", _t('DynamicTemplate.PROPERTIESTAB', 'Properties'),
					$titleField,
					new ReadonlyField("URL", _t('Folder.URL', 'URL')),
					new ReadonlyField("Created", _t('Folder.CREATED','First Uploaded')),
					new ReadonlyField("LastEdited", _t('Folder.LASTEDITED','Last Updated')),
					$propButtons
				),
				new Tab("Files", _t('Folder.FILESTAB', "Files"),
					$fileList,
//					$deleteButton,
					$fileButtons,
					new HiddenField("FileIDs"),
					new HiddenField("DestFolderID")
				),
				new Tab("Upload", _t('Folder.UPLOADTAB', "Upload"),
					new LabelField('UploadPrompt', _t('DynamicTemplate.UPLOADPROMPT', 'Upload files to your template. Uploads will automatically be added to the right place.')),
					new LiteralField("UploadIframe",
						$this->getUploadIframe()
					)
				),
				new Tab("Usage", _t('DynamicTemplate.USAGETAG', 'Usage'),
					new LabelField('xxx', '(Not yet implemented. This will let the user define constraints on template usage, e.g. what page types or page instances the template can be applied to.)')
				),
				new Tab("Advanced", _t('DynamicTemplate.ADVANCEDTAB', "Advanced"),
					new LabelField('AdvancedPrompt', _t('DynamicTemplate.ADVANCEDPROMPT', '(Not yet implemented. This will let the user add actions and define the mapping between actions and files, as well as showing the manifest)')),
					new DynamicTemplateManifestField("Manifest", "Manifest Contents", $this)
				)
			),
			new HiddenField("ID")
		);
		
		if(!$this->canEdit()) {
			$fields->removeFieldFromTab("Root", "Upload");
		}

		$this->extend('updateCMSFields', $fields);
		
		return $fields;
	}

	/**
	 * Creates the target folder
	 */
	function requireDefaultRecords() {
		parent::requireDefaultRecords();

		$holder = Folder::findOrMake(self::$dynamic_template_folder);
	}

	/**
	 * Create a new file in the template called $filename. A new empty file
	 * is added to the file system, empty, and a File record created. The
	 * file is also added to the manifest if it's a type where that's
	 * required.
	 * 
	 * On error, an exception is thrown, such as if the file type is not
	 * supported.
	 * 
	 * @param String $filename		Name of file. Should not contain
	 * 								slashes, the location will be determined
	 * 								automatically.
	 * @returns File
	 */
	public function addNewFile($filename) {
		if (strpos($filename, '/') !== FALSE) throw new Exception('addNewFile expects a file with no path');
		$extension = DynamicTemplate::get_extension($filename);
		$subdir = $this->getSubdirByExtension($extension, true);

		// Create the physical target folder and the Folder objects in the DB
		$dir = BASE_PATH . "/" . $this->RelativePath . $subdir;

		@Filesystem::makeFolder($dir);

		$p = $this->RelativePath;
		if (substr($p, 0, 7) == "assets/") $p = substr($p, 7);
		$subFolder = Folder::findOrMake($p . $subdir);

		if(file_exists("{$dir}/{$filename}")) throw new Exception("file $filename already exists in the template");

		// Now create the physical file
		file_put_contents("{$dir}/{$filename}", "");
		$result = $this->constructChildInFolder(basename("{$dir}/{$filename}"), $subFolder);

		$this->addFileToManifest($filename);
		
		return $result;
	}

	public static function get_extension($path) {
		if (preg_match('/^.*(\.[^.\/]+)$/', $path, $matches))
			return $matches[1];
		return null;
	}

	/**
	 * Given a file extension, return the subdirectory where files of that
	 * type are stored in the template. Throws an exception on unsupported file
	 * types. If $editable is true, then only .ss, .css and .js are considered
	 * supported.
	 */
	protected function getSubdirByExtension($extension, $editable = false) {
		switch ($extension) {
			// template files
			case ".ss":
				$subdir = "templates";
				break;

			// images
			case ".jpg":
			case ".jpeg":
			case ".png":
			case ".gif":
				if ($editable) throw new Exception("File type $ext are not supported as an editable file at the moment");
				$subdir = "images";
				break;
			case ".css":
				$subdir = "css";
				break;
			case ".js":
				$subdir= "javascript";
				break;
			default:
				throw new Exception("File type $ext is not supported in dynamic templates");
		}
		return $subdir;
	}

	/**
	 * Take a file uploaded via a POST form, and save it inside this folder.
	 * We automatically organise the files within the template based on
	 * file type, and create folders as required.
	 */
	function addUploadToFolder($tmpFile) {
		if(!is_array($tmpFile)) {
			user_error("Folder::addUploadToFolder() Not passed an array.  Most likely, the form hasn't got the right enctype", E_USER_ERROR);
		}
		if(!isset($tmpFile['size'])) {
			return;
		}
		
		$base = BASE_PATH;

		// Generate default filename
		$file = str_replace(' ', '-',$tmpFile['name']);
		$file = ereg_replace('[^A-Za-z0-9+.-]+','',$file);
		$file = ereg_replace('-+', '-',$file);

		while($file[0] == '_' || $file[0] == '.') {
			$file = substr($file, 1);
		}

		// Work out the subfolder within the template where this file should
		// go.
		$doubleBarrelledExts = array('.gz', '.bz', '.bz2');
		$ext = "";
		if(preg_match('/^(.*)(\.[^.]+)$/', $file, $matches)) {
			$fileSansExt = $matches[1];
			$ext = $matches[2];
			// Special case for double-barrelled 
			if(in_array($ext, $doubleBarrelledExts) && preg_match('/^(.*)(\.[^.]+)$/', $fileSansExt, $matches)) {
				$file = $matches[1];
				$ext = $matches[2] . $ext;
			}
		}

		$subdir = $this->getSubdirByExtension($ext);

		// Create the physical target folder and the Folder objects in the DB
		$dir = $base . "/" . $this->RelativePath . $subdir;

		@Filesystem::makeFolder($dir);

		// call findOrMake with a path relative to assets but not including assets,
		// otherwise it stupidly creates an assets Folder as well.
		$p = $this->RelativePath;
		if (substr($p, 0, 7) == "assets/") $p = substr($p, 7);
		$subFolder = Folder::findOrMake($p . $subdir);

		// If there is already a file of this name in the destination folder,
		// attempt to rename with numbers to avoid conflicts
		$i = 1;
		while(file_exists("{$dir}/{$fileSansExt}{$ext}")) {
			$i++;
			$oldFile = $file;
			
			if(strpos($fileSansExt, '.') !== false) {
				$fileSansExt = ereg_replace('[0-9]*(\.[^.]+$)', $i . '\\1', $fileSansExt);
			} elseif(strpos($fileSansExt, '_') !== false) {
				$fileSansExt = ereg_replace('_([^_]+$)', '_' . $i, $fileSansExt);
			} else {
				$fileSansExt .= "_$i";
			}
			
			if($oldFile == $fileSansExt && $i > 2) user_error("Couldn't fix $fileSansExt$ext with $i", E_USER_ERROR);
		}

		// Now move the uploaded file to the right place, and create the File record.
		if (move_uploaded_file($tmpFile['tmp_name'], "{$dir}/{$fileSansExt}{$ext}")) {
			// Update with the new image
			$result = $this->constructChildInFolder(basename("{$dir}/{$fileSansExt}{$ext}"), $subFolder);
		} else {
			if(!file_exists($tmpFile['tmp_name'])) user_error("Folder::addUploadToFolder: '$tmpFile[tmp_name]' doesn't exist", E_USER_ERROR);
			else user_error("Folder::addUploadToFolder: Couldn't copy '$tmpFile[tmp_name]' to '{$dir}/{$fileSansExt}{$ext}'", E_USER_ERROR);
			return false;
		}

		$this->addFileToManifest("{$fileSansExt}{$ext}");
		
		return $result;
	}

	/**
	 * Given a file, add it to the manifest, and write a new manifest file out.
	 * @param String $path		path relative to the site base, or with no path if
	 * 							it's in the template.
	 */
	function addFileToManifest($path) {
		$manifest = $this->getManifest();
		$manifest->addPath("index", $path);
		$this->flushManifest($manifest);
	}

	/**
	 * Construct a child, as Folder does, except that the child is not directly
	 * owned by the dynamic template, but the folder object $subFolder under it.
	 * @param String $name
	 * @param String $subFolder
	 * @return int	ID of file created.
	 */
	function constructChildInFolder($name, $subFolder) {
		// Determine the class name - File, Folder or Image
		$baseDir = $subFolder->FullPath;
		if(is_dir($baseDir . $name)) {
			$className = "Folder";
		} else {
			// Could use getimagesize to get the type of the image
			$ext = strtolower(substr($name,strrpos($name,'.')+1));
			switch($ext) {
				case "gif": case "jpg": case "jpeg": case "png": $className = "Image"; break;
				default: $className = "File";
			}
		}

		if(Member::currentUser()) $ownerID = Member::currentUser()->ID;
		else $ownerID = 0;
	
		$filename = DB::getConn()->addslashes($subFolder->Filename . $name);
		if($className == 'Folder' ) $filename .= '/';

		$name = DB::getConn()->addslashes($name);
		
		DB::query("INSERT INTO \"File\" 
			(\"ClassName\", \"ParentID\", \"OwnerID\", \"Name\", \"Filename\", \"Created\", \"LastEdited\", \"Title\")
			VALUES ('$className', $subFolder->ID, $ownerID, '$name', '$filename', " . DB::getConn()->now() . ',' . DB::getConn()->now() . ", '$name')");
			
		return DB::getGeneratedID("File");
	}
}

/**
 * A simple decorator on Folder that catches uploads to the dynamic template
 * folder, and trigger auto-extraction of the uploaded file.
 */
class DynamicTemplateDecorator extends DataObjectDecorator {
	/**
	 * After upload, extract the uploaded bundle.
	 * @return
	 */
	function onAfterUpload() {
		// Extraction is only performed if the holder is present and the uploaded
		// file is being put in that folder.
		if (!($folder = DataObject::get_one("Folder", "\"Filename\"='assets/" . DynamicTemplate::$dynamic_template_folder . "'"))) return;
		if ($this->owner->ParentID != $folder->ID) return;

		$errors = array();
		DynamicTemplate::extract_bundle($this->owner, &$errors);
		if (count($errors) > 0) {
			die("The following errors occurred on upload:<br/>" . implode("<br/>", $errors));
		}
	}
}

/**
 * This is a representation of the contents of the manifest file. The manifest
 * is cached in a serialised form of this class. It also provides functions
 * for manipulating manifests by the admin interface.
 */
class DynamicTemplateManifest {
	/**
	 * A map that holds metadata about the template. Currently the only
	 * understood key is 'classes' which is a list of PHP classes and
	 * their descendents to which this dynamic template can be applied.
	 */
	public $metadata;

	/** 
	 * The actions in this manifest. Each action is an
	 * array of sections, and each section is an array of files in
	 * that section. The canonical sections we're interested in
	 * are 'templates', 'css' and 'javascript', which are the only
	 * types of file in a dynamic template that can be included in
	 * a page using Requirements, or rendered with SSViewer.
	 * 
	 * The files list in each section is an array of maps, each of which
	 * has 'path', 'linked', and additional properties as required.
	 * File entries in the 'templates' section have a 'type' key, with
	 * a value of 'main', 'Current' or 'Layout' as understood by SSViewer.
	 * @todo Document structure with more detail.
	 */
	public $actions;

	/**
	 * This is set to true by methods that change the manifest
	 * object in such a way it needs to be written back to the
	 * file system.
	 */
	protected $modified;

	/**
	 * Map file extensions to the subfolder they sit in within the template.
	 * Files that are not of these extensions are not recorded in the 
	 * manifest, although they may exist within the template. These
	 * are the only file types that have magic handling in the module.
	 */
	var $extMap = array(".css" => "css", ".js" => "javascript", ".ss" => "templates");

	function __construct() {
		$this->actions = array();
		$this->metadata = $this->defaultMetadata();

		$this->modified = false;
	}

	function getModified() {
		return $this->modified;
	}

	/**
	 * Set modified flag. Generally this is only called by DynamicTemplate::flushManifest.
	 */
	public function setModified($m) {
		$this->modified = $m;
	}

	/**
	 * Return true if the manifest contains a link to the supplied path. This is
	 * assumed to be relative to site root.
	 * @param String $path		Path to look for.
	 * @param String $action	Name of action to look in. If null, looks in all actions
	 */
	function hasPath($path, $action = null) {
		if ($action && !isset($this->actions[$action])) return false;
		foreach ($this->actions as $a => $sections) {
			if ($action && $action != $a) continue;
			foreach ($sections as $subdir => $files) {
				foreach ($files as $file) {
					if ($path == $file['path']) return true;
				}
			}
		}
		return false;
	}

	function addPath($action, $path, $extra = null) {
		// First check if this type of file is even recorded in the
		// manifest.
		$extension = $this->getExtension($path);
		if (!isset($this->extMap[$extension])) return;

		$section = $this->extMap[$extension];

		// create the action if not present
		if (!isset($this->actions[$action])) $this->actions[$action] = array();

		// create the section if not present
		if (!isset($this->actions[$action][$section])) $this->actions[$action][$section] = array();

		$linked = (strpos($path, '/') === FALSE) ? false : true;
		$f = array(
			'path' => $path,
			'linked' => $linked
		);

		if ($section == "templates") {
			// @todo	make this 'main' if there isn't one, or 'Layout' if
			//			there isn't one.
		}
		
		$this->actions[$action][$section][] = $f;
		$this->modified = true;
	}

	/**
	 * Helper function to get the extension of a file given it's path.
	 * @return String Returns the extension, including the ".", or null if
	 * 		   it has no extension. 
	 */
	protected function getExtension($path) {
		if (preg_match('/^.*(\.[^.\/]+)$/', $path, $matches))
			return $matches[1];
		return null;
	}

	/**
	 * Remove a path from the action if it's there.
	 * @returns void
	 */
	function removePath($action, $path) {
		// First check if this type of file is even recorded in the
		// manifest.
		$extension = $this->getExtension($path);
		if (!isset($this->extMap[$extension])) return;

		$section = $this->extMap[$extension];
		if (!isset($this->actions[$action]) || !isset($this->actions[$action][$section])) return;

		foreach ($this->actions[$action][$section] as $i => $file) {
			if (isset($file['path']) && $file['path'] == $path) {
				unset($this->actions[$action][$section][$i]);
				$this->modified = true;
			}
		}
	}

	/**
	 * Synchronise links within an action to a given list of links.
	 * @param String $action	The action in the manifest to synchronise
	 * @param array $links		An array of path => 1 entries, which is a list
	 * 							of all the paths we want in the action. This means
	 * 							that any paths that are in the action that are
	 * 							not in the links array are removed from the manifest.
	 * @param String $basepath	If specified, only links that whose path starts
	 * 							with $basepath are processed; others are ignored.
	 */
	public function syncLinks($action, $links, $basepath = null) {
		foreach ($this->actions[$action] as $section => $files) {
			foreach ($files as $i => $file) {
				if (!isset($file['linked']) ||
					!isset($file['path']) ||
					!$file['linked'])
					continue;
				if ($basepath && substr($file['path'], 0, strlen($basepath)) != $basepath)
					continue;
				if (!isset($links[$file['path']]))
					$this->removePath($action, $file['path']);
			}
		}
	}

	/**
	 * Given the content of a MANIFEST file, parse the file into an
	 * object.
	 * @param File file
	 * @return DynamicTemplateManifest
	 */
	public function loadFromFile($file) {
		$errors = array();

		require_once 'thirdparty/spyc/spyc.php';

		$ar = Spyc::YAMLLoad($file->FullPath);

		$first = true;

		// top level items are controller actions.
		foreach ($ar as $action => $def) {
			if ($first && $action == "metadata") {
				$this->parseMetadata($def);
				$first = false;
				continue;
			}
			$first = false;

			$new = array();
			$new['templates'] = array();
			$new['css'] = array();
			$new['javascript'] = array();

			if (isset($def['templates']) && is_array($def['templates'])) {
				$hasMain = false;
				foreach ($def['templates'] as $path => $type) {
					$f = array('path' => $path);
					$f['linked'] = (strpos($path, '/') === FALSE) ? false : true;
					$f['type'] = $type;
					if ($type == "main") $hasMain = true;
					$new['templates'][] = $f;
				}

				if (!$hasMain) {
					if (count($new['templates']) == 1 && !$new['templates'][0]['type'])
						// make the first one main, if it has no type
						$new['templates'][0]['type'] = "main";
					else
						$e[] = "Templates are present, but there is no main and no obvious candidate";
				}
			}

			if (isset($def['css']) && is_array($def['css'])) {
				foreach ($def['css'] as $path => $value) {
					$f = array('path' => $path);
					$f['linked'] = (strpos($path, '/') === FALSE) ? false : true;
					$f['media'] = $value;
					$new['css'][] = $f;
				}
			}

			if (isset($def['javascript']) && is_array($def['javascript'])) {
				foreach ($def['javascript'] as $path => $value) {
					$f = array('path' => $path);
					$f['linked'] = (strpos($path, '/') === FALSE) ? false : true;
					$new['javascript'][] = $f;
				}
			}
			$this->actions[$action] = $new;
		}

		// Validate what we've read

		// If there is only one action, and it is not index,
		// call it default, so there is a handler for every action.
		if (count($this->actions) == 1 && !isset($this->actions['index'])) {
			reset($this->actions);
			$key = key($this->actions);
			$this->actions['index'] = $this->actions[$key];
			unset($this->actions[$key]);
		}

		$this->modified = true;

		// Other checks
		if (count($this->actions) == 0) $errors[] = "There are no actions in the template's manifest";

		return $errors;
	}

	public function appliesToClass($class) {
		if (!isset($this->metadata)) return true; // no metadata so no class constraints
		if (!isset($this->metadata["classes"])) return true;

		// Check each item in classes. Each will be the name of a base class. If
		// the class of the item passed in is a subclass, this template applies
		// to it.
		foreach ($this->metadata["classes"] as $classConstraint) {
			if (ClassInfo::is_subclass_of($class, $classConstraint)) return true;
		}

		return false;
	}

	/**
	 * Return whatever is the default metadata.
	 * @return array
	 */
	function defaultMetadata() {
		return array();
	}

	/**
	 * Generate normalise metadata for the manifest from the given definition
	 * from the MANIFEST file. Manipulates $manifest and adds definitions as appropriate.
	 * @param array $def
	 * @return void
	 */
	function parseMetadata($def) {
		// Determine which classes this applies to
		$classes = isset($def["classes"]) ? $def["classes"] : 
					(isset($def["class"]) ? $def["class"] : "");

		if (trim($classes) == "") $classes = array();
		else $classes = explode(",", $classes);

		$this->metadata["classes"] = $classes;
	}

	/**
	 * Generate a MANIFEST file from the manifest object.
	 * @return String content for a new MANIFEST file.
	 */
	function generateFileContent() {
		$content = "";

		// Generate the metadata
		if (isset($this->metadata)) {
			$content .= "metadata:\n";
			foreach($this->metadata as $key => $value) {
				$content .= "  {$key}:";
				if (is_array($value)) $content .= implode(",", $value);
				else $content .= $value;
				$content .= "\n";
			}
		}

		foreach ($this->actions as $action => $sections) {
			$content .= "{$action}:\n";
			foreach ($sections as $subdir => $files) {
				$content .= "  {$subdir}:\n";

				foreach ($files as $file) {
					if (isset($file['type'])) $value = $file['type'];
					else if (isset($file['media'])) $value = $file['media'];
					else $value = "";
					$content .= "    {$file['path']}: {$value}\n";
				}
			}
		}

		return $content;
	}

	/**
	 * Change the type of a template. If there is a template that has
	 * that type, it's type will be reset so there is only one of any given
	 * type.
	 * @param String $action	Action the template is in.
	 * @param String $path		Path of file to change
	 * @param String $type		New type; must be Layout, main or "".
	 * @return void
	 */
	public function setTemplateType($action, $path, $type) {
		if (!isset($this->actions[$action]["templates"])) return;

		$newId = -1;
		$oldId = -1;
		foreach ($this->actions[$action]['templates'] as $i => $file) {
			if ($file['path'] == $path) $newId = $i;
			if (isset($file['type']) && $file['type'] == $type) $oldId = $i;
		}

		if ($newId < 0) return;  // no change, couldn't find path
		if ($newId >= 0 && $newId == $oldId) return; // no change

		$this->modified = true;

		if ($oldId >= 0) $this->actions[$action]['templates'][$oldId]['type'] = "";
		$this->actions[$action]['templates'][$newId]['type'] = $type;
	}
}

class DynamicTemplateManifestField extends FormField {
	function __construct($name, $title = null, $value = null) {
		parent::__construct($name, $title, $value, null);
	}

	function Field() {return "not implemented - requires refactoring for new manifest internal structure";
		// This is a hack. In practice something is going wrong, and Value()
		// is the manifest test rather than the object, so there's a bug.
		if (is_array($v = $this->Value())) $manifest = $v;
		else if (!$v || !$v->ID) return "";

		$markup = "Metadata";
		$markup .= "<ul class=\"manifest-metadata\">";
		if (isset($manifest['.metadata'])) {
			foreach ($manifest['.metadata'] as $key => $value) {
				$markup .= "<li>$key => $value</li>";
			}
		}
		$markup .= "</ul>";
		$markup .= "<br/>Actions";
		$markup .= "<ul class=\"manifest-actions\">";
		foreach ($manifest as $index => $config) {
			if ($index == ".metadata") continue;

			$markup .= "<li>{$index}:";
			$markup .= "<ul class=\"manifest-action-items\">";
			foreach ($config as $key => $value) {
				if ($key == "templates") { // key/value pairs
					foreach ($value as $k => $v) {
						$markup .= "<li>$k => $v</li>";
					}
				}
				else { // just values
					foreach ($value as $v) {
						$markup .= "<li>$v</li>";
					}
				}
			}
			$markup .= "</ul>";
			$markup .= "</li>";
		}
		$markup .= "</ul>";

		return $markup;
	}
}
