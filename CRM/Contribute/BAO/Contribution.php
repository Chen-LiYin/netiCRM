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

require_once 'CRM/Utils/Money.php';
require_once 'CRM/Contribute/PseudoConstant.php';
require_once 'CRM/Contribute/DAO/Contribution.php';

require_once 'CRM/Core/BAO/CustomField.php';
require_once 'CRM/Core/BAO/CustomValue.php';
class CRM_Contribute_BAO_Contribution extends CRM_Contribute_DAO_Contribution {

  /**
   * static field for all the contribution information that we can potentially import
   *
   * @var array
   * @static
   */
  static $_importableFields = NULL;

  /**
   * static field for all the contribution information that we can potentially export
   *
   * @var array
   * @static
   */
  static $_exportableFields = NULL;

  function __construct() {
    parent::__construct();
  }

  /**
   * takes an associative array and creates a contribution object
   *
   * the function extract all the params it needs to initialize the create a
   * contribution object. the params array could contain additional unused name/value
   * pairs
   *
   * @param array  $params (reference ) an assoc array of name/value pairs
   * @param array $ids    the array that holds all the db ids
   *
   * @return object CRM_Contribute_BAO_Contribution object
   * @access public
   * @static
   */
  static function add(&$params, &$ids) {
    if (empty($params)) {
      return;
    }

    $duplicates = array();
    if (self::checkDuplicate($params, $duplicates,
        CRM_Utils_Array::value('contribution', $ids)
      )) {
      $error = &CRM_Core_Error::singleton();
      $d = implode(', ', $duplicates);
      $error->push(CRM_Core_Error::DUPLICATE_CONTRIBUTION,
        'Fatal',
        array($d),
        "Duplicate error - existing contribution record(s) have a matching Transaction ID or Invoice ID. Contribution record ID(s) are: $d"
      );
      return $error;
    }

    // first clean up all the money fields
    $moneyFields = array('total_amount',
      'net_amount',
      'fee_amount',
      'non_deductible_amount',
    );
    //if priceset is used, no need to cleanup money
    if (CRM_UTils_Array::value('skipCleanMoney', $params)) {
      unset($moneyFields[0]);
    }

    foreach ($moneyFields as $field) {
      if (isset($params[$field])) {
        $params[$field] = CRM_Utils_Rule::cleanMoney($params[$field]);
      }
    }

    if (CRM_Utils_Array::value('payment_instrument_id', $params)) {
      require_once 'CRM/Contribute/PseudoConstant.php';
      $paymentInstruments = CRM_Contribute_PseudoConstant::paymentInstrument('name');
      if ($params['payment_instrument_id'] != array_search('Check', $paymentInstruments)) {
        $params['check_number'] = 'null';
      }
    }

    require_once 'CRM/Utils/Hook.php';
    if (CRM_Utils_Array::value('contribution', $ids)) {
      CRM_Utils_Hook::pre('edit', 'Contribution', $ids['contribution'], $params);
    }
    else {
      CRM_Utils_Hook::pre('create', 'Contribution', NULL, $params);
    }

    $contribution = new CRM_Contribute_BAO_Contribution();
    $contribution->copyValues($params);

    $contribution->id = CRM_Utils_Array::value('contribution', $ids);

    // make sure we always have created date when adding new record
    if (!$contribution->id && !$params['created_date']) {
      $contribution->created_date = date('YmdHis');
    }

    // also add financial_trxn details as part of fix for CRM-4724
    $contribution->trxn_result_code = CRM_Utils_Array::value('trxn_result_code', $params);
    $contribution->payment_processor = CRM_Utils_Array::value('payment_processor', $params);

    require_once 'CRM/Utils/Rule.php';
    if (!CRM_Utils_Rule::currencyCode($contribution->currency)) {
      require_once 'CRM/Core/Config.php';
      $config = CRM_Core_Config::singleton();
      $contribution->currency = $config->defaultCurrency;
    }

    $result = $contribution->save();

    // reset the group contact cache for this group
    require_once 'CRM/Contact/BAO/GroupContactCache.php';
    CRM_Contact_BAO_GroupContactCache::remove();

    if (CRM_Utils_Array::value('contribution', $ids)) {
      CRM_Utils_Hook::post('edit', 'Contribution', $contribution->id, $contribution);
    }
    else {
      CRM_Utils_Hook::post('create', 'Contribution', $contribution->id, $contribution);
    }

    return $result;
  }

  /**
   * Given the list of params in the params array, fetch the object
   * and store the values in the values array
   *
   * @param array $params input parameters to find object
   * @param array $values output values of the object
   * @param array $ids    the array that holds all the db ids
   *
   * @return CRM_Contribute_BAO_Contribution|null the found object or null
   * @access public
   * @static
   */
  static function &getValues(&$params, &$values, &$ids) {
    if (empty($params)) {
      return NULL;
    }
    $contribution = new CRM_Contribute_BAO_Contribution();

    $contribution->copyValues($params);

    if ($contribution->find(TRUE)) {
      $ids['contribution'] = $contribution->id;

      CRM_Core_DAO::storeValues($contribution, $values);

      return $contribution;
    }
    return NULL;
  }

  /**
   * takes an associative array and creates a contribution object
   *
   * @param array $params (reference ) an assoc array of name/value pairs
   * @param array $ids    the array that holds all the db ids
   *
   * @return object CRM_Contribute_BAO_Contribution object
   * @access public
   * @static
   */
  static function &create(&$params, &$ids) {
    require_once 'CRM/Utils/Money.php';
    require_once 'CRM/Utils/Date.php';
    require_once 'CRM/Contribute/PseudoConstant.php';

    // FIXME: a cludgy hack to fix the dates to MySQL format
    $dateFields = array('receive_date', 'cancel_date', 'receipt_date', 'thankyou_date', 'created_date');
    foreach ($dateFields as $df) {
      if (isset($params[$df])) {
        $params[$df] = CRM_Utils_Date::isoToMysql($params[$df]);
      }
    }

    // calculate receipt id
    if (!$params['receipt_id'] && empty($params['skipRecentView']) && !empty($params['receipt_date'])) {
      $params['receipt_id'] = CRM_Contribute_BAO_Contribution::genReceiptID($params, FALSE);
    }

    require_once 'CRM/Core/Transaction.php';
    $transaction = new CRM_Core_Transaction();

    $contribution = self::add($params, $ids);

    if (is_a($contribution, 'CRM_Core_Error')) {
      $transaction->rollback();
      return $contribution;
    }

    $params['contribution_id'] = $contribution->id;

    if (CRM_Utils_Array::value('custom', $params) &&
      is_array($params['custom'])
    ) {
      require_once 'CRM/Core/BAO/CustomValueTable.php';
      CRM_Core_BAO_CustomValueTable::store($params['custom'], 'civicrm_contribution', $contribution->id);
    }

    $session = &CRM_Core_Session::singleton();

    if (CRM_Utils_Array::value('note', $params)) {
      require_once 'CRM/Core/BAO/Note.php';

      $noteParams = array(
        'entity_table' => 'civicrm_contribution',
        'note' => $params['note'],
        'entity_id' => $contribution->id,
        'contact_id' => $session->get('userID'),
        'modified_date' => date('Ymd'),
      );
      if (!$noteParams['contact_id']) {
        $noteParams['contact_id'] = $params['contact_id'];
      }

      CRM_Core_BAO_Note::add($noteParams,
        CRM_Utils_Array::value('note', $ids)
      );
    }

    // check if activity record exist for this contribution, if
    // not add activity
    require_once "CRM/Activity/DAO/Activity.php";
    $activity = new CRM_Activity_DAO_Activity();
    $activity->source_record_id = $contribution->id;
    $activity->activity_type_id = CRM_Core_OptionGroup::getValue('activity_type',
      'Contribution',
      'name'
    );
    if (!$activity->find()) {
      require_once "CRM/Activity/BAO/Activity.php";
      CRM_Activity_BAO_Activity::addActivity($contribution, 'Offline');
    }


    if (CRM_Utils_Array::value('soft_credit_to', $params)) {
      $csParams = array();
      if ($id = CRM_Utils_Array::value('softID', $params)) {
        $csParams['id'] = $params['softID'];
      }
      if ($pcpId = CRM_Utils_Array::value('pcp_id', $params)) {
        $pcp = new CRM_Contribute_DAO_PCP();
        $pcp->id = $pcpId;
        if ($pcp->find()) {
          $csParams['pcp_id'] = $pcpId;
        }
        $pcp->free();
      }
      $csParams['pcp_display_in_roll'] = !empty($params['pcp_display_in_roll']) ? 1 : 0;
      foreach (array('pcp_roll_nickname', 'pcp_personal_note') as $val) {
        if (CRM_Utils_Array::value($val, $params)) {
          $csParams[$val] = $params[$val];
        }
      }
      $csParams['contribution_id'] = $contribution->id;
      $csParams['contact_id'] = $params['soft_credit_to'];
      // first stage: we register whole amount as credited to given person
      $csParams['amount'] = $contribution->total_amount;

      self::addSoftContribution($csParams);
    }

    $transaction->commit();

    // do not add to recent items for import, CRM-4399
    if (!CRM_Utils_Array::value('skipRecentView', $params)) {
      require_once 'CRM/Utils/Recent.php';
      require_once 'CRM/Contribute/PseudoConstant.php';
      require_once 'CRM/Contact/BAO/Contact.php';
      $url = CRM_Utils_System::url('civicrm/contact/view/contribution',
        "action=view&reset=1&id={$contribution->id}&cid={$contribution->contact_id}&context=home"
      );

      $contributionTypes = CRM_Contribute_PseudoConstant::contributionType();
      $title = CRM_Contact_BAO_Contact::displayName($contribution->contact_id) . ' - (' . CRM_Utils_Money::format($contribution->total_amount, $contribution->currency) . ' ' . ' - ' . $contributionTypes[$contribution->contribution_type_id] . ')';

      $recentOther = array();
      if (CRM_Core_Permission::checkActionPermission('CiviContribute', CRM_Core_Action::UPDATE)) {
        $recentOther['editUrl'] = CRM_Utils_System::url('civicrm/contact/view/contribution',
          "action=update&reset=1&id={$contribution->id}&cid={$contribution->contact_id}&context=home"
        );
      }

      if (CRM_Core_Permission::checkActionPermission('CiviContribute', CRM_Core_Action::DELETE)) {
        $recentOther['deleteUrl'] = CRM_Utils_System::url('civicrm/contact/view/contribution',
          "action=delete&reset=1&id={$contribution->id}&cid={$contribution->contact_id}&context=home"
        );
      }

      // add the recently created Contribution
      CRM_Utils_Recent::add($title,
        $url,
        $contribution->id,
        'Contribution',
        $contribution->contact_id,
        NULL,
        $recentOther
      );
    }

    return $contribution;
  }

