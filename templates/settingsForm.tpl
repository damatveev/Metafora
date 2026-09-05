<script type="text/javascript">
	$(function() {ldelim}
		$('#metaforaSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>
<div class="legacyDefaults">
	<form class="pkp_form" id="metaforaSettingsForm" method="post" action="{url router=PKP\core\PKPApplication::ROUTE_COMPONENT op="manage" plugin="MetaforaExportPlugin" category="importexport" verb="save"}">
		{csrf}
		{fbvFormArea id="metaforaSettingsFormArea"}
			{fbvFormSection title="plugins.importexport.metafora.settings.connection"}
				{fbvElement type="text" id="apiUrl" value=$apiUrl label="plugins.importexport.metafora.settings.apiUrl" size=$fbvStyles.size.LARGE}
				{fbvElement type="text" password="true" id="apiToken" value=$apiToken label="plugins.importexport.metafora.settings.apiToken" size=$fbvStyles.size.LARGE}
				{fbvElement type="text" id="apiTestEndpoint" value=$apiTestEndpoint label="plugins.importexport.metafora.settings.apiTestEndpoint" size=$fbvStyles.size.LARGE}
			{/fbvFormSection}
			{fbvFormSection title="plugins.importexport.metafora.settings.export"}
				{fbvElement type="select" id="exportFormat" from=$exportFormats selected=$exportFormat label="plugins.importexport.metafora.settings.exportFormat" translate=false}
				{fbvElement type="checkbox" id="validateXml" checked=$validateXml label="plugins.importexport.metafora.settings.validateXml"}
				{fbvElement type="checkbox" id="includePdf" checked=$includePdf label="plugins.importexport.metafora.settings.includePdf"}
				{fbvElement type="checkbox" id="includeReferences" checked=$includeReferences label="plugins.importexport.metafora.settings.includeReferences"}
				{fbvElement type="checkbox" id="autoExport" checked=$autoExport label="plugins.importexport.metafora.settings.autoExport"}
			{/fbvFormSection}
		{/fbvFormArea}
		{fbvFormButtons submitText="common.save"}
	</form>
</div>
