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
	 * A normalised manifest is an array whose keys are controller action
	 * names for named actions in the manifest, or 'index' for actions that
	 * are not explicitly specified.
	 * Each value corresponding to the action is itself an associative arrays
	 * with the following properties:
	 * - ['templates'] section is an array of template names, passed to the
	 *   viewer constructor to resolve.
	 * - ['css'] section is a map with keys being path names to CSS files to load,
	 *   and values being the media string (passed to Requirements::css(), second
	 *   parameter.
	 * - ['javascript'] section is an array of path names to javascript files.
	 */	 
	function getManifest() {
		if (!$this->ManifestCache) {
			$manifest = $this->generateManifest();
			$this->ManifestCache = serialize($manifest);
			$this->write();
			return $manifest;
		}

		// return the deserialised manifest property.
		return unserialize($this->ManifestCache);
	}

	function rewriteManifestFile($newManifest) {
		$this->ManifestCache = serialize($newManifest);
		$this->write();
	}

	/**
	 * Generate the normalised manifest array for this template. If there is
	 * a file within this folder called MANIFEST, then use that. Otherwise
	 * generate a default manifest based on what is present in this folder.
	 * Note: if there is a file present, but it doesn't parse, the default
	 * logic is used instead. The manifest is assumed to have been checked for
	 * errors at the time it's loaded, and should be dealt with then.
	 *
	 * Normalised manifest is an associative array where the actions of the page
	 * are the indices (with index being the default action). Within each action,
	 * the components of that action are stored like this:
	 * - The ['templates'] section is an assoc array, where the key is
	 *   'main', 'Current' or 'Layout', as understood by SSViewer, and the
	 *   value is the path of the template file relative to the app root.
	 * - The ['css'] section is an array, in rendering order, where the
	 *   key is the path name to the CSS, and value is the media type string.
	 * - The ['javascript'] section is an array where each value is the
	 *   path of the javascript file.
	 * Also, at the top level of the manifest is a special entry ".metadata"
	 * which is loaded from the metadata section at the start of the manifest, if
	 * defined. Note it is called .metadata internally, so that if there is a legitimate
	 * action called metadata, there is no conflict.
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
		$manifest = array();
		$manifest['.metadata'] = $this->defaultMetadata();
		$manifest['index'] = array();
		$templates = $this->getFilesInDirByExt("templates", ".ss");
		if (count($templates) > 0) $manifest['index']['templates']['main'] = $templates[0];
		$css = $this->getFilesInDirByExt("css", ".css");
		$manifest['index']['css'] = array();
		foreach ($css as $c) $manifest['index']['css'][$c] = null; // media
		$manifest['index']['javascript'] = $this->getFilesInDirByExt("javascript", ".js");
		return $manifest;
	}

	/**
	 * Return whatever is the default metadata.
	 * @return array
	 */
	function defaultMetadata() {
		return array();
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
		require_once 'thirdparty/spyc/spyc.php';

		$manifest = array();

		$e = array();

		$ar = Spyc::YAMLLoad($file->FullPath);

		$manifest[".metadata"] = $this->defaultMetadata();

		$first = true;

		// top level items are controller actions.
		foreach ($ar as $action => $def) {
			if ($first && $action == "metadata") {
				$this->parseMetadata($def, &$manifest);
				$first = false;
				continue;
			}
			$first = false;

			$new = array();
			$new['templates'] = array();
			$new['css'] = array();
			$new['javascript'] = array();

			if (isset($def['templates'])) {
				foreach ($def['templates'] as $type => $template)
					$new['templates'][$type] = Director::baseFolder() . "/" . $this->Filename . "templates/" . $template;
			}

			// If there is no main template, try to guess it, or raise an
			// error if we can't determine it. If there are no templates, that's
			// OK.
			if (count($new['templates']) > 0 && !isset($new['templates']["main"])) {
				if (count($new['templates']) == 1) {
					// There is only one template, so make it main
				}
				else $e[] = "Templates are present, but types are not identified.";
			}

			if (isset($def['css'])) {
				foreach ($def['css'] as $key => $value) {
					if (is_numeric($key)) $new['css'][$this->Filename . "css/" . $value] = null;
					else $new['css'][$this->Filename . "css/" . $key] = $value;
				}
			}

			if (isset($def['javascript'])) {
				foreach ($def['javascript'] as $file)
					$new['javascript'][] = $this->Filename . "javascript/" . $file; 
			}
			$manifest[$action] = $new;
		}

		// If there is only one action, and it is not index,
		// call it default, so there is a handler for every action.
		if (count($manifest) == 1 && !isset($manifest['index'])) {
			reset($manifest);
			$key = key($manifest);
			$manifest['index'] = $manifest[$key];
			unset($manifest[$key]);
		}

		// Other checks
		if (count($manifest) == 0) $e[] = "There are no actions in the template's manifest";

		if (count($e) == 0) return $manifest;
		$errors = $e;
		return null;
	}

	/**
	 * Generate normalise metadata for the manifest from the given definition
	 * from the MANIFEST file. Manipulates $manifest and adds definitions as appropriate.
	 * @param array $def
	 * @return void
	 */
	function parseMetadata($def, &$manifest) {
		// Determine which classes this applies to
		$classes = isset($def["classes"]) ? $def["classes"] : 
					(isset($def["class"]) ? $def["class"] : "");

		if (trim($classes) == "") $classes = array();
		else $classes = explode(",", $classes);

		$manifest[".metadata"]["classes"] = $classes;
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

		if (!$manifest) return false;
		if (!isset($manifest[".metadata"])) return true; // no metadata so no class constraints
		if (!isset($manifest[".metadata"]["classes"])) return true;

		$class = (is_string($item)) ? $item : $item->ClassName;

		// Check each item in classes. Each will be the name of a base class. If
		// the class of the item passed in is a subclass, this template applies
		// to it.
		foreach ($manifest[".metadata"]["classes"] as $classConstraint) {
			if (ClassInfo::is_subclass_of($class, $classConstraint)) return true;
		}

		return false;
	}

	/**
	 * Return the FieldSet used to edit a dynamic template in the CMS.
	 */
	function getCMSFields() {
//		Debug::show("dynamic template is " . print_r($this, true));
		$fileList = new DynamicTemplateFilesField(
			"Files",
			"Files", 
			$this
		);
//		$fileList->setFolder($this);
//		$fileList->setPopupCaption(_t('Folder.VIEWEDITASSET', "View/Edit Asset"));

		$titleField = ($this->ID && $this->ID != "root") ? new TextField("Title", _t('Folder.TITLE')) : new HiddenField("Title");
		if( $this->canEdit() ) {
			$deleteButton = new InlineFormAction('deletemarked',_t('Folder.DELSELECTED','Delete selected files'), 'delete');
			$deleteButton->includeDefaultJS(false);
		} else {
			$deleteButton = new HiddenField('deletemarked');
		}

		$fields = new FieldSet(
			new HiddenField("Name"),
			new TabSet("Root",
				new Tab("Properties", _t('DynamicTemplate.PROPERTIESTAB', 'Properties'),
					$titleField,
					new ReadonlyField("URL", _t('Folder.URL', 'URL')),
					new ReadonlyField("Created", _t('Folder.CREATED','First Uploaded')),
					new ReadonlyField("LastEdited", _t('Folder.LASTEDITED','Last Updated'))
				),
				new Tab("Files", _t('Folder.FILESTAB', "Files"),
					$fileList,
					$deleteButton,
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

		switch ($ext) {
			// template files
			case ".ss":
				$subdir = "templates";
				break;
			case ".jpg":
			case ".jpeg":
			case ".png":
			case ".gif":
				$subdir = "images";
				break;
			case ".css":
				$subdir = "css";
				break;
			case ".js":
				$subdir= "javascript";
				break;
			default:
				user_error("File type $ext is not support in dynamic templates");
		}

		// Create the physical target folder and the Folder objects in the DB
		$dir = $base . "/" . $this->RelativePath . $subdir;

		@Filesystem::makeFolder($dir);

		// call findOrMake with a path relative to assets but ot including assets,
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
			return $this->constructChildInFolder(basename("{$dir}/{$fileSansExt}{$ext}"), $subFolder);
		} else {
			if(!file_exists($tmpFile['tmp_name'])) user_error("Folder::addUploadToFolder: '$tmpFile[tmp_name]' doesn't exist", E_USER_ERROR);
			else user_error("Folder::addUploadToFolder: Couldn't copy '$tmpFile[tmp_name]' to '{$dir}/{$fileSansExt}{$ext}'", E_USER_ERROR);
			return false;
		}
	}

	// Construct a child, as Folder does, except that the child is not directly
	// owned by the dynamic template, but the folder object $subFolder under it.
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
 * Form field that shows a list of files within a dynamic template. This basically
 * generates the hierarchical view of folders and files within the template,
 * and the cleverness is handled by treeTable.
 */
class DynamicTemplateFilesField extends FormField {
	function __construct($name, $title = null, $value = null) {
		parent::__construct($name, $title, $value, null);
	}

	// Generate all markup for the tree.
	function Field() {
		$markup = "<table id=\"files-tree\">";

		// for each subfolder, 
		if (($dt = $this->Value()) && ($subFolders = $dt->AllChildren())) {
			foreach ($subFolders as $subFolder) {
				$markup .= "<tr id=\"filetree-node-{$subFolder->ID}\">";
				$markup .= "<td>{$subFolder->Name}</td>";
				$markup .= "</tr>";

				// Now show the files in these folders
				$files = $subFolder->AllChildren();
				foreach ($files as $file) {
					$markup .= "<tr id=\"filetree-node-{$file->ID}\" class=\"child-of-filetree-node-{$subFolder->ID}\">";
					$markup .= "<td>{$file->Name}</td>";
					$markup .= "<td>View Edit Delete</td>";
					$markup .= "</tr>";
				}
			}
		}

		$markup .= "</table>";
		return $markup;
	}
}

class DynamicTemplateManifestField extends FormField {
	function __construct($name, $title = null, $value = null) {
		parent::__construct($name, $title, $value, null);
	}

	function Field() {
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
