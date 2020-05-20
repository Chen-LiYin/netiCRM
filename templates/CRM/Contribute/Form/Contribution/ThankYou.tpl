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
{if $special_style}
  {include file="CRM/common/contributionPageSpecial.tpl"}
  <div id="intro_text" class="crm-section intro_text-section">
    {$intro_text}
  </div>
{/if}

{if $action & 1024}
    {include file="CRM/Contribute/Form/Contribution/PreviewHeader.tpl"}
{/if}

{include file="CRM/common/TrackingFields.tpl"}

<div class="crm-block crm-contribution-thankyou-form-block">
    {if ($thankyou_text and $payment_result_type neq 4) or $special_style}
        <div id="thankyou_text" class="crm-section thankyou_text-section">
            {$thankyou_text}
        </div>
    {/if}
    
    {* Show link to Tell a Friend (CRM-2153) *}
    {if $friendText}
        <div id="tell-a-friend" class="crm-section friend_link-section">
            <a href="{$friendURL}" title="{$friendText}" class="button"><span>&raquo; {$friendText}</span></a>
       </div>{if !$linkText}<br /><br />{/if}
    {/if}  
    {* Add button for donor to create their own Personal Campaign page *}
    {if $linkText}
 	<div class="crm-section create_pcp_link-section">
        <a href="{$linkTextUrl}" title="{$linkText}" class="button"><span>&raquo; {$linkText}</span></a>
    </div><br /><br />
    {/if}  

    <div class="crmdata-contact" style="display:none">{$contact_id}</div>
    <div class="crmdata-contribution" style="display:none">{$contribution_id}</div>
    <div class="crmdata-contribution-type" style="display:none">{$contribution_type_id}</div>
    <div {if $payment_result_type eq 4}class="messages error"{else}id="help"{/if}>
      {* PayPal_Standard sets contribution_mode to 'notify'. We don't know if transaction is successful until we receive the IPN (payment notification) *}
      {if $payment_result_type eq 1 && $is_monetary}
        <h3>{ts}Congratulations! Your payment has been completed!{/ts}</h3>
        {if $is_email_receipt}
          <div>
          {ts}You will receive an email acknowledgement of this payment.{/ts} 

          {if $onBehalfEmail AND ($onBehalfEmail neq $email)}
            {ts 1=$email 2=$onBehalfEmail}An email with your payment details has been sent to %1 and to %2.{/ts}
          {else}
            {ts 1=$email}An email with your payment details has been sent to %1.{/ts}
          {/if}
          </div>
        {/if}
      {elseif $payment_result_type eq 4 && $is_monetary}
        <h3>{ts}Payment failed.{/ts}</h3>
        {ts}We were unable to process your payment. You will not be charged in this transaction.{/ts}
        {ts}Possible reason{/ts}:
        <ul>
        {if $payment_result_message}
          <li>{$payment_result_message}</li>
        {else}
          <li>{ts}Network or system error. Please try again a minutes later, if you still can't success, please contact us for further assistance.{/ts}</li>
        {/if}
        </ul>

        {if $action & 1024}
            {capture assign=contribution_page_url}{crmURL p='civicrm/contribute/transact' q="reset=1&id=$id&action=preview&retry=1" h=0 }{/capture}
        {else}
            {capture assign=contribution_page_url}{crmURL p='civicrm/contribute/transact' q="reset=1&id=$id&retry=1" h=0 }{/capture}
        {/if}
        {ts 1=$contribution_page_url}We apologize for any inconvenience caused, please go back to the <a href='%1'>donation page</a> to retry.{/ts}
      {elseif $is_pay_later && $is_monetary}
        <h3>{ts}Keep supporting it. Payment has not been completed yet with entire process.{/ts}</h3>
        <div class="">
        {if $pay_later_receipt and !$payment_result_type}
          {$pay_later_receipt}
        {else}
          {ts}Please note the expiration date of payment, you could pay with chosen payment payment method due to the date. If you have overdue payment, or if your payment method has expired, it might require you to do again.{/ts}
        {/if}
        </div>
        {if $is_email_receipt}
          <div>
          {if $onBehalfEmail AND ($onBehalfEmail neq $email)}
            <!-- {ts 1=$email 2=$onBehalfEmail}Remider email has been sent to %1 and to %2.{/ts} -->
          {else}
            <!-- {ts 1=$email}Remider email has been sent to %1.{/ts} -->
          {/if}
          </div>
        {/if}{*is_email_receipt*}
      {elseif $contributeMode EQ 'notify' OR ($contributeMode EQ 'direct' && $is_recur) }
        <div>{ts 1=$paymentProcessor.name}Your contribution has been submitted to %1 for processing. Please print this page for your records.{/ts}</div>
        {if $is_email_receipt}
          <div>
          {if $onBehalfEmail AND ($onBehalfEmail neq $email)}
            {ts 1=$email 2=$onBehalfEmail}An email receipt will be sent to %1 and to %2 once the transaction is processed successfully.{/ts}
          {else}
            {ts 1=$email}An email receipt will be sent to %1 once the transaction is processed successfully.{/ts}
          {/if}
          </div>
        {/if}
      {else}
        <div>
        {ts}Your transaction has been processed successfully. Please print this page for your records.{/ts}</div>
        {if $is_email_receipt}
          <div>		    
          {if $onBehalfEmail AND ($onBehalfEmail neq $email)}
            {ts 1=$email 2=$onBehalfEmail}An email with details has been sent to %1 and to %2.{/ts}
          {else}
            {ts 1=$email}An email with details has been sent to %1.{/ts}
          {/if}
          </div>
        {/if}
      {/if}{*is_pay_later*}
    </div>
    <div class="spacer"></div>
    
    {include file="CRM/Contribute/Form/Contribution/MembershipBlock.tpl" context="thankContribution"}

    {if $amount GT 0 OR $minimum_fee GT 0 OR ( $priceSetID and $lineItem ) }
    <div class="crm-group amount_display-group">
        <div class="header-dark">
            {if !$membershipBlock AND $amount OR ( $priceSetID and $lineItem )}{ts}Payment Information{/ts}{else}{ts}Membership Fee{/ts}{/if}
        </div>
        <div class="display-block">
          {if $trxn_id}
          <div><label>{ts}Transaction ID{/ts}:</label> <strong class="crmdata-trxn-id">{$trxn_id}</strong></div>
          {/if}
          {if $payment_instrument}
          <div><label>{ts}Payment Instrument{/ts}:</label> <span class="crmdata-instrument">{$payment_instrument}</span></div>
          {/if}
            {if $lineItem and $priceSetID}
              {if !$amount}{assign var="amount" value=0}{assign var="product_amount" value=0}{/if}
              {assign var="totalAmount" value=$amount}
              {assign var="product_amount" value=$amount}
              {include file="CRM/Price/Page/LineItem.tpl" context="Contribution"}
            {elseif $membership_amount } 
              {$membership_name} {ts}Membership{/ts}: <strong>{$membership_amount|crmMoney}<span class="crmdata-amount-member" style="display:none">{$membership_amount}</span></strong><br />
              {if $amount}
                {if ! $is_separate_payment }
                  {ts}Amount{/ts}: <strong>{$amount|crmMoney}</strong><br />
                  {capture assign=product_amount}{$amount}{/capture}
                {else}
                  {ts}Additional Contribution{/ts}: <strong>{$amount|crmMoney}</strong><br />
                {/if}
              {/if} 		
              <strong> -------------------------------------------</strong><br />
              {ts}Total{/ts}: <strong>{$amount+$membership_amount|crmMoney}<span class="crmdata-amount" style="display:none">{$amount+$membership_amount}</span></strong><br />
              {capture assign=product_amount}{$amount+$membership_amount}{/capture}
            {else}
              {ts}Amount{/ts}: <strong>{$amount|crmMoney} {if $amount_level } - {$amount_level} {/if}<span class="crmdata-amount" style="display:none">{$amount}</span></strong><br />
              {capture assign=product_amount}{$amount}{/capture}
            {/if}
            {if $receive_date}
            {ts}Date{/ts}: <strong>{$receive_date|crmDate}</strong><br />
            {/if}
            {if $membership_trx_id}
            {ts}Membership Transaction #{/ts}: {$membership_trx_id}
            {/if}
        
            {* Recurring contribution / pledge information *}
            {if $is_recur}
                {if $installments}
    		<p><strong>{ts 1=$frequency_interval 2=$frequency_unit 3=$installments}This recurring contribution will be automatically processed every %1 %2(s) for a total %3 installments (including this initial contribution).{/ts}</strong></p>
                {else}
                    <p><strong>{ts 1=$frequency_interval 2=$frequency_unit}This recurring contribution will be automatically processed every %1 %2(s).{/ts}</strong></p>
                {/if}
                <p>
                {if $contributeMode EQ 'notify'}
                  {ts 1=$receiptFromEmail}To modify or cancel future contributions please contact us at %1.{/ts}
                {/if}
                {if $contributeMode EQ 'direct'}
                  {ts 1=$receiptFromEmail}To modify or cancel future contributions please contact us at %1.{/ts}
                {/if}
                {if $is_email_receipt}
                    {ts}You will receive an email receipt for each recurring contribution.{/ts}
                {/if}
                </p>
                <span class="crmdata-recur" style="display:none">Y</span>
                <span class="crmdata-installments" style="display:none">{$installments}</span>
                <span class="crmdata-frequency-unit" style="display:none">{$frequency_unit}</span>
            {else}
                <span class="crmdata-recur" style="display:none">N</span>
            {/if}{*is_recur*}
            {if $is_pledge}
                {if $pledge_frequency_interval GT 1}
                    <p><strong>{ts 1=$pledge_frequency_interval 2=$pledge_frequency_unit 3=$pledge_installments}I pledge to contribute this amount every %1 %2s for %3 installments.{/ts}</strong></p>
                {else}
                    <p><strong>{ts 1=$pledge_frequency_interval 2=$pledge_frequency_unit 3=$pledge_installments}I pledge to contribute this amount every %2 for %3 installments.{/ts}</strong></p>
                {/if}
                <p>
                {if $is_pay_later}
                    {ts 1=$receiptFromEmail}We will record your initial pledge payment when we receive it from you. You will be able to modify or cancel future pledge payments at any time by logging in to your account or contacting us at %1.{/ts}
                {else}
                    {ts 1=$receiptFromEmail}Your initial pledge payment has been processed. You will be able to modify or cancel future pledge payments at any time by logging in to your account or contacting us at %1.{/ts}
                {/if}
                {if $max_reminders}
                    {ts 1=$initial_reminder_day}We will send you a payment reminder %1 days prior to each scheduled payment date. The reminder will include a link to a page where you can make your payment online.{/ts}
                {/if}
                </p>
            {/if}
        </div>
    </div>
    {/if}
    
    {include file="CRM/Contribute/Form/Contribution/Honor.tpl"}

    {if $customPreGroup}
        {foreach from=$customPreGroup item=field key=cname}
            {if $field.groupTitle}
                {assign var=groupTitlePre  value=$field.groupTitle} 
            {/if}
        {/foreach}
    	<div class="crm-group custom_pre-group">
            <div class="header-dark">
                {$groupTitlePre}
            </div>  
            <fieldset class="label-left">
                {include file="CRM/UF/Form/Block.tpl" fields=$customPreGroup}
            </fieldset>
        </div>
    {/if}
    
    {if $pcpBlock}
    <div class="crm-group pcp_display-group">
        <div class="header-dark">
            {ts}Contribution Honor Roll{/ts}
        </div>
        <div class="display-block">
            {if $pcp_display_in_roll}
                {ts}List my contribution{/ts}
                {if $pcp_is_anonymous}
                    <strong>{ts}anonymously{/ts}.</strong>
                {else}
                    {ts}under the name{/ts}: <strong>{$pcp_roll_nickname}</strong><br/>
                    {if $pcp_personal_note}
                        {ts}With the personal note{/ts}: <strong>{$pcp_personal_note}</strong>
                    {else}
                     <strong>{ts}With no personal note{/ts}</strong>
                     {/if}
                {/if}
            {else}
		        {ts}Don't list my contribution in the honor roll.{/ts}
            {/if}
            <br />
       </div>
    </div>
    {/if}
    
    {if $onBehalfParams}
    <div class="crm-group onBehalf_display-group label-left">
        <div class="header-dark">
            {ts}On Behalf Of{/ts}
        </div>
        {foreach from=$onBehalfParams item=item key=key}
        <div class="crm-section">
            <div class="label"><label>{$key}</label></div>
            <div class="content">{$item}</div>
            <div class="clear"></div>
        </div>
        {/foreach}
    </div>
    {/if}

    {if $contributeMode ne 'notify' and ! $is_pay_later and $is_monetary and ( $amount GT 0 OR $minimum_fee GT 0 )}    
    <div class="crm-group billing_name_address-group">
        <div class="header-dark">
            {ts}Billing Name and Address{/ts}
        </div>
    	<div class="crm-section no-label billing_name-section">
    		<div class="content">{$billingName}</div>
    		<div class="clear"></div>
    	</div>
    	<div class="crm-section no-label billing_address-section">
    		<div class="content">{$address|nl2br}</div>
    		<div class="clear"></div>
    	</div>
        <div class="crm-section no-label contributor_email-section">
        	<div class="content">{$email}</div>
        	<div class="clear"></div>
        </div>
    </div>
    {/if}

    {if $contributeMode eq 'direct' and ! $is_pay_later and $is_monetary and ( $amount GT 0 OR $minimum_fee GT 0 )}
    <div class="crm-group credit_card-group">
        <div class="header-dark">
         {if $paymentProcessor.payment_type & 2}
            {ts}Direct Debit Information{/ts}
         {else}
            {ts}Credit Card Information{/ts}
         {/if}
        </div>
         {if $paymentProcessor.payment_type & 2}
            <div class="display-block">
                {ts}Account Holder{/ts}: {$account_holder}<br />
                {ts}Bank Identification Number{/ts}: {$bank_identification_number}<br />
                {ts}Bank Name{/ts}: {$bank_name}<br />
                {ts}Bank Account Number{/ts}: {$bank_account_number}<br />
            </div>
         {else}
             <div class="crm-section no-label credit_card_details-section">
                 <div class="content">{$credit_card_type}</div>
             	<div class="content">{$credit_card_number}</div>
             	<div class="content">{ts}Expires{/ts}: {$credit_card_exp_date|truncate:7:''|crmDate}</div>
             	<div class="clear"></div>
             </div>
         {/if}
    </div>
    {/if}

    {include file="CRM/Contribute/Form/Contribution/PremiumBlock.tpl" context="thankContribution"}

    {if $customPostGroup}
        {foreach from=$customPostGroup item=field key=cname}
            {if $field.groupTitle}
                {assign var=groupTitlePost  value=$field.groupTitle} 
            {/if}
        {/foreach}
    	<div class="crm-group custom_post-group">
            <div class="header-dark">
                {$groupTitlePost}
            </div>  
            <fieldset class="label-left">
                {include file="CRM/UF/Form/Block.tpl" fields=$customPostGroup}
            </fieldset>
        </div>
    {/if}
    {if $thankyou_footer and $payment_result_type neq 4}
    <div id="thankyou_footer" class="contribution_thankyou_footer-section">
        {$thankyou_footer}
    </div>
    {/if}

    {capture assign=product_id}{ts}Contribution Page{/ts}-{$id}{/capture}
    {if !$trxn_id}
      {capture assign=transaction_id}{ts}Contribution ID{/ts}-{$contribution_id}{/capture}
    {else}
      {assign var=transaction_id value=$trxn_id}
    {/if}
    {if $is_recur}
      {capture assign=product_category}{ts}Recurring Contribution{/ts}{/capture}
    {else}
      {capture assign=product_category}{ts}Non-recurring Contribution{/ts}{/capture}
    {/if}
    {include file="CRM/common/DataLayer.tpl" dataLayerType='purchase' transaction_id=$transaction_id total_amount=$product_amount product_name=$contributionPage.title product_id=$product_id product_amount=$product_amount product_category=$product_category product_quantity=1}
    {if $payment_result_type eq 4 && $is_monetary}
      {include file="CRM/common/DataLayer.tpl" dataLayerType='refund' transaction_id=$transaction_id}
    {/if}
</div>