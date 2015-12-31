<?php
/**
 * Reports section of the CMS.
 * 
 * All reports that should show in the ReportAdmin section
 * of the CMS need to subclass {@link SS_Report}, and implement
 * the appropriate methods and variables that are required.
 * 
 * @see SS_Report
 * 
 * @package cms
 * @subpackage reports
 */
class DynamicTemplateAdmin extends ModelAdmin
{

    public static $menu_title = 'Dynamic Templates';

    public static $url_segment = 'dynamictemplates';

    public static $managed_models = array('DynamicTemplate' => array('title' => 'Dynamic Template'));

    public static $url_rule = '/$ModelClass/$Action/$ID';

    public static $tree_class = 'DynamicTemplate';

    public static $allowed_actions = array(
        'LoadFileEditForm',
        'FileEditForm',
        'saveFileEdit',
        'LoadNewFileForm',
        'LoadLinkedFileViewForm',
        'LoadThemeLinkOptionsForm',
        'ThemeLinkOptionsForm',
        'LoadThemeCopyOptionsForm',
        'ThemeCopyOptionsForm',
        'saveThemeLink',
        'DeleteFileFromTemplate',
        'UnlinkFileFromTemplate',
        'ChangeTemplateType',
        'exportaszip',
        'exportastarball',
        'templateimport',
        'TemplateImportForm'
    );

    public function init()
    {
        parent::init();

        Requirements::css("dynamictemplate/css/DynamicTemplateAdmin.css");
        Requirements::css("dynamictemplate/thirdparty/jquery.treetable/src/stylesheets/jquery.treeTable.css");

        Requirements::javascript("dynamictemplate/thirdparty/editarea_0_8_2/edit_area/edit_area_full.js");
        Requirements::javascript("dynamictemplate/thirdparty/jquery.treetable/src/javascripts/jquery.treeTable.min.js");
        Requirements::javascript("dynamictemplate/javascript/DynamicTemplateAdmin.js");
    }

    /**
     * Return true if tar is available, false if not.
     */
    public function TarballAvailable()
    {
        return self::tarball_available();
    }

    public static function tarball_available()
    {
        $out = `tar --version`;
        if ($out == "") {
            return false;
        }
        return true;
    }

    /**
     * Return true if zip library is available, false if not.
     */
    public function ZipAvailable()
    {
        return self::zip_available();
    }

    public static function zip_available()
    {
        return class_exists("ZipArchive");
    }

    // Get the file edit form. The ID is in $ID
    public function LoadFileEditForm()
    {
        $form = $this->FileEditForm();
        return $form->forAjaxTemplate();
    }

    protected $newFileId = null;

    // Override default ModelAdmin::getEditForm, so we can use our alternate GridFieldDetailForm, which gives us
    // more control over the object. I'd much prefer that there was a way for the grid field to instantiate new
    // instances with ability to customise the initial values dynamically.
    public function getEditForm($id = null, $fields = null)
    {
        $list = $this->getList();
        $exportButton = new GridFieldExportButton('before');
        $exportButton->setExportColumns($this->getExportFields());
        $listField = GridField::create(
            $this->sanitiseClassName($this->modelClass),
            false,
            $list,
            $fieldConfig = GridFieldConfig_RecordEditor::create($this->stat('page_length'))
                ->removeComponentsByType('GridFieldFilterHeader')
        );

        // Validation
        if (singleton($this->modelClass)->hasMethod('getCMSValidator')) {
            $detailValidator = singleton($this->modelClass)->getCMSValidator();
            $listField->getConfig()->getComponentByType('DynamicTemplateGridFieldDetailForm')->setValidator($detailValidator);
        }

        $form = new Form(
            $this,
            'EditForm',
            new FieldList(
                $listField,
                new HeaderField(_t('DynamicTemplateAdmin.IMPORTTEMPLATE', 'Import template'), 3),
                new LiteralField(
                    'TemplateImportFormIframe',
                    sprintf(
                        '<iframe src="%s" id="TemplateImportFormIframe" width="100%%" height="250px" border="0"></iframe>',
                        $this->Link('DynamicTemplate/templateimport')
                    )
                )
            ),
            new FieldList()
        );
        $form->addExtraClass('cms-edit-form cms-panel-padded center');
        $form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));
        $form->setFormAction(Controller::join_links($this->Link($this->sanitiseClassName($this->modelClass)), 'EditForm'));
