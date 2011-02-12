/**
 * Load a new page into the right-hand form.
 * 
 * @param formContent string
 * @param response object (optional)
 * @param evalResponse boolean (optional)
 */
/* Note, this is quite a hack. Basically we are overriding loadNewPage()
 * that is defined in LeftAndMain_right.js, so we can make the tree
 * dynamically load. This is the shortest (ugly) path to getting it working,
 * and should be refactored once we have the much saner js of ss3, where
 * we should be able to use entwine to bind the tree-initialisation
 * behaviour to the tree on ajax load.
 */
CMSForm.prototype.loadNewPageOrig = CMSForm.prototype.loadNewPage;
CMSForm.prototype.loadNewPage = function(formContent, response, evalResponse) {
	this.loadNewPageOrig(formContent, response, evalResponse);

	// Here is the the custom behaviour for the tree
	jQuery("#files-tree").treeTable({
		initialState: "expanded",
		clickableNodeNames: true
	});
}
