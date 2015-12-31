<?php

/**
 * An SSViewer sub-class that handles dynamic templates,
 * where the template directories can be explicitly defined in the template
 * list.
 */
class SSDynamicViewer extends SSViewer
{
    /**
     * Constructor handles the same options as SSViewer::__construct(),
     * but also accepts a raw array with at least "main" key. In this case,
     * the constructor does not try to do any interpretation, but uses them
     * as the templates explicitly.
     */
    public function __construct($templateList)
    {
        global $_TEMPLATE_MANIFEST;

        $chosenTemplates = array();

        // flush template manifest cache if requested
        if (isset($_GET['flush']) && $_GET['flush'] == 'all') {
            if (Director::isDev() || Director::is_cli() || Permission::check('ADMIN')) {
                self::flush_template_cache();
            } else {
                return Security::permissionFailure(null, 'Please log in as an administrator to flush the template cache.');
            }
        }

        if (is_string($templateList) && substr((string) $templateList, -3) == '.ss') {
            $chosenTemplates['main'] = $templateList;
        } elseif (is_array($templateList) && isset($templateList["main"])) {
            $chosenTemplates = $templateList;
        } else {
            if (!is_array($templateList)) {
                $templateList = array($templateList);
            }
            
            if (isset($_GET['debug_request'])) {
                Debug::message("Selecting templates from the following list: " . implode(", ", $templateList));
            }

            foreach ($templateList as $template) {
                // @todo If there is a path, use that template
                // if passed as a partial directory (e.g. "Layout/Page"), split into folder and template components
                if (strpos($template, '/') !== false) {
                    list($templateFolder, $template) = explode('/', $template, 2);
                } else {
                    $templateFolder = null;
                }

                // @todo explicit location overrides theme and default

                // Use the theme template if available
                if (self::current_theme() && isset($_TEMPLATE_MANIFEST[$template]['themes'][self::current_theme()])) {
                    $chosenTemplates = array_merge(
                        $_TEMPLATE_MANIFEST[$template]['themes'][self::current_theme()],
                        $chosenTemplates
                    );
                    
                    if (isset($_GET['debug_request'])) {
                        Debug::message("Found template '$template' from main theme '" . self::current_theme() . "': " . var_export($_TEMPLATE_MANIFEST[$template]['themes'][self::current_theme()], true));
                    }
                }
                
                // Fall back to unthemed base templates
                if (isset($_TEMPLATE_MANIFEST[$template]) && (array_keys($_TEMPLATE_MANIFEST[$template]) != array('themes'))) {
                    $chosenTemplates = array_merge(
                        $_TEMPLATE_MANIFEST[$template],
                        $chosenTemplates
                    );
                    
                    if (isset($_GET['debug_request'])) {
                        Debug::message("Found template '$template' from main template archive, containing the following items: " . var_export($_TEMPLATE_MANIFEST[$template], true));
                    }
                    
                    unset($chosenTemplates['themes']);
                }

                if ($templateFolder) {
                    $chosenTemplates['main'] = $chosenTemplates[$templateFolder];
                    unset($chosenTemplates[$templateFolder]);
                }
            }

            if (isset($_GET['debug_request'])) {
                Debug::message("Final template selections made: " . var_export($chosenTemplates, true));
            }
        }

        if (!$chosenTemplates) {
            throw new Exception("None of these templates can be found in theme '" .
                                self::current_theme() .
                                "': ". implode(", ", $templateList) .
                                ". Have you set a main template in the dynamic template?");
        }

        // Now set the real chosenTemplates, via setTemplateFile, because
        // $this->chosenTemplate is private, not protected :-(
        foreach ($chosenTemplates as $type => $file) {
            $this->setTemplateFile($type, $file);
        }
    }
}
