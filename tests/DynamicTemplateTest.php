<?php

class DynamicTemplateTest extends SapphireTest {
	static $fixture_file = 'dynamictemplate/tests/DynamicTemplate.yml';

	function testLoadManifestFile() {
//		require_once 'thirdparty/spyc/spyc.php';
//
//		$ar = Spyc::YAMLLoad("/home/mark/dev/dynamictemplate_dev/dynamictemplate/tests/TestManifest.yml");
//		print_r($ar);
	}

	function testManifestDefault() {
		$folder = $this->objFromFixture("DynamicTemplate", "TemplateWithNoManifest");
		
	}

	/**
	 * Test loading the simple dynamic template with manifest.
	 */
	function testManifestLoad() {
		$folder = $this->objFromFixture("DynamicTemplate", "TemplateWithManifest");
		$manifest = $folder->getManifest();

		$this->assertTrue(isset($manifest['default']), "manifest has default action");
		$this->assertEquals(count($manifest), 1, "manifest has one action");
		$this->assertTrue(isset($manifest['default']['templates']), "manifest default action has templates");
		$this->assertEquals(count($manifest['default']['templates']), 1, "manifest default action has one template");
		$this->assertEquals(
			$manifest['default']['templates'][0],
			"dynamictemplate/tests/TemplateWithManifest/templates/test.ss",
			"manifest default action has test.ss"
		);
	}

	function testNormalisation() {
	}
}

