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

		if (isset($urlParams['Action']) && isset($manifest[$urlParams['Action']]))
			$action = $urlParams['Action'];
		else
			$action = "index";

		// Set up the templates to render from
		if (isset($manifest[$action]["templates"]))
			$this->customTemplates = $manifest[$action]["templates"];
		else if (isset($manifest["index"]["templates"]))
			$this->customTemplates = $manifest["index"]["templates"];

		// Include css
		foreach ($manifest[$action]["css"] as $file => $media) {
			Requirements::css($file, $media);
		}

		// Include javascript
		foreach ($manifest[$action]["javascript"] as $file) {
			Requirements::javascript($file);
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
