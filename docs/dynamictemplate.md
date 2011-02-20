# Dynamic Template Module

## Maintainer Contact

* Mark Stephens
  <mark (at) silverstripe (dot) com>

## Requirements

* SilverStripe 2.4 or newer

## Module Status

Still under active development. Contact author if you are planning to use it.

## Overview

This module lets you alter the presentation of pages on your site flexibly
and dynamically. Example applications include:

* pages (e.g. home or landing pages) whose design may change frequently
* pages where promotional or informational changes are required for a limited
  period of time
* for designing variations of page design for use with the A/B testing module.

The module provides functionality aimed at users with a level of competency
in HTML/CSS, including but not limited to designers and developers.

Key features include:

* a CMS interface for creating, editing, previewing and applying dynamic
  templates to pages.
* import/export of dynamic templates, allowing the development of dynamic
  templates in a development environment, and shipped to the production
  environment.
* dynamic templates can be created from existing template files in a theme,
  use includes, javascript, CSS and images from the theme.

Benefits include:

* Rapid development and application of templates to pages.
* Lowers the barrier of entry to working with SilverStripe templates.
* Code deployments are not required for changes in templates
* Fits in with the page publishing mechanism so changes can be viewed in draft,
  and used in conjunction with features such as embargo/expiry in cmsworkflow
  that automate the publishing/unpublishing of pages.

Different dynamic templates can apply to draft vs. live versions of a page,
and hence a promotional design can be tested, and switched live when ready.

# Installation

Simply add the module to the top level of your SilverStripe installation and
perform a dev/build. A new page type will be created called DynamicTemplatePage,
which you can subclass for pages that require dynamic behaviour.

# CMS Interface

The module adds a new section "Dynamic Templates" to the CMS. This shows the dynamic
templates currently in the system, and supports the following operations:

* Create an empty dynamic template
* Create a dynamic template from the theme
* Delete a dynamic template
* Upload a dynamic template
* Manage a dynamic template

# Internal Representation of Dynamic Templates

A template is a collection of files in a small directory structure.

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
	    themes/mytheme/templates/Page.ss: main
	    MyPage.ss: Layout
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

## Metadata

When a MANIFEST file is provided, it can include as its first definition an
action called "metadata" which can define extra properties for the template.

For example:

    metadata:
      classes: PageA,PageB
    index:
      templates:
        main: BasePage.ss

There is only one meta-data property at present, which is the "classes" property. It
defines a list of classes that the template applies to. The user cannot select a
template for a page unless that page is or descends from one of the classes listed.
This prevents a template designed for one page layout from being applied accidentally
to the wrong page type.

# Known Limitations

* <% include %> tags can be used to include a file from the theme,
  but not from within the dynamic template itself.
* References to resources such as images in the CSS or template markup have
  to rely on knowing where the containing dynamic template is installed on
  assets, so there is coupling between the template and the configuration
  option to determine where templates reside within assets. It might be useful
  to have some notation that can be used in CSS and SS files that indicate
  that an image is in the template, and automatically translate the path.
