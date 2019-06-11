<?php
/*
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
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2010
 * $Id$
 *
 */

require_once 'CRM/Contribute/Form/ContributionBase.php';
require_once 'CRM/Core/Payment.php';

/**
 * This class generates form components for processing a ontribution
 *
 */
class CRM_Contribute_Form_Contribution_Main extends CRM_Contribute_Form_ContributionBase {

  /**
   *Define default MembershipType Id
   *
   */
  public $_paymentProcessors;
  public $_defaultMemTypeId;

  protected $_defaults;

  protected $_ppType;

  /**
   * Function to set variables up before form is built
   *
   * @return void
   * @access public
   */
  public function preProcess() {
    parent::preProcess();

    $defaultFromRequest = array();
    $defaultFromRequest['amt'] = CRM_Utils_Request::retrieve('_amt', 'Positive', $this, FALSE, NULL, 'REQUEST');
    $defaultFromRequest['grouping'] = CRM_Utils_Request::retrieve('_grouping', 'String', $this, FALSE, NULL, 'REQUEST');
    $defaultFromRequest['installments'] = CRM_Utils_Request::retrieve('_installments', 'Integer', $this, FALSE, NULL, 'REQUEST');
    $defaultFromRequest['instrument'] = CRM_Utils_Request::retrieve('_instrument', 'Positive', $this, FALSE, NULL, 'REQUEST');
    $defaultFromRequest['ppid'] = CRM_Utils_Request::retrieve('_ppid', 'Positive', $this, FALSE, NULL, 'REQUEST');
    $defaultFromRequest['membership'] = CRM_Utils_Request::retrieve('_membership', 'Positive', $this, FALSE, NULL, 'REQUEST');
    $instruments = CRM_Contribute_PseudoConstant::paymentInstrument();
    if (isset($defaultFromRequest['instrument']) && empty($instruments[$defaultFromRequest['instrument']])) {
      unset($defaultFromRequest['instrument']);
    }
    $this->_defaultFromRequest = $defaultFromRequest;
    $this->set('defaultFromRequest', $this->_defaultFromRequest);

    $this->_ppType = CRM_Utils_Array::value('type', $_GET);
    require_once 'CRM/Core/Payment/ProcessorForm.php';
    $this->assign('ppType', FALSE);
    if ($this->_ppType) {
      $this->assign('ppType', TRUE);
      return CRM_Core_Payment_ProcessorForm::preProcess($this);
    }

    // make sure we have right permission to edit this user
    $csContactID = CRM_Utils_Request::retrieve('cid', 'Positive', $this, FALSE, NULL);
    $csString = CRM_Utils_Request::retrieve('cs', 'String', $this, FALSE, NULL);
    $currentUserID = $this->_userID;

    if (!empty($csContactID) && !empty($csString) && $currentUserID != $csContactID) {
      if (CRM_Contact_BAO_Contact_Permission::validateChecksumContact($csContactID, $this)) {
        $this->set('csContactID', $csContactID);
        $this->_userID = $csContactID;
      }
    }

    if (CRM_Utils_Array::value('id', $this->_pcpInfo) &&
      CRM_Utils_Array::value('intro_text', $this->_pcpInfo)
    ) {
      $this->assign('intro_text', $this->_pcpInfo['intro_text']);
    }
    elseif (CRM_Utils_Array::value('intro_text', $this->_values)) {
      $this->assign('intro_text', $this->_values['intro_text']);
    }

    if (CRM_Utils_Array::value('footer_text', $this->_values)) {
      $this->assign('footer_text', $this->_values['footer_text']);
    }

    //CRM-5001
    if ($this->_values['is_for_organization']) {
      $msg = ts('Mixed profile not allowed for on behalf of registration/sign up.');
      require_once 'CRM/Core/BAO/UFGroup.php';
      if ($preID = CRM_Utils_Array::value('custom_pre_id', $this->_values)) {
        $preProfile = CRM_Core_BAO_UFGroup::profileGroups($preID);
        foreach (array('Individual', 'Organization', 'Household') as $contactType) {
          if (in_array($contactType, $preProfile) &&
            (in_array('Membership', $preProfile) ||
              in_array('Contribution', $preProfile)
            )
          ) {
            CRM_Core_Error::fatal($msg);
          }
        }
      }

      if ($postID = CRM_Utils_Array::value('custom_post_id', $this->_values)) {
        $postProfile = CRM_Core_BAO_UFGroup::profileGroups($postID);
        foreach (array('Individual', 'Organization', 'Household') as $contactType) {
          if (in_array($contactType, $postProfile) &&
            (in_array('Membership', $postProfile) ||
              in_array('Contribution', $postProfile)
            )
          ) {
            CRM_Core_Error::fatal($msg);
          }
        }
      }
    }
    if (CRM_Utils_Array::value('hidden_processor', $_POST)) {
      $this->set('type', CRM_Utils_Array::value('payment_processor', $_POST));
      $this->set('mode', $this->_mode);
      $this->set('paymentProcessor', $this->_paymentProcessor);

      CRM_Core_Payment_ProcessorForm::preProcess($this);
      CRM_Core_Payment_ProcessorForm::buildQuickForm($this);
    }
    $this->assign('contribution_type_id', $this->_values['contribution_type_id']);

    $meta = array();
    $meta[] = array(
      'tag' => 'meta',
      'attributes' => array(
        'property' => 'og:title',
        'content' => $this->_values['title'] . ' - ' . CRM_Utils_System::variable_get('site_name', 'Drupal'),
      ),
    );

    $descript = $this->_values['intro_text'];
    $descript = preg_replace("/ *<(?<tag>(style|script))( [^=]+=['\"][^'\"]*['\"])*>(.*?(\n))+.*?<\/\k<tag>>/", "", $descript);
    $descript = strip_tags($descript);
    $descript = preg_replace("/(?:(?:&nbsp;)|\n|\r)/", '', $descript);
    $descript = substr($descript,0,150);
    $meta[] = array(
      'tag' => 'meta',
      'attributes' => array(
        'name' => 'description',
        'content' => $descript,
      ),
    );
    $meta[] = array(
      'tag' => 'meta',
      'attributes' =>  array(
        'property' => 'og:description',
        'content' => $descript,
      ),
    );
        if(is_array($this->_values['custom_data_view'])){
      foreach ($this->_values['custom_data_view'] as $ufg) {
        foreach($ufg as $ufg_inner){
          if(is_array($ufg_inner['fields'])){
            foreach ($ufg_inner['fields'] as $uffield) {
              if(is_array($uffield)){
                if($uffield['field_type'] == 'File'){
                  if(!empty($uffield['field_value']['fileURL']) && preg_match('/\.(jpg|png|jpeg)$/',$uffield['field_value']['data'])){
                    $proto = explode('/', $_SERVER['SERVER_PROTOCOL']);
                    $image = strtolower($proto[0]) . '://' . $_SERVER['HTTP_HOST'] . $uffield['field_value']['fileURL'];
                    $meta_ogimg = array(
                      'tag' => 'meta',
                      'attributes' => array(
                        'property' => 'og:image',
                        'value' => $image,
                      ),
                    );
                    break;
                    break;
                    break;
                  }
                }
              }
            }
          }
        }
      }
    }
    if(!empty($meta_ogimg)){
      $meta[] = $meta_ogimg;
    }else{
      preg_match('/< *img[^>]*src *= *["\']?([^"\']*)/i', $this->_values['intro_text'], $matches);
      if(count($matches)>=2){
        $image = $matches[1];
        $meta[] = array(
          'tag' => 'meta',
          'attributes' => array(
            'property' => 'og:image',
            'content' => $image,
          ),
        );
      }
    }
    foreach ($meta as $key => $value) {
      CRM_Utils_System::addHTMLHead($value);
    }

  }

