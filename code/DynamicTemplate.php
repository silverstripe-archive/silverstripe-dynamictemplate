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
	 * Given a file object that contains a bundle, extract the contents,
	 * verify it and if it's OK, create a DynamicTemplate object
	 * with the contents of the file in it.
	 */
	static function extract_bundle($file, $parent) {
	}

	/**
	 * Return the normalised manifest array for this template. We get it from
	 * the cache if its set, otherwise, calculate it and store it in the
	 * cache.
	 * A normalised manifest is an array whose keys are controller action
	 * names for named actions in the manifest, or 'default' for actions that
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

	/**
	 * Generate the normalised manifest array for this template. If there is
	 * a file within this folder called MANIFEST, then use that. Otherwise
	 * generate a default manifest based on what is present in this folder.
	 * Note: if there is a file present, but it doesn't parse, the default
	 * logic is used instead. The manifest is assumed to have been checked for
	 * errors at the time it's loaded, and should be dealt with then.
	 *
	 * Normalised manifest is like this:
	 * - The ['templates'] section is an assoc array, where the key is
	 *   'main', 'Current' or 'Layout', as understood by SSViewer, and the
	 *   value is the path of the template file relative to the app root.
	 * - The ['css'] section is an array, in rendering order, where the
	 *   key is the path name to the CSS, and value is the media type string.
	 * - The ['javascript'] section is an array where each value is the
	 *   path of the javascript file.
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

		// top level items are controller actions.
		foreach ($ar as $action => $def) {
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
}
