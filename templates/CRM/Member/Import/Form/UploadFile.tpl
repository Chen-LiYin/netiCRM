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
 <div class="crm-block crm-form-block crm-member-import-uploadfile-form-block">
{* WizardHeader.tpl provides visual display of steps thru the wizard as well as title for current step *}
 {include file="CRM/common/WizardHeader.tpl"}
<div id="help">
    {ts}The Membership Import Wizard allows you to easily upload memberships from other applications into CiviCRM.{/ts}
    {ts}Files to be imported must be in the 'comma-separated-values' format (CSV) and must contain data needed to match the membership data to an existing contact in your CiviCRM database.{/ts} {help id='upload'}
 </div> 
{* Membership Import Wizard - Step 1 (upload data file) *}
{* @var $form Contains the array for the form elements and other form associated information assigned to the template by the controller *}
 
<div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="top"}</div>   
   <table class="form-layout">
      <div id="upload-file" class="form-item">
       <tr class="crm-member-import-uploadfile-from-block-uploadFile">
          <td class="label">{$form.uploadFile.label}</td>
          <td class="html-adjust with-help-link">{$form.uploadFile.html}
            <a class="help-link new-window-link" href="https://neticrm.tw/resources/2300" target="_blank">{ts}Imported tutorial and example file{/ts}</a>
            <div class="description">{ts}File format must be comma-separated-values (CSV).{/ts}</div>
          </td>
       </tr>
       <tr><td class="label"></td><td>{ts 1=$uploadSize}Maximum Upload File Size: %1 MB{/ts}</td></tr>
       <tr class="crm-member-import-uploadfile-from-block-skipColumnHeader">
           <td class="label">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
	   <td>{$form.skipColumnHeader.html} {$form.skipColumnHeader.label}<br />
               <span class="description">
                {ts}Check this box if the first row of your file consists of field names (Example: 'Contact ID', 'Amount').{/ts}</span>
           </td>
       <tr class="crm-member-import-uploadfile-from-block-createContactMode">
           <td class="label" >{$form.createContactMode.label}</td>
           <td>{$form.createContactMode.html}</td>
       </tr>
       <tr class="create-new-contact"><td class="label">{$form.createContactOption.label}{help id="id-createContactOption"}</td><td>{$form.createContactOption.html}</td></tr>
       <tr class="crm-member-import-uploadfile-from-block-contactType">
           <td class="label">{$form.contactType.label}</tdt>
     <td>{$form.contactType.html}<br />
                <span class="description">
                {ts}Select 'Individual' if you are importing memberships for individual persons.{/ts}
                {ts}Select 'Organization' or 'Household' if you are importing memberships made by contacts of that type. (NOTE: Some built-in contact types may not be enabled for your site.){/ts}
                </span>
           </td>
       </tr>
       <tr class="dedupe-rule-group">
         <td class="label">{$form.dedupeRuleGroup.label}</td>
         <td>
           {$form.dedupeRuleGroup.html}
           <div class="description">
             {capture assign='newrule'}{crmURL p='civicrm/contact/deduperules' q='reset=1'}{/capture}
             {ts 1=$newrule}Use rule you choose above for matching contact in each row. You can also <a href="%1">add new rule</a> anytime.{/ts}
            <ul style="list-style-type: decimal;">
            <li>{ts}Uploading file must include the following columns or the data cannot be imported successfully.{/ts}</li>
            <ul style="list-style-type: disc;">
              <li>{ts}First Name,Last Name,Email(or Dedupe Rule of Contact you selected){/ts}</li>
              <li>{ts}Membership Types{/ts}</li>
              <li>{ts}Membership Start Date{/ts}</li>
            </ul>
            <li>{ts}When importing members, if the member has already have data in the system, the content of the member's personal information (contact, personal field) you imported this time,It is not possible to update the personal information of this member, but only for the purpose of comparing members. If you want to update your membership information, please use the Import Contacts function to do so.{/ts}</li>
            </ul>
            </div>
         </td>
       </tr>
       <tr class="crm-member-import-uploadfile-from-block-dataReferenceField"><td class="label">{$form.dataReferenceField.label}</td><td>{$form.dataReferenceField.html}</td></tr>
       <tr class="crm-member-import-uploadfile-from-block-date">{include file="CRM/Core/Date.tpl"}</tr>  
{if $savedMapping}
       <tr  class="crm-member-import-uploadfile-from-block-savedMapping">
         <td>{if $loadedMapping}{ts}Select a Different Field Mapping{/ts}{else}{ts}Load Saved Field Mapping{/ts}{/if}</td>
         <td>{$form.savedMapping.html}<br />
           <span class="description">{ts}If you want to use a previously saved import field mapping - select it here.{/ts}</span>
         </td>
       </tr>
{/if} 
</div>
</table>
<div class="spacer"></div>

<div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
</div>
 <script>{literal}
cj(document).ready(function($){
  var showHideCreateContact = function(init){
    if($('#createContactMode\\\[createMembership\\\]:checked').length > 0){
      $("tr.create-new-contact").show('normal');
      $('.crm-member-import-uploadfile-from-block-contactType').show('normal');
      $('.dedupe-rule-group').show('normal');
    }else{
      $("tr.create-new-contact").hide('normal');
      $('.dedupe-rule-group').hide('normal');
      $('.crm-member-import-uploadfile-from-block-contactType').hide('normal');
    }
    if($('#createContactMode\\\[updateMembership\\\]:checked').length > 0){
      $('.crm-member-import-uploadfile-from-block-dataReferenceField').show('normal');
    }else{
      $('.crm-member-import-uploadfile-from-block-dataReferenceField').hide('normal');
    }
  }
  var showHideDedupeRule = function(){
    $("input[name=contactType]:checked").each(function(){
      var contactType = $(this).next('.elem-label').text();
      $("#dedupeRuleGroup option").each(function(){
        if ($(this).attr("value") > 0) {
          var re = new RegExp("^"+contactType,"g");
          if(!$(this).text().match(re)){
            $(this).hide();
          }
          else{
            $(this).show();
          }
        }
      });
      var $option = $("#dedupeRuleGroup option").filter(function(){
        if($(this).css('display') == 'none'){
          return false;
        }
        return true;
      });
      $("#dedupeRuleGroup").val($option.val());
    });
  }

  $(".crm-member-import-uploadfile-from-block-createContactMode input.form-checkbox").click(showHideCreateContact);
  $('.create-new-contact input[type=radio]').click(showHideCreateContact);
  $("input[name=contactType]").click(showHideDedupeRule);
  $("tr.create-new-contact label.crm-form-elem").css('display', 'block');
  $("tr.create-new-contact").find("br").remove();
  showHideCreateContact(true);
  showHideDedupeRule();
});
{/literal}</script>
