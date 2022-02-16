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
{* Displays contribution/event fees when price set is used. *}
{foreach from=$lineItem item=value key=priceset}
    {if $value neq 'skip'}
    {if $lineItem|@count GT 1} {* Header for multi participant registration cases. *}
        {if $priceset GT 0}<br />{/if}
        <strong>{ts 1=$priceset+1}Participant %1{/ts}</strong> {$part.$priceset.info}
    {/if}				 
    <table>
            <tr class="columnheader">
                <th>{ts}Item{/ts}</th>
                <th class="right">{ts}Qty{/ts}</th>
                <th class="right">{ts}Unit Price{/ts}</th>
                <th class="right">{ts}Total Price{/ts}</th>
	 {if $pricesetFieldsCount}<th class="right">{ts}Total Participants{/ts}</th>{/if} 
            </tr>
            {foreach from=$value item=line}
            <tr>
                <td>{if $line.html_type eq 'Text'}{$line.label}{else}{$line.field_title} - {$line.label}{/if} {if $line.description}<div class="description">{$line.description}</div>{/if}</td>
                <td class="right">{$line.qty}</td>
                <td class="right">{$line.unit_price|crmMoney}</td>
                <td class="right">{$line.line_total|crmMoney}</td>
         {if $pricesetFieldsCount}<td class="right">{$line.participant_count}</td> {/if}
            </tr>
                {if $line.discount}
                <tr>
                    <td class="right" colspan="3">
                        {$couponDescription}
                    </td>
                    <td class="right">
                        - {$line.discount|crmMoney}
                    </td>
                    <td>
                    </td>
                </tr>
                {/if}
            {/foreach}
            {if $coupon.coupon_track_id}
            <tr>
                <td colspan="{if $pricesetFieldsCount}4{else}3{/if}">{ts}Coupon{/ts} - {$coupon.code} - {$coupon.description}</td>
                <td class="right"><div id="coupon_calc" class="font-red" data-coupon-type="{$coupon.coupon_type}" data-coupon-discount="{$coupon.discount}">{if $coupon.coupon_type == 'monetary'} - {$coupon.discount|crmMoney}{else} - {$coupon.discount_amount|crmMoney}{/if}</div></td>
            </tr>
            {/if}
    </table>
    {/if}
{/foreach}

<div class="crm-section no-label total_amount-section">
  <table>
  <tr>
    <td class="right">
        {if $couponDescription && !$usedOptionsDiscount}
            <div class="content">
                {$couponDescription}:&nbsp;&nbsp;-{$totalDiscount|crmMoney}
            </div>
        {/if}
    <div class="content bold">
      {ts}Total Amount{/ts}: <span id="total-amount-display" data-total-amount="{$totalAmount}">{$totalAmount|crmMoney}</span>
      <span class="crmdata-amount" style="display:none">{$totalAmount}</span>
    </div>
    <div class="content bold">
      {if $pricesetFieldsCount}
      {ts}Total Participants{/ts}:
      {foreach from=$lineItem item=pcount}
        {if $pcount neq 'skip'}
        {assign var="lineItemCount" value=0}
	
        {foreach from=$pcount item=p_count}
          {assign var="lineItemCount" value=$lineItemCount+$p_count.participant_count}
        {/foreach}
        {if $lineItemCount < 1 }
      	  {assign var="lineItemCount" value=1}
        {/if}
        {assign var="totalcount" value=$totalcount+$lineItemCount}
        {/if} 
      {/foreach}
      {$totalcount}
      {/if}
     </div>
     </td>
  </tr>
  </table>
</div>

{if $hookDiscount.message}
    <div class="crm-section hookDiscount-section">
        <em>({$hookDiscount.message})</em>
    </div>
{/if}
