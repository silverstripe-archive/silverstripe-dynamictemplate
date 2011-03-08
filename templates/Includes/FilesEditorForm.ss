<div class="lhs" <% if HelpType %><% else %>width="100%"<% end_if %>>
	<% include Form %>
</div>
<% if HelpType %>
	<div class="rhs">
		<% if HelpType=templates %>
			<h3>Template helper</h3><br>
			<div class="insertable">&lt;% control X %&gt;&lt;% end_control %&gt;</div>
			<div class="insertable">&lt;% if X %&gt;&lt;% end_if %&gt;</div>
			<div class="insertable">&lt;% if X %&gt;&lt;% else %&gt;&lt;% end_if %&gt;</div>
			<div class="insertable">&lt;% require themedCSS() %&gt;</div>
			<div class="insertable">&lt;% require javascript() %&gt;</div>
		<% else_if HelpType=css %>
			<h3>CSS helper</h3><br>
			<div class="insertable">margin: 0 0 0 0;</div>
			<div class="insertable">clear: left;</div>
			<div class="insertable">clear: both;</div>
			<div class="insertable">clear: right;</div>
		<% else_if HelpType=javascript %>
			<h3>JavaScript helper</h3><br>
			<div class="insertable">(function($) {
})(jQuery);</div>
			<div class="insertable">$('document').onready(function() {
});</div>
		<% end_if %>
	</div>
<% end_if %>
