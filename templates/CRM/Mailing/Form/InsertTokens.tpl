{*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.1                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2011                                |
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
<script type="text/javascript" src="{$config->resourceBase}packages/jquery/plugins/jquery.fieldselection.js"></script>
{literal}
<script type="text/javascript" >
var text_message = null;
var html_message = null;
var prefix = '';
var isPDF        = false;
var isMailing    = false;
var json_message = "body_json";

{/literal}

{if $form.formName eq 'MessageTemplates'}
    {literal}
    text_message = "msg_text";
    html_message = "msg_html";
    {/literal}
{elseif $form.formName eq 'SMS'}
    prefix = "SMS";
    text_message = "sms_text_message";
    isMailing = true;
{elseif $form.formName eq 'ThankYou'}
    html_message = "receipt_text"; // contribution thank you
    text_message = "sms_text"; // contribution thank you
    isMailing = false;
{elseif $form.formName eq 'Registration'}
    html_message = "confirm_email_text"; // event registration notification
    isMailing = false;
{else}
    {literal}
    text_message = "text_message";
    html_message = "html_message";
    isMailing    = true;
    {/literal}
{/if}

{if $form.formName eq 'PDF'}
    {literal}
    isPDF = true;
    {/literal}
{/if}

{if $templateSelected}
    {literal}
    if ( document.getElementsByName("saveTemplate")[0].checked ) {
        document.getElementById('template').selectedIndex = {/literal}{$templateSelected}{literal};  	
    }
    {/literal}
{/if}
{literal}

var editor = {/literal}"{$editor}"{literal};

function showSaveUpdateChkBox(prefix) {
  prefix = prefix || '';
  cj(document).ready(function($){
    var $update = $('input[id='+prefix+'updateTemplate]');
    var $save = $('input[id='+prefix+'saveTemplate]');
    var $saveName = $('#saveDetails');
    $saveName.hide();
    if ($update.is(":checked")) {
      $save.prop("checked", false);
    }
    if ($save.is(":checked")) {
      $update.prop("checked", false);
      $saveName.show();
    }

    // prevent check both checkboxes
    $update.on("click", function(){
      if ($(this).is(':checked')) {
        $save.prop("checked", false);
        $saveName.hide();
      }
    });
    $save.on("click", function(){
      if ($(this).is(':checked')) {
        $update.prop("checked", false);
        $saveName.show();
      }
    });
  });
}

function selectValue( val, prefix) {
    prefix = prefix || '';
    if (!val) {
      return;
    }
    cj("#loading").remove();
    var yesno = confirm("{/literal}{ts}Are your sure to use template to replace your work? You will lose any customizations you have made.{/ts}{literal}");
    if(!yesno) {
      document.getElementById('template').selectedIndex = '';  	
      return;
    }
    document.getElementsByName(prefix + "saveTemplate")[0].checked = false;
    document.getElementsByName(prefix + "updateTemplate")[0].checked = false;
    showSaveUpdateChkBox(prefix);
    if ( !val ) {
        if ( !isPDF ) {
            document.getElementById(text_message).value ="";
            if (prefix == 'SMS') {
                return;
            }
            document.getElementById("subject").value ="";
        }
        if ( editor == "ckeditor" ) {
            oEditor = CKEDITOR.instances[html_message];
            oEditor.setData('');
        } else if ( editor == "tinymce" ) {
            tinyMCE.getInstanceById(html_message).setContent( html_body );
        } else if ( editor == "joomlaeditor" ) { 
            document.getElementById(html_message).value = '' ;
            tinyMCE.execCommand('mceSetContent',false, '');               
        } else if ( editor =="drupalwysiwyg" ) {
            //doesn't work! WYSIWYG API doesn't support a clear or replace method       
        } else {	
            document.getElementById(html_message).value = '' ;
        }
        if ( isPDF ) {
            showBindFormatChkBox();
        }
        return;
    }

    var dataUrl = {/literal}"{crmURL p='civicrm/ajax/template' h=0 }"{literal};
    cj("#template").after('<i class="zmdi zmdi-spinner zmdi-hc-spin" id="loading"></i>');
    if (cj("#subject").length) {
      cj("#subject").attr("readonly", "readonly");
    }
    window.setTimeout(function(){
      cj.post( dataUrl, {tid: val}, function( data ) {
        cj("#subject").removeAttr("readonly");
        if ( !isPDF ) {
            cj("#subject").val( data.subject );

            if(prefix == 'SMS'){
                cj("#"+text_message).val(data.msg_text);
                return;
            }
            else {
              // do not load text message from template
              cj("#"+text_message).val("");

              // check if json object
              if (cj("#"+json_message).length > 0 && !data.msg_html && data.msg_text) {
                try {
                  data.msg_json = JSON.parse(data.msg_text);
                }
                catch (e) {
                  console.log(e);
                  data.msg_json = null;
                  return false;
                }
                cj("#"+json_message).val(data.msg_text);
                if (cj("input[name=upload_type][value=2]").length) {
                  cj("input[name=upload_type][value=2]").click();
                  cj("input[name=upload_type][value=2]").trigger('click');
                  window.nmEditorInstance.render();
                }
              }
              else {
                if (cj("input[name=upload_type][value=1]").length) {
                  cj("input[name=upload_type][value=1]").click();
                  cj("input[name=upload_type][value=1]").trigger('click');
                }
              }
            }
        }
        var html_body  = "";
        if (  data.msg_html ) {
            html_body = data.msg_html;
        }

        if ( editor == "ckeditor" ) {
            oEditor = CKEDITOR.instances[html_message];
            oEditor.setData( html_body );
        } else if ( editor == "tinymce" ) {
            tinyMCE.execInstanceCommand('html_message',"mceInsertContent",false, html_body );
        } else if ( editor == "joomlaeditor" ) { 
            cj("#"+ html_message).val( html_body );
            tinyMCE.execCommand('mceSetContent',false, html_body);           
        } else if ( editor =="drupalwysiwyg" ) {
            Drupal.wysiwyg.instances[html_message].insert(html_body);
        } else {	
            cj("#"+ html_message).val( html_body );
        }
        if ( isPDF ) {
            var bind = data.pdf_format_id ? true : false ;
            selectFormat( data.pdf_format_id, bind );
            if ( !bind ) {
                document.getElementById("bindFormat").style.display = "none";
            }
        }
        cj("#loading").remove();
      }, 'json');
    }, 1000);
}

if ( isMailing ) {
    document.getElementById(prefix + "editMessageDetails").style.display = "flex";

    function verify(select, prefix) {
        prefix = prefix || '';
        if (document.getElementsByName(prefix + "saveTemplate")[0].checked  == false) {
            document.getElementById(prefix + "saveDetails").style.display = "none";
        }

        var templateExists = true;
        if (document.getElementById(prefix + "template") == null) {
            templateExists = false;
        }

        document.getElementById(prefix + "saveTemplateName").disabled = false;
    }

    if (cj("#sms_text_message").length) {
        showSaveUpdateChkBox('SMS');
    }
    if (cj("#text_message").length) {
        showSaveUpdateChkBox();
    }


    {/literal}
    {if $editor eq "ckeditor"}
        {literal}
        cj( function() {
            oEditor = CKEDITOR.instances['html_message'];
            oEditor.BaseHref = '' ;
            oEditor.UserFilesPath = '' ; 
	    oEditor.on( 'focus', verify );
        });
        {/literal}
    {elseif $editor eq "tinymce"}
        {literal}
        cj( function( ) {
	if ( isMailing ) { 
 	  cj('div.html').hover( 
	  function( ) {
	     if ( tinyMCE.get(html_message) ) {
	     tinyMCE.get(html_message).onKeyUp.add(function() {
 	        verify( );
  	     });
	     }
          },
	  function( ) {
	     if ( tinyMCE.get(html_message) ) {
	       if ( tinyMCE.get(html_message).getContent() ) {
                 verify( );
               } 
	     }
          }
	  );
        }
        });
        {/literal}
    {elseif $editor eq "drupalwysiwyg"}
      {literal}
      cj( function( ) {
        if ( isMailing ) { 
          cj('div.html').hover(
            verify,
            verify
          );  
        }
     });
     {/literal}
     {/if}
    {literal}
 }

    function tokenReplText ( element )
    {
        var token     = cj("#"+element.id).val( )[0];

        {/literal}
        {if $form.formName eq 'SMS'}
        {literal}

            cj( "#"+ text_message ).replaceSelection( token ); 

            if ( isMailing ) { 
                verify('', 'SMS');
            }
        {/literal}
        {elseif $form.formName eq 'ThankYou'}
        {literal}
            cj( "#"+ text_message ).replaceSelection( token ); 
        {/literal}
        {else}
        {literal}

            if ( element.id == 'token3' ) {
            ( isMailing ) ? text_message = "subject" : text_message = "msg_subject"; 
            }else {
            ( isMailing ) ? text_message = "text_message" : text_message = "msg_text";
            }
            cj( "#"+ text_message ).replaceSelection( token ); 

            if ( isMailing ) { 
                verify();
            }

        {/literal}
        {/if}
        {literal}
    }

    function tokenReplHtml ( )
    {
        var token2     = cj("#token2").val( )[0];
        var editor     = {/literal}"{$editor}"{literal};
        if ( editor == "tinymce" ) {
            tinyMCE.execInstanceCommand('html_message',"mceInsertContent",false, token2 );
        } else if ( editor == "joomlaeditor" ) { 
            tinyMCE.execCommand('mceInsertContent',false, token2);
            var msg       = document.getElementById(html_message).value;
            var cursorlen = document.getElementById(html_message).selectionStart;
            var textlen   = msg.length;
            document.getElementById(html_message).value = msg.substring(0, cursorlen) + token2 + msg.substring(cursorlen, textlen);
            var cursorPos = (cursorlen + token2.length);
            document.getElementById(html_message).selectionStart = cursorPos;
            document.getElementById(html_message).selectionEnd   = cursorPos;
            document.getElementById(html_message).focus();            
        } else if ( editor == "ckeditor" ) {
            oEditor = CKEDITOR.instances[html_message];
            oEditor.insertHtml(token2.toString() );
        } else {
            cj( "#"+ html_message ).replaceSelection( token2 );
        }

        if ( isMailing ) { 
             verify();
        }
    }

    cj(function() {
        cj('.accordion .head').addClass( "ui-accordion-header ui-helper-reset ui-state-default ui-corner-all ");
        cj('.resizable-textarea textarea').css( 'width', '99%' );
        cj('.grippie').css( 'margin-right', '3px');
        cj('.accordion .head').hover( function() { cj(this).addClass( "ui-state-hover");
        }, function() { cj(this).removeClass( "ui-state-hover");
    }).bind('click', function() { 
        var checkClass = cj(this).find('span').attr( 'class' );
        var len        = checkClass.length;
        if ( checkClass.substring( len - 1, len ) == 's' ) {
            cj(this).find('span').removeClass().addClass('ui-icon ui-icon-triangle-1-e');
            cj("span#help"+cj(this).find('span').attr('id')).hide();
        } else {
            cj(this).find('span').removeClass().addClass('ui-icon ui-icon-triangle-1-s');
            cj("span#help"+cj(this).find('span').attr('id')).show();
        }
        cj(this).next().toggle(); return false; }).next().hide();
        cj('span#html').removeClass().addClass('ui-icon ui-icon-triangle-1-s');
        cj("div.html").show();
       
        if ( !isMailing ) {
           cj("div.text").show();
        }   
    });

    {/literal}{include file="CRM/common/Filter.tpl"}{literal}
    function showToken(element, id ) {
	initFilter(id);
	cj("#token"+id).css({"width":"290px", "size":"8"});
	var tokenTitle = {/literal}'{ts}Select Token{/ts}'{literal};
        cj("#token"+element ).show( ).dialog({
            title       : tokenTitle,
            modal       : true,
            width       : '310px',
            resizable   : false,
            bgiframe    : false,
            position    : { my: "right center", at: "right center", of: window},
            overlay     : { opacity: 0.9, background: "black" },
            beforeclose : function(event, ui) { cj(this).dialog("destroy"); },
            buttons     : { 
                {/literal}"{ts}Done{/ts}"{literal}: function() { 
                    cj(this).dialog("close");
                        //focus on editor/textarea after token selection     
                        if (element == 'Text') {
                            cj('#' + text_message).focus();
                        } else if (element == 'Html' ) {
                            switch ({/literal}"{$editor}"{literal}) {
                                case 'ckeditor': { oEditor = CKEDITOR.instances[html_message]; oEditor.focus(); break;}
                                case 'tinymce'  : { tinyMCE.get(html_message).focus(); break; } 
                                case 'joomlaeditor' : { tinyMCE.get(html_message).focus(); break; } 
                                default         : { cj("#"+ html_message).focus(); break; } 
                        }
                    } else if (element == 'Subject') {
                           var subject = null;
                           ( isMailing ) ? subject = "subject" : subject = "msg_subject";
                           cj('#'+subject).focus();       
                    }
                }
            }
        });
        return false;
    }

    cj(function() {
        if ( !cj().find('div.crm-error').text() ) {
          cj(window).load(function () {           
            setSignature();
          });
        }

        cj("#fromEmailAddress").change( function( ) {
            setSignature( );
        });
    });
    function setSignature( ) {
        var emailID = cj("#fromEmailAddress").val( );
        if ( !isNaN( emailID ) ) {
            var dataUrl = {/literal}"{crmURL p='civicrm/ajax/signature' h=0 }"{literal};
            cj.post( dataUrl, {emailID: emailID}, function( data ) {
                var editor     = {/literal}"{$editor}"{literal};
                
                if ( data.signature_text ) {
                    // get existing text & html and append signatue
                    var textMessage =  cj("#"+ text_message).val( ) + '\n\n--\n' + data.signature_text;

                    // append signature
                    cj("#"+ text_message).val( textMessage ); 
                }
                
                if ( data.signature_html ) {
                    var htmlMessage =  cj("#"+ html_message).val( ) + '<br/><br/>--<br/>' + data.signature_html;
                    
                    // set wysiwg editor
                    if ( editor == "ckeditor" ) {
                        oEditor = CKEDITOR.instances[html_message];
                        var htmlMessage = oEditor.getData( ) + '<br/><br/>--' + data.signature_html;
                        oEditor.setData( htmlMessage  );
                    } else if ( editor == "tinymce" ) {
                        tinyMCE.execInstanceCommand('html_message',"mceInsertContent",false, htmlMessage );
                    }  else if ( editor == "drupalwysiwyg" ) {
                        Drupal.wysiwyg.instances[html_message].insert(htmlMessage);
                    } else {	
                        cj("#"+ html_message).val( htmlMessage );
                    }
                }

            }, 'json'); 
        } 
    }
</script>
{/literal}