  function setDefaultValues() {
    // process defaults only once
    if (!empty($this->_defaults)) {
      // return $this->_defaults;
    }

    if (!empty($this->_onbehalf)) {
      return;
    }

    // check if the user is registered and we have a contact ID
    $session = CRM_Core_Session::singleton();
    $contactID = $this->_userID;

    if ($contactID) {
      $options = array();
      $fields = array();
      require_once "CRM/Core/BAO/CustomGroup.php";
      $removeCustomFieldTypes = array('Contribution', 'Membership');
      require_once 'CRM/Contribute/BAO/Contribution.php';
      $contribFields = &CRM_Contribute_BAO_Contribution::getContributionFields();

      // remove component related fields
      foreach ($this->_fields as $name => $dontCare) {
        //don't set custom data Used for Contribution (CRM-1344)
        if (substr($name, 0, 7) == 'custom_') {
          $id = substr($name, 7);
          if (!CRM_Core_BAO_CustomGroup::checkCustomField($id, $removeCustomFieldTypes)) {
            continue;
          }
          // ignore component fields
        }
        elseif (array_key_exists($name, $contribFields) || (substr($name, 0, 11) == 'membership_')) {
          continue;
        }
        $fields[$name] = 1;
      }

      $names = array("first_name", "middle_name", "last_name", "street_address-{$this->_bltID}", "city-{$this->_bltID}",
        "postal_code-{$this->_bltID}", "country_id-{$this->_bltID}", "state_province_id-{$this->_bltID}",
      );
      foreach ($names as $name) {
        $fields[$name] = 1;
      }
      $fields["state_province-{$this->_bltID}"] = 1;
      $fields["country-{$this->_bltID}"] = 1;
      $fields["email-{$this->_bltID}"] = 1;
      $fields["email-Primary"] = 1;

      require_once "CRM/Core/BAO/UFGroup.php";
      CRM_Core_BAO_UFGroup::setProfileDefaults($contactID, $fields, $this->_defaults);

      // use primary email address if billing email address is empty
      if (empty($this->_defaults["email-{$this->_bltID}"]) &&
        !empty($this->_defaults["email-Primary"])
      ) {
        $this->_defaults["email-{$this->_bltID}"] = $this->_defaults["email-Primary"];
      }

      foreach ($names as $name) {
        if (!empty($this->_defaults[$name])) {
          $this->_defaults["billing_" . $name] = $this->_defaults[$name];
        }
      }

      if (!empty($_POST)) {
        $payment_processor = CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_ContributionPage', $this->_id, 'payment_processor');
        if (!is_numeric($payment_processor)) {
          $this->set('type', CRM_Utils_Array::value('payment_processor', $_POST));
          $this->set('mode', $this->_mode);

          require_once 'CRM/Core/Payment/ProcessorForm.php';
          CRM_Core_Payment_ProcessorForm::preProcess($this);
          CRM_Core_Payment_ProcessorForm::buildQuickForm($this);
        }
      }
    }
    else{
      if (isset($this->_fields['group'])) {
        CRM_Contact_BAO_Group::publicDefaultGroups($this->_defaults);
      }
    }

    //set custom field defaults set by admin if value is not set
    if (!empty($this->_fields)) {
      //set custom field defaults
      require_once "CRM/Core/BAO/CustomField.php";
      foreach ($this->_fields as $name => $field) {
        if ($customFieldID = CRM_Core_BAO_CustomField::getKeyID($name)) {
          if (!isset($this->_defaults[$name])) {
            CRM_Core_BAO_CustomField::setProfileDefaults($customFieldID, $name, $this->_defaults,
              NULL, CRM_Profile_Form::MODE_REGISTER
            );
          }
        }
      }
    }

    //set default membership for membershipship block
    require_once 'CRM/Member/BAO/Membership.php';
    if ($this->_membershipBlock) {
      $this->_defaults['selectMembership'] = $this->_defaultMemTypeId ? $this->_defaultMemTypeId : CRM_Utils_Array::value('membership_type_default', $this->_membershipBlock);
      if (!empty($this->_defaultFromRequest['grouping']) && strstr($this->_defaultFromRequest['grouping'], 'membership-')) {
        list($dontcare, $defaultFromRequestMembership) = explode('-', $this->_defaultFromRequest['grouping']);
        if (CRM_Utils_Type::validate($defaultFromRequestMembership, 'Positive', FALSE)) {
          $this->_defaults['selectMembership'] = $defaultFromRequestMembership;
        }
      }
      if(empty($this->_defaults['selectMembership'])) {
        if($this->_membershipBlock['membership_types']) {
          $membershipTypes = explode(',', $this->_membershipBlock['membership_types']);
          if(count($membershipTypes) == 1){
            $this->_defaults['selectMembership'] = reset($membershipTypes);
          }
        }
      }
    }

    if ($this->_membershipContactID) {
      $this->_defaults['is_for_organization'] = 1;
      $this->_defaults['org_option'] = 1;
    }
    elseif ($this->_values['is_for_organization']) {
      $this->_defaults['org_option'] = 0;
    }

    if ($this->_values['is_for_organization'] &&
      !isset($this->_defaults['location'][1]['email'][1]['email'])
    ) {
      $this->_defaults['location'][1]['email'][1]['email'] = CRM_Utils_Array::value("email-{$this->_bltID}",
        $this->_defaults
      );
    }

    //if contribution pay later is enabled and payment
    //processor is not available then freeze the pay later checkbox with
    //default check
    if (CRM_Utils_Array::value('is_pay_later', $this->_values) && empty($this->_paymentProcessors) ) {
      $this->_defaults['is_pay_later'] = 1;
    }

    //         // hack to simplify credit card entry for testing
    //         $this->_defaults['credit_card_type']     = 'Visa';
    //         $this->_defaults['amount']               = 168;
    //         $this->_defaults['credit_card_number']   = '4807731747657838';
    //         $this->_defaults['cvv2']                 = '000';
    //         $this->_defaults['credit_card_exp_date'] = array( 'Y' => '2012', 'M' => '05' );

    //         // hack to simplify direct debit entry for testing
    //         $this->_defaults['account_holder'] = 'User Name';
    //         $this->_defaults['bank_account_number'] = '12345678';
    //         $this->_defaults['bank_identification_number'] = '12030000';
    //         $this->_defaults['bank_name'] = 'Bankname';

    //build set default for pledge overdue payment.
    if (CRM_Utils_Array::value('pledge_id', $this->_values)) {
      //get all payment statuses.
      $statuses = array();
      $returnProperties = array('status_id');
      CRM_Core_DAO::commonRetrieveAll('CRM_Pledge_DAO_Payment', 'pledge_id', $this->_values['pledge_id'],
        $statuses, $returnProperties
      );

      require_once 'CRM/Contribute/PseudoConstant.php';
      $paymentStatusTypes = CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name');
      $duePayment = FALSE;
      foreach ($statuses as $payId => $value) {
        if ($paymentStatusTypes[$value['status_id']] == 'Overdue') {
          $this->_defaults['pledge_amount'][$payId] = 1;
        }
        elseif (!$duePayment && $paymentStatusTypes[$value['status_id']] == 'Pending') {
          $this->_defaults['pledge_amount'][$payId] = 1;
          $duePayment = TRUE;
        }
      }
    }
    elseif (CRM_Utils_Array::value('pledge_block_id', $this->_values)) {
      //set default to one time contribution.
      $this->_defaults['is_pledge'] = 0;
    }

    // to process Custom data that are appended to URL
    require_once 'CRM/Core/BAO/CustomGroup.php';
    $getDefaults = CRM_Core_BAO_CustomGroup::extractGetParams($this, "'Contact', 'Individual', 'Contribution'");
    if (!empty($getDefaults)) {
      $this->_defaults = array_merge($this->_defaults, $getDefaults);
    }

    $config = CRM_Core_Config::singleton();
    // set default country from config if no country set
    if (!CRM_Utils_Array::value("billing_country_id-{$this->_bltID}", $this->_defaults)) {
      $this->_defaults["billing_country_id-{$this->_bltID}"] = $config->defaultContactCountry;
    }

    // now fix all state country selectors
    require_once 'CRM/Core/BAO/Address.php';
    CRM_Core_BAO_Address::fixAllStateSelects($this, $this->_defaults);

    if ($this->_priceSetId) {
      foreach ($this->_priceSet['fields'] as $key => $val) {
        foreach ($val['options'] as $keys => $values) {
          if ($values['is_default']) {
            if ($val['html_type'] == 'CheckBox') {
              $this->_defaults["price_{$key}"][$keys] = 1;
            }
            else {
              $this->_defaults["price_{$key}"] = $keys;
            }
          }
        }
      }
    }

    if (!empty($this->_paymentProcessors)) {
      if(count($this->_paymentProcessors) == 1){
        $pid = key($this->_paymentProcessors);
        $this->_defaults['payment_processor'] = $pid;
      }
      else{
        foreach ($this->_paymentProcessors as $pid => $value) {
          if (CRM_Utils_Array::value('is_default', $value)) {
            $this->_defaults['payment_processor'] = $pid;
          }
        }
        if (!empty($this->_defaultFromRequest['ppid'])) {
          $this->_defaults['payment_processor'] = $this->_defaultFromRequest['ppid'];
        } 
      }
    }

    return $this->_defaults;
  }

