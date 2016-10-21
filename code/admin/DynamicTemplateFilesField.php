<?php

/**
 * Form field that shows a list of files within a dynamic template. This
 * is based on what's in the manifest, merged with files in the dynamic template.
 * The following are possible:
 * - a File object exists and it's in the manifest.
 * - a relative link is in the manifest, pointing to a file outside
 *   of the dynamic template, and no File exists.
 * - a File object exists, but is not in the manifest, such as for images.
 * 
 * The tree display is done by treeTable.
 */
class DynamicTemplateFilesField extends FormField
{
    public function __construct($name, $title = null, $value = null)
    {
        parent::__construct($name, $title, $value, null);
    }

    protected function getDynamicTemplate()
    {
        $result = $this->Value();
        if ($result != null) {
            return $result;
        }
        $id = Session::get("dynamictemplates_currentID");
        if ($id) {
            return DataObject::get_by_id("DynamicTemplate", $id);
        }
        return null;
    }

    /**
     * Generate a nested. Top level is the subfolders of the dynamic
     * template. Level beneath are the files. Each file is map,
     * with 'path' property, and 'ID' if it has an actual File object.
     * @todo Currently this totally ignores actions, effectively
     * only including what's in 'index' action.
     */
    protected function calcTree()
    {
        $null = null; // used with a reference. Don't remove.
        $result = array();
        $dt = $this->getDynamicTemplate();
        $manifest = $dt->getManifest();

        $treeId = 1;

        // Process the manifest
        if (isset($manifest->actions["index"])) {
            foreach ($manifest->actions["index"] as $folder => $value) {
                $subFolder = array("path" => $folder, "tree_id" => $treeId++, "children" => array());

                foreach ($value as $entry) {
                    $item = array('path' => $entry['path'], 'tree_id' => $treeId++);
                    if ($folder == 'templates') {
                        $item['template_type'] = isset($entry['type']) ? $entry['type'] : "";
                    }
                    $subFolder["children"][] = $item;
                }

                $result[] = $subFolder;
            }
        }

        // Now process the file system.
        if ($subFolders = $dt->AllChildren()) {
            foreach ($subFolders as $subFolder) {
                // locate the subfolder if it exists and return a reference to it.
                $ref = &$null;
                foreach ($result as $i => $sf) {
                    if ($sf['path'] == $subFolder->Name) {
                        $ref = &$result[$i];
                    }
                }
                if (!$ref) {
                    $newItem = array("path" => $subFolder->Name, "tree_id" => $treeId++, "children" => array());
                    $i = count($result);
                    $result[$i] = $newItem;
                    $ref = &$result[$i];
                }

                $files = $subFolder->AllChildren();
                if ($files) {
                    foreach ($files as $file) {
                        // see if its in the array already.
                    $found = null;
                        foreach ($ref["children"] as $key => $item) {
                            if ($item['path'] == $file->Name) {
                                $found = $key;
                            }
                        }
                        if ($found === null) {
                            $item = array("path" => $file->Name, "ID" => $file->ID, "tree_id" => $treeId++);
                            $ref["children"][] = $item;
                        } else {
                            $ref["children"][$found]["ID"] = $file->ID;
                        }
                    }
                }
            }
        }

        // Final sanity check pass. Any file that is not linked but that doesn't have an ID,
        // we remove from here. This can happen if a file is removed from the file system and manifest
        // doesn't get updated. We just don't want to show these.
        foreach ($result as $sf => $subFolder) {
            foreach ($subFolder["children"] as $i => $item) {
                if (strpos($item["path"], "/") === false && !isset($item['ID'])) {
                    unset($result[$sf]["children"][$i]);
                }
            }
        }

        return $result;
    }

    // Generate all markup for the tree.
    public function Field($properties = array())
    {
        $markup = "<table id=\"files-tree\">";

        $tree = $this->calcTree();

        foreach ($tree as $subFolder) {
            $markup .= "<tr id=\"filetree-node-{$subFolder["tree_id"]}\">";
            $markup .= "<td>{$subFolder["path"]}</td>";
            $markup .= "</tr>";

            foreach ($subFolder["children"] as $file) {
                $markup .= "<tr id=\"filetree-node-{$file['tree_id']}\" class=\"child-of-filetree-node-{$subFolder['tree_id']}\">";
                $markup .= "<td>{$file['path']}</td>";
                $markup .= $this->actionsForFile($subFolder, $file);
                $markup .= "</tr>";
            }
        }

        $markup .= "</table>";
        return $markup;
    }

