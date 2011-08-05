<h2><% _t('DYNAMICTEMPLATES','Dynamic Templates') %></h2>
	<div id="treepanes" style="overflow-y: auto;">
			<ul id="TreeActions">
				<li class="action" id="addtemplate"><button><% _t('CREATE','Create') %></button></li>
				<% if TarballAvailable %>
					<li class="action" id="importtarball"><button><% _t('IMPORT', 'Import tarball') %></button></li>
				<% end_if %>
				<% if ZipAvailable %>
					<li class="action" id="importzip"><button><% _t('IMPORT', 'Import zip') %></button></li>
				<% end_if %>
			</ul>
			<div style="clear:both;"></div>

			<% control ImportTarballForm %>
				<form class="actionparams" id="$FormName" style="display: none" action="$FormAction">
					<% control Fields %>
					$FieldHolder
					<% end_control %>
					<div>
						<input class="action" type="submit" value="<% _t('GO','Go') %>" />
					</div>
				</form>
			<% end_control %>

			<form class="actionparams" id="addtemplate_options" style="display: none">
				<div>
				<input type="hidden" name="ParentID" />
				<input type="hidden" name="SecurityID" value="$SecurityID" />
				<input class="action" type="submit" value="<% _t('GO','Go') %>" />
				</div>
			</form>
		
			$DeleteItemsForm
		
			<form class="actionparams" id="sortitems_options" style="display: none">
				<p id="sortitems_message" style="margin: 0"><% _t('TOREORG','To reorganise your folders, drag them around as desired.') %></p>
			</form>
		
			$TemplatesAsUL
	</div>
