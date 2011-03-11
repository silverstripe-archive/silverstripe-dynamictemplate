<?php

class DynamicTemplateTest extends SapphireTest {
	static $fixture_file = 'dynamictemplate/tests/DynamicTemplate.yml';

	/**
	 * Before running the tests, we need to copy the test templates into assets.
	 * The yaml file points to these temporary folders. This is required because
	 * Folder/File classes have dependencies on assets, but we don't want them
	 * permanently sitting there.
	 *
	 * @return void
	 */
	function setUpOnce() {
		parent::setUpOnce();

	}

	function setUp() {
		parent::setUp();

		// clear File to start.
		$files = DataObject::get("File");
		if ($files) foreach ($files as $f) $f->deleteDatabaseOnly();

		$folder = Folder::findOrMake('/dynamic_templates/');
		$test1 = $this->setUpTemplate($folder, 'tmp__TemplateNoManifest');
		$test2 = $this->setUpTemplate($folder, 'tmp__TemplateWithManifest');
		$this->recurse_copy(
			Director::baseFolder() . "/dynamictemplate/tests/TemplateNoManifest",
			Director::baseFolder() . "/assets/dynamic_templates/tmp__TemplateNoManifest"
		);
		$this->recurse_copy(
			Director::baseFolder() . "/dynamictemplate/tests/TemplateWithManifest",
			Director::baseFolder() . "/assets/dynamic_templates/tmp__TemplateWithManifest"
		);

		$test1->syncChildren();
		$test2->syncChildren();
//		$this->dump_files("end of setUp");
	}

	function setUpTemplate($parent, $name) {
		$template = new DynamicTemplate();
		$template->ParentID = $parent->ID;
		$template->Name = $name;
		$template->Title = $name;
		$template->write();
		return $template;
	}

	function dump_files($where) {
		echo $where . ":\n";
		$files = DataObject::get("File");
		if ($files) foreach ($files as $file) {
			echo "  {$file->ID}:{$file->ParentID}:{$file->ClassName}:{$file->Name}:{$file->Filename}\n";
		}
		else
			echo "  none\n";
	}

	/**
	 * Remove the temp copies of the templates we created.
	 * @return void
	 */
	function tearDownOnce() {
		parent::tearDownOnce();

		$this->delete_directory(Director::baseFolder() . "/assets/dynamic_templates/tmp__TemplateNoManifest");
		$this->delete_directory(Director::baseFolder() . "/assets/dynamic_templates/tmp__TemplateWithManifest");
	}

	function recurse_copy($src,$dst) {
		$dir = opendir($src);
		@mkdir($dst);
		while(false !== ( $file = readdir($dir)) ) {
			if (( $file != '.' ) && ( $file != '..' )) {
				if (is_dir($src . '/' . $file) ) $this->recurse_copy($src . '/' . $file,$dst . '/' . $file);
				else copy($src . '/' . $file,$dst . '/' . $file);
			}
		}
		closedir($dir); 
	}

	function delete_directory($dirname) {
	   if (is_dir($dirname))
		  $dir_handle = opendir($dirname);
	   if (!$dir_handle)
		  return false;
	   while($file = readdir($dir_handle)) {
		  if ($file != "." && $file != "..") {
			 if (!is_dir($dirname."/".$file))
				unlink($dirname."/".$file);
			 else
				$this->delete_directory($dirname.'/'.$file);
		  }
	   }
	   closedir($dir_handle);
	   rmdir($dirname);
	   return true;
	}

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
		$folder = DataObject::get_one("DynamicTemplate", "\"Name\"='tmp__TemplateNoManifest'");
		$this->assertTrue($folder != null, "Template with no manifest exists");

		$manifest = $folder->getManifest();