  /**
   * Function to build the form
   *
   * @return None
   * @access public
   */
  public function buildQuickForm() {
    if ($this->_ppType) {
      return CRM_Core_Payment_ProcessorForm::buildQuickForm($this);
    }

    $config = CRM_Core_Config::singleton();

    $this->applyFilter('__ALL__', 'trim');
    $this->add('text', "email-{$this->_bltID}",
      ts('Email Address'), array('size' => 30, 'maxlength' => 60), TRUE
    );
    $this->addRule("email-{$this->_bltID}", ts('Email is not valid.'), 'email');

    $this->_paymentProcessors = $this->get('paymentProcessors');
    $pps = array();
    if (!empty($this->_paymentProcessors)) {
      $pps = $this->_paymentProcessors;
      $recur_support = array();
      foreach ($pps as $key => $v) {
        $pps[$key] = $v['name'];
        if(!empty($v['is_recur'])){
          $recur_support[] = $key;
        }
      }
    }
    $this->assign('recur_support', json_encode($recur_support));

    if (count($pps)) {
      if (CRM_Utils_Array::value('is_pay_later', $this->_values)) {
        $pps[0] = $this->_values['pay_later_text'];
        $this->assign('pay_later_receipt', $this->_values['pay_later_receipt']);
      }
      $this->addRadio('payment_processor', ts('Payment Method'), $pps, NULL, "&nbsp;", TRUE);
    }
    else {
      $this->addElement('hidden', 'payment_processor', 0);
      $this->assign('is_pay_later', $this->_values['is_pay_later']);
      $this->assign('pay_later_text', $this->_values['pay_later_text']);
      $this->assign('pay_later_receipt', $this->_values['pay_later_receipt']);
    }

    //build pledge block.

    //don't build membership block when pledge_id is passed
    if (!CRM_Utils_Array::value('pledge_id', $this->_values)) {
      $this->_separateMembershipPayment = FALSE;
      if (in_array("CiviMember", $config->enableComponents)) {
        $isTest = 0;
        if ($this->_action & CRM_Core_Action::PREVIEW) {
          $isTest = 1;
        }

        require_once 'CRM/Member/BAO/Membership.php';
        $this->_separateMembershipPayment = CRM_Member_BAO_Membership::buildMembershipBlock($this,
          $this->_id,
          TRUE, NULL, FALSE,
          $isTest, $this->_membershipContactID
        );
      }
      $this->set('separateMembershipPayment', $this->_separateMembershipPayment);
    }

    // If we configured price set for contribution page
    // we are not allow membership signup as well as any
    // other contribution amount field, CRM-5095
    if (isset($this->_priceSetId) && $this->_priceSetId) {
      $this->add('hidden', 'priceSetId', $this->_priceSetId);
      // build price set form.
      $this->set('priceSetId', $this->_priceSetId);
      require_once 'CRM/Price/BAO/Set.php';
      CRM_Price_BAO_Set::buildPriceSet($this);
    }
    elseif (CRM_Utils_Array::value('amount_block_is_active', $this->_values)
      && !CRM_Utils_Array::value('pledge_id', $this->_values)
    ) {
      $this->buildAmount($this->_separateMembershipPayment);
      if ($this->_values['is_monetary'] &&
        $this->_values['is_recur']
      ) {
        foreach ($this->_paymentProcessors as $value) {
          if($value['is_recur']){
            $this->buildRecur();
          }
        }
      }
    }

    if (CRM_Utils_Array::value('is_pay_later', $this->_values)) {
      $this->buildPayLater();
    }

    if ($this->_values['is_for_organization']) {
      $this->buildOnBehalfOrganization();
    }

    //we allow premium for pledge during pledge creation only.
    if (!CRM_Utils_Array::value('pledge_id', $this->_values)) {
      require_once 'CRM/Contribute/BAO/Premium.php';
      CRM_Contribute_BAO_Premium::buildPremiumBlock($this, $this->_id, TRUE);
    }

    if ($this->_values['honor_block_is_active']) {
      $this->buildHonorBlock();
    }

    //don't build pledge block when mid is passed
    if (!$this->_mid) {
      $config = CRM_Core_Config::singleton();
      if (in_array('CiviPledge', $config->enableComponents)
        && CRM_Utils_Array::value('pledge_block_id', $this->_values)
      ) {
        require_once 'CRM/Pledge/BAO/PledgeBlock.php';
        CRM_Pledge_BAO_PledgeBlock::buildPledgeBlock($this);
      }
    }

    $this->buildCustom($this->_values['custom_pre_id'], 'customPre');
    $this->buildCustom($this->_values['custom_post_id'], 'customPost');

    // doing this later since the express button type depends if there is an upload or not
    if ($this->_values['is_monetary']) {
      require_once 'CRM/Core/Payment/Form.php';
      if ($this->_paymentProcessor['payment_type'] & CRM_Core_Payment::PAYMENT_TYPE_DIRECT_DEBIT) {
        CRM_Core_Payment_Form::buildDirectDebit($this);
      }
      else {
        CRM_Core_Payment_Form::buildCreditCard($this);
      }
    }

    //to create an cms user
    if (!$this->_userID) {
      $createCMSUser = FALSE;
      if ($this->_values['custom_pre_id']) {
        $profileID = $this->_values['custom_pre_id'];
        $createCMSUser = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_UFGroup', $profileID, 'is_cms_user');
      }
      if (!$createCMSUser &&
        $this->_values['custom_post_id']
      ) {
        $profileID = $this->_values['custom_post_id'];
        $createCMSUser = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_UFGroup', $profileID, 'is_cms_user');
      }

      if ($createCMSUser) {
        require_once 'CRM/Core/BAO/CMSUser.php';
        CRM_Core_BAO_CMSUser::buildForm($this, $profileID, TRUE);
      }
    }
    if ($this->_pcpId) {
      $this->assign('pcp', TRUE);
      $this->add('checkbox', 'pcp_display_in_roll', ts('Show my contribution in the public honor roll'), NULL, NULL,
        array('onclick' => "showHideByValue('pcp_display_in_roll','','nameID|nickID|personalNoteID','block','radio',false); pcpAnonymous( );")
      );
      $extraOption = array('onclick' => "return pcpAnonymous( );");
      $elements = array();
      $elements[] = &$this->createElement('radio', NULL, '', ts('Include my name and message'), 0, $extraOption);
      $elements[] = &$this->createElement('radio', NULL, '', ts('List my contribution anonymously'), 1, $extraOption);
      $this->addGroup($elements, 'pcp_is_anonymous', NULL, '&nbsp;&nbsp;&nbsp;');
      $this->_defaults['pcp_is_anonymous'] = 0;
      $this->_defaults['pcp_display_in_roll'] = 1;

      $this->add('text', 'pcp_roll_nickname', ts('Name'), array('maxlength' => 30));
      $this->add('textarea', "pcp_personal_note", ts('Personal Note'), array('style' => 'height: 3em; width: 40em;'));
    }

    //we have to load confirm contribution button in template
    //when multiple payment processor as the user
    //can toggle with payment processor selection
    $billingModePaymentProcessors = 0;
    if (!empty($this->_paymentProcessors)) {
      foreach ($this->_paymentProcessors as $key => $values) {
        if ($values['billing_mode'] == CRM_Core_Payment::BILLING_MODE_BUTTON) {
          $billingModePaymentProcessors++;
        }
      }
    }
    if ($billingModePaymentProcessors && count($this->_paymentProcessors) == $billingModePaymentProcessors) {
      $allAreBillingModeProcessors = TRUE;
    }
    else {
      $allAreBillingModeProcessors = FALSE;
    }

    if (!($allAreBillingModeProcessors && !$this->_values['is_pay_later'])) {
      $this->addButtons(array(
          array(
            'type' => 'upload',
            'name' => ts('Next >>'),
            'spacing' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;',
            'isDefault' => TRUE,
          ),
        )
      );
    }

    $this->addFormRule(array('CRM_Contribute_Form_Contribution_Main', 'formRule'), $this);

    $this->assign('receiptYesNo',$config->receiptYesNo);
    $this->assign('receiptTitle',$config->receiptTitle);
    $this->assign('receiptSerial',$config->receiptSerial);
    $this->assign('receiptDonorCredit',$config->receiptDonorCredit);

    if(!empty($this->_submitValues['same_as_post'])){
      $this->assign('same_as',$this->_submitValues['same_as_post']);
    }
    if(!empty($this->_submitValues['receipt_name'])){
      $this->assign('receipt_name',$this->_submitValues['receipt_name']);
    }
    if(!empty($this->_submitValues['receipt_type'])){
      $this->assign('receipt_type',$this->_submitValues['receipt_type']);
    }
    
    $achievement = CRM_Contribute_BAO_ContributionPage::goalAchieved($this->_id);
    $this->assign('achievement', $achievement);

    // hidden track id
    $this->addElement('hidden', 'track', $this->get('trackId'));
  }

