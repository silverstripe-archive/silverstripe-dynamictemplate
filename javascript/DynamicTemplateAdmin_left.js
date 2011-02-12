if(typeof SiteTreeHandlers == 'undefined') SiteTreeHandlers = {};
SiteTreeHandlers.parentChanged_url = 'admin/dynamictemplates/ajaxupdateparent';
SiteTreeHandlers.orderChanged_url = 'admin/dynamictemplates/ajaxupdatesort';
SiteTreeHandlers.showRecord_url = 'admin/dynamictemplates/show/';
SiteTreeHandlers.controller_url = 'admin/dynamictemplates';
SiteTreeHandlers.loadPage_url = 'admin/dynamictemplates/getitem';


SiteTree.prototype = {
	castAsTreeNode: function(li) {
		behaveAs(li, SiteTreeNode, this.options);
	},
	
	getIdxOf : function(treeNode) {
		if(treeNode && treeNode.id)
			return treeNode.id;
	},
	
	getTreeNodeByIdx : function(idx) {
		if(!idx) idx = "0";
		return document.getElementById(idx);
	},
	
	initialise: function() {
		this.observeMethod('SelectionChanged', this.changeCurrentTo);	
	}

};

SiteTreeNode.prototype.onselect = function() {
	$('sitetree').changeCurrentTo(this);
	if($('sitetree').notify('SelectionChanged', this)) {
		this.getTemplateFromServer();
	}
	return false; 
};

SiteTreeNode.prototype.getTemplateFromServer = function() {
	if(this.id) {
		var id = this.id;
		// strip record- off front of id. getPageFromServer expects a pure int.
		if(id.match(/record-([0-9a-zA-Z\-]+)$/))
			id = RegExp.$1;
		$('Form_EditForm').getPageFromServer(id);
	}
};

function reloadSiteTree() {
	
	new Ajax.Request( 'admin/dynamictemplates/getsitetree', {
		method: get,
		onSuccess: function( response ) {
			$('sitetree_holder').innerHTML = response.responseText;
		},
		onFailure: function( response ) {
				
		}	
	});

}
