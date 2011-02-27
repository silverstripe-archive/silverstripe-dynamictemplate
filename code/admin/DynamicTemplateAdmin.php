<?php

class DynamicTemplateAdmin extends LeftAndMain {
	static $url_segment = 'dynamictemplates';

	static $url_rule = '/$Action/$ID/$Extra';
	
	static $menu_title = 'Dynamic Templates';

	static $tree_class = 'DynamicTemplate';

	static $allowed_actions = array(
		'FileEditForm',
		'LoadFileEditForm',
		'LoadNewFileForm',
		'saveFileEdit',
		'DeleteFileFromTemplate',
		'UnlinkFileFromTemplate',
		'ChangeTemplateType',
		'ThemeLinkOptionsForm',
		'LoadThemeLinkOptionsForm',
		'saveThemeLink',
		'LoadLinkedFileViewForm'
	);

	public function init() {
		parent::init();
		Requirements::css("dynamictemplate/css/DynamicTemplateAdmin.css");
		Requirements::css("dynamictemplate/thirdparty/jquery.treetable/src/stylesheets/jquery.treeTable.css");
		Requirements::javascript("dynamictemplate/thirdparty/jquery.treetable/src/javascripts/jquery.treeTable.min.js");
		Requirements::javascript("dynamictemplate/javascript/DynamicTemplateAdmin_left.js");
		Requirements::javascript("dynamictemplate/javascript/DynamicTemplateAdmin_right.js");
		Requirements::javascript("dynamictemplate/thirdparty/jquery.entwine-0.9/dist/jquery.entwine-dist.js");
		Requirements::javascript("dynamictemplate/thirdparty/editarea_0_8_2/edit_area/edit_area_full.js");

		self::$tree_class = "DynamicTemplate";
	}

	public function SiteTreeAsUL() {
		return $this->getSiteTreeFor($this->stat('tree_class'), null, 'ChildFolders');
	}
	
	function getSiteTreeFor($className, $rootID = null, $childrenMethod = null, $numChildrenMethod = null, $filterFunction = null, $minNodeCount = 30) {
		if (!$childrenMethod) $childrenMethod = 'ChildFolders';
		return parent::getSiteTreeFor($className, $rootID, $childrenMethod, $numChildrenMethod, $filterFunction, $minNodeCount);
	}

	public function TemplatesAsUL() {
		$rootLink = $this->Link('show') . '/root';

		$treeTitle = "Dynamic Templates";

		$items = DataObject::get("DynamicTemplate");
		if ($items) {
			$html = "<ul id=\"sitetree\" class=\"tree unformatted\">";
			foreach ($items as $item)
				$html .= "<li id=\"record-{$item->ID}\" class=\"DynamicTemplate closed\">
				<a href=\"admin/dynamictemplates/show/{$item->ID}\" class=\"DynamicTemplate closed\">$item->Title</a>
				</li>";
			$html .= "</ul>";
		}
		else {
			$html = "";
		}

		$html = "<ul id=\"sitetree\" class=\"tree unformatted\"><li id=\"record-0\" class=\"Root nodelete\"><a href=\"$rootLink\"><strong>$treeTitle</strong></a>"
				. $html . "</li></ul>";

		return $html;
	}

	public function getitem() {
		$this->setCurrentPageID($_REQUEST['ID']);
		SSViewer::setOption('rewriteHashlinks', false);

		if(isset($_REQUEST['ID']) && is_numeric($_REQUEST['ID'])) {
			$record = DataObject::get_by_id("DynamicTemplate", $_REQUEST['ID']);
			if($record && !$record->canView()) return Security::permissionFailure($this);
		}

		$form = $this->EditForm();
		if ($form) {
			$content =  $form->formHtmlContent();
			if($this->ShowSwitchView()) {
				$content .= '<div id="AjaxSwitchView">' . $this->SwitchView() . '</div>';
			}
			
			return $content;
		}
		else return "";
	}

	function getEditForm($id) {
		if($id && $id != "root") {
			$record = DataObject::get_by_id("DynamicTemplate", $id);
		} else {
			return new Form(
				$this,
				"EditForm",
				new FieldSet(new LabelField("selectSomething", "Please a select a dynamic template on the left, or Create or Upload a new one.")),
				new FieldSet()
			);
		}

		if($record) {
			$fields = $record->getCMSFields();
			$actions = new FieldSet();
			
			// Only show save button if not 'assets' folder
			if($record->canEdit()) {
				$actions = new FieldSet(
					new FormAction('save',_t('AssetAdmin.SAVEDYNAMICTEMPLATE','Save dynamic template'))
				);
			}
			
			$form = new Form($this, "EditForm", $fields, $actions);
			if($record->ID) {
				$form->loadDataFrom($record);
			} else {
				$form->loadDataFrom(array(
					"ID" => "root",
					"URL" => Director::absoluteBaseURL() . self::$url_segment . '/',
				));
			}
			
			if(!$record->canEdit()) {
				$form->makeReadonly();
			}

			$this->extend('updateEditForm', $form);

			return $form;
		}
	}