  /**
   * This function is to make a copy of a Contribution
   * including all related payment record of participant and membership
   *
   * @param int $id the contribution id
   *
   * @return void
   * @access public
   */
  static function copy($id) {
    $exclude = array(
      'payment_processor_id' => 'null',
      'payment_instrument_id' => 'null',
      'receive_date' => 'null',
      'trxn_id' => 'null',
      'invoice_id' => 'null',
      'cancel_date' => 'null',
      'cancel_reason' => 'null',
      'receipt_date' => 'null',
      'thankyou_date' => 'null',
      'contribution_recur_id' => 'null',
      'check_number' => 'null',
      'receipt_id' => 'null',
      'created_date' => date('YmdHis'),
      'contribution_status_id' => 2,
    );
    $copyContrib = &CRM_Core_DAO::copyGeneric('CRM_Contribute_DAO_Contribution',
      array('id' => $id),
      $exclude
    );

    //copy custom data
    $extends = array('Contribution');
    $groupTree = CRM_Core_BAO_CustomGroup::getGroupDetail(NULL, NULL, $extends);
    if ($groupTree) {
      foreach ($groupTree as $groupID => $group) {
        $table[$groupTree[$groupID]['table_name']] = array('entity_id');
        foreach ($group['fields'] as $fieldID => $field) {
          if ($field['data_type'] == 'File') {
            continue;
          }
          $table[$groupTree[$groupID]['table_name']][] = $groupTree[$groupID]['fields'][$fieldID]['column_name'];
        }
      }

      foreach ($table as $tableName => $tableColumns) {
        $insert = 'INSERT INTO ' . $tableName . ' (' . implode(', ', $tableColumns) . ') ';
        $tableColumns[0] = $copyContrib->id;
        $select = 'SELECT ' . implode(', ', $tableColumns);
        $from = ' FROM ' . $tableName;
        $where = " WHERE {$tableName}.entity_id = {$id}";
        $query = $insert . $select . $from . $where;
        $dao = CRM_Core_DAO::executeQuery($query, CRM_Core_DAO::$_nullArray);
      }
    }

    // copy participant payment
    $copyPP = &CRM_Core_DAO::copyGeneric('CRM_Event_DAO_ParticipantPayment',
      array(
        'contribution_id' => $id,
      ),
      array('contribution_id' => $copyContrib->id)
    );

    // copy membership payment
    $copyMP = &CRM_Core_DAO::copyGeneric('CRM_Member_DAO_MembershipPayment',
      array(
        'contribution_id' => $id,
      ),
      array('contribution_id' => $copyContrib->id)
    );

    CRM_Utils_Hook::copy('Contribution', $copyContrib);

    return $copyContrib;
  }

  /**
   * Get the values for pseudoconstants for name->value and reverse.
   *
   * @param array   $defaults (reference) the default values, some of which need to be resolved.
   * @param boolean $reverse  true if we want to resolve the values in the reverse direction (value -> name)
   *
   * @return void
   * @access public
   * @static
   */
  static function resolveDefaults(&$defaults, $reverse = FALSE) {
    require_once 'CRM/Contribute/PseudoConstant.php';
    self::lookupValue($defaults, 'contribution_type', CRM_Contribute_PseudoConstant::contributionType(), $reverse);
    self::lookupValue($defaults, 'payment_instrument', CRM_Contribute_PseudoConstant::paymentInstrument(), $reverse);
    self::lookupValue($defaults, 'contribution_status', CRM_Contribute_PseudoConstant::contributionStatus(), $reverse);
    self::lookupValue($defaults, 'pcp', CRM_Contribute_PseudoConstant::pcPage(), $reverse);
  }

  /**
   * This function is used to convert associative array names to values
   * and vice-versa.
   *
   * This function is used by both the web form layer and the api. Note that
   * the api needs the name => value conversion, also the view layer typically
   * requires value => name conversion
   */
  static function lookupValue(&$defaults, $property, &$lookup, $reverse) {
    $id = $property . '_id';

    $src = $reverse ? $property : $id;
    $dst = $reverse ? $id : $property;

    if (!array_key_exists($src, $defaults)) {
      return FALSE;
    }

    $look = $reverse ? array_flip($lookup) : $lookup;

    if (is_array($look)) {
      if (!array_key_exists($defaults[$src], $look)) {
        return FALSE;
      }
    }
    $defaults[$dst] = $look[$defaults[$src]];
    return TRUE;
  }

  /**
   * Takes a bunch of params that are needed to match certain criteria and
   * retrieves the relevant objects. We'll tweak this function to be more
   * full featured over a period of time. This is the inverse function of
   * create.  It also stores all the retrieved values in the default array
   *
   * @param array $params   (reference ) an assoc array of name/value pairs
   * @param array $defaults (reference ) an assoc array to hold the name / value pairs
   *                        in a hierarchical manner
   * @param array $ids      (reference) the array that holds all the db ids
   *
   * @return object CRM_Contribute_BAO_Contribution object
   * @access public
   * @static
   */
  static function retrieve(&$params, &$defaults, &$ids) {
    $contribution = CRM_Contribute_BAO_Contribution::getValues($params, $defaults, $ids);
    return $contribution;
  }

  /**
   * combine all the importable fields from the lower levels object
   *
   * The ordering is important, since currently we do not have a weight
   * scheme. Adding weight is super important and should be done in the
   * next week or so, before this can be called complete.
   *
   * @return array array of importable Fields
   * @access public
   */
  function &importableFields($contacType = 'Individual', $status = TRUE) {
    if (!self::$_importableFields) {
      if (!self::$_importableFields) {
        self::$_importableFields = array();
      }

      if (!$status) {
        $fields = array('' => array('title' => ts('- do not import -')));
      }
      else {
        $fields = array('' => array('title' => ts('- Contribution Fields -')));
      }

      $note = CRM_Core_DAO_Note::import();
      $tmpFields = CRM_Contribute_DAO_Contribution::import();
      $tmpFields['contribution_contact_id']['title'] = ts('Contacts') . '::' .$tmpFields['contribution_contact_id']['title'];
      unset($tmpFields['option_value']);
      $optionFields = CRM_Core_OptionValue::getFields($mode = 'contribute');

      $contactFields = array();
      $tmpContactFields = CRM_Contact_BAO_Contact::importableFields($contacType, NULL);
      $contactFieldsIgnore = array('id', 'note', 'do_not_import', 'contact_sub_type', 'group_name', 'tag_name');

      // Using new Dedupe rule.
      $ruleParams = array(
        'contact_type' => $contacType,
        'level' => 'Strict',
      );
      foreach ($tmpContactFields as $fieldName => $fieldData) {
        $fieldName = trim($fieldName);
        if (in_array($fieldName, $contactFieldsIgnore)) {
          continue;
        }
        $index = $fieldName;
        $contactFields[$index] = $fieldData;
        $contactFields[$index]['title'] = ts('Contacts') . '::' .$contactFields[$index]['title'];
      }

      $fields = array_merge($fields, CRM_Contribute_DAO_ContributionType::export());
      $fields = array_merge($fields, $tmpFields);
      $fields = array_merge($fields, CRM_Core_BAO_CustomField::getFieldsForImport('Contribution'));
      $fields = array_merge($fields, $optionFields);
      $fields = array_merge($fields, $note);
      $fields = array_merge($fields, $contactFields);
      self::$_importableFields = $fields;
    }
    return self::$_importableFields;
  }

  function &exportableFields() {
    if (!self::$_exportableFields) {
      if (!self::$_exportableFields) {
        self::$_exportableFields = array();
      }
      require_once 'CRM/Core/OptionValue.php';
      require_once 'CRM/Contribute/DAO/Product.php';
      require_once 'CRM/Contribute/DAO/ContributionProduct.php';
      require_once 'CRM/Contribute/DAO/ContributionType.php';

      $impFields = CRM_Contribute_DAO_Contribution::export();
      $expFieldProduct = CRM_Contribute_DAO_Product::export();
      $expFieldsContrib = CRM_Contribute_DAO_ContributionProduct::export();
      $typeField = CRM_Contribute_DAO_ContributionType::export();
      $optionField = CRM_Core_OptionValue::getFields($mode = 'contribute');
      $contributionStatus = array('contribution_status' => array('title' => ts('Contribution Status'),
          'name' => 'contribution_status',
          'data_type' => CRM_Utils_Type::T_STRING,
        ));

      $contributionNote = array('contribution_note' => array('title' => ts('Contribution Note'),
          'name' => 'contribution_note',
          'data_type' => CRM_Utils_Type::T_TEXT,
        ));

      $contributionRecurId = array('contribution_recur_id' => array('title' => ts('Recurring Contributions ID'),
          'name' => 'contribution_recur_id',
          'where' => 'civicrm_contribution.contribution_recur_id',
          'data_type' => CRM_Utils_Type::T_INT,
        ));

      $fields = array_merge($impFields, $typeField, $contributionStatus, $optionField, $expFieldProduct,
        $expFieldsContrib, $contributionNote, $contributionRecurId,
        CRM_Core_BAO_CustomField::getFieldsForImport('Contribution')
      );

      self::$_exportableFields = $fields;
    }
    return self::$_exportableFields;
  }

  function getTotalAmountAndCount($status = NULL, $startDate = NULL, $endDate = NULL) {

    $where = array();
    switch ($status) {
      case 'Valid':
        $where[] = 'contribution_status_id = 1';
        break;

      case 'Cancelled':
        $where[] = 'contribution_status_id = 3';
        break;
    }

    if ($startDate) {
      $where[] = "receive_date >= '" . CRM_Utils_Type::escape($startDate, 'Timestamp') . "'";
    }
    if ($endDate) {
      $where[] = "receive_date <= '" . CRM_Utils_Type::escape($endDate, 'Timestamp') . "'";
    }

    $whereCond = implode(' AND ', $where);

    $query = "
    SELECT  sum( total_amount ) as total_amount, 
            count( civicrm_contribution.id ) as total_count, 
            currency
      FROM  civicrm_contribution
INNER JOIN  civicrm_contact contact ON ( contact.id = civicrm_contribution.contact_id ) 
     WHERE  $whereCond 
       AND  ( is_test = 0 OR is_test IS NULL )
       AND  contact.is_deleted = 0
  GROUP BY  currency
";

    $dao = CRM_Core_DAO::executeQuery($query, CRM_Core_DAO::$_nullArray);
    $amount = array();
    $count = 0;
    require_once 'CRM/Utils/Money.php';
    while ($dao->fetch()) {
      $count += $dao->total_count;
      $amount[] = CRM_Utils_Money::format($dao->total_amount, $dao->currency);
    }
    if ($count) {
      return array('amount' => implode(', ', $amount),
        'count' => $count,
      );
    }
    return NULL;
  }

  /**
   * Delete the indirect records associated with this contribution first
   *
   * @return $results no of deleted Contribution on success, false otherwise
   * @access public
   * @static
   */
  static function deleteContribution($id) {
    require_once 'CRM/Utils/Hook.php';
    CRM_Utils_Hook::pre('delete', 'Contribution', $id, CRM_Core_DAO::$_nullArray);

    require_once 'CRM/Core/Transaction.php';
    $transaction = new CRM_Core_Transaction();

    $results = NULL;
    //delete activity record
    require_once "CRM/Activity/BAO/Activity.php";
    $params = array('source_record_id' => $id,
      // activity type id for contribution
      'activity_type_id' => 6,
    );

    CRM_Activity_BAO_Activity::deleteActivity($params);

    //delete billing address if exists for this contribution.
    self::deleteAddress($id);

    //update pledge and pledge payment, CRM-3961
    require_once 'CRM/Pledge/BAO/Payment.php';
    CRM_Pledge_BAO_Payment::resetPledgePayment($id);

    // remove entry from civicrm_price_set_entity, CRM-5095
    require_once 'CRM/Price/BAO/Set.php';
    if (CRM_Price_BAO_Set::getFor('civicrm_contribution', $id)) {
      CRM_Price_BAO_Set::removeFrom('civicrm_contribution', $id);
    }
    // cleanup line items.
    require_once 'CRM/Price/BAO/Field.php';
    require_once 'CRM/Event/BAO/ParticipantPayment.php';
    $participantId = CRM_Core_DAO::getFieldValue('CRM_Event_DAO_ParticipantPayment', $id, 'participant_id', 'contribution_id');

    // delete any related entity_financial_trxn and financial_trxn records.
    require_once 'CRM/Core/BAO/FinancialTrxn.php';
    CRM_Core_BAO_FinancialTrxn::deleteFinancialTrxn($id, 'civicrm_contribution');

    if ($participantId) {
      require_once 'CRM/Price/BAO/LineItem.php';
      CRM_Price_BAO_LineItem::deleteLineItems($participantId, 'civicrm_participant');
    }
    else {
      require_once 'CRM/Price/BAO/LineItem.php';
      CRM_Price_BAO_LineItem::deleteLineItems($id, 'civicrm_contribution');
    }

    //delete note.
    require_once 'CRM/Core/BAO/Note.php';
    $note = CRM_Core_BAO_Note::getNote($id, 'civicrm_contribution');
    $noteId = key($note);
    if ($noteId) {
      CRM_Core_BAO_Note::del($noteId, FALSE);
    }

    $dao = new CRM_Contribute_DAO_Contribution();
    $dao->id = $id;

    $results = $dao->delete();

    $transaction->commit();

    CRM_Utils_Hook::post('delete', 'Contribution', $dao->id, $dao);

    // delete the recently created Contribution
    require_once 'CRM/Utils/Recent.php';
    $contributionRecent = array(
      'id' => $id,
      'type' => 'Contribution',
    );
    CRM_Utils_Recent::del($contributionRecent);

    return $results;
  }