  /**
   * build the radio/text form elements for the amount field
   *
   * @return void
   * @access private
   */
  function buildAmount($separateMembershipPayment = FALSE) {
    $elements = array();
    $defaultFromRequestAmountId = NULL; 
    if (!empty($this->_values['amount'])) {
      // first build the radio boxes
      CRM_Utils_Hook::buildAmount('contribution', $this, $this->_values['amount']);

      // set default display
      if (!empty($this->_values['default_amount_id'])) {
        $this->_defaultAmountGrouping = $this->_values['amount'][$this->_values['default_amount_id']]['grouping'];
        if (!empty($this->_defaultFromRequest['grouping'])) {
          $this->_defaultAmountGrouping = $this->_defaultFromRequest['grouping'];
        }
      }
      foreach ($this->_values['amount'] as $amount) {
        // detect default from request
        if ($this->_defaultFromRequest['amt'] == $amount['value']) {
          if (!empty($this->_defaultFromRequest['grouping']) && ($amount['grouping'] == $this->_defaultFromRequest['grouping'] || empty($amount['grouping']))) {
            $defaultFromRequestAmountId = $amount['amount_id'];
          }
          elseif (empty($this->_defaultFromRequest['grouping'])) {
            $defaultFromRequestAmountId = $amount['amount_id'];
          }
        }

        // set default price option
        $attributes = array(
          'data-grouping' => isset($amount['grouping']) ? $amount['grouping'] : '',
          'data-default' => !empty($amount['filter']) ? 1 : 0,
          'onclick' => 'clearAmountOther();',
        );
        $elements[] = &$this->createElement('radio', NULL, '',
          CRM_Utils_Money::format($amount['value']) . ' ' . $amount['label'],
          $amount['amount_id'],
          $attributes
        );

        // add default amount option
        if (!empty($amount['filter']) && !empty($this->_defaultAmountGrouping) && $amount['grouping'] == $this->_defaultAmountGrouping) {
          $this->_defaults['amount'] = $amount['amount_id'];
        }
      }
      if (empty($this->_defaults['amount']) && !empty($this->_values['default_amount_id'])) {
        $this->_defaults['amount'] = $this->_values['default_amount_id'];
      }
      if ($defaultFromRequestAmountId) {
        $this->_defaults['amount'] = $defaultFromRequestAmountId;
      }
    }

    if ($separateMembershipPayment) {
      $elements[''] = $this->createElement('radio', NULL, NULL, ts('No thank you'), 'no_thanks', array('onclick' => 'clearAmountOther();'));
      $this->assign('is_separate_payment', TRUE);
    }

    $title = ts('Contribution Amount');
    if ($this->_values['is_allow_other_amount']) {
      if (!empty($this->_values['amount'])) {
        $elements[] = &$this->createElement('radio', NULL, '',
          ts('Other Amount'), 'amount_other_radio'
        );

        $this->addGroup($elements, 'amount', $title, '<br />');

        if (!$separateMembershipPayment) {
          $this->addRule('amount', ts('%1 is a required field.', array(1 => ts('Amount'))), 'required');
        }
        $this->add('text', 'amount_other', ts('Other Amount'), array('size' => 10, 'maxlength' => 10, 'onfocus' => 'useAmountOther();'));
      }
      else {
        if ($separateMembershipPayment) {
          $title = ts('Additional Contribution');
        }
        $this->add('text', 'amount_other', $title, array('size' => 10, 'maxlength' => 10, 'onfocus' => 'useAmountOther();'));
        if (!$separateMembershipPayment) {
          $this->addRule('amount_other', ts('%1 is a required field.', array(1 => $title)), 'required');
        }
      }

      $this->assign('is_allow_other_amount', TRUE);

      $this->addRule('amount_other', ts('Please enter a valid amount (numbers and decimal point only).'), 'money');
      if ($this->_defaultFromRequest['amt'] && empty($defaultFromRequestAmountId)) {
        $this->_defaults['amount'] = 'amount_other_radio';
        $this->_defaults['amount_other'] = $this->_defaultFromRequest['amt'];
      }
    }
    else {
      if (!empty($this->_values['amount'])) {
        if ($separateMembershipPayment) {
          $title = ts('Additional Contribution');
        }
        $this->addGroup($elements, 'amount', $title, '<br />');

        if (!$separateMembershipPayment) {
          $this->addRule('amount', ts('%1 is a required field.', array(1 => ts('Amount'))), 'required');
        }
      }
      $this->assign('is_allow_other_amount', FALSE);
    }
  }

