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

	$("#theme-files-tree").treeTable({
		initialState: "expanded",
		clickableNodeNames: true
	});

	$(document).ready(function() {
		// create edit-source-overlay.
		$("body").append('<div id="popup-overlay"></div><div id="popup"><div class="content"></div></div>');
		$.entwine('filetree', function($) {
			$('#popup').entwine({
				// show the popup with 'content' in it. intent is a class
				// prefixed with is- that can alter presentation in the css.
				showPopup: function(content, intent) {
					$('#popup .content').html(content);
					$("#popup-overlay").show();
					if (intent) $('#popup').addClass(intent);
					$('#popup').show();
				},

				// hide the popup. Remove any intent classes added by
				// showPopup.
				hidePopup: function() {
					$('#popup').hide();
					$('#popup-overlay').hide();
					var classes = $('#popup').attr('class').split(/\s+/);
					$.each(classes, function(index, item) {
						if (item.substr(0, 3) == 'is-') $('#popup').removeClass(item);
					});
				}
			});

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
							$('#popup').showPopup(data,'is-editing-source');
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

			// tree item action: unlink - unlinks a linked file.
			$('#files-tree .action-unlink').entwine({
				onclick: function(e) {
					var btn = this;
					var url = this.parent().attr("href");
					$.get(
						url,
						null,
						function(data, textStatus, xhr) {
							if (data == "ok") {
								statusMessage('File unlinked', 'good');
								btn.parent().parent().parent().remove(); // delete the <tr> that holds this record.
							}
							else statusMessage('Problem unlinking file', 'bad');
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
			$('#popup #Form_FileEditForm_action_cancelFileEdit').entwine({
				onclick: function(e) {
					$('#popup').hidePopup();
					return false;
				}
			});

			// edit source action: save. submits the form by ajax, and then closes the overlay.
			$('#popup #Form_FileEditForm_action_saveFileEdit').entwine({
				onclick: function(e) {
					$('#Form_FileEditForm').ajaxSubmit({
						success: function() {
							statusMessage('Saved', 'good');
						}
					});
					$('#popup').hidePopup();
					return false;
				}
			});

			/* These events handle linking files */
			$('#Form_EditForm_linkfile').entwine({
				// When link file is clicked, get the dialog by ajax and put it in the 
				// popup.
				onclick: function(e) {
					var url = 'admin/dynamictemplates/LoadThemeLinkOptionsForm';
					$.get(
						url,
						null, // data
						function(data, textStatus, xhr) {
							// the data is a form, we insert that into
							// the overlay.
							$('#popup').showPopup(data, 'is-editing-links');
							$("#theme-files-tree").treeTable({
								initialState: "expanded",
								clickableNodeNames: true
							});
						},
						"html"
					);

					// block the regular action handling
					return false;
				}
			});
			
			// link to theme: save.
			$('#popup #Form_ThemeLinkOptionsForm_action_saveThemeLink').entwine({
				onclick: function(e) {
					$('#Form_ThemeLinkOptionsForm').ajaxSubmit({
						success: function() {
							statusMessage('Saved', 'good');
						}
					});
					$('#popup').hidePopup();
					return false;
				}
			});

			// link to theme: cancel. just closes the overlay.
			$('#popup #Form_ThemeLinkOptionsForm_action_cancelThemeLink').entwine({
				onclick: function(e) {
					$('#popup').hidePopup();
					return false;
				}
			});

			// new file. Popup asks for a file name, with no path.
			// submits this to the server which creates the file in the
			// right place, and returns the editor to edit the content.
			$('#Form_EditForm_newfile').entwine({
				onclick: function(e) {
					var markup = '<div class="newfile"><div class="input-container">New file name:<br/><input type="text" class="filename"></div><div class="actions"><button class="action-ok">ok</button><button class="action-cancel">cancel</button></div></div>';
					$('#popup').showPopup(markup, 'is-editing-filename');

					// block the regular action handling
					return false;
				}
			});

			$('#popup .newfile .action-ok').entwine({
				onclick: function(e) {
					var filename = $('#popup .newfile .filename').attr('value');
					if (filename == '') {
						alert('File name cannot be empty');
						return false;
					}

					var url = 'admin/dynamictemplates/LoadNewFileForm';
					url += '?filename=' + filename;
					$.get(
						url,
						null, // data
						function(data, textStatus, xhr) {
							// the data is a form, we insert that into
							// the overlay.
							$('#popup').hidePopup(); // clear from getting name
							$('#popup').showPopup(data, 'is-editing-source');
							$("#theme-files-tree").treeTable({
								initialState: "expanded",
								clickableNodeNames: true
							});
						},
						"html"
					);
				}
			});

			$('#popup .newfile .action-cancel').entwine({
				onclick: function(e) {
					$('#popup').hidePopup();
					return false;
				}
			});
		});
	});
})(jQuery);
