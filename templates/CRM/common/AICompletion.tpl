{* AICompletion files start *}
{* style files *}
{*
  The `<link rel="stylesheet">` tag placed inside the `<body>` is valid according to the specifications. For more information:

  - [HTML Standard 4.2.4 The link element](https://html.spec.whatwg.org/multipage/semantics.html#the-link-element) and search for "body-ok"
  - [Keywords that are body-ok affect whether link elements are allowed in the body](https://html.spec.whatwg.org/multipage/links.html#body-ok)

  Keywords that are "body-ok" determine whether link elements are allowed in the body. The "body-ok" keywords include dns-prefetch, modulepreload, pingback, preconnect, prefetch, preload, and stylesheet.

  However, both the `<link rel="stylesheet">` tag and `@import url` inside the `<body>` are not considered best practices. This is because if CSS is imported within the <body> tag, it may cause flickering or changes in styles during page rendering, which goes against the principle of separating concerns.

  Consider creating a smarty `{css}` similar to the way JavaScript is loaded, allowing CSS to be placed in the `<head>` section. This can help improve the overall performance and adhere to the best practices.

  refs #37730 46f
*}

<link rel="stylesheet" href="{$config->resourceBase}packages/AICompletion/AICompletion.css?v{$config->ver}">

{* script files *}
{js src=packages/AICompletion/AICompletion.js group=999 weight=998 library=civicrm/civicrm-js-aicompletion}{/js}
{* AICompletion files end *}

{* Added global js variable: AICompletion *}
{literal}
<script type="text/javascript">
window.AICompletion = {
  language: '{/literal}{$tsLocale}{literal}',
  translation: {
    'Copy': '{/literal}{ts}Copy{/ts}{literal}',
    'Save': '{/literal}{ts}Save{/ts}{literal}',
    'Saved': '{/literal}{ts}Saved{/ts}{literal}',
    'Title': '{/literal}{ts}Title{/ts}{literal}',
    'Submit': '{/literal}{ts}Submit{/ts}{literal}',
    'Try Again': '{/literal}{ts}Try Again{/ts}{literal}',
    'Role': '{/literal}{ts}Role{/ts}{literal}',
    'Tone Style': '{/literal}{ts}Tone Style{/ts}{literal}',
    'Content': '{/literal}{ts}Content{/ts}{literal}',
    'AI-generated Text Templates': '{/literal}{ts}AI-generated Text Templates{/ts}{literal}',
    'Saved Templates': '{/literal}{ts}Saved Templates{/ts}{literal}',
    'Community Recommendations': '{/literal}{ts}Community Recommendations{/ts}{literal}',
    'Save As New Template': '{/literal}{ts}Save As New Template{/ts}{literal}',
    'Recommend': '{/literal}{ts}Recommend{/ts}{literal}',
    'Warning! Applying this template will clear your current settings. Proceed with the application?': '{/literal}{ts}Warning! Applying this template will clear your current settings. Proceed with the application?{/ts}{literal}',
    'Remember to verify AI-generated text before using it.': '{/literal}{ts}Remember to verify AI-generated text before using it.{/ts}{literal}',
    'Save prompt as shared template': '{/literal}{ts}Save prompt as shared template{/ts}{literal}',
    'Once saved as a shared template, you can reuse this template for editing. Please enter a template title to identify the purpose of the template. If you need to edit a shared template, please go to the template management interface to edit.': '{/literal}{ts}Once saved as a shared template, you can reuse this template for editing. Please enter a template title to identify the purpose of the template. If you need to edit a shared template, please go to the template management interface to edit.{/ts}{literal}'
  }
};
</script>
{/literal}

{* AICompletion HTML start *}
<div class="netiaic-container">
  <div class="netiaic-inner">
    <div class="netiaic-content">
      <div class="inner">
        <div class="netiaic-chat">
          <div class="inner">
            <div id="ai-msg-welcome" class="ai-msg msg is-finished">
              <div class="msg-avatar"><i class="zmdi zmdi-mood"></i></div>
              <div class="msg-content">{ts}Hi, this is netiCRM copywriting helper, please be careful not to enter personal information or other confidential information.{/ts}</div>
            </div>
          </div>
        </div>
        <div class="netiaic-form-container">
          <div class="inner">
            <div class="netiaic-form-content">
              <ul class="netiaic-use-tpl">
                <li><a href="#" class="use-default-template">{ts}Use default template{/ts}</a></li>
                <li><a href="#" class="use-other-templates">{ts}Use other templates{/ts}</a></li>
              </ul>
              <div class="netiaic-prompt-role-section crm-section crm-select-section form-item">
                <div class="label"><label for="netiaic-prompt-role">{ts}Role{/ts}</label></div>
                <div class="edit-value content">
                  <div class="crm-form-elem crm-form-select">
                    <select id="netiaic-prompt-role" name="netiaic-prompt-role" class="netiaic-prompt-role-select form-select" data-placeholder="{ts}Please enter or select the role you want AI to represent (e.g., fundraiser).{/ts}"><option></option></select>
                  </div>
                </div>
              </div>
              <div class="netiaic-prompt-tone-section crm-section crm-select-section form-item">
                <div class="label"><label for="netiaic-prompt-tone">{ts}Tone Style{/ts}</label></div>
                <div class="edit-value content">
                  <div class="crm-form-elem crm-form-select">
                    <select id="netiaic-prompt-tone" name="netiaic-prompt-tone" class="netiaic-prompt-tone-select form-select" data-placeholder="{ts}Please enter or select the desired writing style (e.g., casual).{/ts}"><option></option></select>
                  </div>
                </div>
              </div>
              <div class="netiaic-prompt-content-section crm-section crm-textarea-section form-item">
                <div class="crm-form-elem crm-form-textarea">
                  <textarea name="netiaic-prompt-content" placeholder="{ts}Please enter the fundraising copy you would like AI to generate.{/ts}" class="netiaic-prompt-content-textarea form-textarea"></textarea>
                  <div class="netiaic-prompt-content-command netiaic-command">
                    <div class="inner">
                      <ul class="netiaic-command-list">
                        <li data-name="org_info" class="netiaic-command-item">
                          <a class="get-org-info" href="#">{ts}Click to insert organization intro.{/ts}</a>
                          <a href="#" target="_blank">({ts}Edit{/ts}<i class="zmdi zmdi-edit"></i>)</a> {* TODO: Need to change to correct URL *}
                          <div class="netiaic-command-item-desc"> {* TODO: smarty var *} </div>
                        </li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="netiaic-form-footer">
              <div class="netiaic-usage-info">
                {ts}Your usage limit is <span class="usage-max">{$maxUsage}</span> times, currently used <span class="usage-current">{$currentUsage}</span> times.{/ts}
              </div>
              <button type="button" class="shine-btn netiaic-form-submit">
                <i class="zmdi zmdi-mail-send"></i>
                <span class="text">{ts}Submit{/ts}</span>
                <span class="loader"></span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
{* AICompletion HTML end *}

{literal}
<script type="text/javascript">
(function ($) {
  $(function() {
    // TODO: timeout is workaround
    setTimeout(function() {
      $('.netiaic-container:not(.is-initialized)').AICompletion();
    }, 3000);
  });
})(cj);
</script>
{/literal}