  /**
   * Function to add the honor block
   *
   * @return None
   * @access public
   */
  function buildHonorBlock() {
    $this->assign("honor_block_is_active", TRUE);
    $this->set("honor_block_is_active", TRUE);

    $this->assign("honor_block_title", CRM_Utils_Array::value('honor_block_title', $this->_values));
    $this->assign("honor_block_text", CRM_Utils_Array::value('honor_block_text', $this->_values));

    $attributes = CRM_Core_DAO::getAttribute('CRM_Contact_DAO_Contact');
    $extraOption = array('onclick' => "enableHonorType();");
    // radio button for Honor Type
    $honorOptions = array();
    $honor = CRM_Core_PseudoConstant::honor();
    foreach ($honor as $key => $var) {
      $honorTypes[$key] = HTML_QuickForm::createElement('radio', NULL, NULL, $var, $key, $extraOption);
    }
    $this->addGroup($honorTypes, 'honor_type_id', NULL);

    // prefix
    $this->addElement('select', 'honor_prefix_id', ts('Prefix'), array('' => ts('- prefix -')) + CRM_Core_PseudoConstant::individualPrefix());
    // first_name
    $this->addElement('text', 'honor_first_name', ts('First Name'), $attributes['first_name']);

    //last_name
    $this->addElement('text', 'honor_last_name', ts('Last Name'), $attributes['last_name']);

    //email
    $this->addElement('text', 'honor_email', ts('Email Address'));
    $this->addRule("honor_email", ts('Honoree Email is not valid.'), 'email');
  }

  /**
   * build elements to enable pay on behalf of an organization.
   *
   * @access public
   */
  function buildOnBehalfOrganization() {
    if ($this->_membershipContactID) {
      require_once 'CRM/Core/BAO/Location.php';
      $entityBlock = array('contact_id' => $this->_membershipContactID);
      CRM_Core_BAO_Location::getValues($entityBlock, $this->_defaults);
    }

    require_once 'CRM/Contact/BAO/Contact/Utils.php';
    if ($this->_values['is_for_organization'] != 2) {
      $attributes = array('onclick' =>
        "return showHideByValue('is_for_organization','true','for_organization','block','radio',false);",
      );
      $this->addElement('checkbox', 'is_for_organization',
        $this->_values['for_organization'],
        NULL, $attributes
      );
    }
    else {
      $this->addElement('hidden', 'is_for_organization', TRUE);
    }
    $this->assign('is_for_organization', TRUE);
    CRM_Contact_BAO_Contact_Utils::buildOnBehalfForm($this, 'Organization', NULL,
      NULL, ts('Organization Details')
    );
  }

  /**
   * build elements to enable pay later functionality
   *
   * @access public
   */
  function buildPayLater() {

    $attributes = NULL;
    $this->assign('hidePaymentInformation', FALSE);

    if (!in_array($this->_paymentProcessor['billing_mode'], array(2, 4)) &&
      $this->_values['is_monetary'] && is_array($this->_paymentProcessor)
    ) {
      $attributes = array('onclick' => "return showHideByValue('is_pay_later','','payment_information',
                                                     'block','radio',true);");

      $this->assign('hidePaymentInformation', TRUE);
    }
    //hide the paypal exress button and show continue button
    if ($this->_paymentProcessor['payment_processor_type'] == 'PayPal_Express') {
      $attributes = array('onclick' => "showHidePayPalExpressOption();");
    }

    $element = $this->addElement('checkbox', 'is_pay_later',
      $this->_values['pay_later_text'], NULL, $attributes
    );
    //if payment processor is not available then freeze
    //the paylater checkbox with default checked.
    if (empty($this->_paymentProcessor)) {
      $element->freeze();
    }
  }

