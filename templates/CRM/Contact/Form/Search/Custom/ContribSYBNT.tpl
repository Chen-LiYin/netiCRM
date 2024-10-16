{*
 +--------------------------------------------------------------------+
 | CiviCRM version 3.3                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2010                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*}

<div id="searchForm" class="crm-block crm-form-block crm-contact-custom-search-contribSYBNT-form-block">
<div class="crm-custom-search-description">
  <p>{ts}Someone had donated last year but not this year. You can take care of them to see if they still remember you.{/ts}</p>
</div>
  <div class="crm-accordion-wrapper crm-custom_search_form-accordion crm-accordion-{if !$rows}open{else}closed{/if}">
    <div class="crm-accordion-header crm-master-accordion-header">
      <div class="zmdi crm-accordion-pointer"></div>
      {ts}Edit Search Criteria{/ts}
    </div><!-- /.crm-accordion-header -->
    <div class="crm-accordion-body">

        <table class="form-layout-compressed">
            <tr class="crm-contact-custom-search-contribSYBNT-form-block-contribution_type_id">
                <td class="label">
                  {$form.contribution_type_id.label}
                </td>
                <td colspan="2">
                  {$form.contribution_type_id.html}
                  {include file="CRM/common/chosen.tpl" selector="#contribution_type_id"}
                </td>
            </tr>
            <tr>
                <td class="label">
                    <h3>{ts}Have Donations{/ts}</h3>
                </td>
                <td colspan="2">
                </td>
            </tr>
            <tr class="crm-contact-custom-search-contribSYBNT-form-block-inclusion_date_one">
                <td class="label"><label>{ts}Date{/ts}</label></td>
                <td>{ts}From{/ts}: {include file="CRM/common/jcalendar.tpl" elementName=include_start_date}</td>
                <td>{ts}To{/ts}: {include file="CRM/common/jcalendar.tpl" elementName=include_end_date}</td>
            </tr>
            <tr class="crm-contact-custom-search-contribSYBNT-form-block-min_amount_1">
                <td class="label"><label>{ts}Total Receive Amount{/ts}</label></td>
                <td>{ts}Min{/ts}: {$form.include_min_amount.html}</td>
                <td>{ts}Max{/ts}: {$form.include_max_amount.html}</td>
            </tr>
            <tr>
                <td class="label">
                    <h3>{ts}Without Donations{/ts}</h3>
                </td>
                <td colspan="2">
                </td>
            </tr>
            <tr class="crm-contact-custom-search-contribSYBNT-form-block-exclusion_date">
                <td class="label"><label>{ts}Date{/ts}</label></td>
                <td>{ts}From{/ts}: {include file="CRM/common/jcalendar.tpl" elementName=exclude_start_date}</td>
                <td>{ts}To{/ts}:{include file="CRM/common/jcalendar.tpl" elementName=exclude_end_date}</td>
            </tr>
            <tr>
                <td colspan="3">
                  <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
                </td>
            </tr>
        </table> 
    </div><!-- /.crm-accordion-body -->
</div><!-- /.crm-accordion-wrapper -->
</div><!-- /.crm-form-block -->

{if $rowsEmpty}
    {include file="CRM/Contact/Form/Search/Custom/EmptyResults.tpl"}
{/if}

{if $summary}
    {$summary.summary}: {$summary.total}
{/if}

{if $rows}
    {* Search request has returned 1 or more matching rows. Display results and collapse the search criteria fieldset. *}
    {assign var="showBlock" value="'searchForm_show'"}
    {assign var="hideBlock" value="'searchForm'"}    
	<div class="crm-results-block">
    <div class="crm-search-tasks">        
      {include file="CRM/Contact/Form/Search/ResultTasks.tpl"}
		</div>
        <p>

        {include file="CRM/common/pager.tpl" location="top"}

        {* Include alpha pager if defined. *}
        {if $atoZ}
            {include file="CRM/common/pagerAToZ.tpl"}
        {/if}

        {strip}
        <table class="selector" summary="{ts}Search results listings.{/ts}">
            <thead class="sticky">
                <th scope="col" title="Select All Rows">{$form.toggleSelect.html}</th>
                {foreach from=$columnHeaders item=header}
                    <th scope="col">
                        {if $header.sort}
                            {assign var='key' value=$header.sort}
                            {if $sort->_response.$key}{$sort->_response.$key.link}{else}{$header.name}{/if}
                        {else}
                            {$header.name}
                        {/if}
                    </th>
                {/foreach}
                <th>&nbsp;</th>
            </thead>
            {counter start=0 skip=1 print=false}
            {foreach from=$rows item=row}
                <tr id='rowid{$row.contact_id}' class="{cycle values="odd-row,even-row"}">
                    {assign var=cbName value=$row.checkbox}
                    <td>{$form.$cbName.html}</td>
                        {foreach from=$columnHeaders item=header}
                            {assign var=fName value=$header.sort}
                            {if $fName eq 'sort_name'}
                                <td><a href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$row.contact_id`"}">{$row.sort_name}</a></td>
                            {else}
                                <td>{$row.$fName}</td>
                            {/if}
                        {/foreach}
                    <td>{$row.action}</td>
                </tr>
            {/foreach}
        </table>
        {/strip}

        <script type="text/javascript">
        {* this function is called to change the color of selected row(s) *}
           var fname = "{$form.formName}";	
           on_load_init_checkboxes(fname);
        </script>

        {include file="CRM/common/pager.tpl" location="bottom"}
    </fieldset>
    </div>
    {* END Actions/Results section *}
{/if}
{literal}
<script type="text/javascript">
cj(function() {
   cj().crmaccordions(); 
});
</script>
{/literal}