	// Get the file edit form. The ID is in $ID
	public function LoadFileEditForm() {
		$form = $this->FileEditForm();
		return $form->forAjaxTemplate();
	}

	protected $newFileId = null;

	public function FileEditForm() {
		if ($this->newFileId) $id = $this->newFileId;
		else if (isset($_POST['ID'])) $id = $_POST['ID'];
		else $id = $this->urlParams['ID'];

		$do = DataObject::get_by_id("File", $id);

		$form = new Form(
			$this,
			"FileEditForm",
			new FieldSet(
				new LabelField("Filename", "File: " . $do->Name),
				$sourceTextField = new TextareaField("SourceText", "", 20, 100),
				new HiddenField('ID', 'ID'),
				new HiddenField('BackURL', 'BackURL', $this->Link())
			),
			new FieldSet(
				new FormAction('saveFileEdit', _t('DynamicTemplate.SAVEFILEEDIT', 'Save source file')),
				new FormAction('cancelFileEdit', _t('DynamicTemplate.CANCELFILEEDIT', 'Cancel'))
			)
		);

		$form->setTemplate('FilesEditorForm');
		$sourceTextField->setValue(file_get_contents($do->getFullPath()));

		$form->loadDataFrom($do);

		// Work out what type of help to provide.
		if ($do->Parent()->Name == "templates" || $do->Parent()->Name == "css" || $do->Parent()->Name == "javascript")
			$form->HelpType = $do->Parent()->Name;
		return $form;
	}

	public function LoadLinkedFileViewForm() {
		$form = $this->LinkedFileViewForm();
		return $form->forAjaxTemplate();
	}

	public function LinkedFileViewForm() {
		// grab the parameters
		$id = $this->urlParams['ID'];
		if (!$id) throw new Exception("Invalid path");

		// Extract parameters from this ID. It's base 64 of 
		// templateID:path
		$id = base64_decode($id);
		$params = explode(':', $id);
		if (count($params) != 2) throw Exception("Invalid params, expected 2 components");

		$dynamicTemplateId = $params[0];
		$path = $params[1];

		$form = new Form(
			$this,
			"LinkedFileViewForm",
			new FieldSet(
				new LabelField("Filename", "File: " . $path),
				$sourceTextField = new TextareaField("SourceText", "", 20, 100),
				new HiddenField('ID', 'ID'),
				new HiddenField('BackURL', 'BackURL', $this->Link())
			),
			new FieldSet(
				new FormAction('cancelFileEdit', _t('DynamicTemplate.CANCELFILEEDIT', 'Cancel'))
			)
		);

		$form->setTemplate('FilesEditorForm');

		// Get the contents of the file
		$contents = file_get_contents(BASE_PATH . $path);
		$sourceTextField->setValue($contents);
		$sourceTextField->setReadonly(true);

		$form->HelpType = null;

		return $form;
	}

	function Helper() {
		return "helper text";
	}

	// Action for deleting a file from the template. This causes physical removal
	// and from the DB, and from the manifest if it's referenced in there.
	// @todo Check permissions, check $id
	// @todo return ajax response
	public function DeleteFileFromTemplate() {
		try {
			$id = $this->urlParams['ID'];
			if (!$id) throw new Exception("ID is not valid");

			// first, find the file in the DB.
			$file = DataObject::get_by_id("File", $id);
			if (!$file) throw new Exception("Could not locate file $id");

			// get the parent, and use it's name to determine where
			// in the manifest we might expect to find this file.
			$fileName = $file->Name;
			$parentName = $file->Parent()->Name;
			$dynamicTemplate = $file->Parent()->Parent();

			// remove the file (ensure its not a folder), and remove from file system.
			$file->delete(); // should remove from file system as well.

			// look for the file in the manifest. If it's there, remove it
			// and write the manifest back.
			$manifest = $dynamicTemplate->getManifest();
			//@todo This needs to remove the file from the manifest in all actions,
			//		not just index, as the file has been physically removed.
			$manifest->removePath('index', $fileName);
			$dynamicTemplate->flushManifest($manifest);

			return "ok";
		}
		catch (Exception $e) {
			throw $e;
		}
	}

