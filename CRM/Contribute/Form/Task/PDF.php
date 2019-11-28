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
 *
 */

require_once 'CRM/Contribute/Form/Task.php';

/**
 * This class provides the functionality to email a group of
 * contacts.
 */
class CRM_Contribute_Form_Task_PDF extends CRM_Contribute_Form_Task {

  /**
   * Are we operating in "single mode", i.e. updating the task of only
   * one specific contribution?
   *
   * @var boolean
   */
  public $_single = FALSE;

  protected $_tmpreceipt;

  protected $_rows;

  /**
   * build all the data structures needed to build the form
   *
   * @return void
   * @access public
   */
  function preProcess() {
    $id = CRM_Utils_Request::retrieve('id', 'Positive', $this, FALSE);

    if ($id) {
      $this->_contributionIds = array($id);
      $this->_componentClause = " civicrm_contribution.id IN ( $id ) ";
      $this->_single = TRUE;
      $this->assign('totalSelectedContributions', 1);
    }
    else {
      parent::preProcess();
    }

    $deductible_type_id = array();
    $sql = "SELECT * FROM civicrm_contribution_type WHERE is_deductible = 0";
    $dao = CRM_Core_DAO::executeQuery($sql);
    while($dao->fetch()){
      $deductible_type_id[] = $dao->id;
    }
    if(count($deductible_type_id) > 0){
      $deductible_type = implode(',', $deductible_type_id);
      $deductible_type_clause = "OR contribution_type_id IN ($deductible_type)";
    }
    // check that all the contribution ids have pending status
    $query = " SELECT count(*) FROM civicrm_contribution WHERE (contribution_status_id != 1 $deductible_type_clause) AND {$this->_componentClause}";
    $count = CRM_Core_DAO::singleValueQuery($query, CRM_Core_DAO::$_nullArray);
    if ($count != 0) {
      $msg = ts('Contribution need to match conditions below in order to generate receipt(and receipt serial id number)');
      $cond1 = ts('Contribution record must dedutible.(base on <a href="%1">Contribution type</a> settings)',
        array(1 => CRM_Utils_System::url('civicrm/admin/contribute/contributionType','reset=1'))
        );
      $cond2 = ts('Contribution record must completed.');
      $str = "<label>$msg</label>;
  <ul>
    <li>$cond1</li>
    <li>$cond2</li>
  </ul>";
      CRM_Core_Error::statusBounce($str);
    }

    // we have all the contribution ids, so now we get the contact ids
    parent::setContactIDs();
    $this->assign('single', $this->_single);

    $qfKey = CRM_Utils_Request::retrieve('qfKey', 'String', $this);
    require_once 'CRM/Utils/Rule.php';
    $urlParams = 'force=1';
    if (CRM_Utils_Rule::qfKey($qfKey)) {
      $urlParams .= "&qfKey=$qfKey";
    }

    $url = CRM_Utils_System::url('civicrm/contribute/search', $urlParams);
    $breadCrumb = array(array('url' => $url, 'title' => ts('Search Results')));

    CRM_Utils_System::appendBreadCrumb($breadCrumb);
    CRM_Utils_System::setTitle(ts('Print Contribution Receipts'));
  }

  /**
   * Build the form
   *
   * @access public
   *
   * @return void
   */
  public function buildQuickForm() {
    // make receipt target popup new tab
    $this->updateAttributes(array('target' => '_blank'));

    $options = self::getPrintingTypes();

    $this->addRadio( 'window_envelope',ts('Apply to window envelope'),$options,null,'<br/>',true );

    $this->assign('elements', array('window_envelope'));

    $this->addButtons(array(
        array('type' => 'next',
          'name' => ts('Download Receipt(s)'),
          'isDefault' => TRUE,
        ),
        array('type' => 'back',
          'name' => ts('Cancel'),
        ),
      )
    );
  }

  /**
   * process the form after the input has been submitted and validated
   *
   * @access public
   *
   * @return None
   */
  public function postProcess() {
    // get all the details needed to generate a receipt
    $contribIDs = implode(',', $this->_contributionIds);

    $details = &CRM_Contribute_Form_Task_Status::getDetails($contribIDs);
    $details = array_replace(array_flip($this->_contributionIds), $details);
    $params = $this->controller->exportValues($this->_name);

    self::makeReceipt($details, $params['window_envelope']);
    self::makePDF();
    CRM_Utils_System::civiExit();
  }

  public function pushFile($html) {
    // tmp directory
    file_put_contents($this->_tmpreceipt, $html, FILE_APPEND);
  }
  public function popFile() {
    $return = file_get_contents($this->_tmpreceipt);
    unlink($this->_tmpreceipt);
    return $return;
  }

