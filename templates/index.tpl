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
			<style>
				.metaforaArticlesTable__head,
				.metaforaArticleRow {
					display: grid;
					grid-template-columns: 34px minmax(130px, 1.1fr) minmax(260px, 2.4fr) minmax(180px, 1.5fr) 105px 120px minmax(160px, 1.3fr);
					align-items: start;
					gap: 10px;
				}
				.metaforaArticlesTable__head {
					padding: 7px 10px;
					background: #6268e8;
					color: #fff;
					font-size: 11px;
					font-weight: 600;
					text-transform: uppercase;
				}
				.metaforaArticleRow {
					padding: 10px;
					border: 1px solid #ddd;
					border-top: 0;
					background: #f5f5f5;
					font-size: 13px;
				}
				.metaforaArticleRow:nth-child(odd) { background: #fff; }
				.metaforaArticleRow__muted { color: #777; }
				.metaforaArticleRow__action a { white-space: nowrap; }
				.metaforaStatus { font-weight: 600; }
				.metaforaStatus--success { color: #2e7d32; }
				.metaforaStatus--failed { color: #c62828; }
				.metaforaStatus--sending { color: #1565c0; }
				.metaforaStatus--not_sent { color: #777; }
				.metaforaErrorDetails summary { cursor: pointer; color: #c62828; }
				.metaforaErrorDetails pre { max-width: 420px; overflow: auto; white-space: pre-wrap; }
				.metaforaStatusFilter { margin: 0 0 10px; }
				@media (max-width: 900px) {
					.metaforaArticlesTable { overflow-x: auto; }
					.metaforaArticlesTable__head,
					.metaforaArticleRow { min-width: 980px; }
				}
			</style>
			<script type="text/javascript">
				$(function() {ldelim}
					$('#metaforaArticlesForm').pkpHandler('$.pkp.controllers.form.FormHandler');
					window.metaforaFilterRows = function(value) {ldelim}
						$('.metaforaArticleRow').each(function() {ldelim}
							$(this).toggle(value === 'all' || $(this).attr('data-metafora-status') === value);
						{rdelim});
					{rdelim};
				{rdelim});
			</script>
			<form id="metaforaArticlesForm" class="pkp_form" action="{plugin_url path="sendSubmissions"}" method="post">
				{csrf}
				{fbvFormArea id="metaforaArticlesFormArea"}
					<div class="metaforaStatusFilter">
						<label for="metaforaStatusFilter">{translate key="plugins.importexport.metafora.filter.label"}</label>
						<select id="metaforaStatusFilter" onchange="window.metaforaFilterRows(this.value)">
							<option value="all">{translate key="plugins.importexport.metafora.filter.all"}</option>
							<option value="success">{translate key="plugins.importexport.metafora.filter.success"}</option>
							<option value="failed">{translate key="plugins.importexport.metafora.filter.failed"}</option>
							<option value="not_sent">{translate key="plugins.importexport.metafora.filter.notSent"}</option>
						</select>
					</div>
					<div class="metaforaArticlesTable" role="table" aria-label="{translate key="plugins.importexport.metafora.tab.articles"}">
						<div class="metaforaArticlesTable__head" role="row">
							<span></span>
							<span>{translate key="plugins.importexport.metafora.table.issue"}</span>
							<span>{translate key="plugins.importexport.metafora.table.title"}</span>
							<span>{translate key="plugins.importexport.metafora.table.authors"}</span>
							<span>{translate key="plugins.importexport.metafora.table.action"}</span>
							<span>{translate key="plugins.importexport.metafora.table.status"}</span>
							<span>{translate key="plugins.importexport.metafora.table.error"}</span>
						</div>
					</div>
					<submissions-list-panel v-bind="components.submissions" @set="set">
						<template #item="{ldelim}item{rdelim}">
							<div class="metaforaArticleRow" role="row" :data-metafora-status="components.submissions.metaforaStatuses[item.id] ? components.submissions.metaforaStatuses[item.id].status : 'not_sent'">
								<div>
									<input
										type="checkbox"
										name="selectedSubmissions[]"
										:value="item.id"
										v-model="selectedSubmissions"
									/>
								</div>
								<div class="metaforaArticleRow__muted">{{ item.issue && (item.issue.identification || item.issue.title) || '—' }}</div>
								<div v-strip-unsafe-html="localize(item.publications.find(p => p.id == item.currentPublicationId).fullTitle, item.publications.find(p => p.id == item.currentPublicationId).locale)"></div>
								<div>{{ item.authors && item.authors.map(a => a.fullName || ((a.givenName || '') + ' ' + (a.familyName || '')).trim()).filter(Boolean).join(', ') || '—' }}</div>
								<div class="metaforaArticleRow__action">
									<pkp-button element="a" :href="item.urlWorkflow">{{ t('common.view') }}</pkp-button>
								</div>
								<div class="metaforaStatus" :class="'metaforaStatus--' + (components.submissions.metaforaStatuses[item.id] ? components.submissions.metaforaStatuses[item.id].status : 'not_sent')">
									{{ !components.submissions.metaforaStatuses[item.id] ? '⚪ ' + t('plugins.importexport.metafora.table.notSent') : components.submissions.metaforaStatuses[item.id].status === 'success' ? '🟢 ' + t('plugins.importexport.metafora.table.sent') : components.submissions.metaforaStatuses[item.id].status === 'sending' ? '🔵 ' + t('plugins.importexport.metafora.table.sending') : '🔴 ' + t('plugins.importexport.metafora.table.failed') }}
								</div>
								<div class="metaforaArticleRow__muted">
									<details v-if="components.submissions.metaforaStatuses[item.id] && components.submissions.metaforaStatuses[item.id].status === 'failed'" class="metaforaErrorDetails">
										<summary>{{ components.submissions.metaforaStatuses[item.id].message || t('plugins.importexport.metafora.table.failed') }}</summary>
										<div>{{ t('plugins.importexport.metafora.details.date') }}: {{ components.submissions.metaforaStatuses[item.id].updatedAt || '—' }}</div>
										<div>{{ t('plugins.importexport.metafora.details.type') }}: {{ components.submissions.metaforaStatuses[item.id].exportType || '—' }}</div>
										<div>HTTP: {{ components.submissions.metaforaStatuses[item.id].httpStatus || '—' }}</div>
										<pre>{{ typeof components.submissions.metaforaStatuses[item.id].response === 'string' ? components.submissions.metaforaStatuses[item.id].response : JSON.stringify(components.submissions.metaforaStatuses[item.id].response, null, 2) }}</pre>
									</details>
									<span v-else>—</span>
								</div>
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
