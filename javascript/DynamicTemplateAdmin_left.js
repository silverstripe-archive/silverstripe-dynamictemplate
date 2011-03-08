if(typeof SiteTreeHandlers == 'undefined') SiteTreeHandlers = {};
SiteTreeHandlers.parentChanged_url = 'admin/dynamictemplates/ajaxupdateparent';
SiteTreeHandlers.orderChanged_url = 'admin/dynamictemplates/ajaxupdatesort';
SiteTreeHandlers.showRecord_url = 'admin/dynamictemplates/show/';
SiteTreeHandlers.controller_url = 'admin/dynamictemplates';
SiteTreeHandlers.loadPage_url = 'admin/dynamictemplates/getitem';
SiteTreeHandlers.loadTree_url = 'admin/dynamictemplates/getsubtree';

/**
 * Add page action
 * @todo Remove duplication between this and the CMSMain Add page action
 */
var addtemplate = {
	button_onclick : function() {
		addtemplate.form_submit();
		return false;
	},

	form_submit : function() {
		var st = $('sitetree');
		$('addtemplate_options').elements.ParentID.value = st.firstSelected() ? st.getIdxOf(st.firstSelected()) : 0;
		Ajax.SubmitForm('addtemplate_options', null, {
			onSuccess : function(response) {
				Ajax.Evaluator(response);

				// explicitly disable the loading, since the default
				// behaviour doesn't work, even though it should
				if ($('Loading')) $('Loading').style.display = 'none';
				jQuery("#sitetree .loading").removeClass("loading");

				// a totally blech hack. Something doesn't initialise the
				// form properly, so we do what it should have done, which
				// prevents a javascript error when the form is saved.
				$("Form_EditForm").formName = "right";
			},
			onFailure : function(response) {
				errorMessage('Error adding page', response);
			}
		});

		return false;
	}
}

/**
 * Initialisation function to set everything up
 */
Behaviour.addLoader(function () {
	// Set up add page
	Observable.applyTo($('addtemplate_options'));
	if($('addtemplate')) {
		$('addtemplate').onclick = addtemplate.button_onclick;
		$('addtemplate').getElementsByTagName('button')[0].onclick = function() {return false;};
		$('addtemplate_options').onsubmit = addtemplate.form_submit;
	}

});

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