  /**
   * Check if there is a contribution with the same trxn_id or invoice_id
   *
   * @param array  $params (reference ) an assoc array of name/value pairs
   * @param array  $duplicates (reference ) store ids of duplicate contribs
   *
   * @return boolean true if duplicate, false otherwise
   * @access public
   * static  */
  static function checkDuplicate($input, &$duplicates, $id = NULL) {
    if (!$id) {
      $id = CRM_Utils_Array::value('id', $input);
    }
    $trxn_id = CRM_Utils_Array::value('trxn_id', $input);
    $invoice_id = CRM_Utils_Array::value('invoice_id', $input);

    $clause = array();
    $input = array();

    if ($trxn_id) {
      $clause[] = "trxn_id = %1";
      $input[1] = array($trxn_id, 'String');
    }

    if ($invoice_id) {
      $clause[] = "invoice_id = %2";
      $input[2] = array($invoice_id, 'String');
    }

    if (empty($clause)) {
      return FALSE;
    }

    $clause = implode(' OR ', $clause);
    if ($id) {
      $clause = "( $clause ) AND id != %3";
      $input[3] = array($id, 'Integer');
    }

    $query = "SELECT id FROM civicrm_contribution WHERE $clause";
    $dao = &CRM_Core_DAO::executeQuery($query, $input);
    $result = FALSE;
    while ($dao->fetch()) {
      $duplicates[] = $dao->id;
      $result = TRUE;
    }
    return $result;
  }

  /**
   * Check contribution related object can still process payment
   *
   * @param array  $id of contribution
   * @param array  $ids from getComponentDetails
   * @param object $form from form
   *
   * @return boolean true if 
   * @access public
   * static  */
  static function checkPaymentAvailable($id, $ids, $form = NULL){
    $return = FALSE;
    $mode = isset($form->_mode) ? $form->_mode : 'live';
    switch($ids['component']){
      case 'event':
        $pending_status = CRM_Event_PseudoConstant::participantStatus(NULL, "class = 'Pending'", 'name'); 
        $negative_status = CRM_Event_PseudoConstant::participantStatus(NULL, "class = 'Negative'", 'name'); 
        $positive_status = CRM_Event_PseudoConstant::participantStatus(NULL, "is_counted = 1", 'name');
        $participant_status_id = CRM_Core_DAO::getFieldValue("CRM_Event_DAO_Participant", $ids['participant'], 'status_id');
        $contribution_status_id = CRM_Core_DAO::getFieldValue("CRM_Contribute_DAO_Contribution", $id, 'contribution_status_id');
        $registration_end_date = CRM_Core_DAO::getFieldValue("CRM_Event_DAO_Event", $ids['event'], 'registration_end_date');

        // event not end
        if(empty($registration_end_date) || strtotime($registration_end_date) > time()){
          $is_full = CRM_Event_BAO_Participant::eventFull($ids['event']);
          if(!empty($pending_status[$participant_status_id])){
            // full but pending status can count in
            if($is_full){
              if(!empty($positive_status[$participant_status_id])){
                $return = TRUE;
              }
            }
            // not full and contribution status not fail
            else{
              if($contribution_status_id == 2){
                $return = TRUE;
              }
            }
          }
          elseif(!empty($negative_status[$participant_status_id])){
            // failed, but event not full
            if(empty($is_full) && $contribution_status_id == 4){
              $return = TRUE;
            }
          }
        }
        if($return){
          $pp = CRM_Core_DAO::getFieldValue("CRM_Event_DAO_Event", $ids['event'], 'payment_processor');
          $ppids = explode(CRM_Core_DAO::VALUE_SEPARATOR, $pp);
          $pps = CRM_Core_BAO_PaymentProcessor::getPayments($ppids, $mode);
          if($form){
            $form->set('paymentProcessors', $pps);
          }
        }
        break;
      case 'contribute':
        $page_id = CRM_Core_DAO::getFieldValue("CRM_Contribute_DAO_Contribution", $id, 'contribution_page_id');
        if($page_id){
          if($ids['membership']){
          
          }
          else{
            $return = TRUE;
          }
        }
        if($return){
          $pp = CRM_Core_DAO::getFieldValue("CRM_Contribute_DAO_ContributionPage", $page_id, 'payment_processor');
          $ppids = explode(CRM_Core_DAO::VALUE_SEPARATOR, $pp);
          $pps = CRM_Core_BAO_PaymentProcessor::getPayments($ppids, $mode);
          if($form){
            $form->set('paymentProcessors', $pps);
          }
        }
        break;
    }
    return $return;
  }


  /**
   * takes an associative array and creates a contribution_product object
   *
   * the function extract all the params it needs to initialize the create a
   * contribution_product object. the params array could contain additional unused name/value
   * pairs
   *
   * @param array  $params (reference ) an assoc array of name/value pairs
   *
   * @return object CRM_Contribute_BAO_ContributionProduct object
   * @access public
   * @static
   */
  static function addPremium(&$params) {

    require_once 'CRM/Contribute/DAO/ContributionProduct.php';
    $contributionProduct = new CRM_Contribute_DAO_ContributionProduct();
    $contributionProduct->copyValues($params);
    return $contributionProduct->save();
  }

  /**
   * Function to get list of contribution fields for profile
   * For now we only allow custom contribution fields to be in
   * profile
   *
   * @return return the list of contribution fields
   * @static
   * @access public
   */
  static function getContributionFields() {
    $contributionFields = &CRM_Contribute_DAO_Contribution::export();
    require_once 'CRM/Core/OptionValue.php';
    $contributionFields = array_merge($contributionFields, CRM_Core_OptionValue::getFields($mode = 'contribute'));
    require_once 'CRM/Contribute/DAO/ContributionType.php';
    $contributionFields = array_merge($contributionFields, CRM_Contribute_DAO_ContributionType::export());

    foreach ($contributionFields as $key => $var) {
      if ($key == 'contribution_contact_id') {
        continue;
      }
      $fields[$key] = $var;
    }

    $fields = array_merge($fields, CRM_Core_BAO_CustomField::getFieldsForImport('Contribution'));
    return $fields;
  }

  static function getCurrentandGoalAmount($pageID) {
    $query = "
SELECT p.goal_amount as goal, sum( c.total_amount ) as total
  FROM civicrm_contribution_page p,
       civicrm_contribution      c
 WHERE p.id = c.contribution_page_id
   AND p.id = %1
   AND c.cancel_date is null
GROUP BY p.id
";

    $config = CRM_Core_Config::singleton();
    $params = array(1 => array($pageID, 'Integer'));
    $dao = &CRM_Core_DAO::executeQuery($query, $params);

    if ($dao->fetch()) {
      return array($dao->goal, $dao->total);
    }
    else {
      return array(NULL, NULL);
    }
  }

  /**
   * Function to create is honor of
   *
   * @param array $params  associated array of fields (by reference)
   * @param int   $honorId honor Id
   *
   * @return contact id
   */
  function createHonorContact(&$params, $honorId = NULL) {
    $honorParams = array('first_name' => $params["honor_first_name"],
      'last_name' => $params["honor_last_name"],
      'prefix_id' => $params["honor_prefix_id"],
      'email-Primary' => $params["honor_email"],
    );
    if (!$honorId) {
      require_once "CRM/Core/BAO/UFGroup.php";
      $honorParams['email'] = $params["honor_email"];

      require_once 'CRM/Dedupe/Finder.php';
      $dedupeParams = CRM_Dedupe_Finder::formatParams($honorParams, 'Individual');
      $dedupeParams['check_permission'] = FALSE;
      $ids = CRM_Dedupe_Finder::dupesByParams($dedupeParams, 'Individual');

      // if we find more than one contact, use the first one
      $honorId = CRM_Utils_Array::value(0, $ids);
    }

    $contact = &CRM_Contact_BAO_Contact::createProfileContact($honorParams,
      CRM_Core_DAO::$_nullArray,
      $honorId
    );
    return $contact;
  }

  /**
   * Function to get list of contribution In Honor of contact Ids
   *
   * @param int $honorId In Honor of Contact ID
   *
   * @return return the list of contribution fields
   *
   * @access public
   * @static
   */
  static function getHonorContacts($honorId) {
    $params = array();
    require_once 'CRM/Contribute/DAO/Contribution.php';
    $honorDAO = new CRM_Contribute_DAO_Contribution();
    $honorDAO->honor_contact_id = $honorId;
    $honorDAO->find();

    require_once 'CRM/Contribute/PseudoConstant.php';
    $status = CRM_Contribute_Pseudoconstant::contributionStatus($honorDAO->contribution_status_id);
    $type = CRM_Contribute_Pseudoconstant::contributionType();

    while ($honorDAO->fetch()) {
      $params[$honorDAO->id]['honorId'] = $honorDAO->contact_id;
      $params[$honorDAO->id]['display_name'] = CRM_Core_DAO::getFieldValue('CRM_Contact_DAO_Contact', $honorDAO->contact_id, 'display_name');
      $params[$honorDAO->id]['type'] = $type[$honorDAO->contribution_type_id];
      $params[$honorDAO->id]['type_id'] = $honorDAO->contribution_type_id;
      $params[$honorDAO->id]['amount'] = CRM_Utils_Money::format($honorDAO->total_amount, $honorDAO->currency);
      $params[$honorDAO->id]['source'] = $honorDAO->source;
      $params[$honorDAO->id]['receive_date'] = $honorDAO->receive_date;
      $params[$honorDAO->id]['contribution_status'] = CRM_Utils_Array::value($honorDAO->contribution_status_id, $status);
    }

    return $params;
  }

  /**
   * function to get the sort name of a contact for a particular contribution
   *
   * @param  int    $id      id of the contribution
   *
   * @return null|string     sort name of the contact if found
   * @static
   * @access public
   */
  static function sortName($id) {
    $id = CRM_Utils_Type::escape($id, 'Integer');

    $query = "
SELECT civicrm_contact.sort_name
FROM   civicrm_contribution, civicrm_contact
WHERE  civicrm_contribution.contact_id = civicrm_contact.id
  AND  civicrm_contribution.id = {$id}
";
    return CRM_Core_DAO::singleValueQuery($query, CRM_Core_DAO::$_nullArray);
  }

