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

	$.entwine('dt', function($) {

		// Tree presentation binding
		$("#files-tree").entwine({
			onmatch: function(e) {
				this.treeTable({
					initialState: "expanded",
					clickableNodeNames: true
				});
			}
		});

		$("#Form_ItemEditForm").entwine({
			refreshForm: function() {
				// @todo Improve this - it causes complete CMS reload and not ajax reload of the edit form. Ideally
				// @todo   we want to stay on the same tab as well. Either this, or ajax request to reload just the tree.
//				$('.cms-container').entwine('.').entwine('ss').loadPanel(window.History.getState().url, null, null);

				// @todo This works sporadically, usually after the first load but not subsequently.
				var button = $('#Form_ItemEditForm_action_doSave');
				button.trigger("click");
			}
		});

		//=========== Popup editing ============

		$('#popup').entwine({
			// show the popup with 'content' in it. intent is a class
			// prefixed with is- that can alter presentation in the css.
			showPopup: function(content, intent) {
				$('#popup .content').html(content);
				$("#popup-overlay").show();
				if (intent) $('#popup').addClass(intent);
				$('#popup-container').show();
				return this;
			},

			// hide the popup. Remove any intent classes added by
			// showPopup.
			hidePopup: function() {
				$('#popup-container').hide();
				$('#popup-overlay').hide();
				var classes = $('#popup').attr('class').split(/\s+/);
				$.each(classes, function(index, item) {
					if (item.substr(0, 3) == 'is-') $('#popup').removeClass(item);
				});
				return this;
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
			startSyntaxEditor: function(textarea, syntax, editing) {
				editAreaLoader.init({
					id : textarea,
					syntax: syntax,				// syntax to be uses for highgliting
					start_highlight: true,		// to display with highlight mode on start-up
					allow_resize: "no",
					toolbar: "search,go_to_line,fullscreen,|,undo,redo,|,select_font,highlight",
					font_size: 9,
					font_family: "monospace",
					allow_toggle: false,
					min_width: 750,
					min_height: 320,
					is_editable: editing
				});
			},

			/**
			 * Stop the syntax editor. We need to do this after we save or cancel from an editor or viewer,
			 * so it's state is cleared.
			 */
			stopSyntaxEditor: function(textarea) {
				editAreaLoader.delete_instance(textarea);
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

		// When a helper insertable is clicked, insert it's contents into
		// the editor
		$('#popup .insertable').entwine({
			onclick: function(e) {
				var text = this.text();
				var ta = $('#popup').getEditingTextarea();
				$(ta).insertAtCaret(text);
			}
		});

		// edit source action: cancel. just closes the overlay.
		$('#popup #Form_FileEditForm_action_cancelFileEdit').entwine({
			onclick: function(e) {
				$('#popup').hidePopup().stopSyntaxEditor('Form_FileEditForm_SourceText');
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
//					headers: {
//						"X-Pjax" : 'DTEditForm'
//					},
					success: function() {
						statusMessage('Saved', 'good');
						$("#Form_ItemEditForm").refreshForm();
					}
				});
				$('#popup').hidePopup().stopSyntaxEditor('Form_FileEditForm_SourceText');

				return false;
			}
		});

		//========= Template controls ==========

		// new file. Popup asks for a file name, with no path.
		// submits this to the server which creates the file in the
		// right place, and returns the editor to edit the content.
		$('#Form_ItemEditForm_newfile').entwine({
			onclick: function(e) {
				var markup = '<div class="newfile"><div class="input-container">New file name:<br/><input type="text" class="filename"></div><div class="actions"><button class="action-ok">ok</button><button class="action-cancel">cancel</button></div></div>';
				$('#popup').showPopup(markup, 'is-editing-filename');
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

				var url = 'admin/dynamictemplates/DynamicTemplate/LoadNewFileForm';
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

		$('#Form_ItemEditForm_linkfile').entwine({
			// When link file is clicked, get the dialog by ajax and put it in the 
			// popup.
			onclick: function(e) {
				var url = 'admin/dynamictemplates/DynamicTemplate/LoadThemeLinkOptionsForm';
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

				return false;
			}
		});

		// link to theme: save.
		$('#popup #Form_ThemeLinkOptionsForm_action_saveThemeLink').entwine({
			onclick: function(e) {
				$('#Form_ThemeLinkOptionsForm').ajaxSubmit({
					success: function() {
						statusMessage('Saved', 'good');
						$("Form_ItemEditForm").refreshForm();
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

		$('#Form_ItemEditForm_copyfile').entwine({
			// When copy file is clicked, get the dialog by ajax and put it in the 
			// popup.
			onclick: function(e) {
				var url = 'admin/dynamictemplates/DynamicTemplate/LoadThemeCopyOptionsForm';
				$.get(
					url,
					null, // data
					function(data, textStatus, xhr) {
						$('#popup').showPopup(data, 'is-editing-links');
						$("#theme-files-tree").treeTable({
							initialState: "expanded",
							clickableNodeNames: true
						});
					},
					"html"
				);

				return false;
			}
		});

		// link to theme: save.
		$('#popup #Form_ThemeCopyOptionsForm_action_saveThemeCopy').entwine({
			onclick: function(e) {
				$('#Form_ThemeCopyOptionsForm').ajaxSubmit({
					success: function() {
						statusMessage('Saved', 'good');
						$("Form_ItemEditForm").refreshForm();
					}
				});

				$('#popup').hidePopup();
				return false;
			}
		});

		$("#Form_ItemEditForm_exportastarball").entwine({
			onclick: function(e) {
				var url = 'admin/dynamictemplates/DynamicTemplate/exportastarball';
				window.location = url;
				return false;
			}
		});

		$("#Form_ItemEditForm_exportaszip").entwine({
			onclick: function(e) {
				var url = 'admin/dynamictemplates/DynamicTemplate/exportaszip';
				window.location = url;
				return false;
			}
		});

		// link to theme: cancel. just closes the overlay.
		$('#popup #Form_ThemeCopyOptionsForm_action_cancelThemeCopy').entwine({
			onclick: function(e) {
				$('#popup').hidePopup();
				return false;
			}
		});

		$('#Form_LinkedFileViewForm_action_cancelFileEdit').entwine({
			onclick: function(e) {
				$('#popup').hidePopup().stopSyntaxEditor('Form_LinkedFileViewForm_SourceText');
				return false;
			}
		});

		//=========== Tree controls ============

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
		$("#files-tree .action-edit, #files-tree .action-view").entwine({
			onclick: function(e) {
				// grab the URL from the parent of the button, which is an <a>
				var url = this.attr("rel");
				var syntax = this.getEditorSyntax();
				var editing = this.hasClass("action-edit");

				$.get(
					url,
					null, // data
					function(data, textStatus, xhr) {
						// the data is a form, we insert that into
						// the overlay.
						if (editing) {
							$('#popup').showPopup(data,'is-editing-source');
							$('#popup').startSyntaxEditor('Form_FileEditForm_SourceText', syntax, editing);
						}
						else {
							$('#popup').showPopup(data,'is-viewing-source');
							$('#popup').startSyntaxEditor('Form_LinkedFileViewForm_SourceText', syntax, editing);
						}
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
				var url = this.attr("rel");
				$.get(
					url,
					null,
					function(data, textStatus, xhr) {
						if (data == "ok") {
							statusMessage('File deleted', 'good');
							btn.parent().parent().remove(); // delete the <tr> that holds this record.
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
				var url = this.attr("rel");
				$.get(
					url,
					null,
					function(data, textStatus, xhr) {
						if (data == "ok") {
							statusMessage('File unlinked', 'good');
							btn.parent().parent().remove(); // delete the <tr> that holds this record.
						}
						else statusMessage('Problem unlinking file', 'bad');
					}
				);
				return false;
			}
		});

		$('#files-tree .action-select-template-type').entwine({
			onchange: function(e) {
				var url = this.attr("rel"); // a href which has URL to ajax function
				url += '&mode=' + this[0].value;

				$.get(
					url,
					null,
					function(data, textStatus, xhr) {
						if (data == "ok") {
							statusMessage('Changed type', 'good');
							$("Form_ItemEditForm").refreshForm();
						}
						else statusMessage('Problem changing type', 'bad');
					}
				);

				return false;
			}
		});
	});

	$(document).ready(function() {
		// create edit-source-overlay.
		$("body").append('<div id="popup-overlay"></div><div id="popup-container"><div id="popup"><div class="content"></div></div></div>');
	});
} (jQuery));