  /**
   * build elements to collect information for recurring contributions
   *
   * @access public
   */
  function buildRecur() {
    $attributes = CRM_Core_DAO::getAttribute('CRM_Contribute_DAO_ContributionRecur');
    if ($this->_values['is_recur'] == 2) {
      $this->_defaults['is_recur'] = 1;
    }
    else {
      if ($this->_values['is_recur'] == 1 && !empty($this->_defaultAmountGrouping)) {
        if ($this->_defaultAmountGrouping == 'recurring') {
          $this->_defaults['is_recur'] = 1;
        }
        else {
          $this->_defaults['is_recur'] = 0;
        }
      }
      else {
        $this->_defaults['is_recur'] = 0;
      }
    }

    if ($this->_values['is_recur_interval']) {
      $this->add('text', 'frequency_interval', ts('Every'),
        $attributes['frequency_interval']
      );
      $this->addRule('frequency_interval', ts('Frequency must be a whole number (EXAMPLE: Every 3 months).'), 'integer');
    }
    else {
      // make sure frequency_interval is submitted as 1 if given
      // no choice to user.
      $this->add('hidden', 'frequency_interval', 1);
    }

    $units = array();
    $unitVals = explode(CRM_Core_BAO_CustomOption::VALUE_SEPERATOR, $this->_values['recur_frequency_unit']);
    $unitTrans = array(
      'day' => 'daily',
      'week' => 'weekly',
      'month' => 'monthly',
      'year' => 'yearly',
    );
    $frequencyUnits = CRM_Core_OptionGroup::values('recur_frequency_units');
    foreach ($unitVals as $key => $val) {
      if (array_key_exists($val, $frequencyUnits)) {
        $units[$val] = ts($unitTrans[$val]);
      }
    }
     
    if (count($units) > 1) {
      $this->add('select', 'frequency_unit', ts('Frequency'), $units);
      $recurOptionLabel = ts('Recurring contributions');
    }
    else {
      $unitVal = key($units);
      $this->addElement('hidden', 'frequency_unit', $unitVal);
      $recurOptionLabel = ts('Recurring contributions').' - '.$units[$unitVal];
    }
    $elements = array();
    if ($this->_values['is_recur'] < 2) {
      $elements[] = &$this->createElement('radio', NULL, '', ts('I want to make a one-time contribution.'), 0);
    }
    $elements[] = &$this->createElement('radio', NULL, '', $recurOptionLabel, 1);
    $this->addGroup($elements, 'is_recur', NULL, '<br />');

    $attributes['installments'] = array(
      'placeholder' => ts('no limit'),
      'min' => 2,
      'style' => 'max-width:100px',
    );
    $this->add('number', 'installments', ts('Installments'), $attributes['installments']);
    if (isset($this->_defaultFromRequest['installments'])) { 
      $this->_defaults['installments'] = $this->_defaultFromRequest['installments'];
    }
    $this->addRule('installments', ts('Number of installments must be a whole number.'), 'integer');
  }

