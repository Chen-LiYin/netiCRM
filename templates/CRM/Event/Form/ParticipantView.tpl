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
{* View existing event registration record. *}
<div class="crm-block crm-content-block crm-event-participant-view-form-block">
    <h3>{ts}View Participant{/ts}</h3>
    <div class="action-link-button">
        <div class="crm-submit-buttons">
            {if call_user_func(array('CRM_Core_Permission','check'), 'edit event participants')}
	       {assign var='urlParams' value="reset=1&id=$id&cid=$contact_id&action=update&context=$context&selectedChild=event"}
	       {if ( $context eq 'fulltext' || $context eq 'search' ) && $searchKey}
	       {assign var='urlParams' value="reset=1&id=$id&cid=$contact_id&action=update&context=$context&selectedChild=event&key=$searchKey"}	   
	       {/if}
               <a class="button" href="{crmURL p='civicrm/contact/view/participant' q=$urlParams}" accesskey="e"><span><div class="zmdi zmdi-edit"></div> {ts}Edit{/ts}</span></a>
            {/if}
            {if call_user_func(array('CRM_Core_Permission','check'), 'delete in CiviEvent')}
                {assign var='urlParams' value="reset=1&id=$id&cid=$contact_id&action=delete&context=$context&selectedChild=event"}
	        {if ( $context eq 'fulltext' || $context eq 'search' ) && $searchKey}
	        {assign var='urlParams' value="reset=1&id=$id&cid=$contact_id&action=delete&context=$context&selectedChild=event&key=$searchKey"}	   
	        {/if}
                <a class="button" href="{crmURL p='civicrm/contact/view/participant' q=$urlParams}"><span><div class="zmdi zmdi-delete"></div> {ts}Delete{/ts}</span></a>
            {/if}
            {include file="CRM/common/formButtons.tpl" location="top"}
        </div>
    </div>
    <table class="crm-info-panel">
    {if $participant_registered_by_id} {* Display primary participant *}
        <tr class="crm-event-participantview-form-block-registeredBy">
            <td class="label">{ts}Registered by ID{/ts}</td>
            <td><i class="zmdi zmdi-dot-circle"></i><a href="{crmURL p='civicrm/contact/view/participant' q="reset=1&id=$participant_registered_by_id&cid=$registered_by_contact_id&action=view"}" title="{ts}view primary participant{/ts}">{$registered_by_display_name}</a></td>
        </tr>
    {/if}
    <tr class="crm-event-participantview-form-block-displayName">
	    <td class="label">{ts}Participant Name{/ts}</td>
	    <td class="bold">
        {if $participant_registered_by_id}
            &nbsp; <i class="zmdi zmdi-long-arrow-return zmdi-hc-flip-horizontal"></i> 
        {/if}
	    	<a href="{crmURL p='civicrm/contact/view' q="reset=1&cid=$contact_id"}" title="view contact record">{$displayName}</a>
	    	<div class="crm-submit-buttons">
                <a class="button" href="{crmURL p='civicrm/event/badge' q="reset=1&context=view&id=$id&cid=$contact_id"}" title="{ts}Print Event Name Badge{/ts}"><span><div class="zmdi zmdi-print"></div> {ts}Print Name Badge{/ts}</span></a>
                {if $checkinUrl}
                <a class="button" href="{$checkinUrl}"><span><div class="zmdi zmdi-assignment-account"></div> {ts}Check In{/ts}</span></a>
                {/if}
	    	</div>
	    </td>
	</tr>
	{if $additionalParticipants} {* Display others registered by this participant *}
        <tr class="crm-event-participantview-form-block-additionalParticipants">
            <td class="label">{ts}Also Registered by this Participant{/ts}</td>
            <td>
                {foreach from=$additionalParticipants key=apName item=apURL}
                    <i class="zmdi zmdi-long-arrow-return zmdi-hc-flip-horizontal"></i> <a href="{$apURL}" title="{ts}view additional participant{/ts}">{$apName}</a><br />
                {/foreach}
            </td>
        </tr>
	{/if}
    <tr class="crm-event-participantview-form-block-event">
	    <td class="label">{ts}Event{/ts}</td><td>
	    	<a href="{crmURL p='civicrm/event/manage/eventInfo' q="action=update&reset=1&id=$event_id"}" title="{ts}Configure this event{/ts}">{$event}</a>
	    </td>
	</tr>
    <tr class="crm-event-participantview-form-block-role">
	    <td class="label">{ts}Participant Role{/ts}</td>
	    <td>{$role}</td></tr>
        <tr class="crm-event-participantview-form-block-register_date">
	    <td class="label">{ts}Registration Date and Time{/ts}</td>
	    <td>{$register_date|crmDate}&nbsp;</td>
	</tr>
    <tr class="crm-event-participantview-form-block-status">
	    <td class="label">{ts}Status{/ts}</td><td>{$status}{if $confirmLink} (<a href="{$confirmLink}" target="_blank">{ts}Confirm Registration{/ts} {ts}Link{/ts}</a>){/if}</td>
	</tr>
    {if $source}
        <tr class="crm-event-participantview-form-block-event_source">
	    	<td class="label">{ts}Event Source{/ts}</td><td>{$source}&nbsp;</td>
	    </tr>
    {/if}
    {if $fee_level}
        <tr class="crm-event-participantview-form-block-fee_amount">
            {if $lineItem}
                <td class="label">{ts}Event Fees{/ts}</td>
                <td>{include file="CRM/Price/Page/LineItem.tpl" context="Event"}</td> 
            {else}
                <td class="label">{ts}Event Level{/ts}</td>
                <td>{$fee_level}&nbsp;{if $fee_amount}- {$fee_amount|crmMoney:$fee_currency}{/if}</td>
            {/if}
        </tr>
        {if !$lineItem AND $coupon.coupon_track_id}
        <tr>
            <td class="label">{ts}Coupon{/ts}</td>
            <td>{$coupon.code} - {$coupon.description} <span id="coupon_calc" class="font-red" data-coupon-type="{$coupon.coupon_type}" data-coupon-discount="{$coupon.discount}">{if $coupon.coupon_type == 'monetary'} - {$coupon.discount|crmMoney}{else} - {$coupon.discount_amount|crmMoney}%{/if}</span></td>
        </tr>
        {/if}
    {/if}
    {foreach from=$note item="rec"}
	    {if $rec }
            <tr><td class="label">{ts}Note{/ts}</td><td>{$rec}</td></tr>
	    {/if}
    {/foreach}
    </table>         
    {include file="CRM/Custom/Page/CustomDataView.tpl"}
    {if $accessContribution and $rows.0.contribution_id}
        {include file="CRM/Contribute/Form/Selector.tpl" context="Search"} 
    {/if}
    {assign var='urlParams' value="reset=1&action=add&cid=$contact_id&context=contribution&participant_id=$id"}
    <a class="button" href="{crmURL p='civicrm/contact/view/contribution' q=$urlParams}" accesskey="n"><span><div class="zmdi zmdi-plus"></div> {ts}Add a participant payment record{/ts}</span></a>
    <div class="crm-submit-buttons">
        {if call_user_func(array('CRM_Core_Permission','check'), 'edit event participants')}
	  {assign var='urlParams' value="reset=1&id=$id&cid=$contact_id&action=update&context=$context&selectedChild=event"}
	  {if ( $context eq 'fulltext' || $context eq 'search' ) && $searchKey}
	  {assign var='urlParams' value="reset=1&id=$id&cid=$contact_id&action=update&context=$context&selectedChild=event&key=$searchKey"}	   
	  {/if}

           <a class="button" href="{crmURL p='civicrm/contact/view/participant' q=$urlParams}" accesskey="e"><span><div class="zmdi zmdi-edit"></div> {ts}Edit{/ts}</span></a>
        {/if}
        {if call_user_func(array('CRM_Core_Permission','check'), 'delete in CiviEvent')}
	  {assign var='urlParams' value="reset=1&id=$id&cid=$contact_id&action=delete&context=$context&selectedChild=event"}
	  {if ( $context eq 'fulltext' || $context eq 'search' ) && $searchKey}
	  {assign var='urlParams' value="reset=1&id=$id&cid=$contact_id&action=delete&context=$context&selectedChild=event&key=$searchKey"}	   
	  {/if}
            <a class="button" href="{crmURL p='civicrm/contact/view/participant' q=$urlParams}"><span><div class="zmdi zmdi-delete"></div> {ts}Delete{/ts}</span></a>
        {/if}
        {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>
</div>