		$this->assertTrue(isset($manifest->actions['index']), "Default action derived with no manifest");
		$this->assertEquals(count($manifest->actions), 1, "only one action identified where no manifest");
		$this->assertTrue(isset($manifest->actions['index']['templates']), "templates found where no manifest");
		$this->assertEquals(count($manifest->actions['index']['templates']), 1, "one template found when no manifest");
		$this->assertTrue(isset($manifest->actions['index']['templates'][0]["type"]), "main template derived from no manifest");
		$this->assertTrue(
			strpos(
				$manifest->actions['index']['templates'][0]['path'], 'test.ss') !== FALSE,
			"picked up the right template without manifest"
		);

		$this->assertTrue(isset($manifest->actions['index']['css']), 'css present');
		$this->assertEquals(count($manifest->actions['index']['css']), 1, 'exactly one css file');

		$this->assertTrue(isset($manifest->actions['index']['javascript']), "javascript present");
		$this->assertEquals(count($manifest->actions['index']['javascript']), 1, "exactly one javascript file");
	}

	/**
	 * Test loading the simple dynamic template with manifest.
	 */
	function testManifestLoad() {
//		$this->dump_files("testManifestLoad start");

		$folder = DataObject::get_one("DynamicTemplate", "\"Name\"='tmp__TemplateWithManifest'");
		$manifest = $folder->getManifest();

		$this->assertTrue(isset($manifest->actions['index']), "manifest has index action");
		$this->assertEquals(count($manifest->actions), 1, "manifest has one action");
		$this->assertTrue(isset($manifest->actions['index']['templates']), "manifest default action has templates");
		$this->assertEquals(count($manifest->actions['index']['templates']), 1, "manifest default action has one template");
		$this->assertTrue(
			strpos($manifest->actions['index']['templates'][0]['path'], 'test.ss') !== FALSE,
			"manifest default action has test.ss"
		);

		// Test manifest is cached.
		$m = unserialize($folder->ManifestCache);
		$this->assertTrue(is_object($m) && is_a($m, 'DynamicTemplateManifest'), "Cached manifest is a DynamicTemplateManifest");
		$this->assertTrue(isset($m->actions["index"]), "Cached manifest has index");
	}

	/**
	 * Test that a page with a dynamic template renders the right bits
	 */
	function testPageRender() {
		$folder = DataObject::get_one("DynamicTemplate", "\"Name\"='tmp__TemplateWithManifest'");
		$page1 = new DynamicTemplatePage();
		$page1->Title = 'page1';
		$page1->DynamicTemplateID = $folder->ID;

		$controller = new DynamicTemplatePage_Controller($page1);
		$controller->init();
		$html = $controller->defaultAction("index");
//		echo "html is: " . $html . "\n";
		$this->assertTrue(preg_match("/^\s*This is a test\.\s*$/mU", $html) > 0, "expected test content");
		$this->assertTrue(preg_match("/^\s*\<link rel=.stylesheet.*href=.*assets\/dynamic_templates\/tmp__TemplateWithManifest\/css\/test.css.*$/mU", $html) > 0, "CSS injected");
		$this->assertTrue(preg_match("/.*\<script.*src=.*assets\/dynamic_templates\/tmp__TemplateWithManifest\/javascript\/test.js.*$/mU", $html) > 0, "javascript injected");
	}

