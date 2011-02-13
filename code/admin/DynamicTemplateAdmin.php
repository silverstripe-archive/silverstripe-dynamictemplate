<?php

class DynamicTemplateAdmin extends LeftAndMain {
	static $url_segment = 'dynamictemplates';

	static $url_rule = '/$Action/$ID';
	
	static $menu_title = 'Dynamic Templates';

	static $tree_class = 'DynamicTemplate';

	static $allowed_actions = array(
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
//				$html .= "<li id=\"record-{$item->ID}\" class=\"class-DynamicTemplate\"><span class=\"a DynamicTemplate\"><span class=\"b\"><span class=\"c\">
//				<a href=\"admin/dynamictemplates/show/{$item->ID}\">$item->Title</a>
//				</span></span></span></li>";
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
			$record = singleton("DynamicTemplate");
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

/*	function Link($action = null) {
		$link = parent::Link($action);
		Debug::show($link);
		return $link;
	}*/
}
