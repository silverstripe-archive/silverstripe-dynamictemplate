<?php

class DynamicTemplateAdmin extends LeftAndMain {
	static $url_segment = 'dynamictemplates';

	static $url_rule = '/$Action/$ID/$Extra';
	
	static $menu_title = 'Dynamic Templates';

	static $tree_class = 'DynamicTemplate';

	static $allowed_actions = array(
		'FileEditForm',
		'LoadFileEditForm',
		'saveFileEdit',
		'DeleteFileFromTemplate',
		'UnlinkFileFromTemplate',
		'ChangeTemplateType'
	);

	public function init() {
		parent::init();
		Requirements::css("dynamictemplate/css/DynamicTemplateAdmin.css");
		Requirements::css("dynamictemplate/thirdparty/jquery.treetable/src/stylesheets/jquery.treeTable.css");
		Requirements::javascript("dynamictemplate/thirdparty/jquery.treetable/src/javascripts/jquery.treeTable.min.js");
		Requirements::javascript("dynamictemplate/javascript/DynamicTemplateAdmin_left.js");
		Requirements::javascript("dynamictemplate/javascript/DynamicTemplateAdmin_right.js");
		Requirements::javascript("dynamictemplate/thirdparty/jquery.entwine-0.9/dist/jquery.entwine-dist.js");

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

	public function FileEditForm() {
		if (isset($_POST['ID'])) $id = $_POST['ID'];
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

		$form->loadDataFrom($do);
		$sourceTextField->setValue(file_get_contents($do->getFullPath()));

		// Work out what type of help to provide.
		if ($do->Parent()->Name == "templates" || $do->Parent()->Name == "css" || $do->Parent()->Name == "javascript")
			$form->HelpType = $do->Parent()->Name;
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
			$modified = false;
			foreach ($manifest['index'][$parentName] as $key => $value) {
				if ($value['path'] == $fileName) {
					unset($manifest['index'][$parentName][$key]);
					$modified = true;
				}
			}
			if ($modified) $dynamicTemplate->rewriteManifestFile($manifest);

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

			if (!isset($manifest['index']) ||
				!isset($manifest['index'][$subFolder]) ||
				!is_array($manifest['index'][$subFolder])) 
				throw new Exception("Section '$subFolder' is invalid");

			$modified = false;
			foreach ($manifest['index'][$subFolder] as $key => $file) {
				if ($file['path'] == $path) {
					unset($manifest['index'][$subFolder][$key]);
					$modified = true;
				}
			}

			if ($modified) $dynamicTemplate->rewriteManifestFile($manifest);

			return "ok";
		}
		catch (Exception $e) {
			throw $e;
			// @todo	Determine error handling, need to send back a valid
			// 			ajax response.
		}
	}

	public function ChangeTemplateType() {
		try {
			$id = $this->urlParams['ID'];
			$extra = $this->urlParams['Extra'];
			if (!$id) throw new Exception("Invalid path");
			if ($extra != "main" && $extra != "Layout") throw new Exception("Invalid template type");

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

			// Locate this template, and set it to the required type, in $extra.
			// Before we do that, we remove that type from the manifest to ensure
			// that no two templates in the same action have the same type.	

			$modified = false;
			
			if ($modified) $dynamicTemplate->rewriteManifestFile($manifest);

			// @todo Implement the change
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
}
