<?php

class DynamicTemplatePage extends Page {
	static $has_one = array(
		"DynamicTemplate" => "DynamicTemplate"
	);

	/**
	 * If true, pages of this type can render without a dynamic template,
	 * and hence the template is optional in the CMS. If false, the page
	 * must have a template and it is required in the CMS.
	 */
	static $dynamic_template_optional = true;

	function getCMSFields() {
		$fields = parent::getCMSFields();

		$ds = DataObject::get("DynamicTemplate", null, "Title");
		$items = array();
		if (self::$dynamic_template_optional) $items = array("0" => "No template");
		if ($ds) foreach ($ds as $d) {
			if ($d->appliesTo($this)) $items[$d->ID] = $d->Title;
		}
		
		$fields->addFieldToTab(
			"Root.Content.Main",
			new DropdownField(
				"DynamicTemplateID",
				"Dynamic template",
				$items
			));
		return $fields;
	}
}

class DynamicTemplatePage_Controller extends Page_Controller {
	function init() {
		parent::init();

		$this->customTemplates = null;

		// If there is no dynamic template, default page rendering
		// behaviour applies.
		if (!$this->DynamicTemplateID) return;

		$manifest = $this->DynamicTemplate()->getManifest();

		if (isset($urlParams['Action']) && isset($manifest->actions[$urlParams['Action']]))
			$action = $urlParams['Action'];
		else
			$action = "index";

		// Set up the templates to render from
//@todo Need to check that values we're passing through directly out of manifest are what SSViewer actually expects.
//		May need to manipulate the arrays, swapping keys and values for templates.
		if (isset($manifest->actions[$action]["templates"]))
			$this->customTemplates = $manifest->actions[$action]["templates"];
		else if (isset($manifest->actions["index"]["templates"]))
			$this->customTemplates = $manifest->actions["index"]["templates"];

		// Include css
// @todo assert that css element exists
		foreach ($manifest->actions[$action]["css"] as $file) {
// @todo Check the media logic
			Requirements::css($file['path'], $file['media']);
		}

		// Include javascript
// @todo Assert that javascript element exists
		foreach ($manifest->actions[$action]["javascript"] as $file) {
// @todo Test this logic for template files and linked files.
			Requirements::javascript($file['path']);
		}
	}

	function getViewer($action) {
		// If there is no dynamic template, or it doesn't contain
		// SSViewer templates, use default handling.
		if (!$this->customTemplates) return parent::getViewer($action);

		// @todo: Handle all cases that can come out in the templates
		// for an action. Determine error handling if action is not handled,
		// and index is not present.

		return new SSDynamicViewer($this->customTemplates);
	}
}