  static function annual($contactID) {
    if (is_array($contactID)) {
      $contactIDs = implode(',', $contactID);
    }
    else {
      $contactIDs = $contactID;
    }

    $config = CRM_Core_Config::singleton();
    $startDate = $endDate = NULL;

    $currentMonth = date('m');
    $currentDay = date('d');
    if ((int ) $config->fiscalYearStart['M'] > $currentMonth ||
      ((int ) $config->fiscalYearStart['M'] == $currentMonth &&
        (int ) $config->fiscalYearStart['d'] > $currentDay
      )
    ) {
      $year = date('Y') - 1;
    }
    else {
      $year = date('Y');
    }
    $nextYear = $year + 1;

    if ($config->fiscalYearStart) {
      if ($config->fiscalYearStart['M'] < 10) {
        $config->fiscalYearStart['M'] = '0' . $config->fiscalYearStart['M'];
      }
      if ($config->fiscalYearStart['d'] < 10) {
        $config->fiscalYearStart['d'] = '0' . $config->fiscalYearStart['d'];
      }
      $monthDay = $config->fiscalYearStart['M'] . $config->fiscalYearStart['d'];
    }
    else {
      $monthDay = '0101';
    }
    $startDate = "$year$monthDay";
    $endDate = "$nextYear$monthDay";

    $query = "
SELECT count(*) as count,
       sum(total_amount) as amount,
       avg(total_amount) as average,
       currency
  FROM civicrm_contribution b
 WHERE b.contact_id IN ( $contactIDs )
   AND b.contribution_status_id = 1
   AND b.is_test = 0
   AND b.receive_date >= $startDate
   AND b.receive_date <  $endDate
GROUP BY currency
";
    $dao = &CRM_Core_DAO::executeQuery($query, CRM_Core_DAO::$_nullArray);
    $count = 0;
    $amount = $average = array();
    require_once 'CRM/Utils/Money.php';
    while ($dao->fetch()) {
      if ($dao->count > 0 && $dao->amount > 0) {
        $count += $dao->count;
        $amount[] = CRM_Utils_Money::format($dao->amount, $dao->currency);
        $average[] = CRM_Utils_Money::format($dao->average, $dao->currency);
      }
    }
    if ($count > 0) {
      return array($count,
        implode(',&nbsp;', $amount),
        implode(',&nbsp;', $average),
      );
    }
    return array(0, 0, 0);
  }

  /**
   * Check if there is a contribution with the params passed in.
   * Used for trxn_id,invoice_id and contribution_id
   *
   * @param array  $params (reference ) an assoc array of name/value pairs
   *
   * @return array contribution id if success else NULL
   * @access public
   * static  */
  static function checkDuplicateIds($params) {
    $dao = new CRM_Contribute_DAO_Contribution();

    $clause = array();
    $input = array();
    foreach ($params as $k => $v) {
      if ($v) {
        $clause[] = "$k = '$v'";
      }
    }
    $clause = implode(' AND ', $clause);
    $query = "SELECT id FROM civicrm_contribution WHERE $clause";
    $dao = &CRM_Core_DAO::executeQuery($query, $input);

    while ($dao->fetch()) {
      $result = $dao->id;
      return $result;
    }
    return NULL;
  }

  /**
   * Function to get the contribution details for component export
   *
   * @param int     $exportMode export mode
   * @param string  $componentIds  component ids
   *
   * @return array associated array
   *
   * @static
   * @access public
   */
  static function getContributionDetails($exportMode, $componentIds) {
    require_once "CRM/Export/Form/Select.php";

    $paymentDetails = array();
    $componentClause = ' IN ( ' . implode(',', $componentIds) . ' ) ';

    if ($exportMode == CRM_Export_Form_Select::EVENT_EXPORT) {
      $componentSelect = " civicrm_participant_payment.participant_id id";
      $additionalClause = "
INNER JOIN civicrm_participant_payment ON (civicrm_contribution.id = civicrm_participant_payment.contribution_id
AND civicrm_participant_payment.participant_id {$componentClause} )
";
    }
    elseif ($exportMode == CRM_Export_Form_Select::MEMBER_EXPORT) {
      $componentSelect = " civicrm_membership_payment.membership_id id";
      $additionalClause = "
INNER JOIN civicrm_membership_payment ON (civicrm_contribution.id = civicrm_membership_payment.contribution_id
AND civicrm_membership_payment.membership_id {$componentClause} )
";
    }
    elseif ($exportMode == CRM_Export_Form_Select::PLEDGE_EXPORT) {
      $componentSelect = " civicrm_pledge_payment.id id";
      $additionalClause = "
INNER JOIN civicrm_pledge_payment ON (civicrm_contribution.id = civicrm_pledge_payment.contribution_id
AND civicrm_pledge_payment.pledge_id {$componentClause} )
";
    }

    $query = " SELECT total_amount, contribution_status.name as status_id, contribution_status.label as status, payment_instrument.name as payment_instrument, receive_date,
                          trxn_id, {$componentSelect}
FROM civicrm_contribution 
LEFT JOIN civicrm_option_group option_group_payment_instrument ON ( option_group_payment_instrument.name = 'payment_instrument')
LEFT JOIN civicrm_option_value payment_instrument ON (civicrm_contribution.payment_instrument_id = payment_instrument.value
     AND option_group_payment_instrument.id = payment_instrument.option_group_id )
LEFT JOIN civicrm_option_group option_group_contribution_status ON (option_group_contribution_status.name = 'contribution_status')
LEFT JOIN civicrm_option_value contribution_status ON (civicrm_contribution.contribution_status_id = contribution_status.value 
                               AND option_group_contribution_status.id = contribution_status.option_group_id )
{$additionalClause}
";

    $dao = &CRM_Core_DAO::executeQuery($query, CRM_Core_DAO::$_nullArray);

    while ($dao->fetch()) {
      $paymentDetails[$dao->id] = array('total_amount' => $dao->total_amount,
        'contribution_status' => $dao->status,
        'receive_date' => $dao->receive_date,
        'pay_instru' => $dao->payment_instrument,
        'trxn_id' => $dao->trxn_id,
      );
    }

    return $paymentDetails;
  }

  /**
   *  Function to create address associated with contribution record.
   *  @param array $params an associated array
   *  @param int   $billingID $billingLocationTypeID
   *
   *  @return address id
   *  @static
   */
  static function createAddress(&$params, $billingLocationTypeID) {

    $billingFields = array("street_address",
      "city",
      "state_province_id",
      "postal_code",
      "country_id",
    );

    //build address array
    $addressParams = array();
    $addressParams['location_type_id'] = $billingLocationTypeID;
    $addressParams['is_billing'] = 1;
    $addressParams['address_name'] = "{$params['billing_first_name']}" . CRM_Core_DAO::VALUE_SEPARATOR . "{$params['billing_middle_name']}" . CRM_Core_DAO::VALUE_SEPARATOR . "{$params['billing_last_name']}";

    foreach ($billingFields as $value) {
      $addressParams[$value] = $params["billing_{$value}-{$billingLocationTypeID}"];
    }

    require_once "CRM/Core/BAO/Address.php";
    $address = CRM_Core_BAO_Address::add($addressParams, FALSE);

    return $address->id;
  }

  /**
   *  Function to create soft contributon with contribution record.
   *  @param array $params an associated array
   *
   *  @return soft contribution id
   *  @static
   */
  static function addSoftContribution($params) {
    require_once 'CRM/Contribute/DAO/ContributionSoft.php';
    $softContribution = new CRM_Contribute_DAO_ContributionSoft();
    $softContribution->copyValues($params);

    // set currency for CRM-1496
    if (!isset($softContribution->currency)) {
      $config = &CRM_Core_Config::singleton();
      $softContribution->currency = $config->defaultCurrency;
    }

    return $softContribution->save();
  }

  /**
   *  Function to retrieve soft contributon for contribution record.
   *  @param array $params an associated array
   *
   *  @return soft contribution id
   *  @static
   */
  static function getSoftContribution($params, $all = FALSE) {
    require_once 'CRM/Contribute/DAO/ContributionSoft.php';

    $cs = new CRM_Contribute_DAO_ContributionSoft();
    $cs->copyValues($params);
    $softContribution = array();
    if ($cs->find(TRUE)) {
      if ($all) {
        foreach (array('pcp_id', 'pcp_display_in_roll', 'pcp_roll_nickname', 'pcp_personal_note') as $key => $val) {
          $softContribution[$val] = $cs->$val;
        }
      }
      $softContribution['soft_credit_to'] = $cs->contact_id;
      $softContribution['soft_credit_id'] = $cs->id;
    }
    return $softContribution;
  }

  /**
   *  Function to retrieve the list of soft contributons for given contact.
   *  @param int $contact_id contact id
   *
   *  @return array
   *  @static
   */
  static function getSoftContributionList($contact_id, $isTest = 0) {
    $query = "SELECT ccs.id, ccs.amount as amount,
                         ccs.contribution_id, 
                         ccs.pcp_id,
                         ccs.pcp_display_in_roll,
                         ccs.pcp_roll_nickname,
                         ccs.pcp_personal_note,
                         cc.receive_date,
                         cc.contact_id as contributor_id,
                         cc.contribution_status_id as contribution_status_id,
                         cp.title as pcp_title,
                         cc.currency,
                         contact.display_name,
                         cct.name as contributionType
                  FROM civicrm_contribution_soft ccs
                       LEFT JOIN civicrm_contribution cc
                              ON ccs.contribution_id = cc.id
                       LEFT JOIN civicrm_pcp cp 
                              ON ccs.pcp_id = cp.id
                       LEFT JOIN civicrm_contact contact
                              ON ccs.contribution_id = cc.id AND
                                 cc.contact_id = contact.id 
                       LEFT JOIN civicrm_contribution_type cct
                              ON cc.contribution_type_id = cct.id
                  WHERE cc.is_test = {$isTest} AND ccs.contact_id = " . $contact_id;

    $cs = CRM_Core_DAO::executeQuery($query, CRM_Core_DAO::$_nullArray);
    require_once "CRM/Contribute/PseudoConstant.php";
    $contributionStatus = CRM_Contribute_Pseudoconstant::contributionStatus();
    $result = array();
    while ($cs->fetch()) {
      $result[$cs->id]['amount'] = $cs->amount;
      $result[$cs->id]['currency'] = $cs->currency;
      $result[$cs->id]['contributor_id'] = $cs->contributor_id;
      $result[$cs->id]['contribution_id'] = $cs->contribution_id;
      $result[$cs->id]['contributor_name'] = $cs->display_name;
      $result[$cs->id]['contribution_type'] = $cs->contributionType;
      $result[$cs->id]['receive_date'] = $cs->receive_date;
      $result[$cs->id]['pcp_id'] = $cs->pcp_id;
      $result[$cs->id]['pcp_title'] = $cs->pcp_title;
      $result[$cs->id]['pcp_display_in_roll'] = $cs->pcp_display_in_roll;
      $result[$cs->id]['pcp_roll_nickname'] = $cs->pcp_roll_nickname;
      $result[$cs->id]['pcp_personal_note'] = $cs->pcp_personal_note;
      $result[$cs->id]['contribution_status'] = CRM_Utils_Array::value($cs->contribution_status_id, $contributionStatus);

      if ($isTest) {
        $result[$cs->id]['contribution_status'] = $result[$cs->id]['contribution_status'] . '<br /> (test)';
      }
    }
    return $result;
  }