/*	function testUnzipWithDir() {
		if (!class_exists("ZipArchive")) return;

		$errors = array();
		DynamicTemplate::extract_bundle("dynamictemplate/tests/_UnitTestTemplateDir.zip", &$errors);
		$this->assertEquals(count($errors), 0, "Zipped template with dir extracts with no errors");
		// @todo Check that scandir returns the 4 things

		DynamicTemplate::extract_bundle("dynamictemplate/tests/_UnitTestTemplateNoDir.zip", &$errors);
		$this->assertEquals(count($errors), 0, "Zipped template without dir extracts with no errors");
		// @todo Check that scandir returns the same 4 things
		// @todo remove both artifacts created by the unit test, ensuring the file system is also synced.
	}
*/
	function testManifestCreation() {
		$manifest = new DynamicTemplateManifest();

		// check initial state of the manifest
		$this->assertTrue($manifest != null, "Manifest exists");
		$this->assertTrue(
			is_object($manifest) && is_a($manifest, 'DynamicTemplateManifest'),
			'Manifest is a DynamicTemplateManifest object'
		);
		$this->assertTrue(isset($manifest->actions), 'Empty manifest has actions');
		$this->assertEquals(count($manifest->actions), 0, 'Empty manifest has no actions');
		$this->assertTrue(isset($manifest->metadata), 'Empty manifest has metadata');
		$this->assertEquals(count($manifest->metadata), 0, 'Empty manifest has 0 metadata items');
		$this->assertTrue(!$manifest->hasPath("foo.ss", "index"), "Doesn't have a path and we don't expect it to");

		// add a template, non-standard action, not linked
		$manifest->addPath('myaction', 'test.ss', 'main');
		$this->assertTrue(isset($manifest->actions['myaction']), 'added action is present');
		$this->assertTrue(!isset($manifest->actions['index']), "index action is not present, and shouldn't be");
		$this->assertEquals(count($manifest->actions), 1, "There is 1 action");
		$this->assertTrue(isset($manifest->actions['myaction']['templates']), 'Templates area exists in manifest');
		$this->assertEquals(count($manifest->actions['myaction']['templates']), 1, 'There is 1 template in the manifest');
		$this->assertEquals($manifest->actions['myaction']['templates'][0]['path'], 'test.ss', 'template we added is present');
		$this->assertTrue(isset($manifest->actions['myaction']['templates'][0]['type']), 'template has type');
		$this->assertEquals($manifest->actions['myaction']['templates'][0]['type'], 'main', 'template we added is flagged main');
		$this->assertEquals($manifest->actions['myaction']['templates'][0]['linked'], false, 'template we added is correctly not flagged as linked');

		$this->assertTrue($manifest->hasPath("test.ss", "myaction"), "Has a path and we do expect");
		$this->assertTrue(!$manifest->hasPath("test.ss", "index"), "Doesn't have a path in index action and we don't expect it to");
		$this->assertTrue(!$manifest->hasPath("foo.ss", "myaction"), "Doesn't have a path and we don't expect it to");

		// add a linked template so we can test that
		$manifest->addPath('myaction', '/somedir/foo.ss', 'Layout');
		$this->assertEquals(count($manifest->actions['myaction']['templates']), 2, 'there are now two templates');
		$this->assertEquals($manifest->actions['myaction']['templates'][0]['path'], 'test.ss', 'first template is still present');
		$this->assertEquals($manifest->actions['myaction']['templates'][1]['path'], '/somedir/foo.ss', 'new template has correct path');
		$this->assertTrue(isset($manifest->actions['myaction']['templates'][1]['type']), 'new template has type');
		$this->assertEquals($manifest->actions['myaction']['templates'][1]['type'], 'Layout', 'new template we added is flagged Layout');
		$this->assertEquals($manifest->actions['myaction']['templates'][1]['linked'], true, 'new template we added is correctly flagged as linked');
		$this->assertEquals($manifest->actions['myaction']['templates'][0]['type'], 'main', 'original template still flagged main');

		// add the original path again. it should not be added again
		$manifest->addPath('myaction', 'test.ss', 'main');
		$this->assertEquals(count($manifest->actions['myaction']['templates']), 2, 'there are still two templates');

		// remove the original template
		$manifest->removePath('myaction', 'test.ss');

		$this->assertEquals(count($manifest->actions['myaction']['templates']), 1, 'There is only 1 remaining template in the manifest');
		$this->assertEquals($manifest->actions['myaction']['templates'][1]['path'], '/somedir/foo.ss', 'remaining template has correct path');
		$this->assertEquals($manifest->actions['myaction']['templates'][1]['type'], 'Layout', 'remaining template we added is still flagged Layout');
	}

	function testManifestStorage() {

	}

	function testManifestHelpers() {
		$this->assertEquals(DynamicTemplateManifest::get_extension('file.ss'), '.ss', 'get extension successfully strips an extension');
		$this->assertEquals(DynamicTemplateManifest::get_extension('path.with.dot/file.tar.gz'), '.gz', 'get extension on path with lots of dots');
	}
}

