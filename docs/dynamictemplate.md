# Dynamic Template Module

## Maintainer Contact

* Mark Stephens
  <mark (at) silverstripe (dot) com>

## Requirements

* SilverStripe 2.4 or newer

## Module Status

Still under active development. Contact author if you are planning to use it.

## Overview

This module lets you create pages whose style is rapidly changeable through
the use of dynamic template "bundles", which can be uploaded and applied
through the CMS.

Changing templates does not require a developer. A designer can create and
upload a bundle directly, and apply it to a page when ready.

Typical usages are:

* a page whose presentation is defined in the theme, but periodically may
  need to have some layout, css or javascript overridden or added to, e.g.
  for the occasional promotion.
* a page whose design changes weekly. The theme might only have minimal
  templating for this page type, and it relies entirely on having a dynamic
  template selected.

Different dynamic templates can apply to draft vs. live versions of a page,
and hence a promotional design can be tested, and switched live when ready.

# Installation

Simply add the module to the top level of your SilverStripe installation and
perform a dev/build. A new page type will be created called DynamicTemplatePage,
which you can subclass for pages that require dynamic behaviour.

# Dynamic Templates

A template is a collection of files in a small directory structure. The template
can be zipped at its source, and the ZIP file uploaded to the CMS. On upload to
a configurable location within assets, the ZIP is automatically extracted, and
becomes immediately available to apply to pages.

The top level of a template folder will typically contain:

* A file called `MANIFEST` which defines the assets present in the template
  and how they are applied to the page when rendered.
* Directories 'templates', 'javascript', 'css' and 'images' as needed. Other
  directories may also be present.

A zip file containing a template can either have the folders and MANIFEST
directly at the top level of the ZIP file, or these may be contained in a
single folder at the top level of the ZIP. (Absolute paths should not be
used in the zip file).

## MANIFEST

This is a YAML file that defines what is in the template and how it behaves
when applied to a DynamicTemplatePage derivative.

The following is a simple example of a MANIFEST file:

	index:
	  templates:
	    main: BasePage.ss
	    Layout: MyPage.ss
	  css:
	    layout.css
	    screen.css: screen
	    print.css: print
	  javascript:
	    MyPage.js


At the top level are the actions available in the controller. Typically
this is just "index", but there may be others if the controller understands
others (e.g. `mypage/profile` has an action "profile", which can have distinct
template from `mypage/edit`, which has an action "edit". `mypage` has an action
of index.

The templates for an action consist of label/value pair. The label (e.g. "main:"
above) is understood by SilverStripes template renderer, and can be either
"main", "Layout" or "Content", with the meanings being consistent with the
renderer's usage. The value part, as above, is the name of a .ss file within
the templates directory. (If there are further folders, the .ss files may
be prefixed with these). When a page is rendered with a dynamic template,
the full path names are automatically determined and passed into the viewer.

Note: a current limitation is that you can't specified a template for Layout,
and have the viewer automatically determine the main template. This will be
resolved as soon as possible, for maximum flexbility and re-use.

CSS declarations in the manifest file can simply list the name of a file in
the css directory, or can also include the screen or print media directives.
(CSS files used in rendering a dynamic content page are added to the page
using Requirements::css() in the controller's init() function. The pathname
will be expanded automatically to the path relative to the base URL of the
site)

javascript declarations are handled in a similar way. Currently there is no way
to control the order of the javascript files relative to other javascript
files that are included on the page (all js files within a dynamic template
are rendered in the order they occur in the manifest.)

# Known Limitations

* <% include %> tag won't work in a dynamic template. This requires
  SSViewer to understand the included template may be inside a dynamic
  template. It is being worked on.
* References to resources such as images in the CSS or template markup have
  to rely on knowing where the containing dynamic template is installed on
  assets, so there is coupling between the template and the configuration
  option to determine where templates reside within assets. It might be useful
  to have some notation that can be used in CSS and SS files that indicate
  that an image is in the template, and automatically translate the path.