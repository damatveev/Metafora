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
				{fbvElement type="text" id="apiUrl" value=$formData.apiUrl label="plugins.importexport.metafora.settings.apiUrl" size=$fbvStyles.size.LARGE}
				{fbvElement type="text" password="true" id="apiToken" value=$formData.apiToken label="plugins.importexport.metafora.settings.apiToken" size=$fbvStyles.size.LARGE}
				{fbvElement type="text" id="apiTestEndpoint" value=$formData.apiTestEndpoint label="plugins.importexport.metafora.settings.apiTestEndpoint" size=$fbvStyles.size.LARGE}
			{/fbvFormSection}
			{fbvFormSection title="plugins.importexport.metafora.settings.export"}
				{fbvElement type="checkbox" id="includePdf" checked=$includePdf label="plugins.importexport.metafora.settings.includePdf"}
				<p>{translate key="plugins.importexport.metafora.settings.jatsNotice"}</p>
			{/fbvFormSection}
		{/fbvFormArea}
		{fbvFormButtons submitText="common.save"}
	</form>
</div>