jQuery.fn.extend({
	insertAtCaret: function(myValue){
		return this.each(function(i) {
			if (document.selection) {
				this.focus();
				sel = document.selection.createRange();
				sel.text = myValue;
				this.focus();
			}
			else if (this.selectionStart || this.selectionStart == '0') {
				var startPos = this.selectionStart;
				var endPos = this.selectionEnd;
				var scrollTop = this.scrollTop;
				this.value = this.value.substring(0, startPos)+myValue+this.value.substring(endPos,this.value.length);
				this.focus();
				this.selectionStart = startPos + myValue.length;
				this.selectionEnd = startPos + myValue.length;
				this.scrollTop = scrollTop;
			} else {
				this.value += myValue;
				this.focus();
			}
		})
	}
});

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
				},

				// Return the textarea where edits are actually represented.
				getEditingTextarea: function() {
					var fs=window.frames;
					if (fs["frame_Form_FileEditForm_SourceText"] && fs["frame_Form_FileEditForm_SourceText"].editArea) {
						return fs["frame_Form_FileEditForm_SourceText"].editArea.textarea;
					}
					else return $('Form_FileEditForm_SourceText')[0];
				},

				/**
				 * Start syntax editing on the specific textarea
				 * @param String textarea		ID of textarea
				 * @param String synax			One of 'css', 'js' or 'html'
				 */
				startSyntaxEditor: function(textarea, syntax) {
					editAreaLoader.init({
						id : textarea,
						syntax: syntax,				// syntax to be uses for highgliting
						start_highlight: true,		// to display with highlight mode on start-up
						allow_resize: "no",
						toolbar: "search,go_to_line,fullscreen,|,undo,redo,|,select_font,highlight",
						font_size: 9,
						allow_toggle: false
					});
				},

				/**
				 * Gets the syntax for the editor, based on s.
				 * @param String s		one of: ss, css, js, templates, javascript
				 * @return String		one of: html, css, js
				 */
				getSyntax: function(s) {
					switch (s) {
						case 'ss':
						case 'templates':
							return 'html';
						case 'js':
						case 'javascript':
							return 'js';
						case 'css':
							return 'css';
						default:
							return null;
					}
				}
			});

			// Look for class type-x and return one of
			// 'css', 'js' or 'html' if the type is 'css', 'javascript' or 'templates',
			// respectively, or null if there is no matching class.
			$('#files-tree tr button').entwine({
				getEditorSyntax: function() {
					var classes = $(this).attr('class').split(/\s+/);
					var result = null;
					$.each(classes, function(index, item) {
						if (item.substr(0, 5) == 'type-') result = item.substr(5);
					});
					return $('#popup').getSyntax(result);
				}
			});

			// When edit is clicked
			$("#files-tree .action-edit").entwine({
				onclick: function(e) {
					// grab the URL from the parent of the button, which is an <a>
					var url = this.parent().attr("href");
					var syntax = this.getEditorSyntax();

					$.get(
						url,
						null, // data
						function(data, textStatus, xhr) {
							// the data is a form, we insert that into
							// the overlay.
							$('#popup').showPopup(data,'is-editing-source');
							$('#popup').startSyntaxEditor('Form_FileEditForm_SourceText', syntax);
						},
						"html"
					);

					// make the ajax call to fetch the editor, and show it in the popup
					return false;
				}
			});

			// When a helper insertable is clicked, insert it's contents into
			// the editor
			$('#popup .insertable').entwine({
				onclick: function(e) {
					var text = this.text();
					var ta = $('#popup').getEditingTextarea();
					$(ta).insertAtCaret(text);
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
					// grab the value from the text editor, so we can
					// stick it back into the textarea that gets submitted back.
					// @todo This is a nasty piece of coupling, because the editor
					//       is not a nice jquery plugin, and doesn't provide
					//		 an API for getting some of the info we need.
					//		 Note also that although the editor is supposed to
					//       handle form submissions, the fact that we are using
					//		 jquery.form to submit via ajax escapes the editor.
					//		 which is why we have to grab the currently edited
					//		 field out.
					var ta = $('#popup').getEditingTextarea();
					if (ta) $('#Form_FileEditForm_SourceText').val(ta.value);

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
							// determine syntax from file name
							var ext = filename.split('.').pop();
							var syntax = $('#popup').getSyntax(ext);
							$('#popup').startSyntaxEditor('Form_FileEditForm_SourceText', syntax);
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

			$('#Form_LinkedFileViewForm_action_cancelFileEdit').entwine({
				onclick: function(e) {
					$('#popup').hidePopup();
					return false;
				}
			});
		})

		$('.actionparams').attr("action", window.location.pathname + 'addtemplate');
	});


	$('#Form_EditForm_deletetemplate').live( 'click', function(){
		$('#Form_EditForm').attr("action", window.location.pathname + 'deletetemplate');
		var URL = window.location.pathname + 'deletetemplate';
		$.ajax({
	  		url: URL,
		  	data: "",
		  		success: function(data) {
				if(data){
					statusMessage('deleting.....');
					$('#Form_EditForm').html(data);
					$('#sitetree li .current').remove();
					statusMessage('Template Deleted', 'good');
				} else{

				}
			}
		});
		return false;
	});


	$('#Form_EditForm_exporttemplate').live( 'click', function(){
		$('#Form_EditForm').attr("action", window.location.pathname + 'create_zip');
		var URL = window.location.pathname + 'create_zip';
		$.ajax({
	  		url: URL,
		  	data: "",
		  		success: function(data) {
				if(data){

				} else{

				}
			}
		});
		return false;
	});

	$('#Form_EditForm_savetemplate').live( 'click', function(){
		$('#Form_EditForm').attr("action", window.location.pathname + 'save');
		var URL = window.location.pathname + 'save';
		$.ajax({
	  		url: URL,
		  	data: "",
		  		success: function(data) {
				if(data){

				} else{

				}
			}
		});
		return false;
	});



})(jQuery);