  public function makePDF($download = TRUE) {
    $template = &CRM_Core_Smarty::singleton();
    $pages = self::popFile();
    $template->assign('pages', $pages);
    $pages = $template->fetch('CRM/common/Receipt.tpl');
    $pdf_real_filename = CRM_Utils_PDF_Utils::html2pdf($pages, 'Receipt.pdf', 'portrait', 'a4', $download);
    if (!$download) {
      return $pdf_real_filename;
    }
  }

  public function makeReceipt($details, $window_envelope = NULL) {
    $config = CRM_Core_Config::singleton();
    $tmpDir = empty($config->uploadDir) ? CIVICRM_TEMPLATE_COMPILEDIR : $config->uploadDir;
    $this->_tmpreceipt = tempnam($tmpDir, 'receipt');
    if (is_numeric($details)) {
      $details = &CRM_Contribute_Form_Task_Status::getDetails($details);
    }

    switch ($window_envelope) {
      case 'single_page_letter':
        $print_type = array(
          'copy' => ts('Copy Receipts'),
        );
        break;
      case 'single_page_letter_with_copied':
        $print_type = array(
          'copy' => ts('Copy Receipts'),
          'original' => ts('Original Receipts'),
        );
        break;
      case 'none':
      default:
        $print_type = array(
          'original' => ts('Original Receipts'),
          'copy' => ts('Copy Receipts'),
        );
        $window_envelope = '';
        break;
    }
    // domain info
    $domain = CRM_Core_BAO_Domain::getDomain();
    $location = $domain->getLocationValues();

    $baseIPN = new CRM_Core_Payment_BaseIPN();
    $config = &CRM_Core_Config::singleton();
    $count = 0;

    foreach ($details as $contribID => $detail) {
      $input = $ids = $objects = array();
      $input['component'] = $detail['component'];
      $ids['contact'] = $detail['contact'];
      $ids['contribution'] = $contribID;
      $ids['contributionRecur'] = NULL;
      $ids['contributionPage'] = NULL;
      $ids['membership'] = $detail['membership'];
      $ids['participant'] = $detail['participant'];
      $ids['event'] = $detail['event'];


      if (!$baseIPN->validateData($input, $ids, $objects, FALSE)) {
        CRM_Core_Error::fatal();
      }
      $contribution = &$objects['contribution'];

      $deductible = CRM_Contribute_BAO_ContributionType::deductible($contribution->contribution_type_id);
      if(!$deductible) {
        continue;
      }

      $template = &CRM_Core_Smarty::singleton();
      $template->assign('print_type', $print_type);
      $template->assign('single_page_letter', $window_envelope);
      $template->assign('domain_name', $domain->name);
      $template->assign('domain_email', $location['email'][1]['email']);
      $template->assign('domain_phone', $location['phone'][1]['phone']);
      $template->assign('domain_address', $location['address'][1]['display_text']);
      $template->assign('receiptOrgInfo', htmlspecialchars_decode($config->receiptOrgInfo));
      $template->assign('receiptDescription', htmlspecialchars_decode($config->receiptDescription));
      if (!empty($config->imageBigStampName)) {
        $template->assign('imageBigStampUrl', $config->imageUploadDir . $config->imageBigStampName);
      }
      if (!empty($config->imageSmallStampName)) {
        $template->assign('imageSmallStampUrl', $config->imageUploadDir . $config->imageSmallStampName);
      }

      // set some fake input values so we can reuse IPN code
      $input['amount'] = $contribution->total_amount;
      $input['is_test'] = $contribution->is_test;
      $input['fee_amount'] = $contribution->fee_amount;
      $input['net_amount'] = $contribution->net_amount;
      $input['trxn_id'] = $contribution->trxn_id;
      $input['trxn_date'] = isset($contribution->trxn_date) ? $contribution->trxn_date : NULL;

      $values = array();
      if ($count) {
        $html = '<div style="page-break-after: always;"></div>';
      }

      if(empty($contribution->receipt_id)){
        if(empty($contribution->receipt_date)){
          $contribution->receive_date = CRM_Utils_Date::isoToMysql($contribution->receive_date);
          $contribution->created_date = CRM_Utils_Date::isoToMysql($contribution->created_date);
          $contribution->receipt_date = date('YmdHis');
        }else{
          $contribution->receipt_date = date('YmdHis', strtotime($contribution->receipt_date));
        }
        $receipt_id = CRM_Contribute_BAO_Contribution::genReceiptID($contribution);
      }
      $html .= CRM_Contribute_BAO_Contribution::getReceipt($input, $ids, $objects, $values);

      // do not use array to prevent memory exhusting
      self::pushFile($html);
      // dump to file then retrive lately

      // reset template values before processing next transactions
      $template->clearTemplateVars();
      $count++;
      unset($html);
    }
  }

  static public function getPrintingTypes(){
    return array(
      'none' => ts('Contain copied receipt without address'),
      'single_page_letter' => ts('Single page with address letter'),
      'single_page_letter_with_copied' => ts('Single page with address letter and copied receipt'),
    );
  }
}

