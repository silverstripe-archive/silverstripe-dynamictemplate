<div class="lhs" <% if HelpType %><% else %>width="100%"<% end_if %>>
	<% include Form %>
</div>
<% if HelpType %>
	<div class="rhs">
		<% if HelpType=templates %>
			Template helper<br>
U+25control end_control<br>
if end_if<br>
if else end_if<br>
		<% else_if Helper=css %>
			CSS helper<br>
		<% else_if Helper=javascript %>
			JavaScript helper<br>
		<% end_if %>
	</div>
<% end_if %>
