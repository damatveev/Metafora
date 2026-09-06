<script type="text/javascript">
	$(function() {ldelim}
		$('#metaforaSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>
<div class="semantic-defaults">
	<form class="pkp_form" id="metaforaSettingsForm" method="post" action="{url router=PKP\core\PKPApplication::ROUTE_COMPONENT op="manage" plugin="MetaforaExportPlugin" category="importexport" verb="save"}">
		{csrf}
		{fbvFormArea id="metaforaSettingsFormArea"}
			{fbvFormSection title="plugins.importexport.metafora.settings.connection"}
				{fbvElement type="text" id="apiUrl" value=$apiUrl label="plugins.importexport.metafora.settings.apiUrl" size=$fbvStyles.size.LARGE}
				{fbvElement type="text" password="true" id="apiToken" value=$apiToken label="plugins.importexport.metafora.settings.apiToken" size=$fbvStyles.size.LARGE}
			{/fbvFormSection}
			{fbvFormSection title="plugins.importexport.metafora.settings.export"}
				{fbvElement type="select" id="exportFormat" from=$metaforaExportFormats selected="jats" disabled="disabled" label="plugins.importexport.metafora.settings.exportFormat"}
				{fbvElement type="checkbox" id="validateXml" checked=$validateXml label="plugins.importexport.metafora.settings.validateXml"}
				{fbvElement type="checkbox" id="includePdf" checked=$includePdf label="plugins.importexport.metafora.settings.includePdf"}
				{fbvElement type="checkbox" id="includeReferences" checked=$includeReferences label="plugins.importexport.metafora.settings.includeReferences"}
				<p>{translate key="plugins.importexport.metafora.settings.jatsNotice"}</p>
			{/fbvFormSection}
		{/fbvFormArea}
		{fbvFormButtons submitText="common.save"}
	</form>
</div>
