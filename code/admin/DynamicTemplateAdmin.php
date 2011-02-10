<?php

class DynamicTemplateAdmin extends LeftAndMain {
	static $url_segment = 'dynamictemplates';

	static $url_rule = '/$Action/$ID';
	
	static $menu_title = 'Dynamic Templates';

	public static $tree_class = 'DynamicTemplate';

	static $allowed_actions = array(
	);

	public function SiteTreeAsUL() {
		return $this->getSiteTreeFor($this->stat('tree_class'), null, 'ChildFolders');
	}
	
	function getSiteTreeFor($className, $rootID = null, $childrenMethod = null, $numChildrenMethod = null, $filterFunction = null, $minNodeCount = 30) {
		if (!$childrenMethod) $childrenMethod = 'ChildFolders';
		return parent::getSiteTreeFor($className, $rootID, $childrenMethod, $numChildrenMethod, $filterFunction, $minNodeCount);
	}

	public function TemplatesAsUL() {
		$items = DataObject::get("DynamicTemplate");
		if ($items) {
			$html = "<ul id=\"sitetree\" class=\"tree unformatted\">";
			foreach ($items as $item)
				$html .= "<li id=\"record-{$item->ID}\" class=\"class-DynamicTemplate\"><a href=\"admin/dynamictemplates/show/{$item->ID}\">$item->Title</a>"
				. $html . "</li>";
			$html .= "</ul>";
		}
		else {
			$html = "Use options above to create or import a dynamic template";
		}

		return $html;
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
					new FormAction('save',_t('AssetAdmin.SAVEFOLDERNAME','Save folder name'))
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
}
