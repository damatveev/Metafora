<script type="text/javascript">
	$(function() {ldelim}
		$('#metaforaSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
		function metaforaToggleApiSettings() {ldelim}
			var apiMode = $('input[name="deliveryMode"]:checked').val() !== 'download';
			$('#metaforaApiSettings').toggle(apiMode);
			$('#apiUrl, #apiToken').prop('required', apiMode);
		{rdelim}
		$('input[name="deliveryMode"]').on('change', metaforaToggleApiSettings);
		metaforaToggleApiSettings();
	{rdelim});
</script>
<div class="semantic-defaults">
	<form class="pkp_form" id="metaforaSettingsForm" method="post" action="{url router=PKP\core\PKPApplication::ROUTE_COMPONENT component="grid.settings.plugins.settingsPluginGridHandler" op="manage" plugin="MetaforaExportPlugin" category="importexport" verb="save"}">
		{csrf}
		<fieldset class="pkpFormField pkpFormField--options">
			<legend>{translate key="plugins.importexport.metafora.settings.deliveryMode"}</legend>
			<label><input type="radio" name="deliveryMode" value="api"{if $deliveryMode ne 'download'} checked{/if}> {translate key="plugins.importexport.metafora.settings.deliveryMode.api"}</label><br>
			<label><input type="radio" name="deliveryMode" value="download"{if $deliveryMode eq 'download'} checked{/if}> {translate key="plugins.importexport.metafora.settings.deliveryMode.download"}</label>
		</fieldset>
		<fieldset id="metaforaApiSettings" class="pkpFormField pkpFormField--options">
			<legend>{translate key="plugins.importexport.metafora.settings.connection"}</legend>
			<div class="pkpFormField">
				<label for="apiUrl">{translate key="plugins.importexport.metafora.settings.apiUrl"}</label>
				<input type="url" id="apiUrl" name="apiUrl" value="{$apiUrl|escape}" class="pkpFormField__input pkpFormField__input--large">
			</div>
			<div class="pkpFormField">
				<label for="apiToken">{translate key="plugins.importexport.metafora.settings.apiToken"}</label>
				<input type="password" id="apiToken" name="apiToken" value="{$apiToken|escape}" class="pkpFormField__input pkpFormField__input--large" autocomplete="off">
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
