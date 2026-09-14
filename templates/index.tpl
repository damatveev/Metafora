{extends file="layouts/backend.tpl"}

{block name="page"}
	<link rel="stylesheet" href="{$baseUrl}/plugins/importexport/metafora/styles/admin.css?v=0.4.0.3" />
	<div class="metaforaExportPage">
	<h1 class="app__pageHeading">{$pageTitle}</h1>

	<script type="text/javascript">
		$(function() {ldelim}
			var tabs = $('#metaforaExportTabs')
				.pkpHandler('$.pkp.controllers.TabHandler')
				.tabs('option', 'cache', true);
			var savedTab = window.sessionStorage.getItem('metaforaActiveTab');
			if (savedTab) {ldelim}
				var savedTabIndex = tabs.find('> ul > li > a').toArray().findIndex(function(link) {ldelim}
					return link.getAttribute('href') === savedTab;
				{rdelim});
				if (savedTabIndex >= 0) tabs.tabs('option', 'active', savedTabIndex);
				window.sessionStorage.removeItem('metaforaActiveTab');
			{rdelim}
			window.metaforaReloadTab = function(tabId) {ldelim}
				window.sessionStorage.setItem('metaforaActiveTab', tabId);
				window.location.reload();
			{rdelim};
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
					window.metaforaFilterRows = function(value) {ldelim}
						var table = document.querySelector('.metaforaArticlesTable');
						if (table) table.setAttribute('data-status-filter', value || 'all');
					{rdelim};
					$('#metaforaStatusFilter').on('change', function() {ldelim}
						window.metaforaFilterRows(this.value);
					{rdelim});
					window.metaforaFilterRows($('#metaforaStatusFilter').val());

					var metaforaUrls = {ldelim}
						sync: '{plugin_url path="syncSubmission"}',
						syncSelected: '{plugin_url path="syncSubmissions"}',
						syncIssue: '{plugin_url path="syncIssue"}',
						syncIssues: '{plugin_url path="syncIssues"}',
						replace: '{plugin_url path="replaceSubmission"}',
						sign: '{plugin_url path="signSubmission"}',
						unsign: '{plugin_url path="unsignSubmission"}'
					{rdelim};

					function metaforaPost(url, data) {ldelim}
						var csrf = document.querySelector('input[name="csrfToken"]');
						if (csrf) data.append('csrfToken', csrf.value);

						return fetch(url, {ldelim}
							method: 'POST',
							body: data,
							credentials: 'same-origin',
							headers: {ldelim}'X-Requested-With': 'XMLHttpRequest'{rdelim}
						{rdelim}).then(function(response) {ldelim}
							return response.text().then(function(body) {ldelim}
								var result;
								try {ldelim}
									result = body ? JSON.parse(body) : null;
								{rdelim} catch (error) {ldelim}
									throw new Error('HTTP ' + response.status + ': {translate key="plugins.importexport.metafora.ajax.invalidJson"} ' + body.slice(0, 300));
								{rdelim}
								if (!response.ok) {ldelim}
									throw new Error('HTTP ' + response.status + ': ' + (result && result.message ? result.message : response.statusText));
								{rdelim}
								if (!result || typeof result !== 'object') {ldelim}
									throw new Error('{translate key="plugins.importexport.metafora.ajax.invalidJson"}');
								{rdelim}
								return result;
							{rdelim});
						{rdelim});
					{rdelim}

					function metaforaShowMessage(message, isError, targetId) {ldelim}
						var target = document.getElementById(targetId || 'metaforaAjaxMessage');
						if (!target) return;
						target.textContent = message || '';
						target.className = 'metaforaAjaxMessage' + (isError ? ' metaforaAjaxMessage--error' : '');
						target.hidden = !message;
					{rdelim}

					$(document).on('click', '.metaforaRemoteAction', function(event) {ldelim}
						event.preventDefault();
						var button = $(this);
						var action = button.data('action');
						var submissionId = button.data('submission-id');
						if (!metaforaUrls[action] || !submissionId) return;

						if ((action === 'sign' || action === 'unsign')
							&& !window.confirm('{translate key="plugins.importexport.metafora.signature.confirm"}')) {ldelim}
							return;
						{rdelim}
						if (action === 'replace'
							&& !window.confirm('{translate key="plugins.importexport.metafora.restore.confirm"}')) {ldelim}
							return;
						{rdelim}

						var data = new FormData();
						data.append('submissionId', submissionId);
						button.prop('disabled', true);
						metaforaShowMessage('{translate key="plugins.importexport.metafora.ajax.loading"}', false);
						metaforaPost(metaforaUrls[action], data)
							.then(function(result) {ldelim}
								if (result.success === false && result.message) metaforaShowMessage(result.message, true);
								window.metaforaReloadTab('#articles-tab');
							{rdelim})
							.catch(function(error) {ldelim}
								button.prop('disabled', false);
								metaforaShowMessage(error && error.message ? error.message : '{translate key="plugins.importexport.metafora.sync.error"}', true);
							{rdelim});
					{rdelim});

					$(document).on('click', '#metaforaSyncSelected', function(event) {ldelim}
						event.preventDefault();
						var data = new FormData();
						var count = 0;
						$('#metaforaArticlesForm input[name="selectedSubmissions[]"]:checked').each(function() {ldelim}
							data.append('selectedSubmissions[]', $(this).val());
							count++;
						{rdelim});
						if (!count) {ldelim}
							alert('{translate key="plugins.importexport.metafora.error.noSubmissionsSelected"}');
							return;
						{rdelim}
						var button = $(this);
						button.prop('disabled', true);
						metaforaShowMessage('{translate key="plugins.importexport.metafora.ajax.loading"}', false);
						metaforaPost(metaforaUrls.syncSelected, data)
							.then(function(result) {ldelim}
								if (result.success === false && result.message) metaforaShowMessage(result.message, true);
								window.metaforaReloadTab('#articles-tab');
							{rdelim})
							.catch(function(error) {ldelim}
								button.prop('disabled', false);
								metaforaShowMessage(error && error.message ? error.message : '{translate key="plugins.importexport.metafora.sync.error"}', true);
							{rdelim});
					{rdelim});

					$(document).on('click', '.metaforaIssueRemoteAction', function(event) {ldelim}
						event.preventDefault();
						var button = $(this);
						var data = new FormData();
						data.append('issueId', button.data('issue-id'));
						button.prop('disabled', true);
						metaforaShowMessage('{translate key="plugins.importexport.metafora.ajax.loading"}', false, 'metaforaIssueAjaxMessage');
						metaforaPost(metaforaUrls.syncIssue, data)
							.then(function(result) {ldelim}
								if (result.success === false && result.message) metaforaShowMessage(result.message, true, 'metaforaIssueAjaxMessage');
								window.metaforaReloadTab('#issues-tab');
							{rdelim})
							.catch(function(error) {ldelim}
								button.prop('disabled', false);
								metaforaShowMessage(error && error.message ? error.message : '{translate key="plugins.importexport.metafora.sync.error"}', true, 'metaforaIssueAjaxMessage');
							{rdelim});
					{rdelim});

					$(document).on('click', '#metaforaSyncSelectedIssues', function(event) {ldelim}
						event.preventDefault();
						var data = new FormData();
						var count = 0;
						$('#metaforaIssuesForm input[name="selectedIssues[]"]:checked').each(function() {ldelim}
							data.append('selectedIssues[]', $(this).val());
							count++;
						{rdelim});
						if (!count) {ldelim}
							alert('{translate key="plugins.importexport.metafora.error.noIssuesSelected"}');
							return;
						{rdelim}
						var button = $(this);
						button.prop('disabled', true);
						metaforaShowMessage('{translate key="plugins.importexport.metafora.ajax.loading"}', false, 'metaforaIssueAjaxMessage');
						metaforaPost(metaforaUrls.syncIssues, data)
							.then(function() {ldelim} window.metaforaReloadTab('#issues-tab'); {rdelim})
							.catch(function(error) {ldelim}
								button.prop('disabled', false);
								metaforaShowMessage(error && error.message ? error.message : '{translate key="plugins.importexport.metafora.sync.error"}', true, 'metaforaIssueAjaxMessage');
							{rdelim});
					{rdelim});
				{rdelim});
			</script>
			<div id="metaforaAjaxMessage" class="metaforaAjaxMessage" role="alert" aria-live="assertive" hidden></div>
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
					<div class="metaforaArticlesTable" data-status-filter="all" role="table" aria-label="{translate key="plugins.importexport.metafora.tab.articles"}">
						<div class="metaforaArticlesTable__head" role="row">
							<span></span>
							<span>{translate key="plugins.importexport.metafora.table.issue"}</span>
							<span>{translate key="plugins.importexport.metafora.table.title"}</span>
							<span>{translate key="plugins.importexport.metafora.table.authors"}</span>
							<span>{translate key="plugins.importexport.metafora.table.action"}</span>
							<span>{translate key="plugins.importexport.metafora.table.status"}</span>
							<span>{translate key="plugins.importexport.metafora.table.error"}</span>
						</div>
						<submissions-list-panel v-bind="components.submissions" @set="set">
							<template #item="{ldelim}item{rdelim}">
							<div class="metaforaArticleRow" role="row" :data-metafora-status="components.submissions.metaforaStatuses[item.id] ? (components.submissions.metaforaStatuses[item.id].effectiveStatus || components.submissions.metaforaStatuses[item.id].status) : 'not_sent'">
								<div>
									<input
										type="checkbox"
										name="selectedSubmissions[]"
										:value="item.id"
										v-model="selectedSubmissions"
									/>
								</div>
								<div class="metaforaArticleRow__muted">{{ components.submissions.metaforaMetadata[item.id] ? components.submissions.metaforaMetadata[item.id].issue : '—' }}</div>
								<div v-strip-unsafe-html="localize(item.publications.find(p => p.id == item.currentPublicationId).fullTitle, item.publications.find(p => p.id == item.currentPublicationId).locale)"></div>
								<div>{{ components.submissions.metaforaMetadata[item.id] ? components.submissions.metaforaMetadata[item.id].authors : '—' }}</div>
								<div class="metaforaArticleRow__action">
									<pkp-button element="a" :href="item.urlWorkflow">{{ t('common.view') }}</pkp-button>
									<button type="button" class="pkp_button metaforaRemoteAction" data-action="sync" :data-submission-id="item.id">{translate key="plugins.importexport.metafora.action.sync"}</button>
									<button
									v-if="components.submissions.metaforaRemote[item.id] && components.submissions.metaforaRemote[item.id].remoteExists === true && components.submissions.metaforaRemote[item.id].articleUid && components.submissions.metaforaRemote[item.id].signatureStatus === 'unsigned'"
										type="button"
						class="pkp_button metaforaRemoteAction metaforaRemoteAction--sign"
										data-action="sign"
										:data-submission-id="item.id"
									>{translate key="plugins.importexport.metafora.action.sign"}</button>
									<button
									v-if="components.submissions.metaforaRemote[item.id] && components.submissions.metaforaRemote[item.id].remoteExists === true && components.submissions.metaforaRemote[item.id].articleUid && components.submissions.metaforaRemote[item.id].signatureStatus === 'signed'"
										type="button"
						class="pkp_button metaforaRemoteAction metaforaRemoteAction--unsign"
										data-action="unsign"
										:data-submission-id="item.id"
						>{translate key="plugins.importexport.metafora.action.unsign"}</button>
						<button
							v-if="components.submissions.metaforaRemote[item.id] && components.submissions.metaforaRemote[item.id].remoteExists === false && components.submissions.metaforaRemote[item.id].fileUid"
							type="button"
							class="pkp_button metaforaRemoteAction metaforaRemoteAction--restore"
							data-action="replace"
							:data-submission-id="item.id"
						>{translate key="plugins.importexport.metafora.action.restore"}</button>
								</div>
								<div>
									<div v-if="components.submissions.metaforaRemote[item.id]">
										<div v-if="components.submissions.metaforaRemote[item.id].remoteExists === true" class="metaforaStatus metaforaStatus--success">{translate key="plugins.importexport.metafora.sync.found"}</div>
										<div v-else-if="components.submissions.metaforaRemote[item.id].remoteExists === false" class="metaforaStatus metaforaStatus--not_sent">{translate key="plugins.importexport.metafora.sync.notFound"}</div>
										<div v-else class="metaforaStatus metaforaStatus--sending">{translate key="plugins.importexport.metafora.sync.unknown"}</div>
									</div>
									<div v-else class="metaforaStatus" :class="'metaforaStatus--' + (components.submissions.metaforaStatuses[item.id] ? components.submissions.metaforaStatuses[item.id].status : 'not_sent')">
										{{ !components.submissions.metaforaStatuses[item.id] ? components.submissions.metaforaLabels.notSent : components.submissions.metaforaStatuses[item.id].status === 'success' ? components.submissions.metaforaLabels.sent : components.submissions.metaforaStatuses[item.id].status === 'sending' ? components.submissions.metaforaLabels.sending : components.submissions.metaforaLabels.failed }}
									</div>
									<div v-if="components.submissions.metaforaRemote[item.id]" class="metaforaRemoteState">
										<span>{{ components.submissions.metaforaRemote[item.id].remoteStatus || '—' }}</span>
										<span class="metaforaRemoteState__signature" v-if="components.submissions.metaforaRemote[item.id].signatureStatus === 'signed'">{translate key="plugins.importexport.metafora.signature.signed"}</span>
										<span class="metaforaRemoteState__signature" v-else-if="components.submissions.metaforaRemote[item.id].signatureStatus === 'unsigned'">{translate key="plugins.importexport.metafora.signature.notSigned"}</span>
									</div>
					<div v-if="!components.submissions.metaforaRemote[item.id] || components.submissions.metaforaRemote[item.id].remoteExists !== true" class="metaforaLocalState">
										{translate key="plugins.importexport.metafora.local.lastAttempt"}:
										{{ !components.submissions.metaforaStatuses[item.id] ? components.submissions.metaforaLabels.notSent : components.submissions.metaforaStatuses[item.id].status === 'success' ? components.submissions.metaforaLabels.sent : components.submissions.metaforaStatuses[item.id].status === 'sending' ? components.submissions.metaforaLabels.sending : components.submissions.metaforaLabels.failed }}
									</div>
								</div>
								<div class="metaforaArticleRow__muted">
					<details v-if="components.submissions.metaforaStatuses[item.id] && components.submissions.metaforaStatuses[item.id].status === 'failed' && (!components.submissions.metaforaRemote[item.id] || components.submissions.metaforaRemote[item.id].remoteExists !== true)" class="metaforaErrorDetails">
										<summary>{{ components.submissions.metaforaStatuses[item.id].message || t('plugins.importexport.metafora.table.failed') }}</summary>
										<div>{{ t('plugins.importexport.metafora.details.date') }}: {{ components.submissions.metaforaStatuses[item.id].updatedAt || '—' }}</div>
										<div>{{ t('plugins.importexport.metafora.details.type') }}: {{ components.submissions.metaforaStatuses[item.id].exportType || '—' }}</div>
										<div>HTTP: {{ components.submissions.metaforaStatuses[item.id].httpStatus || '—' }}</div>
										<pre>{{ typeof components.submissions.metaforaStatuses[item.id].response === 'string' ? components.submissions.metaforaStatuses[item.id].response : JSON.stringify(components.submissions.metaforaStatuses[item.id].response, null, 2) }}</pre>
									</details>
									<span v-else-if="components.submissions.metaforaRemote[item.id] && components.submissions.metaforaRemote[item.id].message">{{ components.submissions.metaforaRemote[item.id].message }}</span>
									<span v-else>—</span>
								</div>
							</div>
							</template>
						</submissions-list-panel>
					</div>

					{fbvFormSection}
						<pkp-button :disabled="!components.submissions.itemsMax" @click="toggleSelectAll">
							<template v-if="components.submissions.itemsMax && selectedSubmissions.length >= components.submissions.itemsMax">
								{translate key="common.selectNone"}
							</template>
							<template v-else>
								{translate key="common.selectAll"}
							</template>
						</pkp-button>
						<pkp-button id="metaforaSyncSelected">
							{translate key="plugins.importexport.metafora.action.syncSelected"}
						</pkp-button>
						<pkp-button @click="submit('#metaforaArticlesForm')">
							{if $deliveryMode eq 'download'}{translate key="plugins.importexport.metafora.download.articles"}{else}{translate key="plugins.importexport.metafora.send.articles"}{/if}
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
			<div id="metaforaIssueAjaxMessage" class="metaforaAjaxMessage" role="alert" aria-live="assertive" hidden></div>
			<form id="metaforaIssuesForm" class="pkp_form" action="{plugin_url path="sendIssues"}" method="post">
				{csrf}
				{fbvFormArea id="metaforaIssuesFormArea"}
					<div class="metaforaIssuesTable">
						<div class="metaforaIssuesTable__head"><span></span><span>{translate key="plugins.importexport.metafora.table.issue"}</span><span>{translate key="plugins.importexport.metafora.table.articles"}</span><span>{translate key="plugins.importexport.metafora.table.action"}</span><span>{translate key="plugins.importexport.metafora.table.status"}</span><span>{translate key="plugins.importexport.metafora.table.error"}</span></div>
						{foreach from=$metaforaIssues item=issue}
							<div class="metaforaIssueRow">
								<div><input type="checkbox" name="selectedIssues[]" value="{$issue.id|escape}" /></div>
								<div>{$issue.label|escape}</div>
								<div>{$issue.articleCount|escape}</div>
								<div class="metaforaIssueRow__actions"><a class="pkp_button" href="{$issue.url|escape}">{translate key="common.view"}</a><button type="button" class="pkp_button metaforaIssueRemoteAction" data-issue-id="{$issue.id|escape}">{translate key="plugins.importexport.metafora.action.sync"}</button></div>
								<div class="metaforaStatus metaforaStatus--{$issue.status|escape}">{$issue.statusLabel|escape}</div>
								<div class="metaforaArticleRow__muted">
									{if $issue.problems}
										<details class="metaforaIssueProblems"><summary>{translate key="plugins.importexport.metafora.issue.problemArticles"} ({$issue.problems|@count})</summary><ul>{foreach from=$issue.problems item=problem}<li><strong>#{$problem.submissionId|escape}</strong> {$problem.title|escape} — {$problem.statusLabel|escape}{if $problem.error}: {$problem.error|escape}{/if}</li>{/foreach}</ul></details>
									{else}—{/if}
								</div>
							</div>
						{/foreach}
					</div>
					{if $deliveryMode eq 'download'}
						{fbvFormButtons submitText="plugins.importexport.metafora.download.issues" hideCancel="true"}
					{else}
						{fbvFormButtons submitText="plugins.importexport.metafora.send.issues" hideCancel="true"}
					{/if}
					<pkp-button id="metaforaSyncSelectedIssues">{translate key="plugins.importexport.metafora.action.syncSelected"}</pkp-button>
				{/fbvFormArea}
			</form>
		</div>
	</div>
	<section class="metaforaSupport" aria-labelledby="metaforaSupportTitle">
		<div class="metaforaSupport__content">
			<h2 id="metaforaSupportTitle">{translate key="plugins.importexport.metafora.support.title"}</h2>
			<p>{translate key="plugins.importexport.metafora.support.text"}</p>
			<p class="metaforaSupport__author">
				<strong>{translate key="plugins.importexport.metafora.author"}:</strong>
				Dmitry Matveev · <a href="mailto:d.a.matveev@gmail.com">d.a.matveev@gmail.com</a>
			</p>
			<a class="pkp_button metaforaSupport__button" href="https://boosty.to/matveevd/donate" target="_blank" rel="noopener noreferrer">
				{translate key="plugins.importexport.metafora.support.donate"}
			</a>
		</div>
		<a class="metaforaSupport__qr" href="https://boosty.to/matveevd/donate" target="_blank" rel="noopener noreferrer">
			<img src="{$baseUrl}/plugins/importexport/metafora/images/donate.png" width="150" height="150" alt="{translate key="plugins.importexport.metafora.support.qrAlt"}">
		</a>
	</section>

	</div>
{/block}
