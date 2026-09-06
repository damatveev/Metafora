{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{$pageTitle}
	</h1>

	<script type="text/javascript">
		$(function() {ldelim}
			$('#metaforaExportTabs')
				.pkpHandler('$.pkp.controllers.TabHandler')
				.tabs('option', 'cache', true);
		{rdelim});
	</script>

	<div id="metaforaExportTabs">
		<ul>
			<li><a href="#settings-tab">{translate key="plugins.importexport.common.settings"}</a></li>
			<li><a href="#exportSubmissions-tab">{translate key="plugins.importexport.metafora.tab.articles"}</a></li>
			<li><a href="#exportIssues-tab">{translate key="plugins.importexport.metafora.tab.issues"}</a></li>
		</ul>

		<div id="settings-tab">
			{capture assign=metaforaSettingsGridUrl}{url router=PKP\core\PKPApplication::ROUTE_COMPONENT component="grid.settings.plugins.settingsPluginGridHandler" op="manage" plugin="MetaforaExportPlugin" category="importexport" verb="index" escape=false}{/capture}
			{load_url_in_div id="metaforaSettingsGridContainer" url=$metaforaSettingsGridUrl}
		</div>

		<div id="exportSubmissions-tab">
			<script type="text/javascript">
				$(function() {ldelim}
					$('#metaforaExportSubmissionsForm').pkpHandler('$.pkp.controllers.form.FormHandler');
				{rdelim});
			</script>

			<form id="metaforaExportSubmissionsForm" class="pkp_form" action="{plugin_url path="exportJson"}" method="post">
				{csrf}
				<input type="hidden" name="tab" value="exportSubmissions-tab" />
				{fbvFormArea id="metaforaExportSubmissionsFormArea"}
					<submissions-list-panel
						v-bind="components.submissions"
						@set="set"
					>
						<template #item="{ldelim}item{rdelim}">
							<div class="listPanel__itemSummary">
								<label>
									<input
										type="checkbox"
										name="selectedSubmissions[]"
										:value="item.id"
										v-model="selectedSubmissions"
									/>
									<span
										class="listPanel__itemSubTitle"
										v-strip-unsafe-html="localize(
											item.publications.find(p => p.id == item.currentPublicationId).fullTitle,
											item.publications.find(p => p.id == item.currentPublicationId).locale
										)"
									></span>
								</label>
								<pkp-button element="a" :href="item.urlWorkflow" style="margin-left: auto;">
									{{ t('common.view') }}
								</pkp-button>
							</div>
						</template>
					</submissions-list-panel>

					{fbvFormSection}
						<pkp-button :disabled="!components.submissions.itemsMax" @click="toggleSelectAll">
							<template v-if="components.submissions.itemsMax && selectedSubmissions.length >= components.submissions.itemsMax">
								{translate key="common.selectNone"}
							</template>
							<template v-else>
								{translate key="common.selectAll"}
							</template>
						</pkp-button>
						<pkp-button @click="submit('#metaforaExportSubmissionsForm')">
							{translate key="plugins.importexport.metafora.export.json"}
						</pkp-button>
					{/fbvFormSection}
				{/fbvFormArea}
			</form>
		</div>

		<div id="exportIssues-tab">
			<script type="text/javascript">
				$(function() {ldelim}
					$('#metaforaExportIssuesForm').pkpHandler('$.pkp.controllers.form.FormHandler');
				{rdelim});
			</script>

			<form id="metaforaExportIssuesForm" class="pkp_form" action="{plugin_url path="exportIssues"}" method="post">
				{csrf}
				<input type="hidden" name="tab" value="exportIssues-tab" />
				{fbvFormArea id="metaforaExportIssuesFormArea"}
					{capture assign=issuesListGridUrl}{url router=PKP\core\PKPApplication::ROUTE_COMPONENT component="grid.issues.ExportableIssuesListGridHandler" op="fetchGrid" escape=false}{/capture}
					{load_url_in_div id="metaforaIssuesListGridContainer" url=$issuesListGridUrl}
					{fbvFormButtons submitText="plugins.importexport.metafora.export.issues" hideCancel="true"}
				{/fbvFormArea}
			</form>
		</div>
	</div>
{/block}