  static function getSoftContributionTotals($contact_id, $isTest = 0) {
    $query = "SELECT SUM(amount) as amount,
                         AVG(total_amount) as average,
                         cc.currency
                  FROM civicrm_contribution_soft  ccs 
                       LEFT JOIN civicrm_contribution cc 
                              ON ccs.contribution_id = cc.id 
                  WHERE cc.is_test = {$isTest} AND 
                        ccs.contact_id = {$contact_id}
                  GROUP BY currency ";

    $cs = CRM_Core_DAO::executeQuery($query, CRM_Core_DAO::$_nullArray);

    $count = 0;
    $amount = $average = array();
    require_once 'CRM/Utils/Money.php';

    while ($cs->fetch()) {
      if ($cs->amount > 0) {
        $count++;
        $amount[] = $cs->amount;
        $average[] = $cs->average;
        $currency[] = $cs->currency;
      }
    }

    if ($count > 0) {
      return array(implode(',&nbsp;', $amount),
        implode(',&nbsp;', $average),
        implode(',&nbsp;', $currency),
      );
    }
    return array(0, 0);
  }

  /**
   * Delete billing address record related contribution
   *
   * @param int $contact_id contact id
   * @param int $contribution_id contributionId
   * @access public
   * @static
   */
  static function deleteAddress($contributionId = NULL, $contactId = NULL) {
    $contributionCond = $contactCond = 'null';
    if ($contributionId) {
      $contributionCond = "cc.id = {$contributionId}";
    }
    if ($contactId) {
      $contactCond = "cco.id = {$contactId}";
    }

    $query = "
SELECT ca.id FROM 
civicrm_address ca 
LEFT JOIN civicrm_contribution cc ON cc.address_id = ca.id 
LEFT JOIN civicrm_contact cco ON cc.contact_id = cco.id 
WHERE ( $contributionCond  OR $contactCond )";

    $dao = CRM_Core_DAO::executeQuery($query, CRM_Core_DAO::$_nullArray);

    while ($dao->fetch()) {
      require_once "CRM/Core/BAO/Block.php";
      $params = array('id' => $dao->id);
      CRM_Core_BAO_Block::blockDelete('Address', $params);
    }
  }

  /**
   * This function check online pending contribution associated w/
   * Online Event Registration or Online Membership signup.
   *
   * @param int    $componentId   participant/membership id.
   * @param string $componentName Event/Membership.
   *
   * @return $contributionId pending contribution id.
   * @static
   */
  static function checkOnlinePendingContribution($componentId, $componentName) {
    $contributionId = NULL;
    if (!$componentId ||
      !in_array($componentName, array('Event', 'Membership'))
    ) {
      return $contributionId;
    }

    if ($componentName == 'Event') {
      $idName = 'participant_id';
      $componentTable = 'civicrm_participant';
      $paymentTable = 'civicrm_participant_payment';
      $source = ts('Online Event Registration');
    }

    if ($componentName == 'Membership') {
      $idName = 'membership_id';
      $componentTable = 'civicrm_membership';
      $paymentTable = 'civicrm_membership_payment';
      $source = ts('Online Contribution');
    }

    require_once 'CRM/Contribute/PseudoConstant.php';
    $pendingStatusId = array_search('Pending', CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name'));

    $query = "
   SELECT  component.id as {$idName},
           componentPayment.contribution_id as contribution_id,
           contribution.source source,
           contribution.contribution_status_id as contribution_status_id,
           contribution.is_pay_later as is_pay_later
     FROM  $componentTable component
LEFT JOIN  $paymentTable componentPayment    ON ( componentPayment.{$idName} = component.id )
LEFT JOIN  civicrm_contribution contribution ON ( componentPayment.contribution_id = contribution.id )
    WHERE  component.id = {$componentId}";

    $dao = CRM_Core_DAO::executeQuery($query);

    while ($dao->fetch()) {
      if ($dao->contribution_id &&
        $dao->is_pay_later &&
        $dao->contribution_status_id == $pendingStatusId &&
        strpos($dao->source, $source) !== FALSE
      ) {
        $contributionId = $dao->contribution_id;
        $dao->free();
      }
    }

    return $contributionId;
  }

  /**
   * This function update contribution as well as related objects.
   */
  function transitionComponents($params, $processContributionObject = FALSE) {
    // get minimum required values.
    $contactId = CRM_Utils_Array::value('contact_id', $params);
    $componentId = CRM_Utils_Array::value('component_id', $params);
    $componentName = CRM_Utils_Array::value('componentName', $params);
    $contributionId = CRM_Utils_Array::value('contribution_id', $params);
    $contributionStatusId = CRM_Utils_Array::value('contribution_status_id', $params);

    // if we already processed contribution object pass previous status id.
    $previousContriStatusId = CRM_Utils_Array::value('previous_contribution_status_id', $params);

    $updateResult = array();

    require_once 'CRM/Contribute/PseudoConstant.php';
    $contributionStatuses = CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name');

    // we process only ( Completed, Cancelled, Failed, Overdue ) contributions.
    if (!$contributionId ||
      !in_array($contributionStatusId, array(array_search('Completed', $contributionStatuses),
          array_search('Cancelled', $contributionStatuses),
          array_search('Failed', $contributionStatuses),
          array_search('Overdue', $contributionStatuses),
        ))
    ) {
      return $updateResult;
    }

    if (!$componentName || !$componentId) {
      // get the related component details.
      $componentDetails = self::getComponentDetails(array($contributionId));
      $componentDetails = reset($componentDetails);
    }
    else {
      $componentDetails['contact_id'] = $contactId;
      $componentDetails['component'] = $componentName;

      if ($componentName = 'event') {
        $componentDetails['participant'] = $componentId;
      }
      else {
        $componentDetails['membership'] = $componentId;
      }
    }

    if (CRM_Utils_Array::value('contact_id', $componentDetails)) {
      $componentDetails['contact_id'] = CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_Contribution',
        $contributionId,
        'contact_id'
      );
    }

    // do check for required ids.
    if (!CRM_Utils_Array::value('membership', $componentDetails) &&
      !CRM_Utils_Array::value('participant', $componentDetails) &&
      !CRM_Utils_Array::value('pledge_payment', $componentDetails) ||
      !CRM_Utils_Array::value('contact_id', $componentDetails)
    ) {
      return $updateResult;
    }

    //now we are ready w/ required ids, start processing.

    require_once 'CRM/Core/Payment/BaseIPN.php';
    $baseIPN = new CRM_Core_Payment_BaseIPN();

    $input = $ids = $objects = array();

    $input['component'] = CRM_Utils_Array::value('component', $componentDetails);
    $ids['contribution'] = $contributionId;
    $ids['contact'] = CRM_Utils_Array::value('contact_id', $componentDetails);
    $ids['membership'] = CRM_Utils_Array::value('membership', $componentDetails);
    $ids['participant'] = CRM_Utils_Array::value('participant', $componentDetails);
    $ids['event'] = CRM_Utils_Array::value('event', $componentDetails);
    $ids['pledge_payment'] = CRM_Utils_Array::value('pledge_payment', $componentDetails);
    $ids['contributionRecur'] = NULL;
    $ids['contributionPage'] = NULL;

    if (!$baseIPN->validateData($input, $ids, $objects, FALSE)) {
      CRM_Core_Error::fatal();
    }

    $membership = &$objects['membership'];
    $participant = &$objects['participant'];
    $pledgePayment = &$objects['pledge_payment'];
    $contribution = &$objects['contribution'];

    if ($pledgePayment) {
      require_once 'CRM/Pledge/BAO/Payment.php';
      $pledgePaymentIDs = array();
      foreach ($pledgePayment as $key => $object) {
        $pledgePaymentIDs[] = $object->id;
      }
      $pledgeID = $pledgePayment[0]->pledge_id;
    }

    require_once 'CRM/Event/PseudoConstant.php';
    require_once 'CRM/Event/BAO/Participant.php';
    require_once 'CRM/Pledge/BAO/Pledge.php';
    require_once 'CRM/Member/PseudoConstant.php';
    require_once 'CRM/Member/BAO/Membership.php';

    $membershipStatuses = CRM_Member_PseudoConstant::membershipStatus();

    if ($participant) {
      $participantStatuses = CRM_Event_PseudoConstant::participantStatus();
      $oldStatus = CRM_Core_DAO::getFieldValue("CRM_Event_DAO_Participant", $participant->id, 'status_id');
    }
    // we might want to process contribution object.
    $processContribution = FALSE;

    if ($contributionStatusId == array_search('Cancelled', $contributionStatuses)) {
      if ($membership) {
        $membership->status_id = array_search('Cancelled', $membershipStatuses);
        $membership->save();

        $updateResult['updatedComponents']['CiviMember'] = $membership->status_id;
        if ($processContributionObject) {
          $processContribution = TRUE;
        }
      }

      if ($participant) {
        $updatedStatusId = array_search('Cancelled', $participantStatuses);
        CRM_Event_BAO_Participant::updateParticipantStatus($participant->id, $oldStatus, $updatedStatusId, TRUE);

        $updateResult['updatedComponents']['CiviEvent'] = $updatedStatusId;
        if ($processContributionObject) {
          $processContribution = TRUE;
        }
      }

      if ($pledgePayment) {
        CRM_Pledge_BAO_Payment::updatePledgePaymentStatus($pledgeID, $pledgePaymentIDs, $contributionStatusId);

        $updateResult['updatedComponents']['CiviPledge'] = $contributionStatusId;
        if ($processContributionObject) {
          $processContribution = TRUE;
        }
      }
    }
    elseif ($contributionStatusId == array_search('Failed', $contributionStatuses)) {
      if ($participant) {
        $updatedStatusId = array_search('Cancelled', $participantStatuses);
        CRM_Event_BAO_Participant::updateParticipantStatus($participant->id, $oldStatus, $updatedStatusId, TRUE);

        $updateResult['updatedComponents']['CiviEvent'] = $updatedStatusId;
        if ($processContributionObject) {
          $processContribution = TRUE;
        }
      }

      if ($pledgePayment) {
        CRM_Pledge_BAO_Payment::updatePledgePaymentStatus($pledgeID, $pledgePaymentIDs, $contributionStatusId);

        $updateResult['updatedComponents']['CiviPledge'] = $contributionStatusId;
        if ($processContributionObject) {
          $processContribution = TRUE;
        }
      }
    }
    elseif ($contributionStatusId == array_search('Overdue', $contributionStatuses)) {
      if ($membership) {
        $membership->status_id = array_search('Expired', $membershipStatuses);
        $membership->save();

        $updateResult['updatedComponents']['CiviMember'] = $membership->status_id;
        if ($processContributionObject) {
          $processContribution = TRUE;
        }
      }
    }
    elseif ($contributionStatusId == array_search('Completed', $contributionStatuses)) {

      // only pending contribution related object processed.
      if ($previousContriStatusId &&
        ($previousContriStatusId != array_search('Pending', $contributionStatuses))
      ) {
        // this is case when we already processed contribution object.
        return $updateResult;
      }
      elseif (!$previousContriStatusId &&
        $contribution->contribution_status_id != array_search('Pending', $contributionStatuses)
      ) {
        // this is case when we will going to process contribution object.
        return $updateResult;
      }

      if ($membership) {
        $format = '%Y%m%d';
        require_once 'CRM/Member/BAO/MembershipType.php';

        //CRM-4523
        $currentMembership = CRM_Member_BAO_Membership::getContactMembership($membership->contact_id,
          $membership->membership_type_id,
          $membership->is_test, $membership->id
        );
        if ($currentMembership) {
          CRM_Member_BAO_Membership::fixMembershipStatusBeforeRenew($currentMembership,
            $changeToday = NULL
          );
          $dates = CRM_Member_BAO_MembershipType::getRenewalDatesForMembershipType($membership->id,
            $changeToday = NULL
          );
          $dates['join_date'] = CRM_Utils_Date::customFormat($currentMembership['join_date'], $format);
        }
        else {
          $dates = CRM_Member_BAO_MembershipType::getDatesForMembershipType($membership->membership_type_id);
        }

        //get the status for membership.
        require_once 'CRM/Member/BAO/MembershipStatus.php';
        $calcStatus = CRM_Member_BAO_MembershipStatus::getMembershipStatusByDate($dates['start_date'],
          $dates['end_date'],
          $dates['join_date'],
          'today',
          TRUE
        );

        $formatedParams = array('status_id' => CRM_Utils_Array::value('id', $calcStatus,
            array_search('Current', $membershipStatuses)
          ),
          'join_date' => CRM_Utils_Date::customFormat($dates['join_date'], $format),
          'start_date' => CRM_Utils_Date::customFormat($dates['start_date'], $format),
          'end_date' => CRM_Utils_Date::customFormat($dates['end_date'], $format),
          'reminder_date' => CRM_Utils_Date::customFormat($dates['reminder_date'], $format),
        );

        $membership->copyValues($formatedParams);
        $membership->save();

        //updating the membership log
        $membershipLog = array();
        $membershipLog = $formatedParams;
        $logStartDate = CRM_Utils_Date::customFormat($dates['log_start_date'], $format);
        $logStartDate = ($logStartDate) ? CRM_Utils_Date::isoToMysql($logStartDate) : $formatedParams['start_date'];

        $membershipLog['start_date'] = $logStartDate;
        $membershipLog['membership_id'] = $membership->id;
        $membershipLog['modified_id'] = $membership->contact_id;
        $membershipLog['modified_date'] = date('Ymd');

        require_once 'CRM/Member/BAO/MembershipLog.php';
        CRM_Member_BAO_MembershipLog::add($membershipLog, CRM_Core_DAO::$_nullArray);

        //update related Memberships.
        CRM_Member_BAO_Membership::updateRelatedMemberships($membership->id, $formatedParams);

        $updateResult['membership_end_date'] = CRM_Utils_Date::customFormat($dates['end_date'],
          '%B %E%f, %Y'
        );
        $updateResult['updatedComponents']['CiviMember'] = $membership->status_id;
        if ($processContributionObject) {
          $processContribution = TRUE;
        }
      }

      if ($participant) {
        $updatedStatusId = array_search('Registered', $participantStatuses);
        CRM_Event_BAO_Participant::updateParticipantStatus($participant->id, $oldStatus, $updatedStatusId, TRUE);

        $updateResult['updatedComponents']['CiviEvent'] = $updatedStatusId;
        if ($processContributionObject) {
          $processContribution = TRUE;
        }
      }

      if ($pledgePayment) {
        CRM_Pledge_BAO_Payment::updatePledgePaymentStatus($pledgeID, $pledgePaymentIDs, $contributionStatusId);

        $updateResult['updatedComponents']['CiviPledge'] = $contributionStatusId;
        if ($processContributionObject) {
          $processContribution = TRUE;
        }
      }
    }

    // process contribution object.
    if ($processContribution) {
      require_once 'CRM/Contribute/BAO/Contribution.php';
      $contributionParams = array();
      $fields = array('contact_id', 'total_amount', 'receive_date', 'is_test',
        'payment_instrument_id', 'trxn_id', 'invoice_id', 'contribution_type_id',
        'contribution_status_id', 'non_deductible_amount', 'receipt_date', 'check_number',
      );
      foreach ($fields as $field) {
        if (!CRM_Utils_Array::value($field, $params)) {
          continue;
        }
        $contributionParams[$field] = $params[$field];
      }

      $ids = array('contribution' => $contributionId);
      require_once 'CRM/Contribute/BAO/Contribution.php';
      $contribution = &CRM_Contribute_BAO_Contribution::create($contributionParams, $ids);
    }

    return $updateResult;
  }