//		$form->setAttribute('data-pjax-fragment', 'DTEditForm');

        $this->extend('updateEditForm', $form);
//		$this->getRequest()->addHeader('X-Pjax', 'CurrentForm');

        return $form;
    }

    // Override LeftAndMain's save so we can explicitly control the way we respond after save
    public function save($data, $form)
    {
        //		die(print_r($this->getRequest(), true));
        $className = $this->stat('tree_class');

        // Existing or new record?
        $SQL_id = Convert::raw2sql($data['ID']);
        if (substr($SQL_id, 0, 3) != 'new') {
            $record = DataObject::get_by_id($className, $SQL_id);
            if ($record && !$record->canEdit()) {
                return Security::permissionFailure($this);
            }
            if (!$record || !$record->ID) {
                throw new HTTPResponse_Exception("Bad record ID #" . (int)$data['ID'], 404);
            }
        } else {
            if (!singleton($this->stat('tree_class'))->canCreate()) {
                return Security::permissionFailure($this);
            }
            $record = $this->getNewItem($SQL_id, false);
        }

        // save form data into record
        $form->saveInto($record, true);
        $record->write();
        $this->extend('onAfterSave', $record);
        $this->setCurrentPageID($record->ID);

        $this->response->addHeader('X-Status', rawurlencode(_t('LeftAndMain.SAVEDUP', 'Saved.')));
        return $this->getResponseNegotiator()->respond($this->request);
    }

    public function templateimport()
    {
        Requirements::clear();
        Requirements::css(FRAMEWORK_ADMIN_DIR . '/css/screen.css');
        Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
        Requirements::css(FRAMEWORK_ADMIN_DIR . '/css/MemberImportForm.css');
        Requirements::javascript(FRAMEWORK_DIR . '/thirdparty/jquery-entwine/dist/jquery.entwine-dist.js');
        Requirements::javascript(FRAMEWORK_ADMIN_DIR . '/javascript/MemberImportForm.js');

        return $this->renderWith('BlankPage', array(
            'Form' => $this->TemplateImportForm()->forTemplate(),
            'Content' => ' '
        ));
    }

    public function TemplateImportForm()
    {
        $form = new TemplateImportForm(
            $this,
            'TemplateImportForm'
        );

        return $form;
    }

    /**
     * Return the file edit form, which is used for editing the source text
     * of a file in the template.
     * @return Form
     */
    public function FileEditForm()
    {
        if ($this->newFileId) {
            $id = $this->newFileId;
        } else {
            if (isset($_REQUEST['ID']) && is_numeric($_REQUEST['ID'])) {
                $id = $_REQUEST['ID'];
            } else {
                throw new Exception("invalid ID");
            }
        }
        // else $id = $this->urlParams['ID'];
        $do = DataObject::get_by_id("File", $id);

        $form = new Form(
            $this,
            "FileEditForm",
            new FieldList(
                new LabelField("Filename", "File: " . $do->Name),
                $sourceTextField = new TextareaField("SourceText", ""),
                new HiddenField('ID', 'ID'),
                new HiddenField('BackURL', 'BackURL', $this->Link())
            ),
            new FieldList(
                new FormAction('saveFileEdit', _t('DynamicTemplate.SAVEFILEEDIT', 'Save source file')),
                new FormAction('cancelFileEdit', _t('DynamicTemplate.CANCELFILEEDIT', 'Cancel'))
            )
        );

        $form->setTemplate('FilesEditorForm');
        $sourceTextField->setValue(file_get_contents($do->getFullPath()));
        $sourceTextField->setRows(20);
        $sourceTextField->setColumns(150);

        $form->loadDataFrom($do);

        // Work out what type of help to provide.
        if ($do->Parent()->Name == "templates" || $do->Parent()->Name == "css" || $do->Parent()->Name == "javascript") {
            $form->HelpType = $do->Parent()->Name;
        }

        return $form;
    }

    // Action for saving.
    // @todo	ensure user has privelege to save.
    // @todo	rather than redirect back and reload everything, the form submission
    //			from the popup should be done by ajax, so we just close
    //			popup.
    public function saveFileEdit($data, $form)
    {
        $id = $_POST['ID'];
        $do = DataObject::get_by_id("File", $id);

        if (!$do || !$do->canEdit()) {
            return Security::permissionFailure($this);
        }

        $newSource = $_POST['SourceText'];
        $path = $do->getFullPath();
        file_put_contents($path, $newSource);

        return "ok";
    }

    public function LoadLinkedFileViewForm()
    {
        $form = $this->LinkedFileViewForm();
        return $form->forAjaxTemplate();
    }

    /**
     * Return the linked file view form, which shows a readonly form that contains the
     * source text of the file being viewed.
     * @throws Exception
     * @return Form
     */
    public function LinkedFileViewForm()
    {
        // grab the parameters
        if (isset($_REQUEST['ID'])) {
            $id = addslashes($_REQUEST['ID']);
        } else {
            throw new Exception("invalid ID");
        }

        // Extract parameters from this ID. It's base 64 of 
        // templateID:path
        $id = base64_decode($id);
        $params = explode(':', $id);
        if (count($params) != 2) {
            throw Exception("Invalid params, expected 2 components");
        }

        $dynamicTemplateId = $params[0];
        $path = $params[1];

        $form = new Form(
            $this,
            "LinkedFileViewForm",
            new FieldList(
                new LabelField("Filename", "File: " . $path),
                $sourceTextField = new TextareaField("SourceText", ""),
                new HiddenField('ID', 'ID'),
                new HiddenField('BackURL', 'BackURL', $this->Link())
            ),
            new FieldList(
                new FormAction('cancelFileEdit', _t('DynamicTemplate.CANCELFILEEDIT', 'Cancel'))
            )
        );

        $form->setTemplate('FilesEditorForm');

        // Get the contents of the file
        $contents = file_get_contents(BASE_PATH . $path);
        $sourceTextField->setRows(20);
        $sourceTextField->setColumns(150);
        $sourceTextField->setValue($contents);

        $form->HelpType = null;

        return $form;
    }

    /**
     * Return the DynamicTemplate object currently being edited, which
     * is held in the session, or return null if its not set.
     */
    public function getCurrentDynamicTemplate()
    {
        $id = Session::get("dynamictemplates_currentID");
        if (!$id) {
            return null;
        }
        return DataObject::get_by_id('DynamicTemplate', $id);
    }

    /**
     * Called when a new file is being created. The new file name is
     * part of the URL. Creates the file in the appropriate place,
     * and returns the source editor on it.
     */
    public function LoadNewFileForm()
    {
        // @todo check permissions

        if (!isset($_REQUEST['filename'])) {
            user_error("no file");
        }

        $filename = addslashes($_REQUEST['filename']);

        // Create the file in the template.
        $dt = $this->getCurrentDynamicTemplate();
        $fileId = $dt->addNewFile($filename);

        $this->newFileId = $fileId;

        return $this->LoadFileEditForm();
    }

    public function LoadThemeLinkOptionsForm()
    {
        $form = $this->ThemeLinkOptionsForm();
        return $form->forAjaxTemplate();
    }
        
    // Ajax response handler for getting files that can be linked. This returns
    // the HTML for a form that is displayed in the editor popup.
    // This form shows all relevant files in the theme that could be linked
    // to in the theme.
    public function ThemeLinkOptionsForm()
    {
        $tree = $this->getThemeTree(true);
        $form = new Form(
            $this,
            "ThemeLinkOptionsForm",
            new FieldList(
                new LiteralField("themetreefield", $tree),
                new HiddenField('BackURL', 'BackURL', $this->Link())
            ),
            new FieldList(
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
    public function saveThemeLink($data, $form)
    {
        $dt = $this->getCurrentDynamicTemplate();
        $manifest = $dt->getManifest();

        if (!$dt || !$dt->canEdit()) {
            return Security::permissionFailure($this);
        }

        $links = array();

        $action = 'index';

        // Process the paths that are present. If the path is not there,
        // add it.
        foreach ($_POST as $field => $value) {
            if (substr($field, 0, 10) == 'tree-node-') {
                $links[$value] = 1; // build a list of all links in the manifest.
                if (!$manifest->hasPath($value, $action)) {
                    $manifest->addPath($action, $value);
                }
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

    public function LoadThemeCopyOptionsForm()
    {
        $form = $this->ThemeCopyOptionsForm();
        return $form->forAjaxTemplate();
    }
        
    // Ajax response handler for getting files that can be linked. This returns
    // the HTML for a form that is displayed in the editor popup.
    // This form shows all relevant files in the theme that could be linked
    // to in the theme.
    public function ThemeCopyOptionsForm()
    {
        $tree = $this->getThemeTree(false);
        $form = new Form(
            $this,
            "ThemeCopyOptionsForm",
            new FieldList(
                new LiteralField("themetreefield", $tree),
                new HiddenField('BackURL', 'BackURL', $this->Link())
            ),
            new FieldList(
                new FormAction('saveThemeCopy', _t('DynamicTemplate.SAVETHEMELINK', 'Save copies from theme')),
                new FormAction('cancelThemeCopy', _t('DynamicTemplate.CANCELTHEMELINK', 'Cancel'))
            )
        );

        return $form;
    }

    /**
     * Handle the saving of ThemeCopyOptionsForm. The only type of information
     * we're in is fields named 'tree-node-n' where n is a number we can
     * ignore. The value of each field is a path relative to root. Basically
     * for each file identified, we create a new file based on that file,
     * as if it were uploaded.
     */
    public function saveThemeCopy($data, $form)
    {
        $dt = $this->getCurrentDynamicTemplate();
        $manifest = $dt->getManifest();

        if (!$dt || !$dt->canEdit()) {
            return Security::permissionFailure($this);
        }

        // Process the paths that are present. If the path is not there,
        // add it.
        foreach ($_POST as $field => $value) {
            if (substr($field, 0, 10) == 'tree-node-') {
                $sourcePath = BASE_PATH . "/" . $value;
                $file = $dt->addNewFile(basename($value), false, $sourcePath);
            }
        }

        $dt->flushManifest($manifest);

        return "ok";
    }

    // Action for deleting a file from the template. This causes physical removal
    // and from the DB, and from the manifest if it's referenced in there.
    // @todo Check permissions, check $id
    // @todo return ajax response
    public function DeleteFileFromTemplate()
    {
        try {
            if (isset($_REQUEST['ID']) && is_numeric($_REQUEST['ID'])) {
                $id = $_REQUEST['ID'];
            } else {
                throw new Exception("invalid ID");
            }

            // first, find the file in the DB.
            $file = DataObject::get_by_id("File", $id);
            if (!$file) {
                throw new Exception("Could not locate file $id");
            }

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
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function UnlinkFileFromTemplate()
    {
        try {
            if (isset($_REQUEST['ID'])) {
                $id = addslashes($_REQUEST['ID']);
            } else {
                throw new Exception("invalid ID");
            }

            // Extract parameters from this ID. It's base 64 of 
            // templateID:path
            $id = base64_decode($id);
            $params = explode(':', $id);
            if (count($params) != 3) {
                throw Exception("Invalid params, expected 3 components");
            }

            $dynamicTemplateId = $params[0];
            $subFolder = $params[1];
            $path = $params[2];

            $dynamicTemplate = DataObject::get_by_id('DynamicTemplate', $dynamicTemplateId);
            if (!$dynamicTemplate) {
                throw new Exception("Could not find dynamic template $dynamicTemplateId");
            }

            $manifest = $dynamicTemplate->getManifest();
            $manifest->removePath('index', $path);
            $dynamicTemplate->flushManifest($manifest);

            return "ok";
        } catch (Exception $e) {
            throw $e;
            // @todo	Determine error handling, need to send back a valid
            // 			ajax response.
        }
    }

    //=========== Utility functions =============

    // Get the file tree under the selected theme. Returns the HTML,
    // like the file tree, that can be presented as a tree.
    protected function getThemeTree($checkExisting)
    {
        $this->tree_id = 0;
        $data = $this->getDirectoryRecursive(Director::baseFolder() . "/themes");

        $markup = '<div class="scrolls"><table id="theme-files-tree">';

        $markup .= $this->getDirHtml($data, null, $checkExisting);

        $markup .= '</table></div>';
        return $markup;
    }

    protected function getDirHtml($items, $parent, $checkExisting)
    {
        $dt = $this->getCurrentDynamicTemplate();
        $manifest = $dt->getManifest();

        $markup = "";
        foreach ($items as $node) {
            // Add a tree row for this item
            $markup .= '<tr id="themetree-node-' . $node["tree_id"] . '"';
            if ($parent) {
                $markup .= ' class="child-of-themetree-node-' . $parent['tree_id'] . '"';
            }
            $markup .= "><td>{$node["name"]}</td>";
            $markup .= '<td>';
            if ($node['is_file']) {
                $markup .= '<input name="tree-node-' . $node["tree_id"] . '" type="checkbox" id="themetree-cb-' . $node["tree_id"]. '"';
                // if the manifest has this field, check the box
                if ($checkExisting && $manifest->hasPath($node['path'])) {
                    $markup .= " checked";
                }
                $markup .= ' value="' . $node['path'] . '"';
                $markup .= '>';
            }
            $markup .= '</td>';
            $markup .= '</tr>';

            // if it has children, add their markup recursively before
            // doing the next item. The tree javascript expects the
            // table to be generated this way.
            if (isset($node['children'])) {
                $markup .= $this->getDirHtml($node['children'], $node, $checkExisting);
            }
        }
        return $markup;
    }

    public function getDirectoryRecursive($dir)
    {
        $base = Director::baseFolder();

        if (substr($dir, -1) == '/') {
            $dir = substr($dir, 0, -1);
        }

        if (!file_exists($dir) || !is_dir($dir) || !is_readable($dir)) {
            return false;
        }

        $files = opendir($dir);
        $result = false;

        while (false !== ($file = readdir($files))) {
            if ($file[0] == ".") {
                continue;
            }
            $path = $relpath = $dir . '/' . $file;
            if (substr($path, 0, strlen($base)) == $base) {
                $relpath = substr($relpath, strlen($base));
            }

            if (!is_readable($path)) {
                continue;
            }

            if (is_dir($path)) {
                $result[] = array(
                    'tree_id'    => $this->tree_id++,
                    'name'        => $file,
                    'path'        => $relpath,
                    'is_file'    => false,
                    'children'    => $this->getDirectoryRecursive($path));
            } elseif (is_file($path)) {
                if (preg_match('/^.*(\.[^.]+)$/', $file, $matches)) {
                    $ext = $matches[1];
                } else {
                    $ext = "";
                }

                $result[] = array(
                    'tree_id'    => $this->tree_id++,
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

    /**
     * Called via ajax request to change the type of a template.
     */
    public function ChangeTemplateType()
    {
        try {
            if (isset($_REQUEST['ID'])) {
                $id = addslashes($_REQUEST['ID']);
            } else {
                throw new Exception("invalid ID");
            }

            $mode = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : "";
            if ($mode != "main" && $mode != "Layout" && $mode != "") {
                throw new Exception("invalid mode");
            }

            // Extract parameters from this ID. It's base 64 of 
            // templateID:path
            $id = base64_decode($id);
            $params = explode(':', $id);
            if (count($params) != 2) {
                throw new Exception("Invalid params, expected 2 components");
            }

            $dynamicTemplateId = $params[0];
            $path = $params[1];

            $dynamicTemplate = DataObject::get_by_id('DynamicTemplate', $dynamicTemplateId);
            if (!$dynamicTemplate) {
                throw new Exception("Could not find dynamic template $dynamicTemplateId");
            }

            $manifest = $dynamicTemplate->getManifest();

            $manifest->setTemplateType("index", $path, $mode);

            $dynamicTemplate->flushManifest($manifest);

            return "ok";
        } catch (Exception $e) {
        }
    }

    public function exportaszip()
    {
        $template = $this->getCurrentDynamicTemplate();
        if (!$template) {
            FormResponse::status_message("No template selected, Please select template");
            FormResponse::load_form($this->getitem(), 'Form_EditForm');
            return FormResponse::respond();
        } else {
            $fileData = $template->exportAs("zip");
            $fileName = $template->Name . ".zip";
            return SS_HTTPRequest::send_file($fileData, $fileName, "application/zip");
        }
    }

    public function exportastarball()
    {
        $template = $this->getCurrentDynamicTemplate();
        if (!$template) {
            FormResponse::status_message("No template selected, Please select template");
            FormResponse::load_form($this->getitem(), 'Form_EditForm');
            return FormResponse::respond();
        } else {
            $fileData = $template->exportAs("tar.gz");
            $fileName = $template->Name . ".tar.gz";
            return SS_HTTPRequest::send_file($fileData, $fileName, "application/x-tar");
        }
    }
// 	/**
// 	 * Does the parent permission checks, but also
// 	 * makes sure that instantiatable subclasses of
// 	 * {@link Report} exist. By default, the CMS doesn't
// 	 * include any Reports, so there's no point in showing
// 	 *
// 	 * @param Member $member
// 	 * @return boolean
// 	 */
// 	function canView($member = null) {
// 		if(!$member && $member !== FALSE) $member = Member::currentUser();

// 		if(!parent::canView($member)) return false;

// 		return true;
// 	}


// 	function providePermissions() {
// 		$title = _t("DynamicTemplateAdmin.MENUTITLE", LeftAndMain::menu_title_for_class($this->class));
// 		return array(
// 			"CMS_ACCESS_DynamicTemplateAdmin" => array(
// 				'name' => _t('CMSMain.ACCESS', "Access to '{title}' section", array('title' => $title)),
// 				'category' => _t('Permission.CMS_ACCESS_CATEGORY', 'CMS Access')
// 			)
// 		);
// 	}
}

class TemplateImportForm extends Form
{
    
    public function __construct($controller, $name, $fields = null, $actions = null, $validator = null)
    {
        if (!$fields) {
            $helpHtml = _t(
                'TemplateImportForm.Help1'
            );

            $fields = new FieldList(
                new LiteralField('Help', $helpHtml),
                $fileField = new FileField(
                    'TemplateFile',
                    _t(
                        'DynamicTemplateAdmin_TemplateImportForm.FileFieldLabel',
                        'Template File <small>(Allowed extensions: *.tar.gz, .zip)</small>'
                    )
                )
            );
            $fileField->getValidator()->setAllowedExtensions(array('gz', 'zip'));
        }

        if (!$actions) {
            $actions = new FieldList(
            $importAction = new FormAction('doImport', _t('DynamicTemplateAdmin_TemplateImportForm.BtnImport', 'Import'))
        );
        }

        $importAction->addExtraClass('ss-ui-button');

        if (!$validator) {
            $validator = new RequiredFields('CsvFile');
        }

        parent::__construct($controller, $name, $fields, $actions, $validator);

        $this->addExtraClass('cms');
        $this->addExtraClass('import-form');
    }

    public function doImport($data, $form)
    {
        $errors = array();
        DynamicTemplate::import_file(
            $data['TemplateFile']['tmp_name'],
            $errors,
            $data['TemplateFile']['name']
        );

        if (count($errors) == 0) {
            $this->sessionMessage("Successfully imported template", 'good');
        } else {
            die(print_r($errors, true));
            $this->sessionMessage($errors[0], 'bad');
        }

        $this->controller->redirectBack();
    }
}