	public function UnlinkFileFromTemplate() {
		try {
			$id = $this->urlParams['ID'];
			if (!$id) throw new Exception("Invalid path");

			// Extract parameters from this ID. It's base 64 of 
			// templateID:path
			$id = base64_decode($id);
			$params = explode(':', $id);
			if (count($params) != 3) throw Exception("Invalid params, expected 3 components");

			$dynamicTemplateId = $params[0];
			$subFolder = $params[1];
			$path = $params[2];

			$dynamicTemplate = DataObject::get_by_id('DynamicTemplate', $dynamicTemplateId);
			if (!$dynamicTemplate) throw new Exception("Could not find dynamic template $dynamicTemplateId");

			$manifest = $dynamicTemplate->getManifest();
			$manifest->removePath('index', $path);
			$dynamicTemplate->flushManifest($manifest);

			return "ok";
		}
		catch (Exception $e) {
			throw $e;
			// @todo	Determine error handling, need to send back a valid
			// 			ajax response.
		}
	}

	/**
	 * Called via ajax request to change the type of a template.
	 */
	public function ChangeTemplateType() {
		try {
			$id = $this->urlParams['ID'];
			$extra = $this->urlParams['Extra'];
			if (!$id) throw new Exception("Invalid path");
			if ($extra != "main" && $extra != "Layout" && $extra != "") throw new Exception("Invalid template type");

			// Extract parameters from this ID. It's base 64 of 
			// templateID:path
			$id = base64_decode($id);
			$params = explode(':', $id);
			if (count($params) != 2) throw new Exception("Invalid params, expected 2 components");

			$dynamicTemplateId = $params[0];
			$path = $params[1];

			$dynamicTemplate = DataObject::get_by_id('DynamicTemplate', $dynamicTemplateId);
			if (!$dynamicTemplate) throw new Exception("Could not find dynamic template $dynamicTemplateId");

			$manifest = $dynamicTemplate->getManifest();

			$manifest->setTemplateType("index", $path, $extra);

			$dynamicTemplate->flushManifest($manifest);

			return "ok";
		}
		catch (Exception $e) {
		}
	}

	// Action for saving.
	// @todo	ensure user has privelege to save.
	// @todo	rather than redirect back and reload everything, the form submission
	//			from the popup should be done by ajax, so we just close
	//			popup.
	public function saveFileEdit($data, $form) {
		$id = $_POST['ID'];
		$do = DataObject::get_by_id("File", $id);
		$newSource = $_POST['SourceText'];
		$path = $do->getFullPath();
		file_put_contents($path, $newSource);

		$backURL = $_POST['BackURL'];
		Director::redirect($backURL);
	}

	/**
	 * Called when a new file is being created. The new file name is
	 * part of the URL. Creates the file in the appropriate place,
	 * and returns the source editor on it.
	 */
	public function LoadNewFileForm() {
		// @todo check permissions

		if (!isset($_REQUEST['filename'])) user_error("no file");

		$filename = $_REQUEST['filename'];

		// Create the file in the template.
		$dt = $this->getCurrentDynamicTemplate();
		$fileId = $dt->addNewFile($filename);

		$this->newFileId = $fileId;

		return $this->LoadFileEditForm();
	}

	public function LoadThemeLinkOptionsForm() {
		$form = $this->ThemeLinkOptionsForm();
		return $form->forAjaxTemplate();
	}
		
	// Ajax response handler for getting files that can be linked. This returns
	// the HTML for a form that is displayed in the editor popup.
	// This form shows all relevant files in the theme that could be linked
	// to in the theme.
	public function ThemeLinkOptionsForm() {
		$tree = $this->getThemeTree();
		$form = new Form(
			$this,
			"ThemeLinkOptionsForm",
			new FieldSet(
				new LiteralField("themetreefield", $tree),
				new HiddenField('BackURL', 'BackURL', $this->Link())
			),
			new FieldSet(
				new FormAction('saveThemeLink', _t('DynamicTemplate.SAVETHEMELINK', 'Save links to theme')),
				new FormAction('cancelThemeLink', _t('DynamicTemplate.CANCELTHEMELINK', 'Cancel'))
			)
		);

		return $form;
	}