  /**
   * This function return all contribution related object ids.
   *
   * @param array $ids contribution ids 
   */
  function getComponentDetails($ids) {
    $componentDetails = $pledgePayment = array();
    if (empty($ids)) {
      return $componentDetails;
    }
    $contributionIds = implode(',', $ids);

    $query = "
SELECT    c.id                 as contribution_id,
          c.contact_id         as contact_id,
          c.contribution_page_id as page_id,
          mp.membership_id     as membership_id,
          m.membership_type_id as membership_type_id,
          pp.participant_id    as participant_id,
          p.event_id           as event_id,
          pgp.id               as pledge_payment_id
FROM      civicrm_contribution c
LEFT JOIN civicrm_membership_payment  mp   ON mp.contribution_id = c.id
LEFT JOIN civicrm_participant_payment pp   ON pp.contribution_id = c.id
LEFT JOIN civicrm_participant         p    ON pp.participant_id  = p.id
LEFT JOIN civicrm_membership          m    ON m.id  = mp.membership_id
LEFT JOIN civicrm_pledge_payment      pgp  ON pgp.contribution_id  = c.id
WHERE c.id IN ({$contributionIds}) ORDER BY c.id ASC";

    $dao = CRM_Core_DAO::executeQuery($query);
    while ($dao->fetch()) {
      $componentDetails[$dao->contribution_id] = array(
        'component' => $dao->participant_id ? 'event' : 'contribute',
        'contact_id' => $dao->contact_id,
        'event' => $dao->event_id,
        'participant' => $dao->participant_id,
        'membership' => $dao->membership_id,
        'membership_type' => $dao->membership_type_id,
        'page_id' => $dao->page_id,
      );
      if(!empty($dao->pledge_payment_id)) {
        $pledgePayment[$dao->contribution_id][] = $dao->pledge_payment_id;
      }
    }

    if(!empty($pledgePayment)) {
      foreach($pledgePayment as $cid => $p){
        $componentDetails[$cid]['pledge_payment'] = $pledgePayment;
      }
    }

    return $componentDetails;
  }

  function contributionCount($contactId, $includeSoftCredit = TRUE, $includeHonoree = TRUE) {
    if (!$contactId) {
      return 0;
    }

    $fromClause = "civicrm_contribution contribution";
    $whereConditions = array("contribution.contact_id = {$contactId}");
    if ($includeSoftCredit) {
      $fromClause .= " LEFT JOIN civicrm_contribution_soft softContribution 
                                             ON ( contribution.id = softContribution.contribution_id )";
      $whereConditions[] = " softContribution.contact_id = {$contactId}";
    }
    if ($includeHonoree) {
      $whereConditions[] = " contribution.honor_contact_id = {$contactId}";
    }
    $whereClause = " contribution.is_test = 0 AND ( " . implode(' OR ', $whereConditions) . " )";

    $query = "       
   SELECT  count( contribution.id ) count
     FROM  {$fromClause}
    WHERE  {$whereClause}";

    return CRM_Core_DAO::singleValueQuery($query);
  }

  /**
   * Function to get individual id for onbehalf contribution
   *
   * @param  int   $contributionId  contribution id
   * @param  int   $contributorId   contributer id
   *
   * @return array $ids             containing organization id and individual id
   * @access public
   */
  function getOnbehalfIds($contributionId, $contributorId = NULL) {

    $ids = array();

    if (!$contributionId) {
      return $ids;
    }

    // fetch contributor id if null
    if (!$contributorId) {
      require_once 'CRM/Core/DAO.php';
      $contributorId = CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_Contribution',
        $contributionId, 'contact_id'
      );
    }

    require_once 'CRM/Core/PseudoConstant.php';
    $activityTypeIds = CRM_Core_PseudoConstant::activityType(TRUE, FALSE, FALSE, 'name');
    $activityTypeId = array_search("Contribution", $activityTypeIds);

    if ($activityTypeId && $contributorId) {
      $activityQuery = "
SELECT source_contact_id 
  FROM civicrm_activity 
 WHERE activity_type_id   = %1 
   AND source_record_id   = %2";

      $params = array(1 => array($activityTypeId, 'Integer'),
        2 => array($contributionId, 'Integer'),
      );

      $sourceContactId = CRM_Core_DAO::singleValueQuery($activityQuery, $params);

      // for on behalf contribution source is individual and contributor is organization
      if ($sourceContactId && $sourceContactId != $contributorId) {
        $relationshipTypeIds = CRM_Core_PseudoConstant::relationshipType('name');
        // get rel type id for employee of relation
        foreach ($relationshipTypeIds as $id => $typeVals) {
          if ($typeVals['name_a_b'] == 'Employee of') {
            $relationshipTypeId = $id;
            break;
          }
        }

        require_once 'CRM/Contact/DAO/Relationship.php';
        $rel = new CRM_Contact_DAO_Relationship();
        $rel->relationship_type_id = $relationshipTypeId;
        $rel->contact_id_a = $sourceContactId;
        $rel->contact_id_b = $contributorId;
        if ($rel->find(TRUE)) {
          $ids['individual_id'] = $rel->contact_id_a;
          $ids['organization_id'] = $rel->contact_id_b;
        }
      }
    }