    protected function actionsForFile($subFolder, $file)
    {
        $markup = "";
        if (!preg_match("/^.*\.([^.]+)$/", $file['path'], $matches)) {
            return "";
        }
        if (!isset($matches[1])) {
            return "";
        }
        $ext = $matches[1];

        $hasEdit = in_array($ext, array("ss", "css", "js"));
        $hasView = false;
        $hasDelete = true;
        $hasUnlink = false;

        if (strpos($file['path'], "/") !== false) {
            $hasUnlink = true;
            $hasDelete = false;
            $hasEdit = false; // can't edit linked files
            $hasView = true;
        }

        $markup .= '<td class="layout-col">';
        if ($subFolder['path'] == "templates") {
            // this is a template, so generate a combo for main and Layout
            $items = array("" => "none", "main" => "main", "Layout" => "layout");
            $markup .= '<select rel="' . $this->changeTemplateTypeLink($file) . '" class="action-select-template-type">';
            foreach ($items as $key => $item) {
                $markup .= '<option value="' . $key . '" ';
                if (isset($file['template_type']) && $file['template_type'] == $key) {
                    $markup .= "selected";
                }
                $markup .= '>' . $item;
                $markup .= '</option>';
            }
            $markup .= '</select>';
        }
        $markup .= '</td>';

        $markup .= '<td class="edit-view-col">';
        if ($hasEdit) {
            $markup .= '<button rel="' . $this->editLink($file) . '" class="action-edit type-' . $subFolder['path'] . '">Edit source</button>';
        }
        if ($hasView) {
            $markup .= '<button rel="' . $this->viewLinkedFileLink($file) . '" class="action-view type-' . $subFolder['path'] . '">View source</button>';
        }
        $markup .= '</td>';

        $markup .= '<td class="delete-col">';
        if ($hasUnlink) {
            $markup .= '<button rel="' . $this->unlinkLink($file, $subFolder) . '" class="action-unlink">Unlink</button>';
        }
        if ($hasDelete) {
            $markup .= '<button rel="' . $this->deleteLink($file) . '" class="action-delete">Delete</button>';
        }
        $markup .= '</td>';

        return $markup;
    }

    /**
     * Given the file map from the tree, generate a link to the edit form. This is
     * a link on DynamicTemplateAdmin that returns an edit form via ajax that
     * will edit the content of the file.
     */
    public function editLink($file)
    {
        if (!isset($file['ID'])) {
            return "";
        }
        return "admin/dynamictemplates/DynamicTemplate/LoadFileEditForm?ID={$file['ID']}";
    }

    public function viewLinkedFileLink($file)
    {
        $dt = $this->getDynamicTemplate();
        $params = array($dt->ID, $file['path']);
        return "admin/dynamictemplates/DynamicTemplate/LoadLinkedFileViewForm?ID=" . base64_encode(implode(':', $params));
    }

    /**
     * Given a file map from the tree, generate a link to the ajax method
     * to delete a file. Link has the file ID.
     */
    public function deleteLink($file)
    {
        if (!isset($file['ID'])) {
            return "";
        }
        return "admin/dynamictemplates/DynamicTemplate/DeleteFileFromTemplate?ID={$file['ID']}";
    }

    /**
     * Given a file map from the tree, generate a link to the ajax method
     * to unlink a file. Given that linked files are not File objects,
     * but just paths, we encode have to encode the path. We also need
     * to encode the subfolder and the dynamic template ID is this, separated
     * by colons.
     */
    public function unlinkLink($file, $subFolder)
    {
        $dt = $this->getDynamicTemplate();
        $params = array($dt->ID, $subFolder['path'], $file['path']);
        return "admin/dynamictemplates/DynamicTemplate/UnlinkFileFromTemplate?ID=" . base64_encode(implode(':', $params));
    }

    /**
     * Given a file map from the tree, generate a link to the ajax method
     * for changing a template type. Given that we can't always identify
     * template files by ID (they could be linked), we encode the dynamic
     * template ID and path separated by colons in base 64.
     */
    public function changeTemplateTypeLink($file)
    {
        $dt = $this->getDynamicTemplate();
        $params = array($dt->ID, $file['path']);
        return "admin/dynamictemplates/DynamicTemplate/ChangeTemplateType?ID=" . base64_encode(implode(':', $params));
    }
}