	/**
	 * Handle the saving of ThemeLinkOptionsForm. The only type of information
	 * we're in is fields named 'tree-node-n' where n is a number we can
	 * ignore. The value of each field is a path relative to root. We need
	 * to reconcile the links in the manifest with links identified in the
	 * form submission, giving us these cases:
	 * - the file was not in the manifest before, and needs to be added. The
	 *   file path is present in the form submission.
	 * - the file was present in the manifest before, and is still in the
	 *   manifest. The file path is present in the form submission.
	 * - the file was present in the manifest before, but needs to be
	 *   removed. The file path is not present in the form submission.
	 */
	public function saveThemeLink($data, $form) {
		$dt = $this->getCurrentDynamicTemplate();
		$manifest = $dt->getManifest();

		$links = array();

		$action = 'index';

		// Process the paths that are present. If the path is not there,
		// add it.
		foreach ($_POST as $field => $value) {
			if (substr($field, 0, 10) == 'tree-node-') {
				$links[$value] = 1; // build a list of all links in the manifest.
				if (!$manifest->hasPath($value, $action)) $manifest->addPath($action, $value);
			}
		}

		// Iterate over all links in the manifest. If we find a link in
		// the manifest that is not in the links array from above,
		// we need to remove that link.
		// @todo This should only sync things within a given path
		$manifest->syncLinks('index', $links /*, $basepath*/);

		$dt->flushManifest($manifest);

		return "ok";
	}

	// Get the file tree under the selected theme. Returns the HTML,
	// like the file tree, that can be presented as a tree.
	protected function getThemeTree() {
		$this->tree_id = 0;
		$data = $this->getDirectoryRecursive(Director::baseFolder() . "/themes");

		$markup = '<div class="scrolls"><table id="theme-files-tree">';

		$markup .= $this->getDirHtml($data, null);

		$markup .= '</table></div>';
		return $markup;
	}

	protected function getDirHtml($items, $parent) {
		$dt = $this->getCurrentDynamicTemplate();
		$manifest = $dt->getManifest();

		$markup = "";
		foreach ($items as $node) {
			// Add a tree row for this item
			$markup .= '<tr id="themetree-node-' . $node["tree_id"] . '"';
			if ($parent) $markup .= ' class="child-of-themetree-node-' . $parent['tree_id'] . '"';
			$markup .= "><td>{$node["name"]}</td>";
			$markup .= '<td>';
			if ($node['is_file']) {
				$markup .= '<input name="tree-node-' . $node["tree_id"] . '" type="checkbox" id="themetree-cb-' . $node["tree_id"]. '"';
				// if the manifest has this field, check the box
				if ($manifest->hasPath($node['path'])) $markup .= " checked";
				$markup .= ' value="' . $node['path'] . '"';
				$markup .= '>';
			}
			$markup .= '</td>';
			$markup .= '</tr>';

			// if it has children, add their markup recursively before
			// doing the next item. The tree javascript expects the
			// table to be generated this way.
			if (isset($node['children'])) {
				$markup .= $this->getDirHtml($node['children'], $node);
			}
		}
		return $markup;
	}

	/**
	 * Return the DynamicTemplate object currently being edited, which
	 * is held in the session, or return null if its not set.
	 */
	function getCurrentDynamicTemplate() {
		$id = $this->currentPageID();
		if (!$id) return null;
		return DataObject::get_by_id('DynamicTemplate', $id);
	}

	function getDirectoryRecursive($dir)
	{
		$base = Director::baseFolder();

		if(substr($dir, -1) == '/') $dir = substr($dir, 0, -1);

		if (!file_exists($dir) || !is_dir($dir) || !is_readable($dir))
			return FALSE;

		$files = opendir($dir);
		$result = FALSE;

		while (FALSE !== ($file = readdir($files))) {
			if ($file[0] == ".") continue;
			$path = $relpath = $dir . '/' . $file;
			if (substr($path, 0, strlen($base)) == $base) $relpath = substr($relpath, strlen($base));

			if (!is_readable($path)) continue;

			if(is_dir($path)) {
				$result[] = array(
					'tree_id'	=> $this->tree_id++,
					'name'		=> $file,
					'path'		=> $relpath,
					'is_file'	=> false,
					'children'	=> $this->getDirectoryRecursive($path));
			}
			else if(is_file($path)) {
				if(preg_match('/^.*(\.[^.]+)$/', $file, $matches))
					$ext = $matches[1];
				else
					$ext = "";

				$result[] = array(
					'tree_id'	=> $this->tree_id++,
					'name'      => $file,
					'path'      => $relpath,
					'is_file'   => true,
					'extension' => $ext,
					'filesize'  => filesize($path)
				);
			}
		}

		// close the directory
		closedir($files);

		// return file list
		return $result;
	}
}
