
<div class="crm-form-block">
<table class="form-layout">
  {if $form.batch_prefix}
  <tr class="crm-coupon-form-batch_prefix">
    <td class="label">{$form.batch_prefix.label}</td>
    <td>
      {$form.batch_prefix.html} - RANDOMCODE 
      <div class="description">{ts}Name can only consist of alpha-numeric characters{/ts}</div>
    </td>
  </tr>
  {/if}
  {if $form.num_generate}
  <tr class="crm-coupon-form-num_generate">
    <td class="label">{$form.num_generate.label}</td>
    <td>
      {$form.num_generate.html}
    </td>
  </tr>
  {/if}
  {if $form.code}
  <tr class="crm-coupon-form-code">
    <td class="label">{$form.code.label}</td>
    <td>
      {$form.code.html}
      {if $action == 1}
        <span class="button" id="generate-random"><i class="zmdi zmdi-grain"></i>{ts}Generate Random Code{/ts}</span>
      {else if $action == 2}
        <span class="button" id="change"><i class="zmdi zmdi-edit"></i>{ts}Change{/ts}</span>
      {/if}
      <div class="description">{ts}Name can only consist of alpha-numeric characters{/ts}</div>
    </td>
  </tr>
  {/if}
  <tr class="crm-coupon-form-description">
    <td class="label">{$form.description.label}</td>
    <td>
      {$form.description.html}
      <div class="description">{ts}This short description will appear when user apply this coupon on order.{/ts}</div>
    </td>
  </tr>
  <tr class="crm-coupon-form-block-start_date">
    <td class="label">{$form.start_date.label}</td>
    <td>
      {include file="CRM/common/jcalendar.tpl" elementName=start_date}
    </td>
  </tr>
  <tr class="crm-coupon-form-block-end_date">
    <td class="label">{$form.end_date.label}</td>
    <td>
      {include file="CRM/common/jcalendar.tpl" elementName=end_date}
    </td>
  </tr>
  <tr class="crm-coupon-form-block-civicrm_event">
    <td class="label">{$form.civicrm_event.label}</td>
    <td>{$form.civicrm_event.html}</td>
  </tr>
  <tr class="crm-coupon-form-block-civicrm_price_field_value">
    <td class="label">{$form.civicrm_price_field_value.label}</td>
    <td>{$form.civicrm_price_field_value.html}</td>
  </tr>
  <tr class="crm-coupon-form-block-coupon_type">
    <td class="label">{$form.coupon_type.label}</td>
    <td>{$form.coupon_type.html}</td>
  </tr>
  <tr class="crm-coupon-form-block-discount">
    <td class="label">{$form.discount.label}</td>
    <td>{$form.discount.html}</td>
  </tr>
  <tr class="crm-coupon-form-block-minimal_amount">
    <td class="label">{$form.minimal_amount.label}</td>
    <td>{$form.minimal_amount.html}</td>
  </tr>
  <tr class="crm-coupon-form-block-count_max">
    <td class="label">{$form.count_max.label}</td>
    <td>{$form.count_max.html}</td>
  </tr>
  <tr class="crm-coupon-form-block-is_active">
    <td class="label">{$form.is_active.label}</td>
    <td>{$form.is_active.html}</td>
  </tr>
</table>
<div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
  {include file="CRM/common/chosen.tpl" selector="select#civicrm_event,select#civicrm_price_field_value"}
  <script>{literal}
  cj(document).ready(function($){
    var $batch = $("input[data=batch-create]");
    if ($batch.length) {
      $batch.click(function(e){
        e.preventDefault();
        var form = $(this).closest('form')[0];
        if (form.checkValidity()){
          var procceed = window.confirm("{/literal}{ts}You can't bulk update these coupon after create. Are you sure to procceed?{/ts}{literal}");
          if (procceed) {
            form.submit(); 
          }
        } 
        else {
          form.reportValidity();
        }
      });
    }
    
    var $code = $("input[name=code]");
    $("#generate-random").click(function(){
      var random = Math.random().toString(36).substring(2, 10);
      $code.val(random);
    });
    $("#change").click(function(){
      if ($code.attr('readonly')) {
        var change = window.confirm("{/literal}{ts}Change this field will affect current uses of this coupon.{/ts} {ts}Are you sure you want to continue?{/ts}{literal}");
        if (change) {
          $code.removeAttr("readonly");
        }
      }
    });

    function updateDiscountFeeLabel(){
      var requiredHtml = $('[for="discount"] span').get(0).outerHTML;
      console.log($('#coupon_type').val());
      if($('#coupon_type').val() == 'percentage'){
        $('[for="discount"]').html("{/literal}{ts}Discounted Percentage{/ts}{literal}"+requiredHtml);
      }
      if($('#coupon_type').val() == 'monetary'){
        $('[for="discount"]').html("{/literal}{ts}Discounted Fees{/ts}{literal}"+requiredHtml);
      }
    }

    $('#coupon_type').change(updateDiscountFeeLabel);
    updateDiscountFeeLabel();

  });
  {/literal}</script>
</div>
