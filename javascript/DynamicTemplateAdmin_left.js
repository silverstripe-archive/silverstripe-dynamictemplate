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

(function($) {
	$("#files-tree").treeTable({
		initialState: "expanded",
		clickableNodeNames: true
	});

	$(document).ready(function() {
		// create edit-source-overlay.
		$("body").append('<div id="edit-source-overlay"></div><div id="edit-source-popup"><div class="content"></div></div>');
		$.entwine('filetree', function($) {
			// When edit is clicked
			$("#files-tree .action-edit").entwine({
				onclick: function(e) {
					// grab the URL from the parent of the button, which is an <a>
					var url = this.parent().attr("href");
					$.get(
						url,
						null, // data
						function(data, textStatus, xhr) {
							// the data is a form, we insert that into
							// the overlay.
							$('#edit-source-popup .content').html(data);
							$("#edit-source-overlay").show();
							$("#edit-source-popup").show();
						},
						"html"
					);

					// make the ajax call to fetch the editor, and show it in the popup
					return false;
				}
			});

			// tree item action: delete. deletes a file.
			$('#files-tree .action-delete').entwine({
				onclick: function(e) {
					// grab the URL from the parent of the button, which is an <a>
					var btn = this;
					var url = this.parent().attr("href");
					$.get(
						url,
						null,
						function(data, textStatus, xhr) {
							if (data == "ok") {
								statusMessage('File deleted', 'good');
								btn.parent().parent().parent().remove(); // delete the <tr> that holds this record.
							}
							else statusMessage('Problem deleting file', 'bad');
						}
					);
					return false;
				}
			});

			$('#files-tree a.noclick').entwine({
				onclick: function(e) {
					return false;
				}
			});

			$('#files-tree .action-select-template-type').entwine({
				onchange: function(e) {
					var url = this.parent().attr("href"); // a href which has URL to ajax function
					url += '/' + this[0].value;

					$.get(
						url,
						null,
						function(data, textStatus, xhr) {
							if (data == "ok") {
								statusMessage('Changed type', 'good');
							}
							else statusMessage('Problem changing type', 'bad');
						}
					);

					return false;
				}
			});

			// edit source action: cancel. just closes the overlay.
			$('#edit-source-popup #Form_FileEditForm_action_cancelFileEdit').entwine({
				onclick: function(e) {
					$('#edit-source-popup').hide();
					$('#edit-source-overlay').hide();
					return false;
				}
			});

			// edit source action: save. submits the form by ajax, and then closes the overlay.
			$('#edit-source-popup #Form_FileEditForm_action_saveFileEdit').entwine({
				onclick: function(e) {
					$('#Form_FileEditForm').ajaxSubmit({
						success: function() {
							statusMessage('Saved', 'good');
						}
					});
					$('#edit-source-popup').hide();
					$('#edit-source-overlay').hide();
					return false;
				}
			});
		});
	});
})(jQuery);