    return $ids;
  }

  function getContributionDates() {
    $config = CRM_Core_Config::singleton();
    $currentMonth = date('m');
    $currentDay = date('d');
    if ((int ) $config->fiscalYearStart['M'] > $currentMonth ||
      ((int ) $config->fiscalYearStart['M'] == $currentMonth &&
        (int ) $config->fiscalYearStart['d'] > $currentDay
      )
    ) {
      $year = date('Y') - 1;
    }
    else {
      $year = date('Y');
    }
    $year = array('Y' => $year);
    $yearDate = $config->fiscalYearStart;
    $yearDate = array_merge($year, $yearDate);
    $yearDate = CRM_Utils_Date::format($yearDate);

    $monthDate = date('Ym') . '01';

    $now = date('Ymd');

    return array('now' => $now,
      'yearDate' => $yearDate,
      'monthDate' => $monthDate,
    );
  }

  static function getReceipt(&$input, &$ids, &$objects, &$values) {
    $contribution = &$objects['contribution'];
    $membership = &$objects['membership'];
    $participant = &$objects['participant'];
    $event = &$objects['event'];
    $contact = &$objects['contact'];
    $instruments = CRM_Contribute_PseudoConstant::paymentInstrument();

    $contribID = $ids['contribution'];
    $contactID = $ids['contact'];

    // set display address of contributor
    $config = CRM_Core_Config::singleton();
    $custom_values = CRM_Core_BAO_CustomValueTable::getEntityValues($contribID, 'Contribution');
    $custom_title = $config->receiptTitle;
    $custom_serial = $config->receiptSerial;
    $receipt_logo = $config->receiptLogo;

    $template = &CRM_Core_Smarty::singleton();

    // add the new contribution values
    $template->assign('amount', $input['amount']);

    if ($contribution->contribution_type_id) {
      $values['contribution_type_id'] = $contribution->contribution_type_id;
    }

    $sort_name = $custom_values[$custom_title] ? $custom_values[$custom_title] : $contact->sort_name;
    if ($contact->contact_type == 'Individual') {
      if ($contact->sort_name == $sort_name) {
        $legal_identifier = $custom_values[$custom_serial] ? $custom_values[$custom_serial] : $contact->legal_identifier;
      }
      else {
        $legal_identifier = $custom_values[$custom_serial];
      }
      $template->assign('serial_id', $legal_identifier);
    }
    elseif ($contact->contact_type == 'Organization') {
      if ($contact->sort_name == $sort_name) {
        $sic_code = $custom_values[$custom_serial] ? $custom_values[$custom_serial] : $contact->sic_code;
      }
      else {
        $sic_code = $custom_values[$custom_serial];
      }
      $template->assign('serial_id', $sic_code);
    }

    $addressee = !empty($contact->addressee_custom) ? $contact->addressee_custom : (!empty($contact->addressee_display) ? $contact->addressee_display : $contact->sort_name);
    $template->assign('id' , $contribution->id);
    $template->assign('addressee', $addressee);
    $template->assign('sort_name', $sort_name);
    $template->assign('logo', $receipt_logo);
    $template->assign('trxn_id', $contribution->trxn_id);
    $template->assign('receipt_date', CRM_Utils_Date::customFormat($contribution->receipt_date, '%Y/%m/%d'));
    $template->assign('receipt_id', $contribution->receipt_id);
    $template->assign('action', $contribution->is_test ? 1024 : 1);
    $template->assign('receipt_text', CRM_Utils_Array::value('receipt_text', $values));
    $template->assign('is_monetary', 1);
    $template->assign('currency', $contribution->currency);
    $template->assign('instrument', $instruments[$contribution->payment_instrument_id]);
    $template->assign('receive_date', CRM_Utils_Date::customFormat($contribution->receive_date, '%Y/%m/%d'));

    // refs #18399
    $source_name_array = explode(':', $contribution->source);
    if(count($source_name_array) >= 2){
      $prefix = $source_name_array[0].':';
      $source_name = str_replace($prefix, '', $contribution->source);
      $template->assign('source_name' , trim($source_name));
    }

    $addrParams = array('contact_id' => $contact->id);
    $addresses = CRM_Core_BAO_Address::getValues($addrParams);
    if (!empty($config->receiptAddrType)) {
      $addr = CRM_Core_BAO_Address::getAddressByDefault($addresses, $config->receiptAddrType);
    }
    else {
      $addr = reset($addresses);
    }
    if (!empty($addr)) {
      $template->assign('address', $addr['display_text']);
    }
    else {
      $template->assign('address', '');
    }

    $values['contribution_id'] = $contribution->id;
    $isTest = FALSE;
    if ($contribution->is_test) {
      $isTest = TRUE;
    }

    // get the billing location type
    if (!array_key_exists('related_contact', $values)) {
      $locationTypes = &CRM_Core_PseudoConstant::locationType();
      $billingLocationTypeId = array_search('Billing', $locationTypes);
    }
    else {
      // presence of related contact implies onbehalf of org case,
      // where location type is set to default.
      $locType = CRM_Core_BAO_LocationType::getDefault();
      $billingLocationTypeId = $locType->id;
    }

    // get primary location email if no email exist( for billing location).
    if (!$email) {
      list($displayName, $email) = CRM_Contact_BAO_Contact_Location::getEmailDetails($contactID);
    }

    // set email in the template here
    $tplParams = array(
      'email' => $email,
      'receiptFromEmail' => $values['receipt_from_email'],
      'contactID' => $contactID,
      'contributionID' => $values['contribution_id'],
      'membershipID' => CRM_Utils_Array::value('membership_id', $values),
      // CRM-5095
      'lineItem' => CRM_Utils_Array::value('lineItem', $values),
      // CRM-5095
      'priceSetID' => CRM_Utils_Array::value('priceSetID', $values),
    );

    if ($contributionTypeId = CRM_Utils_Array::value('contribution_type_id', $values)) {
      $tplParams['contributionTypeId'] = $contributionTypeId;
      $tplParams['contributionTypeName'] = CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_ContributionType', $contributionTypeId);
    }

    // use either the contribution or membership receipt, based on whether it’s a membership-related contrib or not
    $sendTemplateParams = array(
      'groupName' => 'msg_tpl_workflow_receipt',
      'valueName' => 'receipt_letter',
      'contactId' => $contactID,
      'tplParams' => $tplParams,
      'isTest' => $isTest,
    );

    list($sent, $subject, $message, $html) = CRM_Core_BAO_MessageTemplates::sendTemplate($sendTemplateParams);
    $all_tpl_vars = $template->get_template_vars();
    return $html;
  }

  static function getAnnualReceipt($contact_id, $option, &$template) {
    $config = CRM_Core_Config::singleton();
    $domain = CRM_Core_BAO_Domain::getDomain();
    $location = $domain->getLocationValues();
    $records = self::getAnnualReceiptRecord($contact_id, $option);
    if(!empty($option['year'])){
      $annualYear = $option['year'];
    }

    $params = array('id' => $contact_id);
    $contact = array();
    CRM_Contact_BAO_Contact::retrieve($params, $contact);
    
    // set display address of contact
    if ($contact['contact_type'] == 'Individual') {
      $serial_id = $contact['legal_identifier'];
    }
    elseif ($contact['contact_type'] == 'Organization') {
      $serial_id = $contact['sic_code'];
    }

    $sort_name = $contact['sort_name'];
    $addressee = !empty($contact['addressee_custom']) ? $contact['addressee_custom'] : (!empty($contact['addressee_display']) ? $contact['addressee_display'] : $sort_name);
    $receipt_logo = $config->receiptLogo;

    $addrParams = array('contact_id' => $contact_id);
    $addresses = CRM_Core_BAO_Address::getValues($addrParams);
    if (!empty($config->receiptAddrType)) {
      $addr = CRM_Core_BAO_Address::getAddressByDefault($addresses, $config->receiptAddrType);
    }
    else {
      $addr = reset($addresses);
    }
    if (!empty($addr)) {
      $address = $addr['display_text'];
    }
    else {
      $address = '';
    }

    // get primary location email if no email exist( for billing location).
    if (!$email) {
      list($displayName, $email) = CRM_Contact_BAO_Contact_Location::getEmailDetails($contact_id);
    }

    // set email in the template here
    if(!empty($records)){
      $config = CRM_Core_Config::singleton();
      $annualRecords = $contactInfo = array();
      if (!empty($config->receiptTitle)) {
        foreach($records as $key => $record) {
          $record['total_amount'] = (int) $record['total_amount'];
          // name
          if (!empty($record['custom_'.$config->receiptTitle])) {
            $name = trim($record['custom_'.$config->receiptTitle]);
          }
          else {
            $name = $sort_name;
          }

          // serial
          if (!empty($record['custom_'.$config->receiptSerial])) {
            $serial = trim($record['custom_'.$config->receiptSerial]);
          }
          elseif($name == $sort_name) {
            $serial = trim($serial_id);
          }
          else {
            $serial = '';
          }

          if (empty($contactInfo[$name]['sort_name'])) {
            $contactInfo[$name]['sort_name'] = $name;
            $contactInfo[$name]['addressee'] = $name != $sort_name ? $name . " ($sort_name)" : $addressee;
          }
          if (empty($contactInfo[$name]['serial_id'])) {
            $contactInfo[$name]['serial_id'] = $serial;
          }
          $annualRecords[$name][$key] = $record;
          $contactInfo[$name]['total'] = $record['total_amount'] + $contactInfo[$name]['total'];
        }
      }
      else {
        $annualRecords[$sort_name] = $records;
      }

      $tplParams = array(
        'email' => $email,
        'receiptFromEmail' => $values['receipt_from_email'],
        'contactID' => $contact_id,
        'sort_name' => $sort_name,
        'contact_info' => $contactInfo,
        'address' => $address,
        'logo' => $receipt_logo,
        'domain_name' => $domain->name,
        'domain_email' => $location['email'][1]['email'],
        'domain_phone' => $location['phone'][1]['phone'],
        'domain_address' => $location['address'][1]['display_text'],
        'receiptOrgInfo' => htmlspecialchars_decode($config->receiptOrgInfo),
        'receiptDescription' => htmlspecialchars_decode($config->receiptDescription),
        'record' => $annualRecords,
        'year' => $annualYear,
      );
      $tplParams['recordHeader'] = !empty($option['recordHeader']) ? $option['recordHeader'] :  array(
          ts('Receipt ID'),
          ts('Contribution Types'),
          ts('Payment Instrument'),
          ts('Receipt Date'),
          ts('Total Amount'),
        );

      // use either the contribution or membership receipt, based on whether it’s a membership-related contrib or not
      $sendTemplateParams = array(
        'groupName' => 'msg_tpl_workflow_receipt',
        'valueName' => 'receipt_letter_annual',
        'contactId' => $contact_id,
        'tplParams' => $tplParams,
        'isTest' => $isTest,
      );

      list($sent, $subject, $message, $html) = CRM_Core_BAO_MessageTemplates::sendTemplate($sendTemplateParams);
      return $html;
    }
  }

  static function getAnnualReceiptRecord($contact_id, $option = NULL){
    $config = CRM_Core_Config::singleton();
    $where = array();

    $date_field_name = !empty($option['dateField']) ? $option['dateField'] : 'receipt_date';

    // filter by year by receipt date
    if(!empty($option['year'])){
      $year = $option['year'];
      $start = $year.'-01-01 00:00:00';
      $end = $year.'-12-31 23:59:59';
      $where[] = "c.$date_field_name >= '$start' AND c.$date_field_name <= '$end'";
    }

    // filter by contribution type, default only dedutible contribution type
    if(is_array($option['contribution_type_id']) && array_search(0, $option['contribution_type_id']) !== FALSE) {
      $empty = array_search(0, $option['contribution_type_id']);
      unset($option['contribution_type_id'][$empty]);
    }
    if(!empty($option['contribution_type_id'])){
      if(is_array($option['contribution_type_id'])){
        $where[] = "c.contribution_type_id IN (".implode(',', $option['contribution_type_id']).')';
      }
      else{
        $where[] = "c.contribution_type_id = ".$option['contribution_type_id'];
      }
    }
    else{
      $types = array();
      CRM_Core_PseudoConstant::populate($types, 'CRM_Contribute_DAO_ContributionType', TRUE);
      $where[] = "c.contribution_type_id IN (".implode(',', array_keys($types)).")";
    }

    // filter by recurring contribution
    if(!empty($option['is_recur'])){
      if($option['is_recur'] == 1){
        $where[] = "c.contribution_recur_id IS NOT NULL";
      }
      elseif($option['is_recur'] == -1){
        $where[] = "c.contribution_recur_id IS NULL";
      }
    }
    $where = !empty($where) ? ' AND '.implode(' AND ', $where) : NULL;

    $args = array(
      1 => array($contact_id, 'Integer'),
    );
    $query = "SELECT c.id, c.contribution_type_id, c.payment_instrument_id, c.receipt_id, DATE(c.$date_field_name) as receipt_date, c.total_amount FROM civicrm_contribution c WHERE c.contact_id = %1 AND c.is_test = 0 AND c.contribution_status_id = 1 $where ORDER BY c.receipt_id, c.$date_field_name ASC";
    $result = CRM_Core_DAO::executeQuery($query, $args);
   
    $contribution_type = array();
    CRM_Core_PseudoConstant::populate($contribution_type, 'CRM_Contribute_DAO_ContributionType', TRUE);
    $instruments = CRM_Contribute_PseudoConstant::paymentInstrument();
    $records = array();
    while($result->fetch()){
      $records[$result->id] = array(
        'receipt_id' => $result->receipt_id,
        'receipt_date' => $result->receipt_date,
        'total_amount' => $result->total_amount,
        'receive_date' => $result->receive_date,
      );
      $records[$result->id]['contribution_type'] = $contribution_type[$result->contribution_type_id];
      $records[$result->id]['instrument'] = $instruments[$result->payment_instrument_id];
    }
    if (!empty($config->receiptTitle) && !empty($records)) {
      $contributionIds = array_keys($records);
      $receiptFields = array(
        $config->receiptTitle,
        $config->receiptSerial,
      );
      $receiptInfo = CRM_Core_BAO_CustomValueTable::getEntitiesValues($contributionIds, 'Contribution', $receiptFields);
      foreach($records as $contributionId => $contrib) {
        if (!empty($receiptInfo[$contributionId])) {
          $records[$contributionId] = array_merge($records[$contributionId], $receiptInfo[$contributionId]);
        }
      }
    }
    return $records;
  }

  static function genReceiptID(&$contrib, $save = TRUE, $is_online = FALSE, $reset = FALSE) {
    if (is_numeric($contrib)) {
      $id = $contrib;
      $contribution = new CRM_Contribute_DAO_Contribution();
      $contribution->id = $id;
      if (!$contribution->find(TRUE)) {
        CRM_Core_Error::debug_log_message("Could not find contribution record: $contributionID");
        return FALSE;
      }
    }
    elseif (is_array($contrib)) {
      $contribution = $contrib;
      $contribution = (object) $contribution;
    }
    else {
      // object
      $contribution = $contrib;
    }

    $needs = array('is_test', 'contribution_status_id', 'receipt_date', 'receipt_id', 'contribution_type_id');
    foreach ($needs as $n) {
      if ($contribution->$n === 'null') {
        $contribution->$n = NULL;
      }
      if (empty($contribution->$n) && $contribution->id) {
        $contribution->$n = CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_Contribution', $contribution->id, $n, 'id');
      }
    }
    if ($reset) {
      $contribution->receipt_id = NULL;
    }
    $accountingCode = array();
    CRM_Core_PseudoConstant::populate($accountingCode, 'CRM_Contribute_DAO_ContributionType', TRUE, 'accounting_code');

    // have receipt date? completed? already have receipt id?
    if (!empty($contribution->receipt_date) && $contribution->contribution_status_id == 1 && empty($contribution->receipt_id)) {
      // contribution type is dedutible?
      $deductible = CRM_Contribute_BAO_ContributionType::deductible($contribution->contribution_type_id);
      if ($deductible) {
        $config = CRM_Core_Config::singleton();
        if ($contribution->id) {
          $fids = CRM_Core_BAO_FinancialTrxn::getFinancialTrxnIds($contribution->id, 'civicrm_contribution');
        }
        $online = 'M';
        $prefix = '';
        if (!empty($fids['entityFinancialTrxnId']) || $is_online) {
          $online = 'A';
        }
        if (strstr($config->receiptPrefix, '!online')) {
          $prefix = str_replace('!online', $online, $config->receiptPrefix);
        }
        else {
          $prefix = $online.$config->receiptPrefix;
        }
        if(!empty($config->receiptPrefix)){
          if (strstr($config->receiptPrefix, '!acc')) {
            $accCode = !empty($accountingCode[$contribution->contribution_type_id]) ? $accountingCode[$contribution->contribution_type_id] : '';
            $prefix = str_replace('!acc', $accCode, $prefix);
          }
          $prefix = CRM_Utils_Date::customFormat($contribution->receipt_date, $prefix);
        }

        if ($contribution->is_test) {
          $prefix = 'test-' . $prefix;
        }
        CRM_Utils_Hook::alterReceiptId($prefix, $contribution);
        $receipt_id = self::lastReceiptID($prefix);

        $contribution->receipt_id = $receipt_id;
        if ($save && $contribution->id) {
          $contribution->save();
          CRM_Core_DAO::executeQuery("DELETE FROM civicrm_sequence WHERE name = %1", array(1 => array($prefix, 'String')));
        }
        return $receipt_id;
      }
    }

    // not a proper contribution to have receipt id
    return FALSE;
  }

  function lastReceiptID($prefix) {
    $receipt_id = 'null';
    $last = 0;
    $counter = 0;
    while($last === 0) {
      $time = microtime(TRUE)-0.35; // 0.35 second ago 
      $counter++;
      $exists = CRM_Core_DAO::singleValueQuery("SELECT value FROM civicrm_sequence WHERE name = %1 AND timestamp >= %2", array(
        1 => array($prefix, 'String'),
        2 => array($time, 'Float'),
      ));
      $exists = (int) $exists;
      if (!$exists) {
        $sql = "SELECT REPLACE(receipt_id, %1, '') FROM civicrm_contribution WHERE UPPER(receipt_id) LIKE UPPER(%2) AND receipt_id IS NOT NULL AND receipt_id != '' ORDER BY receipt_id DESC";
        $last = CRM_Core_DAO::singleValueQuery($sql, array(
          1 => array("{$prefix}-", 'String'),
          2 => array("{$prefix}-%", 'String'),
        ));
        $last = (int) $last;
        if (($last > $exists) || (empty($last) && empty($exists))) {
          CRM_Core_DAO::executeQuery("INSERT INTO civicrm_sequence (name, value, timestamp) VALUES (%1, %2, %3) ON DUPLICATE KEY UPDATE value = %2, timestamp = %3", array(
            1 => array($prefix, 'String'),
            2 => array($last, 'String'),
            3 => array(microtime(TRUE), 'Float'),
          ));
          $num = $last;
          break;
        }
      }
      usleep(340000); // sleep 0.34 sec
    }

    if (empty($num)) {
      // first of this prefix, do generate
      $num = sprintf('%06d', 1);
      $receipt_id = $prefix . '-' . $num;
    }
    else {
      // truncate and generate
      $num++;
      $num = sprintf('%06d', $num);
      $receipt_id = $prefix . '-' . $num;
    }
    return $receipt_id;
  }

  /**
   * Get ids from saved Contribution
   *
   * @param  int $idcontribution id
   */
  static function buildIds($id, $type = 'form'){
    $query = "SELECT c.id as contributionID,
       c.contact_id         as contactID,
       c.contribution_recur_id as contributionRecurID,
       c.contribution_page_id as contributionPageID,
       mp.membership_id     as membershipID,
       pp.participant_id    as participantID,
       p.event_id           as eventID
FROM civicrm_contribution c
LEFT JOIN civicrm_membership_payment  mp ON mp.contribution_id = c.id
LEFT JOIN civicrm_participant_payment pp ON pp.contribution_id = c.id
LEFT JOIN civicrm_participant         p  ON pp.participant_id  = p.id
WHERE c.id = $id";
    
    $dao = CRM_Core_DAO::executeQuery($query);
    $dao->fetch();
    if(!empty($dao->N)){
      if($type == 'form'){
        $params = array(
          'contributionID' => $dao->contributionID,
          'contactID' => $dao->contactID,
          'membershipID' => $dao->membershipID,
          'participantID' => $dao->participantID,
          'eventID' => $dao->eventID,
          'contributionRecurID' => $dao->contributionRecurID,
          'contributionPageID' => $dao->contributionPageID,
        );
      }
      else{
        $params = array(
          'contribution' => $dao->contributionID,
          'contact' => $dao->contactID,
          'membership' => $dao->membershipID,
          'participant' => $dao->participantID,
          'event' => $dao->eventID,
          'contributionRecur' => $dao->contributionRecurID,
          'contributionPage' => $dao->contributionPage,
        );
      }

      foreach($params as $k => $v){
        if(empty($v)){
          unset($params[$k]);
        }
      }
      return $params;
    }
    else{
      return FALSE;
    }
  }

  /**
   * Make notify url
   *
   * This function may be used for payment method transfer_checkout
   *
   * @param array $params variables for notify url
   * @param string $path url path to notify
   * @param boolean $return_query to only return query string 
   * when TRUE, default FALSE
   */
  function makeNotifyUrl(&$params, $path, $return_query = FALSE){
    $query = array();
    $query[] = "contact_id={$params['contactID']}";
    $query[] = "cid={$params['contributionID']}";

    if($params['eventID']) {
      $query[] = "module=event";
      $query[] = "eid={$params['eventID']}";
      $query[] = "pid={$params['participantID']}";
    }
    else {
      $query[] = "module=contribute";
      if ( $params['membershipID'] ) {
        $query[] = "mid=".$params['membershipID'];
      }
      if ($params['related_contact']) {
        $query[] = "rid=".$params['related_contact'];
        if ($params['onbehalf_dupe_alert']) {
          $query[] = "onbehalf_dupe_alert=".$params['onbehalf_dupe_alert'];
        }
      }
    }

    // if recurring donations, add a few more items
    if(!empty($params['contributionRecurID'])) {
      $query[] = "crid={$params['contributionRecurID']}";
    }
    if(!empty($params['contributionPageID'])){
      $query[] = "cpid={$params['contributionPageID']}";
    }

    if($return_query){
      return implode('&', $query);
    }
    $url = CRM_Utils_System::url($path, implode('&', $query), TRUE, NULL, FALSE);
    if (CRM_Utils_System::isSSL()) {
      return str_replace('http://', 'https://', $url);
    }
    else{
      return $url;
    }
  }

  static function getInvoice($contributionId, $paymentInfo, $message, $sendMail = FALSE) {
    $contribution = new CRM_Contribute_DAO_Contribution();
    $contribution->id = $contributionId;
    if($contribution->find(TRUE)){
      $instruments = CRM_Contribute_PseudoConstant::paymentInstrument();
      if(!empty($contribution->payment_instrument_id)){
        $contribution->payment_instrument = $instruments[$contribution->payment_instrument_id];
      }
      $details = CRM_Contribute_BAO_Contribution::getComponentDetails(array($contributionId));
      $ids = reset($details);
      $pageValues = $sendParams = array();

      if(!empty($ids)){
        // get primary location email if no email exist( for billing location).
        if($ids['component'] == 'event'){
          if(!empty($ids['event'])){
            $event_params = array('id' => $ids['event']);
            CRM_Event_BAO_Event::retrieve($event_params, $pageValues);
            $pageValues['url'] = CRM_Utils_System::url('civicrm/event/info', 'reset=1&id='.$pageValues['id'], TRUE);
            if($pageValues['is_email_confirm']){
              $sendParams['from'] = $pageValues['confirm_from_name']. ' <'.$pageValues['confirm_from_email'].'>';
            }
          }
        }
        elseif($ids['component'] == 'contribute'){
          if(!empty($ids['page_id'])){
            CRM_Contribute_BAO_ContributionPage::setValues($ids['page_id'], $pageValues);
            $pageValues['url'] = CRM_Utils_System::url('civicrm/contribute/transact', 'reset=1&id='.$pageValues['id'], TRUE);
            if($pageValues['is_email_receipt']){
              $sendParams['from'] = $pageValues['receipt_from_name'].' <'.$pageValues['receipt_from_email'].'>';
            }
          }
        }
      }
      if(empty($sendParams['from'])){
        $from_emails = array();
        CRM_Core_OptionValue::getValues(array('name' => 'from_email_address'), $from_emails);
        foreach($from_emails as $id => $v){
          if($v['is_default'] == 1){
            $sendParams['from'] = $v['label'];
            break;
          }
        }
      }
      list($displayName, $email) = CRM_Contact_BAO_Contact_Location::getEmailDetails($contribution->contact_id);
      if($sendMail){
        $sendParams['toName'] = $displayName;
        $sendParams['toEmail'] = $email;
      }

      $config = CRM_Core_Config::singleton();
      $tplParams = array(
        'contact_id' => $contact_id,
        'contribution' => (array)$contribution,
        'message' => $message,
        'payment_info' => $paymentInfo,
        'component' => !empty($ids['component']) ? $ids['component'] : '',
        'page' => $pageValues,
        'title' => $pageValues['title'],
        'logo' => !empty($config->receiptLogo) ? $config->receiptLogo : '',
      );

      // use either the contribution or membership receipt, based on whether it’s a membership-related contrib or not
      $sendTemplateParams = array(
        'groupName' => 'msg_tpl_workflow_contribution',
        'valueName' => 'contribution_invoice_notify',
        'contactId' => $ids['contact_id'],
        'tplParams' => $tplParams,
        'isTest' => $contribution->is_test,
      );
      $sendTemplateParams = array_merge($sendTemplateParams, $sendParams); 
      list($sent, $subject, $message, $html) = CRM_Core_BAO_MessageTemplates::sendTemplate($sendTemplateParams);

      if(!$sendMail){
        return $html;
      }
    }
  }
}

