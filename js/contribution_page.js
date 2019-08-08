(function($){

  'use strict';

  var ts = window.ContribPageParams.ts;

  $(document).one('ready', function () {

    window.ContribPage = {
      // isCreditCardOnly : window.ContribPageParams.creditCardOnly,
      currentContribType : "recurring", // "recurring", "single"
      currentContribInstrument : "creditCard", // "creditCard", "other"
      currentPage : $('#crm-container>form').attr('id'), // "Main", "Confirm", "ThankYou"
      currentPageState : "loading", // "loading", "success"
      currentPriceOption : '',
      currentPriceAmount : 0,
      currentFormStep : 1,
      defaultPriceOption : {},
      singleContribMsgText : false,
      executingAnimationCount : 0,
      complete : 0,

      preparePage: function(){
        if (window.ContribPageParams.mobileBackgroundImageUrl) {
          document.querySelector('body').style.setProperty('--mobile-background-url', 'url('+window.ContribPageParams.mobileBackgroundImageUrl+')');
        }

        var $content = $('#main');
        $content.prepend($('#intro_text').prepend($('h1.page-title')));
        $('.sharethis').appendTo('body');

        if(this.currentPage == 'Main'){

          this.setDefaultValues();

          this.checkUrlParamsAction();

          this.prepareStepInfo();

          this.prepareRecurBtnMsg();

          this.prepareForm();

          this.preparePriceSetBlock();

          this.setDefaultPriceOption();

          this.prepareContribTypeForm();

        }
        if(this.currentPage == 'Confirm'){
          this.prepareStepInfo();
          this.updateFormStep(1);
        }

        if(this.currentPage == 'ThankYou'){
          $('#intro_text>*').each(function(){
            if(!$(this).is('.page-title')){
              $(this).remove();
            }
          });
          $('.page-title').after($('#thankyou_text'));

          if(ContribPageParams.thankyouTitle){
            $('.page-title').text(ContribPageParams.thankyouTitle);
            document.title = document.title.replace(ts["Payment failed."], ContribPageParams.thankyouTitle);
          }
          if($(window).width() <= 768) {
            if ($('.messages.error').length) {
              $('#intro_text').hide();
            }
          }
        }
      },

      updateExpenditureSection: function(){
        if($('#expenditure-ratio-box').is(':visible')){
          $('#intro_text').fadeIn('slow');
          $('#expenditure-ratio-box').fadeOut('slow');
        }else{
          $('#intro_text').fadeOut('slow');
          $('#expenditure-ratio-box').fadeIn('slow');
        }
      },

      setDefaultValues: function(){
        var defaultPriceOption = window.ContribPage.defaultPriceOption;
        $('[data-default="1"][data-grouping]').each(
          function(i, ele){
            var contribType = ele.dataset.grouping;
            var regExp = /NT\$ ([\d,]+)/;
            var label = $(ele).next().text();
            if(regExp.test(label)){
              if(contribType == 'recurring'){
                defaultPriceOption['recurring'] = $(ele).val();
              }else if(contribType == 'non-recurring'){
                defaultPriceOption['non-recurring'] = $(ele).val();
              }else if(contribType == ''){
                defaultPriceOption['recurring'] = $(ele).val();
                defaultPriceOption['non-recurring'] = $(ele).val();
              }
            }
          }
        );

        if($('[name="is_recur"]:checked').val() == 1){
          this.currentContribType = 'recurring';
          if($('#installments').val()){
            this.installments = $('#installments').val();
          }
        }else{
          this.currentContribType = 'non-recurring';
        }


        if($('[name="amount"]:checked').length > 0){
          if($('[name="amount"]:checked').val() == 'amount_other_radio'){
            this.currentPriceAmount = $('#amount_other').val();
          }else{
            this.currentPriceOption = $('[name="amount"]:checked').val();
            var reg = new RegExp(/^NT\$ ([\d\,]+)/);
            var option_label = $('[name="amount"]:checked').parent().text();
            if(reg.test(option_label)){
              this.currentPriceAmount = reg.exec(option_label)[1];
            }
          }
          var reg_id = new RegExp(/[\?&]?id=/);
          if(!reg_id.test(location.search)){
            this.currentFormStep = 2;
          }
        }
        else if ($('#amount_other').val()) {
          this.currentPriceAmount = $('#amount_other').val();
        }

        if($('#footer_text').length){
          this.singleContribMsgText = $('#footer_text').html();
        }
      },

      prepareStepInfo: function(){
        var $stepInfo = $('<div class="custom-step-info"></div>');
        $stepInfo.append('<span class="step-text step-text-1">'+ts['Amount Step']+'</span>');
        $stepInfo.append('<span class="step-triangle">▶</span>');
        $stepInfo.append('<span class="step-text step-text-2 step-text-3 step-text-4">'+ts['Profile Step']+'</span>');
        $stepInfo.append('<span class="step-triangle">▶</span>');
        $stepInfo.append('<span class="step-text step-text-5">'+ts['Confirm Step']+'</span>');
        $stepInfo.append('<span class="step-triangle">▶</span>');
        $stepInfo.append('<span class="step-text step-text-6">'+ts['Payment Step']+'</span>');
        $stepInfo.insertBefore('#content');
      },

      prepareRecurBtnMsg: function(){
        var $msgBox = ContribPage.$msgBox = $('<div class="error-msg-bg"><div class="error-msg">'+this.singleContribMsgText+'</div></div>');
        var $singleBtn = this.createGreyBtn(ts['I want contribute once.']);
        $singleBtn.find('a').click(function(event){
          $msgBox.animate({opacity: 0},500,function(){
            $msgBox.hide();
            $msgBox.css('opacity', 1);
            ContribPage.setContributeType('non-recurring');
          });
          event.preventDefault();
        });
        var $recurBtn = this.createBlueBtn(ts['I want recurring contribution.']);
        $recurBtn.find('a').click(function(event){
          ContribPage.setContributeType('recurring');
          ContribPage.quitMsgBox();
          event.preventDefault();
        });
        $msgBox.find('a').click(function(event){
          if(event.originalEvent.target.classList.contains("error-msg-bg")){
            ContribPage.quitMsgBox();
          }
        });
        $msgBox.appendTo($('body')).find('.error-msg').append($singleBtn).append($recurBtn);
        $msgBox.hide();
      },

      quitMsgBox: function(){

        var $msgBox = ContribPage.$msgBox;
        $msgBox.animate({opacity: 0},500,function(){
          $msgBox.hide();
          $msgBox.css('opacity', 1);
        });
      },

      createBlackBtn:function(text){
        return $('<span><a class="button">'+text+'</a></span>');
      },

      createGreyBtn: function(text){
        return $('<span class="crm-button-type-cancel"><a class="button">'+text+'</a></span>');
      },

      createBlueBtn: function(text){
        return $('<span class="crm-button-type-upload"><a class="button">'+text+'</a></span>');
      },

      createBtn: function(text, className){
        return $('<div class="custom-normal-btn '+className+'">'+text+'</div>');
      },

      prepareForm: function() {
        
        var dom_step = '';
        for (var i = 1; i <= 3; i++) {
          dom_step += '<div class="crm-container crm-container-md contrib-step contrib-step-'+i+'"></div>';
        }

        $(dom_step).insertBefore('.crm-contribution-main-form-block');

        if ($('[name=cms_create_account]').length >= 1) {
          var $cms_create_account = $('[name=cms_create_account]').parent();
          var $crm_user_signup = $('.crm_user_signup-section');
        }
        $('.contrib-step-1')
          .append($('.progress-block'))
          .append($cms_create_account)
          .append($crm_user_signup)
          .append($('.payment_options-group'))
          .append('<div class="custom-price-set-section">')
          .append($('.payment_processor-section'))
          .append($('#billing-payment-block'))
          .append(this.createStepBtnBlock(['next-step']));
        $('.contrib-step-1 .step-action-wrapper').addClass('hide-as-show-all');
        var exec_step = 2;
        if($('.custom_pre_profile-group fieldset').length >= 1){
          $('.contrib-step-'+exec_step)
            .append(this.createStepBtnBlock(['last-step', 'priceInfo']).addClass('crm-section'))
            .append($('.custom_pre_profile-group'))
            .append(this.createStepBtnBlock(['last-step', 'next-step']).addClass('hide-as-show-all'));
          exec_step += 1;
        }
        if($('.custom_post_profile-group fieldset').length >= 1){
          $('.contrib-step-'+exec_step)
            .append(this.createStepBtnBlock(['last-step', 'priceInfo']).addClass('crm-section').addClass('hide-as-show-all'))
            .append($('.custom_post_profile-group'))
            .append(this.createStepBtnBlock(['last-step', 'next-step']).addClass('hide-as-show-all'));
          exec_step += 1;
        }
        if($('.premiums-group').length && $('.custom_post_profile-group fieldset').length){
          $('.custom_post_profile-group').after($('.premiums-group'));
        }else if($('.premiums-group').length && $('.custom_pre_profile-group fieldset').length){
          $('.custom_pre_profile-group').after($('.premiums-group'));
        }
        exec_step -= 1;
        $('.contrib-step-'+exec_step).find('.step-action-wrapper').has('.next-step').remove();
        $('.contrib-step-'+exec_step)
          .append(this.createStepBtnBlock(['last-step']).addClass('hide-as-show-all').addClass('crm-section'))
          .append($('.crm-submit-buttons'));
        $('.crm-contribution-main-form-block').hide();

        if($("#billing-payment-block").length == 0){
          $('.crm-section payment_processor-section').insertBefore($('.custom_pre_profile-group'));
        }

        if ($(".is_for_organization-section").length > 0) {
          $(".is_for_organization-section, #for_organization").insertBefore('.custom_pre_profile-group');
        }

        /** Afraid it ban the contributor
        if(this.isCreditCardOnly){
          $('.payment_processor-section, #billing-payment-block').hide();
        }
        */

        $('#crm-container>form').submit(function(){
          if($('label.error').length){
            ContribPage.updateShowAllStep();
          }
        });
        $("")
        
        this.updateFormStep();
        
      },

      createStepBtnBlock: function(objs){
        var $step_block = $('<div class="step-action-wrapper">');
        objs.forEach(function(obj_name){
          if(obj_name == 'last-step'){
            $step_block.append(ContribPage.createGreyBtn(ts['<< Previous']).addClass(obj_name).click(function(event){
              ContribPage.setFormStep(ContribPage.currentFormStep - 1);
              event.preventDefault();
            }));  
          }
          if(obj_name == 'next-step'){
            $step_block.append(ContribPage.createBlueBtn(ts['Next >>']).addClass(obj_name).click(function(event){
              ContribPage.setFormStep(ContribPage.currentFormStep + 1);
              event.preventDefault();
            }));
          }
          if(obj_name == 'priceInfo'){
            $step_block.append('<div class="price-selected-info priceInfo"><div class="info-is-recur"></div><div class="info-price">NTD&nbsp;<span class="info-price-amount"></span></div></div>');
          }
        });
        return $step_block;
      },

      prepareContribTypeForm: function(){
        $('.priceSet-block').before($('<div class="contrib-type-block custom-block"><label>'+ts['Single or Recurring Contribution']+'</label><div class="contrib-type-btn"></div></div><div class="instrument-info-panel custom-block"></div>'));
        if($('[name=is_recur][value=1]').length > 0){
          var $recurBtn = this.createBtn(ts["Recurring contributions"],"custom-recur-btn");
          $recurBtn.click(function(){
            ContribPage.setContributeType('recurring');
          });
          $('.contrib-type-btn').append($recurBtn);
        }
        if($('[name=is_recur]').length==0 || $('[name=is_recur][value=0]').length > 0){
          var $singleBtn = this.createBtn(ts["Single Contribution"],"custom-single-btn");
          $singleBtn.click(function(){
            if(ContribPage.singleContribMsgText){
              ContribPage.$msgBox.show();
            }else{
              ContribPage.setContributeType('non-recurring');
            }
          });
          $('.contrib-type-btn').append($singleBtn);
        }
        this.updateContributeType(false);
      },

      preparePriceSetBlock: function(){
        $('<div class="priceSet-block custom-block"><label>'+ts['Choose Amount Option or Custom Amount']+'</label><div class="price-set-btn"></div></div>').appendTo($('.custom-price-set-section'));
        if($('#amount_other').length){
          var other_amount = '';
          if(!this.currentPriceOption){
            other_amount = this.currentPriceAmount;
          }
          var $other_amount_block = $('<div class="custom-other-amount-block custom-input-block"><label for="custom-other-amount">'+ts['Other Amount']+'</label><input placeholder="'+ts['Type here']+'" name="custom-other-amount" id="custom-other-amount" type="number" min="0" class="custom-input" value="'+other_amount+'"></input></div>');
          var doClickOtherAmount = function(){
            var reg = new RegExp(/^$|^\d+$/);
            var amount = $(this).val();
            if(reg.test(amount) && parseInt(amount) > 0){
              ContribPage.setPriceOption();
              ContribPage.setPriceAmount(amount);
            }
            else if(amount != ''){
              $(this).val(0);
            }
          };
          $other_amount_block.find('input').keyup(doClickOtherAmount).click(doClickOtherAmount);
          $other_amount_block.find('input').blur(function(){
            var amount = $(this).val();
            var defaultOption = ContribPage.defaultPriceOption[ContribPage.currentContribType];
            if((amount == '' && defaultOption) || amount == 0){
              ContribPage.setPriceOption(defaultOption);
            }
          });
          $('.priceSet-block').append($other_amount_block);
        }

        if($('[name=is_recur][value=1]').length > 0){
          var installments = this.installments;
          var $installments_block = $('<div class="custom-installments-block custom-input-block"><label for="custom-installments">'+ts['monthly']+ts['Installments']+'</label><input placeholder="'+ts["no limit"]+'" name="custom-installments" id="custom-installments" type="number" class="custom-input active" min="0" value="'+installments+'"></input></div>');
          var doClickInstallments = function(){
            var installments = $(this).val();
            if(installments == 0){
              $(this).val("");
            }
            ContribPage.setInstallments(installments);
          };
          $installments_block.find('input').keyup(doClickInstallments).click(doClickInstallments);
          $('.priceSet-block').append($installments_block);
        }


        this.updatePriceSetOption();
      },

      checkUrlParamsAction: function() {
        var paramString = window.location.search.substring(1);
        var params = [];
        paramString.split("&").forEach(function(keyValue) {
          var keyValueArray = keyValue.split("=");
          var key = decodeURIComponent(keyValueArray[0]);
          var value = decodeURIComponent(keyValueArray[1]);
          params[key] = value;
        });

        if (params['_ppid'] && params['_ppid'] == $('.payment_processor-section input:checked').val() && 
          params['_grouping'] && params['_grouping'] == this.currentContribType && 
          params['_amt'] && params['_amt'] == this.currentPriceAmount && 
          params['_instrument'] ) {
          window.ContribPage.currentFormStep = 2;
          cj(document).ajaxComplete(function( event, xhr, settings ) {
            if(settings.url.substring(0,38) == '/civicrm/contribute/transact?snippet=4' && 
              cj(xhr.responseText).find('input[id^=civicrm-instrument-dummy]:checked').length) {
              // setTimeout(function(){
              // }, 1000);
              xhr.complete(function(){
                var interval = setInterval(function(){
                  if(cj('input[id^=civicrm-instrument-dummy]:checked').length && window.ContribPage.complete){
                    if(cj('input[id^=civicrm-instrument-dummy]:checked').val() != params['_instrument']){
                      window.ContribPage.setFormStep(1);
                      clearInterval(interval);
                    }
                  }
                }, 100);
              })
              cj(event.currentTarget).unbind('ajaxComplete');
            }
          });

          /*
          var promSkipStep = new Promise(function(resolve, reject) {
            cj(document).ajaxComplete(function(event, xhr, options) {
              var url = options.url;
              if(url.indexOf('civicrm/contribute/transact') != -1 && 
                url.indexOf('type='+ppid) != -1 && 
                url.indexOf('snippet=4') != -1 &&
                xhr.readyState == 4) {
                resolve();
              }
            });
          }).then(function(){
            // window.ContribPage.setFormStep(2);
          });
          */
        }

      },

      updatePriceSetOption: function(){
        $('.price-set-btn').html("");
        var reg = new RegExp(/^NT\$ ([\d\,]+) ?(.*)$/);
        var grouping_text = this.currentContribType;
        $('.amount-section label.crm-form-radio').each(function(ele){
          var $this = $(this);
          var this_grouping = $this.find('input').data('grouping');
          if(this_grouping == grouping_text || this_grouping == ''){
            var text = $(this).find('.elem-label').text();
            if(reg.test(text)){
              var reg_result = reg.exec(text);
              var amount = reg_result[1];
              var val = $this.find('input').val();
              var words = reg_result[2];
              if(words.length > 6){
                var multitext_class = ' multitext';
              }else{
                var multitext_class = '';
              }
              var $option = $('<div data-amount="'+val+'"><span class="amount">'+amount+'</span><span class="description'+multitext_class+'">'+words+'</span></div>');
              $option.click(function(){
                ContribPage.setPriceOption($(this).data('amount'));
                // ContribPage.setFormStep(2);
              });
              $('.price-set-btn').append($option);
            }
          }
        });
        this.updatePriceOption();
        this.updatePriceAmount();
      },

      setPriceOption: function(val){
        this.currentPriceOption = val;
        if(this.currentPriceOption){
          $('.amount-section [value="'+this.currentPriceOption+'"]').click();
          var amount = $('.price-set-btn div[data-amount='+this.currentPriceOption+'] .amount').text();
          this.setPriceAmount(amount);
        }else{
          $('.amount-section .crm-form-radio:last-child input').click();
        }
        this.updatePriceOption();
      },

      updatePriceOption: function(){
        $('.price-set-btn div').removeClass('active');
        if(this.currentPriceOption){
          $('.price-set-btn div[data-amount='+this.currentPriceOption+']').addClass('active');  
        }
      },

      setPriceAmount: function(amount){
        if(this.currentPriceAmount != amount){
          this.currentPriceAmount = amount;
          if(!this.currentPriceOption){
            $('input#amount_other').val(this.currentPriceAmount);
          }
          this.updatePriceAmount();
        }
      },

      updatePriceAmount: function(){
        $('.info-price-amount').text(this.currentPriceAmount);
        if(this.currentPriceAmount && !this.currentPriceOption){
          $('input#custom-other-amount').addClass('active');
        }else{
          $('input#custom-other-amount').val('').removeClass('active');
        }
      },

      /**
       * WHEN setContributeType DO setContribInstrument 
       * @param {[type]} type [description]
       */
      setContributeType: function(type) {
        if( this.currentContribType != type ){
          this.currentContribType = type;
          if(!this.currentContribType){
            return;
          }
          if(this.currentContribType == 'non-recurring'){
            $('[name=is_recur][value=0]').click();
          }
          if(this.currentContribType == 'recurring'){
            $('[name=is_recur][value=1]').click();
          }

          this.updateContributeType(true);
          this.setDefaultPriceOption();
        }
      },

      setDefaultPriceOption: function(){
        if(typeof this.currentPriceAmount == 'string'){
          var amount = this.currentPriceAmount.replace(',','');
        }else{
          var amount = this.currentPriceAmount;
        }
        var grouping_text = this.currentContribType;
        $('.amount-section .content .crm-form-radio').each(function(){
          var $this = $(this);
          var text = $this.find('.elem-label').text().replace(',','');
          var this_grouping = $this.find('input').attr('data-grouping');
          if(text.match(' '+amount+' ') && (this_grouping == grouping_text || this_grouping == '')){
            ContribPage.setPriceOption($this.find('input').val());
          }
        })
      },

      updateContributeType: function(isSelectDefaultOption) {
        if(this.currentContribType == 'non-recurring'){
          $('.contrib-type-btn div').removeClass('selected');
          $('.custom-single-btn').addClass('selected');
          $('.custom-installments-block').hide();
        }
        if(this.currentContribType == 'recurring'){
          $('.contrib-type-btn div').removeClass('selected');
          $('.custom-recur-btn').addClass('selected');
          $('.custom-installments-block').show();
        }
        this.updateContribInfoLabel();
        this.updatePriceSetOption();

        if(isSelectDefaultOption && this.defaultPriceOption[this.currentContribType]){
          this.setPriceOption(this.defaultPriceOption[this.currentContribType]);
        }
      },

      updateContribInfoLabel: function(){
        if(this.currentContribType == 'non-recurring'){
          $('.info-is-recur').text(ts['Single Contribution']);
        }
        if(this.currentContribType == 'recurring'){
          if(!this.installments){
            $('.info-is-recur').text(ts['Every-Month Recurring Contribution']);
          }else{
            $('.info-is-recur').text(this.installments+ts['Installments Recurring Contribution']);
          }
        }
      },

      setFormStep: function(step) {
        if(this.currentFormStep == 1 && step == 2){
          // Check instrument is credit card
          var error_msg = [];
          if(this.currentContribType == 'recurring' && $('#civicrm-instrument-dummy-1:checked').length == 0){
            error_msg.push('You cannot set up a recurring contribution if you are not paying online by credit card.');
          }
          if(window.ContribPageParams.minAmount && !this.currentPriceOption && this.currentPriceAmount < parseInt(window.ContribPageParams.minAmount)){
            error_msg.push('Contribution amount must be at least %1');
            }
          if(window.ContribPageParams.maxAmount && !this.currentPriceOption && this.currentPriceAmount > parseInt(window.ContribPageParams.maxAmount)){
            error_msg.push('Contribution amount cannot be more than %1.');
          }

          if(error_msg.length){
            error_msg.forEach(function(term){
              $('.contrib-step-1 .step-action-wrapper').before($('<label generated="true" class="error" style="color: rgb(238, 85, 85); padding-left: 10px;">'+ts[term]+'</label>'))
            });
            setTimeout(function(){
              $('.contrib-step-1 .error').remove();
            }, 5000);
            return;
          }
        }

        $('.hide-as-show-all').show();
        if(this.currentFormStep != step){
          this.currentFormStep = step;
          this.updateFormStep(1);
        }
      },

      updateFormStep: function(isScrollAnimate) {
        var currentStepClassName = 'contrib-step-'+this.currentFormStep;
        $('[class*=contrib-step-]').each(function(){
          var $this = $(this);
          /**
          class name use type:
            * type-is-back
            * type-is-front
          */
          if($this.hasClass('type-is-front') && !$this.hasClass(currentStepClassName)){
            /** first scroll to top 0.5 second */
            window.ContribPage.executingAnimationCount++;
            setTimeout(function(){
              $this.removeClass('type-is-front').addClass('type-is-fade-out').css({'opacity': 1});
              /** then fade change */
              $this.animate({'opacity': 0} ,500, function(){
                window.ContribPage.executingAnimationCount--;
                $this.removeClass('type-is-fade-out').addClass('type-is-back');
              });
            }, 500);
          }
          else if($this.hasClass(currentStepClassName)){
            /** first scroll to top 0.5 second */
            window.ContribPage.executingAnimationCount++;
            setTimeout(function(){
              $this.removeClass('type-is-back').addClass('type-is-fade-in').css({'opacity': 0});
              /** then fade change */
              $this.animate({'opacity': 1} ,500,  function(){
                window.ContribPage.executingAnimationCount--;
                $this.removeClass('type-is-fade-in').addClass('type-is-front');
              });
            }, 500);
          }
          else if(!$this.hasClass('type-is-back')){
            $this.addClass('type-is-back');
          }
        });

        if (isScrollAnimate) {
          var topPosition = $('#content-main').offset().top - 30;
          $('html,body').animate({ scrollTop: topPosition }, 500);
        }

        $('.step-text').removeClass('active');
        if(this.currentPage == 'Main'){
          $('.step-text-' + this.currentFormStep).addClass('active');
          if($(window).width() <= 480){
            $('.custom-step-info').scrollLeft($('.custom-step-info span.active').offset().left-$('.custom-step-info span').offset().left);
          }
        }else if(this.currentPage == 'Confirm'){
          $('.custom-step-info').scrollLeft($('.step-text-5').offset().left);
          $('.step-text-5').addClass('active');
        }
      },

      updateShowAllStep: function(){
        $('.hide-as-show-all').hide();
        $('[class*=contrib-step-]').each(function(){
          var $this = $(this);
          if($this.hasClass('contrib-step-1') && $this.find('.error').length == 0) {
            return ;
          }
          else {
            // First step need to show.
            $('.contrib-step-2 .step-action-wrapper.crm-section').hide();
          }
          $this.removeClass('type-is-back').addClass('type-is-front').css({opacity: 1});
        });
        ContribPage.currentFormStep = 2;
      },

      setPageState: function(state) {
        this.currentPageState = state;
        this.updatePageState();
      },

      updatePageState: function() {

      },

      setInstallments: function(installments) {
        if(this.installments != installments){
          this.installments = installments;
          $('#installments').val(installments)
          this.updateInstallments();
        }
      },

      updateInstallments: function(){
        this.updateContribInfoLabel();
      },

      isArraysEqual: function(a, b) {
        // refs: https://stackoverflow.com/questions/3115982/how-to-check-if-two-arrays-are-equal-with-javascript
        if (a === b) return true;
        if (a == null || b == null) return false;
        if (a.length != b.length) return false;

        // If you don't care about the order of the elements inside
        // the array, you should sort both arrays here.

        for (var i = 0; i < a.length; ++i) {
          if (a[i] !== b[i]) return false;
        }
        return true;
      },

      prepareAfterAll: function(){
        $('.payment_options-group').hide();
        $('#page').css('background', 'none').css('height','unset');
        var interval = setInterval(function(){
          if (window.ContribPage.executingAnimationCount == 0) {
            window.ContribPage.complete = 1;
            clearInterval(interval);
          }
        }, 100);
      }


    };

    try{
      window.ContribPage.preparePage();
    }catch(e){
      console.log(e);
      window.ContribPage.prepareAfterAll();
    }
    window.ContribPage.prepareAfterAll();
  });
})(jQuery);
