<?php

class DynamicTemplateTest extends SapphireTest {
	static $fixture_file = 'dynamictemplate/tests/DynamicTemplate.yml';

	/**
	 * @todo Test unzipping and permissions correct.
	 * @todo Test rendering against custom main only.
	 * @todo Test rendering with custom main and Layout only.
	 * @todo Test rendering a custom Layout with the theme's main. How is this
	 *       even specified in the manifest? Maybe by explicitly referencing
	 *       the main template within the theme directory.
	 * @todo Test rendering with main and Layout from theme, with css and
	 *       javascript overrides.
	 * @todo Test manifest with error falls back to default rendering.
	 * @todo Test template with no manifest where able to correctly
	 *       calculate the manifest automatically.
	 * @todo Test template with no manifest where it is not able to correctly
	 *       calculate the manifest, and falls back to default.
	 */

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

		// Test manifest is cached.
		$ar = unserialize($folder->ManifestCache);
		$this->assertTrue(is_array($ar), "Cached manifest is an array");
		$this->assertTrue(isset($ar["index"]), "Cached manifest has index");
	}

	/**
	 * Test that a page with a dynamic template renders the right bits
	 */
	function testPageRender() {
		$page1 = $this->objFromFixture("DynamicTemplatePage", "page1");
		$controller = new DynamicTemplatePage_Controller($page1);
		$controller->init();
		$html = $controller->defaultAction("index");
		echo "html is: " . $html . "\n";
		$this->assertTrue(preg_match("/^\s*This is a test\.\s*$/mU", $html) > 0, "expected test content");
		$this->assertTrue(preg_match("/^\s*\<link rel=.stylesheet.*href=.*dynamictemplate\/tests\/TemplateWithManifest\/css\/test\.css.*$/mU", $html) > 0, "CSS injected");
		$this->assertTrue(preg_match("/.*\<script.*src=.*dynamictemplate\/tests\/TemplateWithManifest\/javascript\/test\.js.*$/mU", $html) > 0, "javascript injected");
	}
}

