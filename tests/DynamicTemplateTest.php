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

	/**
	 * Test a dynamic template that has no manifest. This is a test that
	 * the default manifest constructed in this case is constructed
	 * correctly. The dynamic template is set up with a template, a css file
	 * and a javascript file.
	 */
	function testWithNoManifest() {
		$folder = $this->objFromFixture("DynamicTemplate", "TemplateWithNoManifest");
		$manifest = $folder->getManifest();

		$this->assertTrue(isset($manifest['index']), "Default action derived with no manifest");
		$this->assertEquals(count($manifest), 1, "only one action identified where no manifest");
		$this->assertTrue(isset($manifest['index']['templates']), "templates found where no manifest");
		$this->assertEquals(count($manifest['index']['templates']), 1, "one template found when no manifest");
		$this->assertTrue(isset($manifest['index']['templates']['main']), "main template derived from no manifest");
		$this->assertTrue(
			strpos(
				$manifest['index']['templates']["main"],
				"dynamictemplate/tests/TemplateNoManifest/templates/test.ss") !== FALSE,
			"picked up the right template without manifest"
		);

		$this->assertTrue(isset($manifest['index']['css']), "css present");
		$this->assertEquals(count($manifest['index']['css']), 1, "exactly one css file");

		$this->assertTrue(isset($manifest['index']['javascript']), "javascript present");
		$this->assertEquals(count($manifest['index']['javascript']), 1, "exactly one javascript file");
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
//		echo "html is: " . $html . "\n";
		$this->assertTrue(preg_match("/^\s*This is a test\.\s*$/mU", $html) > 0, "expected test content");
		$this->assertTrue(preg_match("/^\s*\<link rel=.stylesheet.*href=.*dynamictemplate\/tests\/TemplateWithManifest\/css\/test\.css.*$/mU", $html) > 0, "CSS injected");
		$this->assertTrue(preg_match("/.*\<script.*src=.*dynamictemplate\/tests\/TemplateWithManifest\/javascript\/test\.js.*$/mU", $html) > 0, "javascript injected");
	}

	function testUnzipWithDir() {
		$errors = array();
		DynamicTemplate::extract_bundle("dynamictemplate/tests/_UnitTestTemplateDir.zip", &$errors);
		$this->assertEquals(count($errors), 0, "Zipped template with dir extracts with no errors");
		// @todo Check that scandir returns the 4 things

		DynamicTemplate::extract_bundle("dynamictemplate/tests/_UnitTestTemplateNoDir.zip", &$errors);
		$this->assertEquals(count($errors), 0, "Zipped template without dir extracts with no errors");
		// @todo Check that scandir returns the same 4 things
		// @todo remove both artifacts created by the unit test, ensuring the file system is also synced.
	}

}

