<script type="text/javascript">
	$(function() {ldelim}
		$('#metaforaSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>
<div class="semantic-defaults">
	<form class="pkp_form" id="metaforaSettingsForm" method="post" action="{url router=PKP\core\PKPApplication::ROUTE_COMPONENT component="grid.settings.plugins.settingsPluginGridHandler" op="manage" plugin="MetaforaExportPlugin" category="importexport" verb="save"}">
		{csrf}
		<fieldset class="pkpFormField pkpFormField--options">
			<legend>{translate key="plugins.importexport.metafora.settings.connection"}</legend>
			<div class="pkpFormField">
				<label for="apiUrl">{translate key="plugins.importexport.metafora.settings.apiUrl"}</label>
				<input type="url" id="apiUrl" name="apiUrl" value="{$apiUrl|escape}" class="pkpFormField__input pkpFormField__input--large" required>
			</div>
			<div class="pkpFormField">
				<label for="apiToken">{translate key="plugins.importexport.metafora.settings.apiToken"}</label>
				<input type="password" id="apiToken" name="apiToken" value="{$apiToken|escape}" class="pkpFormField__input pkpFormField__input--large" required autocomplete="off">
			</div>
		</fieldset>
		<fieldset class="pkpFormField pkpFormField--options">
			<legend>{translate key="plugins.importexport.metafora.settings.export"}</legend>
			<p><strong>{translate key="plugins.importexport.metafora.settings.exportFormat"}:</strong> JATS XML</p>
			<label><input type="checkbox" name="validateXml" value="1"{if $validateXml} checked{/if}> {translate key="plugins.importexport.metafora.settings.validateXml"}</label><br>
			<label><input type="checkbox" name="includePdf" value="1"{if $includePdf} checked{/if}> {translate key="plugins.importexport.metafora.settings.includePdf"}</label><br>
			<label><input type="checkbox" name="includeReferences" value="1"{if $includeReferences} checked{/if}> {translate key="plugins.importexport.metafora.settings.includeReferences"}</label>
			<p>{translate key="plugins.importexport.metafora.settings.jatsNotice"}</p>
		</fieldset>
		{fbvFormButtons submitText="common.save"}
	</form>
</div>
