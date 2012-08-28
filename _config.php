<?php

DataObject::add_extension('File', 'DynamicTemplateExtension');
File::$allowed_extensions[] = "ss";
