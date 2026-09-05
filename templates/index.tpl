{extends file="layouts/backend.tpl"}

{block name="page"}
    <h1 class="app__pageHeading">{$pageTitle}</h1>

    <script type="text/javascript">
        $(function() {ldelim}
            $('#metaforaExportForm').pkpHandler('$.pkp.controllers.form.FormHandler');
        {rdelim});
    </script>

    <form id="metaforaExportForm" class="pkp_form" action="{plugin_url path="exportJson"}" method="post">
        {csrf}
        {fbvFormArea id="metaforaExportFormArea"}
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
                <pkp-button @click="submit('#metaforaExportForm')">
                    {translate key="plugins.importexport.metafora.export.json"}
                </pkp-button>
            {/fbvFormSection}
        {/fbvFormArea}
    </form>
{/block}
