<?php

DataObject::add_extension('File', 'DynamicTemplateDecorator');
File::$allowed_extensions[] = "ss";
