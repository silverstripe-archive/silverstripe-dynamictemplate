<?php

class DynamicTemplateTest extends SapphireTest {
	static $fixture_file = 'dynamictemplate/tests/DynamicTemplate.yml';

	function testManifestDefault() {
		$folder = $this->objFromFixture("DynamicTemplate", "TemplateWithNoManifest");
		
	}

	/**
	 * Test loading the simple dynamic template with manifest.
	 */
	function testManifestLoad() {
		$folder = $this->objFromFixture("DynamicTemplate", "TemplateWithManifest");
		$manifest = $folder->getManifest();

		$this->assertTrue(isset($manifest['index']), "manifest has index action");
		$this->assertEquals(count($manifest), 1, "manifest has one action");
		$this->assertTrue(isset($manifest['index']['templates']), "manifest default action has templates");
		$this->assertEquals(count($manifest['index']['templates']), 1, "manifest default action has one template");
		$this->assertTrue(
			strpos(
				$manifest['index']['templates']["main"],
				"dynamictemplate/tests/TemplateWithManifest/templates/test.ss") !== FALSE,
			"manifest default action has test.ss"
		);
	}

	/**
	 * Test that a page with a dynamic template renders the right bits
	 */
	function testPageRender() {
		$page1 = $this->objFromFixture("DynamicTemplatePage", "page1");
		$controller = new DynamicTemplatePage_Controller($page1);
		$controller->init();
		$this->assertTrue(strpos(
			$controller->defaultAction("index"),
			"This is a test") !== FALSE, "Test template is being rendered");
	}
}

