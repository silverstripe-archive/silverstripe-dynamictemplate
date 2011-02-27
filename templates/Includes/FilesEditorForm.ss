<div class="lhs" <% if HelpType %><% else %>width="100%"<% end_if %>>
	<% include Form %>
</div>
<% if HelpType %>
	<div class="rhs">
		<% if HelpType=templates %>
			Template helper<br>
<div class="insertable">&lt;% control X %&gt;&lt;% end_control %&gt;</div>
<div class="insertable">&lt;% if X %&gt;&lt;% end_if %&gt;</div>
<div class="insertable">&lt;% if X %&gt;&lt;% else %&gt;&lt;% end_if %&gt;</div>
<div class="insertable">&lt;% require themedCSS() %&gt;</div>
<div class="insertable">&lt;% require javascript() %&gt;</div>
		<% else_if Helper=css %>
			CSS helper<br>
		<% else_if Helper=javascript %>
			JavaScript helper<br>
		<% end_if %>
	</div>
<% end_if %>
