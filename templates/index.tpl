{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">{$pageTitle}</h1>

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
			<li><a href="#articles-tab">{translate key="plugins.importexport.metafora.tab.articles"}</a></li>
			<li><a href="#issues-tab">{translate key="plugins.importexport.metafora.tab.issues"}</a></li>
		</ul>

		<div id="settings-tab">
			{include file=$metaforaSettingsTemplate}
		</div>

		<div id="articles-tab">
			<script type="text/javascript">
				$(function() {ldelim}
					$('#metaforaArticlesForm').pkpHandler('$.pkp.controllers.form.FormHandler');
				{rdelim});
			</script>
			<form id="metaforaArticlesForm" class="pkp_form" action="{plugin_url path="sendSubmissions"}" method="post">
				{csrf}
				{fbvFormArea id="metaforaArticlesFormArea"}
					<submissions-list-panel v-bind="components.submissions" @set="set">
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
						<pkp-button @click="submit('#metaforaArticlesForm')">
							{translate key="plugins.importexport.metafora.send.articles"}
						</pkp-button>
					{/fbvFormSection}
				{/fbvFormArea}
			</form>
		</div>

		<div id="issues-tab">
			<script type="text/javascript">
				$(function() {ldelim}
					$('#metaforaIssuesForm').pkpHandler('$.pkp.controllers.form.FormHandler');
				{rdelim});
			</script>
			<form id="metaforaIssuesForm" class="pkp_form" action="{plugin_url path="sendIssues"}" method="post">
				{csrf}
				{fbvFormArea id="metaforaIssuesFormArea"}
					{capture assign=issuesListGridUrl}
						{url router=PKP\core\PKPApplication::ROUTE_COMPONENT component="grid.issues.ExportableIssuesListGridHandler" op="fetchGrid" escape=false}
					{/capture}
					{load_url_in_div id="metaforaIssuesListGridContainer" url=$issuesListGridUrl}
					{fbvFormButtons submitText="plugins.importexport.metafora.send.issues" hideCancel="true"}
				{/fbvFormArea}
			</form>
		</div>
	</div>
{/block}