  /**
   * global form rule
   *
   * @param array $fields  the input form values
   * @param array $files   the uploaded files if any
   * @param array $options additional user data
   *
   * @return true if no errors, else array of errors
   * @access public
   * @static
   */
  static function formRule($fields, $files, $self) {
    $errors = array();
    $amount = self::computeAmount($fields, $self);

    $checked = $self->checkDuplicateAccount($fields, $self);
    if (is_array($checked)) {
      $errors += $checked;
    }

    //check for atleast one pricefields should be selected
    if (CRM_Utils_Array::value('priceSetId', $fields)) {
      $priceField = new CRM_Price_DAO_Field();
      $priceField->price_set_id = $fields['priceSetId'];
      $priceField->find();

      $check = array();

      while ($priceField->fetch()) {
        if (!empty($fields["price_{$priceField->id}"])) {
          $check[] = $priceField->id;
        }
      }

      if (empty($check)) {
        $errors['_qf_default'] = ts("Select at least one option from Contribution(s).");
      }

      require_once 'CRM/Price/BAO/Set.php';
      CRM_Price_BAO_Set::processAmount($self->_values['fee'],
        $fields, $lineItem
      );
      if ($fields['amount'] < 0) {
        $errors['_qf_default'] = ts("Contribution can not be less than zero. Please select the options accordingly");
      }
      $amount = $fields['amount'];
    }

    if (isset($fields['selectProduct']) &&
      $fields['selectProduct'] != 'no_thanks' &&
      $self->_values['amount_block_is_active']
    ) {
      require_once 'CRM/Contribute/DAO/Product.php';
      require_once 'CRM/Utils/Money.php';
      $productDAO = new CRM_Contribute_DAO_Product();
      $productDAO->id = $fields['selectProduct'];
      $productDAO->find(TRUE);
      $min_amount = $productDAO->min_contribution;
      if(!empty($fields['is_recur'])){
        if(!empty($fields['installments'])){
          $total = $amount * $fields['installments'];
          if($total < $min_amount){
            $errors['selectProduct'] = ts('The premium you have selected requires a minimum contribution of %1', array(1 => CRM_Utils_Money::format($min_amount)));
          }
        }
        else{
          if(empty($amount)){
            $errors['selectProduct'] = ts('The premium you have selected requires a minimum contribution of %1', array(1 => CRM_Utils_Money::format($min_amount)));
          }
        }
      }
      elseif($amount < $min_amount) {
        $errors['selectProduct'] = ts('The premium you have selected requires a minimum contribution of %1', array(1 => CRM_Utils_Money::format($min_amount)));
      }
    }

    if ($self->_values["honor_block_is_active"] && CRM_Utils_Array::value('honor_type_id', $fields)) {
      // make sure there is a first name and last name if email is not there
      if (!CRM_Utils_Array::value('honor_email', $fields)) {
        if (!CRM_Utils_Array::value('honor_first_name', $fields) ||
          !CRM_Utils_Array::value('honor_last_name', $fields)
        ) {
          $errors['honor_last_name'] = ts('In Honor Of - First Name and Last Name, OR an Email Address is required.');
        }
      }
    }

    if (isset($fields['is_recur']) && $fields['is_recur']) {
      $installments = CRM_Utils_Array::value('installments', $fields);
      if (strlen($installments) !== 0 && $installments <= 1){
        $errors['installments'] = ts('Installments should be greater than %1.', array(1 => '1'));
      }
      if ($fields['frequency_interval'] <= 0) {
        $errors['frequency_interval'] = ts('Please enter a number for how often you want to make this recurring contribution (EXAMPLE: Every 3 months).');
      }
      if ($fields['frequency_unit'] == '0') {
        $errors['frequency_unit'] = ts('Please select a period (e.g. months, years ...) for how often you want to make this recurring contribution (EXAMPLE: Every 3 MONTHS).');
      }
    }

    if (CRM_Utils_Array::value('is_recur', $fields) && (CRM_Utils_Array::value('payment_processor', $fields) == 0 || CRM_Utils_Array::value('civicrm_instrument_id', $fields) != 1)) {
      $errors['_qf_default'] = ts('You cannot set up a recurring contribution if you are not paying online by credit card.');
    }

    if (CRM_Utils_Array::value('is_for_organization', $fields)) {
      if (CRM_Utils_Array::value('org_option', $fields) && !$fields['onbehalfof_id']) {
        $errors['organization_id'] = ts('Please select an organization or enter a new one.');
      }
      if (!CRM_Utils_Array::value('org_option', $fields) && !$fields['organization_name']) {
        $errors['organization_name'] = ts('Please enter the organization name.');
      }
      if (!$fields['email'][1]['email']) {
        $errors["email[1][email]"] = ts('Organization email is required.');
      }
      if (!$fields['sic_code']) {
        $errors["sic_code"] = ts('Enter a SIC Code');
      }
    }

    if (CRM_Utils_Array::value('selectMembership', $fields) &&
      $fields['selectMembership'] != 'no_thanks'
    ) {
      require_once 'CRM/Member/BAO/Membership.php';
      require_once 'CRM/Member/BAO/MembershipType.php';
      $memTypeDetails = CRM_Member_BAO_MembershipType::getMembershipTypeDetails($fields['selectMembership']);
      if ($self->_values['amount_block_is_active'] &&
        !CRM_Utils_Array::value('is_separate_payment', $self->_membershipBlock)
      ) {
        require_once 'CRM/Utils/Money.php';
        if ($amount < CRM_Utils_Array::value('minimum_fee', $memTypeDetails)) {
          $errors['selectMembership'] = ts('The Membership you have selected requires a minimum contribution of %1',
            array(1 => CRM_Utils_Money::format($memTypeDetails['minimum_fee']))
          );
        }
      }
      elseif (CRM_Utils_Array::value('minimum_fee', $memTypeDetails)) {
        // we dont have an amount, so lets get an amount for cc checks
        $amount = $memTypeDetails['minimum_fee'];
      }
    }

    if ($self->_values['is_monetary']) {
      //validate other amount.
      $checkOtherAmount = FALSE;
      if (CRM_Utils_Array::value('amount', $fields) == 'amount_other_radio' || CRM_Utils_Array::value('amount_other', $fields)) {
        $checkOtherAmount = TRUE;
      }
      $otherAmountVal = CRM_Utils_Array::value('amount_other', $fields);
      if ($checkOtherAmount || $otherAmountVal) {
        if (!$otherAmountVal) {
          $errors['amount_other'] = ts('Amount is required field.');
        }
        //validate for min and max.
        if ($otherAmountVal) {
          $min = CRM_Utils_Array::value('min_amount', $self->_values);
          $max = CRM_Utils_Array::value('max_amount', $self->_values);
          if ($min && $otherAmountVal < $min) {
            $errors['amount_other'] = ts('Contribution amount must be at least %1',
              array(1 => $min)
            );
          }
          if ($max && $otherAmountVal > $max) {
            $errors['amount_other'] = ts('Contribution amount cannot be more than %1.',
              array(1 => $max)
            );
          }
        }
      }
    }

    // validate PCP fields - if not anonymous, we need a nick name value
    if ($self->_pcpId && CRM_Utils_Array::value('pcp_display_in_roll', $fields) &&
      (CRM_Utils_Array::value('pcp_is_anonymous', $fields) == 0) &&
      CRM_Utils_Array::value('pcp_roll_nickname', $fields) == ''
    ) {
      $errors['pcp_roll_nickname'] = ts('Please enter a name to include in the Honor Roll, or select \'contribute anonymously\'.');
    }

    // return if this is express mode
    $config = CRM_Core_Config::singleton();
    if ($self->_paymentProcessor['billing_mode'] & CRM_Core_Payment::BILLING_MODE_BUTTON) {
      if (CRM_Utils_Array::value($self->_expressButtonName . '_x', $fields) ||
        CRM_Utils_Array::value($self->_expressButtonName . '_y', $fields) ||
        CRM_Utils_Array::value($self->_expressButtonName, $fields)
      ) {
        return $errors;
      }
    }

    //validate the pledge fields.
    if (CRM_Utils_Array::value('pledge_block_id', $self->_values)) {
      //validation for pledge payment.
      if (CRM_Utils_Array::value('pledge_id', $self->_values)) {
        if (empty($fields['pledge_amount'])) {
          $errors['pledge_amount'] = ts('At least one payment option needs to be checked.');
        }
      }
      elseif (CRM_Utils_Array::value('is_pledge', $fields)) {
        if (CRM_Utils_Rule::positiveInteger(CRM_Utils_Array::value('pledge_installments', $fields)) == FALSE) {
          $errors['pledge_installments'] = ts('Please enter a valid pledge installment.');
        }
        else {
          if (CRM_Utils_Array::value('pledge_installments', $fields) == NULL) {
            $errors['pledge_installments'] = ts('Pledge Installments is required field.');
          }
          elseif (CRM_Utils_array::value('pledge_installments', $fields) == 1) {
            $errors['pledge_installments'] = ts('Pledges consist of multiple scheduled payments. Select one-time contribution if you want to make your gift in a single payment.');
          }
          elseif (CRM_Utils_array::value('pledge_installments', $fields) == 0) {
            $errors['pledge_installments'] = ts('Pledge Installments field must be > 1.');
          }
        }

        //validation for Pledge Frequency Interval.
        if (CRM_Utils_Rule::positiveInteger(CRM_Utils_Array::value('pledge_frequency_interval', $fields)) == FALSE) {
          $errors['pledge_frequency_interval'] = ts('Please enter a valid Pledge Frequency Interval.');
        }
        else {
          if (CRM_Utils_Array::value('pledge_frequency_interval', $fields) == NULL) {
            $errors['pledge_frequency_interval'] = ts('Pledge Frequency Interval. is required field.');
          }
          elseif (CRM_Utils_array::value('pledge_frequency_interval', $fields) == 0) {
            $errors['pledge_frequency_interval'] = ts('Pledge frequency interval field must be > 0');
          }
        }
      }
    }

    // also return if paylater mode
    if (CRM_Utils_Array::value('is_pay_later', $fields)) {
      return empty($errors) ? TRUE : $errors;
    }

    // if the user has chosen a free membership or the amount is less than zero
    // i.e. we skip calling the payment processor and hence dont need credit card
    // info
    if ((float ) $amount <= 0.0) {
      return $errors;
    }
    
    $self->addFieldRequiredRule($errors, $fields ,$files);

    // make sure that credit card number and cvv are valid
    require_once 'CRM/Utils/Rule.php';
    if (CRM_Utils_Array::value('credit_card_type', $fields)) {
      if (CRM_Utils_Array::value('credit_card_number', $fields) &&
        !CRM_Utils_Rule::creditCardNumber($fields['credit_card_number'], $fields['credit_card_type'])
      ) {
        $errors['credit_card_number'] = ts("Please enter a valid Credit Card Number");
      }

      if (CRM_Utils_Array::value('cvv2', $fields) &&
        !CRM_Utils_Rule::cvv($fields['cvv2'], $fields['credit_card_type'])
      ) {
        $errors['cvv2'] = ts("Please enter a valid Credit Card Verification Number");
      }
    }

    $elements = array('email_greeting' => 'email_greeting_custom',
      'postal_greeting' => 'postal_greeting_custom',
      'addressee' => 'addressee_custom',
    );
    foreach ($elements as $greeting => $customizedGreeting) {
      if ($greetingType = CRM_Utils_Array::value($greeting, $fields)) {
        $customizedValue = CRM_Core_OptionGroup::getValue($greeting, 'Customized', 'name');
        if ($customizedValue == $greetingType &&
          !CRM_Utils_Array::value($customizedGreeting, $fields)
        ) {
          $errors[$customizedGreeting] = ts('Custom %1 is a required field if %1 is of type Customized.',
            array(1 => ucwords(str_replace('_', " ", $greeting)))
          );
        }
      }
    }

    return empty($errors) ? TRUE : $errors;
  }

  public function computeAmount(&$params, &$form) {
    $amount = NULL;

    // first clean up the other amount field if present
    if (isset($params['amount_other'])) {
      $params['amount_other'] = CRM_Utils_Rule::cleanMoney($params['amount_other']);
    }

    if (CRM_Utils_Array::value('amount', $params) == 'amount_other_radio' ||
      CRM_Utils_Array::value('amount_other', $params)
    ) {
      $amount = $params['amount_other'];
    }
    elseif (!empty($params['pledge_amount'])) {
      $amount = 0;
      foreach ($params['pledge_amount'] as $paymentId => $dontCare) {
        $amount += CRM_Core_DAO::getFieldValue('CRM_Pledge_DAO_Payment', $paymentId, 'scheduled_amount');
      }
    }
    else {
      if (CRM_Utils_Array::value('amount', $form->_values)) {
        $amountID = CRM_Utils_Array::value('amount', $params);

        if ($amountID) {
          $params['amount_level'] = $form->_values['amount'][$amountID]['label'];
          $amount = $form->_values['amount'][$amountID]['value'];
        }
      }
    }
    return $amount;
  }

  /**
   * Function to process the form
   *
   * @access public
   *
   * @return None
   */
  public function postProcess() {
    $config = CRM_Core_Config::singleton();

    // we first reset the confirm page so it accepts new values
    $this->controller->resetPage('Confirm');

    // get the submitted form values.
    $params = $this->controller->exportValues($this->_name);

    if (CRM_Utils_Array::value('onbehalfof_id', $params)) {
      $params['organization_id'] = $params['onbehalfof_id'];
    }

    $params['currencyID'] = $config->defaultCurrency;

    $params['amount'] = self::computeAmount($params, $this);
    $memFee = NULL;
    if (CRM_Utils_Array::value('selectMembership', $params)) {
      $membershipTypeValues = CRM_Member_BAO_Membership::buildMembershipTypeValues($this,
        $params['selectMembership']
      );
      $memFee = $membershipTypeValues['minimum_fee'];
      if (!$params['amount'] && !$this->_separateMembershipPayment) {
        $params['amount'] = $memFee ? $memFee : 0;
      }
    }

    if (!isset($params['amount_other'])) {
      $this->set('amount_level', CRM_Utils_Array::value('amount_level', $params));
    }

    if ($priceSetId = CRM_Utils_Array::value('priceSetId', $params)) {
      $lineItem = array();
      require_once 'CRM/Price/BAO/Set.php';
      CRM_Price_BAO_Set::processAmount($this->_values['fee'], $params, $lineItem[$priceSetId]);
      $this->set('lineItem', $lineItem);
    }
    if (($this->_values['is_pay_later'] &&
        empty($this->_paymentProcessor) &&
        !array_key_exists('hidden_processor', $params)
      ) ||
      CRM_Utils_Array::value('payment_processor', $params) == 0
    ) {
      $params['is_pay_later'] = 1;
    }
    else {
      $params['is_pay_later'] = 0;
    }
    $this->set('is_pay_later', $params['is_pay_later']);
    // assign pay later stuff
    $this->_params['is_pay_later'] = CRM_Utils_Array::value('is_pay_later', $params, FALSE);
    $this->assign('is_pay_later', $params['is_pay_later']);
    if ($params['is_pay_later']) {
      $this->assign('pay_later_text', $this->_values['pay_later_text']);
      $this->assign('pay_later_receipt', $this->_values['pay_later_receipt']);
    }

    $this->set('amount', $params['amount']);
    $this->set('amount_level',$params['amount_level']);

    // generate and set an invoiceID for this transaction
    $invoiceID = md5(uniqid(rand(), TRUE));
    $this->set('invoiceID', $invoiceID);

    // required only if is_monetary and valid postive amount
    if ($this->_values['is_monetary'] &&
      is_array($this->_paymentProcessor) &&
      ((float ) $params['amount'] > 0.0 || $memFee > 0.0)
    ) {

      // default mode is direct
      $this->set('contributeMode', 'direct');

      if ($this->_paymentProcessor['billing_mode'] & CRM_Core_Payment::BILLING_MODE_BUTTON) {
        //get the button name
        $buttonName = $this->controller->getButtonName();
        if (in_array($buttonName,
            array($this->_expressButtonName, $this->_expressButtonName . '_x', $this->_expressButtonName . '_y')
          ) &&
          !isset($params['is_pay_later'])
        ) {
          $this->set('contributeMode', 'express');

          $donateURL = CRM_Utils_System::url('civicrm/contribute', '_qf_Contribute_display=1');
          $params['cancelURL'] = CRM_Utils_System::url('civicrm/contribute/transact', "_qf_Main_display=1&qfKey={$params['qfKey']}", TRUE, NULL, FALSE);
          $params['returnURL'] = CRM_Utils_System::url('civicrm/contribute/transact', "_qf_Confirm_display=1&rfp=1&qfKey={$params['qfKey']}", TRUE, NULL, FALSE);
          $params['invoiceID'] = $invoiceID;

          //default action is Sale
          $params['payment_action'] = 'Sale';

          $payment = &CRM_Core_Payment::singleton($this->_mode, $this->_paymentProcessor, $this);
          $token = $payment->setExpressCheckout($params);
          if (is_a($token, 'CRM_Core_Error')) {
            CRM_Core_Error::displaySessionError($token);
            CRM_Utils_System::redirect($params['cancelURL']);
          }

          $this->set('token', $token);

          $paymentURL = $this->_paymentProcessor['url_site'] . "/cgi-bin/webscr?cmd=_express-checkout&token=$token";
          CRM_Utils_System::redirect($paymentURL);
        }
      }
      elseif ($this->_paymentProcessor['billing_mode'] & CRM_Core_Payment::BILLING_MODE_NOTIFY) {
        $this->set('contributeMode', 'notify');
      }
      elseif ($this->_paymentProcessor['billing_mode'] & CRM_Core_Payment::BILLING_MODE_IFRAME) {
        $this->set('contributeMode', 'iframe');
      }
    }
  }

  function checkDuplicateAccount($fields, &$self) {
    // CRM-3907, skip check for preview registrations
    if ($self->_mode == 'test') {
      return FALSE;
    }

    $session = CRM_Core_Session::singleton();
    $currentUserID = $session->get('userID');
    if (!$currentUserID && is_array($fields) && !empty($fields) && $fields['cms_create_account']) {
      $params = $fields;
      $dedupeParams = CRM_Dedupe_Finder::formatParams($params, 'Individual');

      // disable permission based on cache since event registration is public page/feature.
      $dedupeParams['check_permission'] = FALSE;
      $ids = CRM_Dedupe_Finder::dupesByParams($dedupeParams, 'Individual');
      $contactID = CRM_Utils_Array::value(0, $ids);
      if ($contactID) {
        // check if contact exists but email not the same
        if (isset($fields['_qf_default'])) {
          $dao = new CRM_Core_DAO_UFMatch();
          $dao->contact_id = $contactID;
          $dao->find(TRUE);
          if (!empty($dao->uf_name) && ($dao->uf_name !== $fields['email-'.$this->_bltID])) {
            // errors because uf_name(email) will update to new value
            // then the drupal duplicate email check may failed
            // we should validate and stop here before confirm stage
            $url = CRM_Utils_System::url('user', "destination=" . urlencode("civicrm/contribute/transact?reset=1&id={$self->_values['id']}"));
            return array('email-'.$this->_bltID => ts('Accroding your profile, you are one of our registered user. Please <a href="%1">login</a> to proceed.', array(1 => $url)));
          }
        }
      }
    }
  }
}

