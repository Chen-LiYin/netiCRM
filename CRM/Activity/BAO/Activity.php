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

require_once 'CRM/Activity/DAO/Activity.php';
require_once 'CRM/Activity/BAO/ActivityTarget.php';
require_once 'CRM/Activity/BAO/ActivityAssignment.php';
require_once 'CRM/Utils/Hook.php';

/**
 * This class is for activity functions
 *
 */
class CRM_Activity_BAO_Activity extends CRM_Activity_DAO_Activity {

  /**
   * static field for all the activity information that we can potentially export
   *
   * @var array
   * @static
   */
  static $_exportableFields = NULL;

  /**
   * static field for all the activity information that we can potentially import
   *
   * @var array
   * @static
   */
  static $_importableFields = NULL;

  /**
   * Check if there is absolute minimum of data to add the object
   *
   * @param array  $params         (reference ) an assoc array of name/value pairs
   *
   * @return boolean
   * @access public
   */
  public function dataExists(&$params) {
    if (CRM_Utils_Array::value('source_contact_id', $params) ||
      CRM_Utils_Array::value('id', $params)
    ) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Takes a bunch of params that are needed to match certain criteria and
   * retrieves the relevant objects. Typically the valid params are only
   * contact_id. We'll tweak this function to be more full featured over a period
   * of time. This is the inverse function of create. It also stores all the retrieved
   * values in the default array
   *
   * @param array  $params   (reference ) an assoc array of name/value pairs
   * @param array  $defaults (reference ) an assoc array to hold the flattened values
   * @param string $activityType activity type
   *
   * @return object CRM_Core_BAO_Meeting object
   * @access public
   */
  public function retrieve(&$params, &$defaults) {
    $activity = new CRM_Activity_DAO_Activity();
    $activity->copyValues($params);

    if ($activity->find(TRUE)) {
      require_once "CRM/Contact/BAO/Contact.php";
      // TODO: at some stage we'll have to deal
      // TODO: with multiple values for assignees and targets, but
      // TODO: for now, let's just fetch first row
      $defaults['assignee_contact'] = CRM_Activity_BAO_ActivityAssignment::retrieveAssigneeIdsByActivityId($activity->id);
      $assignee_contact_names = CRM_Activity_BAO_ActivityAssignment::getAssigneeNames($activity->id);

      $defaults['assignee_contact_value'] = implode('; ', $assignee_contact_names);

      if ($activity->activity_type_id != CRM_Core_OptionGroup::getValue('activity_type', 'Bulk Email', 'name')) {
        require_once 'CRM/Activity/BAO/ActivityTarget.php';
        $defaults['target_contact'] = CRM_Activity_BAO_ActivityTarget::retrieveTargetIdsByActivityId($activity->id);
        $target_contact_names = CRM_Activity_BAO_ActivityTarget::getTargetNames($activity->id);

        $defaults['target_contact_value'] = implode('; ', $target_contact_names);
      }
      elseif (CRM_Core_Permission::check('access CiviMail')) {
        $defaults['mailingId'] = CRM_Utils_System::url('civicrm/mailing/report',
          "mid={$activity->source_record_id}&reset=1&atype={$activity->activity_type_id}&aid={$activity->id}&cid={$activity->source_contact_id}&context=activity"
        );
      }
      else {
        $defaults['target_contact_value'] = ts('(recipients)');
      }

      if ($activity->source_contact_id and !CRM_Core_DAO::getFieldValue('CRM_Contact_DAO_Contact', $activity->source_contact_id, 'is_deleted')) {
        $defaults['source_contact'] = CRM_Core_DAO::getFieldValue('CRM_Contact_DAO_Contact',
          $activity->source_contact_id,
          'sort_name'
        );
      }

      //get case subject
      require_once "CRM/Case/BAO/Case.php";
      $defaults['case_subject'] = CRM_Case_BAO_Case::getCaseSubject($activity->id);

      CRM_Core_DAO::storeValues($activity, $defaults);

      return $activity;
    }
    return NULL;
  }

  /**
   * Function to delete the activity
   *
   * @param array  $params  associated array
   *
   * @return void
   * @access public
   *
   */
  public function deleteActivity(&$params, $moveToTrash = FALSE) {
    require_once 'CRM/Core/Transaction.php';

    $transaction = new CRM_Core_Transaction();
    if (is_array(CRM_Utils_Array::value('source_record_id', $params))) {
      $sourceRecordIds = implode(',', $params['source_record_id']);
    }
    else {
      $sourceRecordIds = CRM_Utils_Array::value('source_record_id', $params);
    }

    $result = NULL;
    if (!$moveToTrash) {
      if (!isset($params['id'])) {
        if (is_array($params['activity_type_id'])) {
          $activityTypes = implode(',', $params['activity_type_id']);
        }
        else {
          $activityTypes = $params['activity_type_id'];
        }

        $query = "DELETE FROM civicrm_activity WHERE source_record_id IN ({$sourceRecordIds}) AND activity_type_id IN ( {$activityTypes} )";
        $dao = CRM_Core_DAO::executeQuery($query);
      }
      else {
        $activity = new CRM_Activity_DAO_Activity();
        $activity->copyValues($params);
        $result = $activity->delete();
        CRM_Utils_Hook::post('delete', 'Activity', $activity->id, $activity);
      }
    }
    else {
      $activity = new CRM_Activity_DAO_Activity();
      $activity->copyValues($params);

      $activity->is_deleted = 1;
      $result = $activity->save();

      //log activty delete.CRM-4525.
      $logMsg = "Case Activity deleted for";
      $msgs = array();
      $sourceContactId = CRM_Core_DAO::getfieldValue('CRM_Activity_DAO_Activity',
        $activity->id, 'source_contact_id'
      );
      if ($sourceContactId) {
        $msgs[] = " source={$sourceContactId}";
      }
      //get target contacts.
      $targetContactIds = CRM_Activity_BAO_ActivityTarget::getTargetNames($activity->id);
      if (!empty($targetContactIds)) {
        $msgs[] = " target =" . implode(',', array_keys($targetContactIds));
      }
      //get assignee contacts.
      $assigneeContactIds = CRM_Activity_BAO_ActivityAssignment::getAssigneeNames($activity->id);
      if (!empty($assigneeContactIds)) {
        $msgs[] = " assignee =" . implode(',', array_keys($assigneeContactIds));
      }

      $logMsg .= implode(', ', $msgs);

      self::logActivityAction($activity, $logMsg);
    }

    // delete the recently created Activity
    require_once 'CRM/Utils/Recent.php';
    if ($result) {
      $activityRecent = array(
        'id' => $activity->id,
        'type' => 'Activity',
      );
      CRM_Utils_Recent::del($activityRecent);
    }

    $transaction->commit();
    if (isset($activity)) {
      CRM_Utils_Hook::post('delete', 'Activity', $activity->id, $activity);
    }

    return $result;
  }

  /**
   * Delete activity assignment record
   *
   * @param int    $id  activity id
   *
   * @return null
   * @access public
   */
  public function deleteActivityAssignment($activityId) {
    require_once 'CRM/Activity/BAO/ActivityAssignment.php';
    $assignment = new CRM_Activity_BAO_ActivityAssignment();
    $assignment->activity_id = $activityId;
    $assignment->delete();
  }

  /**
   * Delete activity target record
   *
   * @param int    $id  activity id
   *
   * @return null
   * @access public
   */
  public function deleteActivityTarget($activityId) {
    require_once 'CRM/Activity/BAO/ActivityTarget.php';
    $target = new CRM_Activity_BAO_ActivityTarget();
    $target->activity_id = $activityId;
    $target->delete();
  }

  /**
   * Create activity target record
   *
   * @param array    activity_id, target_contact_id
   *
   * @return null
   * @access public
   */
  public function createActivityTarget($params) {
    if (!$params['target_contact_id']) {
      return;
    }
    require_once 'CRM/Activity/BAO/ActivityTarget.php';
    $target = new CRM_Activity_BAO_ActivityTarget();
    $target->activity_id = $params['activity_id'];
    $target->target_contact_id = $params['target_contact_id'];
    // avoid duplicate entries
    $target->find(TRUE);
    $target->save();
  }

  /**
   * Create activity assignment record
   *
   * @param array    activity_id, assignee_contact_id
   *
   * @return null
   * @access public
   */
  public function createActivityAssignment($params) {
    if (!$params['assignee_contact_id']) {
      return;
    }
    require_once 'CRM/Activity/BAO/ActivityAssignment.php';
    $assignee = new CRM_Activity_BAO_ActivityAssignment();
    $assignee->activity_id = $params['activity_id'];
    $assignee->assignee_contact_id = $params['assignee_contact_id'];
    $assignee->save();
  }

  /**
   * Function to process the activities
   *
   * @param object $form         form object
   * @param array  $params       associated array of the submitted values
   * @param array  $ids          array of ids
   * @param string $activityType activity Type
   * @param boolean $record   true if it is Record Activity
   * @access public
   *
   * @return
   */
  public function create(&$params) {
    // check required params
    if (!self::dataExists($params)) {
      CRM_Core_Error::fatal('Not enough data to create activity object,');
    }

    $activity = new CRM_Activity_DAO_Activity();

    if (!CRM_Utils_Array::value('status_id', $params)) {
      if (isset($params['activity_date_time']) &&
        strcmp($params['activity_date_time'], CRM_Utils_Date::processDate(date('Ymd')) == -1)
      ) {
        $params['status_id'] = 2;
      }
      else {
        $params['status_id'] = 1;
      }
    }

    //set priority to Normal for Auto-populated activities (for Cases)
    if (!CRM_Utils_Array::value('priority_id', $params)) {
      require_once 'CRM/Core/PseudoConstant.php';
      $priority = CRM_Core_PseudoConstant::priority();
      $params['priority_id'] = array_search('Normal', $priority);
    }
    if (empty($params['id'])) {
      unset($params['id']);
    }
    if (!empty($params['target_contact_id']) && is_array($params['target_contact_id'])) {
      $params['target_contact_id'] = array_unique($params['target_contact_id']);
    }
    if (!empty($params['assignee_contact_id']) && is_array($params['assignee_contact_id'])) {
      $params['assignee_contact_id'] = array_unique($params['assignee_contact_id']);
    }


    $activity->copyValues($params);

    // start transaction
    require_once 'CRM/Core/Transaction.php';
    $transaction = new CRM_Core_Transaction();

    $result = $activity->save();

    if (is_a($result, 'CRM_Core_Error')) {
      $transaction->rollback();
      return $result;
    }

    $activityId = $activity->id;

    // check and attach and files as needed
    require_once 'CRM/Core/BAO/File.php';
    CRM_Core_BAO_File::processAttachment($params,
      'civicrm_activity',
      $activityId
    );

    // attempt to save activity assignment
    $resultAssignment = NULL;
    if (CRM_Utils_Array::value('assignee_contact_id', $params)) {
      require_once 'CRM/Activity/BAO/ActivityAssignment.php';

      $assignmentParams = array('activity_id' => $activityId);

      if (is_array($params['assignee_contact_id'])) {
        if (CRM_Utils_Array::value('deleteActivityAssignment', $params, TRUE)) {
          // first delete existing assignments if any
          self::deleteActivityAssignment($activityId);
        }

        foreach ($params['assignee_contact_id'] as $acID) {
          if ($acID) {
            $assignmentParams['assignee_contact_id'] = $acID;
            $resultAssignment = CRM_Activity_BAO_ActivityAssignment::create($assignmentParams);
            if (is_a($resultAssignment, 'CRM_Core_Error')) {
              $transaction->rollback();
              return $resultAssignment;
            }
          }
        }
      }
      else {
        $assignmentParams['assignee_contact_id'] = $params['assignee_contact_id'];

        if (CRM_Utils_Array::value('id', $params)) {
          $assignment = new CRM_Activity_BAO_ActivityAssignment();
          $assignment->activity_id = $activityId;
          $assignment->find(TRUE);

          if ($assignment->assignee_contact_id != $params['assignee_contact_id']) {
            $assignmentParams['id'] = $assignment->id;
            $resultAssignment = CRM_Activity_BAO_ActivityAssignment::create($assignmentParams);
          }
        }
        else {
          $resultAssignment = CRM_Activity_BAO_ActivityAssignment::create($assignmentParams);
        }
      }
    }
    else {
      if (CRM_Utils_Array::value('deleteActivityAssignment', $params, TRUE)) {
        self::deleteActivityAssignment($activityId);
      }
    }

    if (is_a($resultAssignment, 'CRM_Core_Error')) {
      $transaction->rollback();
      return $resultAssignment;
    }

    // attempt to save activity targets
    $resultTarget = NULL;
    if (CRM_Utils_Array::value('target_contact_id', $params)) {

      $targetParams = array('activity_id' => $activityId);
      $resultTarget = array();
      if (is_array($params['target_contact_id'])) {
        if (CRM_Utils_Array::value('deleteActivityTarget', $params, TRUE)) {
          // first delete existing targets if any
          self::deleteActivityTarget($activityId);
        }

        foreach ($params['target_contact_id'] as $tid) {
          if ($tid) {
            $targetParams['target_contact_id'] = $tid;
            $resultTarget = CRM_Activity_BAO_ActivityTarget::create($targetParams);
            if (is_a($resultTarget, 'CRM_Core_Error')) {
              $transaction->rollback();
              return $resultTarget;
            }
          }
        }
      }
      else {
        $targetParams['target_contact_id'] = $params['target_contact_id'];

        if (CRM_Utils_Array::value('id', $params)) {
          $target = new CRM_Activity_BAO_ActivityTarget();
          $target->activity_id = $activityId;
          $target->find(TRUE);

          if ($target->target_contact_id != $params['target_contact_id']) {
            $targetParams['id'] = $target->id;
            $resultTarget = CRM_Activity_BAO_ActivityTarget::create($targetParams);
          }
        }
        else {
          $resultTarget = CRM_Activity_BAO_ActivityTarget::create($targetParams);
        }
      }
    }
    else {
      if (CRM_Utils_Array::value('deleteActivityTarget', $params, TRUE)) {
        self::deleteActivityTarget($activityId);
      }
    }

    // write to changelog before transation is committed/rolled
    // back (and prepare status to display)
    if (CRM_Utils_Array::value('id', $params)) {
      $logMsg = "Activity (id: {$result->id} ) updated with ";
    }
    else {
      $logMsg = "Activity created for ";
    }

    $msgs = array();
    if (isset($params['source_contact_id'])) {
      $msgs[] = "source={$params['source_contact_id']}";
    }

    if (CRM_Utils_Array::value('target_contact_id', $params)) {
      if (is_array($params['target_contact_id']) && !CRM_Utils_array::crmIsEmptyArray($params['target_contact_id'])) {
        $msgs[] = "target=" . implode(',', $params['target_contact_id']);
        // take only first target
        // will be used for recently viewed display
        $t = array_slice($params['target_contact_id'], 0, 1);
        $recentContactId = $t[0];
      }
      elseif (isset($params['target_contact_id'])) {
        $msgs[] = "target={$params['target_contact_id']}";
        // will be used for recently viewed display
        $recentContactId = $params['target_contact_id'];
      }
    }
    else {
      // at worst, take source for recently viewed display
      $recentContactId = $params['source_contact_id'];
    }

    if (isset($params['assignee_contact_id'])) {
      if (is_array($params['assignee_contact_id'])) {
        $msgs[] = "assignee=" . implode(',', $params['assignee_contact_id']);
      }
      else {
        $msgs[] = "assignee={$params['assignee_contact_id']}";
      }
    }
    $logMsg .= implode(', ', $msgs);

    self::logActivityAction($result, $logMsg);

    if (CRM_Utils_Array::value('custom', $params) &&
      is_array($params['custom'])
    ) {
      require_once 'CRM/Core/BAO/CustomValueTable.php';
      CRM_Core_BAO_CustomValueTable::store($params['custom'], 'civicrm_activity', $result->id);
    }

    $transaction->commit();
    if (!CRM_Utils_Array::value('skipRecentView', $params)) {
      $recentOther = array();
      require_once 'CRM/Utils/Recent.php';
      if (CRM_Utils_Array::value('case_id', $params)) {
        $caseContactID = CRM_Core_DAO::getFieldValue('CRM_Case_DAO_CaseContact', $params['case_id'], 'contact_id', 'case_id');
        $url = CRM_Utils_System::url('civicrm/case/activity/view',
          "reset=1&aid={$activity->id}&cid={$caseContactID}&caseID={$params['case_id']}&context=home"
        );
      }
      else {
        $q = "action=view&reset=1&id={$activity->id}&atype={$activity->activity_type_id}&cid={$activity->source_contact_id}&context=home";
        if ($activity->activity_type_id != CRM_Core_OptionGroup::getValue('activity_type', 'Email', 'name')) {
          $url = CRM_Utils_System::url('civicrm/contact/view/activity', $q);
          $recentOther['editUrl'] = CRM_Utils_System::url('civicrm/contact/view/activity',
            "action=update&reset=1&id={$activity->id}&atype={$activity->activity_type_id}&cid={$activity->source_contact_id}&context=home"
          );
          require_once 'CRM/Core/Permission.php';
          if (CRM_Core_Permission::check("delete activities")) {
            $recentOther['deleteUrl'] = CRM_Utils_System::url('civicrm/contact/view/activity',
              "action=delete&reset=1&id={$activity->id}&atype={$activity->activity_type_id}&cid={$activity->source_contact_id}&context=home"
            );
          }
        }
        else {
          $url = CRM_Utils_System::url('civicrm/activity', $q);
          if (CRM_Core_Permission::check("delete activities")) {
            $recentOther['deleteUrl'] = CRM_Utils_System::url('civicrm/activity',
              "action=delete&reset=1&id={$activity->id}&atype={$activity->activity_type_id}&cid={$activity->source_contact_id}&context=home"
            );
          }
        }
      }

      if (!isset($activity->parent_id)) {
        require_once 'CRM/Contact/BAO/Contact.php';
        $recentContactDisplay = CRM_Contact_BAO_Contact::displayName($recentContactId);
        // add the recently created Activity
        $activityTypes = CRM_Core_Pseudoconstant::activityType(TRUE, TRUE);
        $activitySubject = CRM_Core_DAO::getFieldValue('CRM_Activity_DAO_Activity', $activity->id, 'subject');

        $title = "";
        if (isset($activitySubject)) {
          $title = $activitySubject . ' - ';
        }

        $title = $title . $recentContactDisplay . ' (' . $activityTypes[$activity->activity_type_id] . ')';

        CRM_Utils_Recent::add($title,
          $url,
          $activity->id,
          'Activity',
          $recentContactId,
          $recentContactDisplay,
          $recentOther
        );
      }
    }

    // reset the group contact cache since smart groups might be affected due to this
    require_once 'CRM/Contact/BAO/GroupContactCache.php';
    CRM_Contact_BAO_GroupContactCache::remove();

    if (CRM_Utils_Array::value('id', $params)) {
      CRM_Utils_Hook::post('edit', 'Activity', $activity->id, $activity);
    }
    else {
      CRM_Utils_Hook::post('create', 'Activity', $activity->id, $activity);
    }

    // if the subject contains a ‘[case #…]’ string, file that activity on the related case (CRM-5916)
    $matches = array();
    if (preg_match('/\[case #([0-9a-h]{7})\]/', $params['subject'], $matches)) {
      $key = CRM_Core_DAO::escapeString(CIVICRM_SITE_KEY);
      $hash = $matches[1];
      $query = "SELECT id FROM civicrm_case WHERE SUBSTR(SHA1(CONCAT('$key', id)), 1, 7) = '$hash'";
      $caseParams = array(
        'activity_id' => $activity->id,
        'case_id' => CRM_Core_DAO::singleValueQuery($query),
      );
      if ($caseParams['case_id']) {
        require_once 'CRM/Case/BAO/Case.php';
        CRM_Case_BAO_Case::processCaseActivity($caseParams);
      }
      else {
        self::logActivityAction($activity, "unknown case hash encountered: $hash");
      }
    }

    return $result;
  }

  public function logActivityAction($activity, $logMessage = NULL) {
    $session = &CRM_Core_Session::singleton();
    $id = $session->get('userID');
    if (!$id) {
      $id = $activity->source_contact_id;
    }
    require_once 'CRM/Core/BAO/Log.php';
    $logParams = array(
      'entity_table' => 'civicrm_activity',
      'entity_id' => $activity->id,
      'modified_id' => $id,
      'modified_date' => date('YmdHis'),
      'data' => $logMessage,
    );
    CRM_Core_BAO_Log::add($logParams);
    return TRUE;
  }

  /**
   * function to get the list Actvities
   *
   * @param array reference $params  array of parameters
   * @param int     $offset          which row to start from ?
   * @param int     $rowCount        how many rows to fetch
   * @param object|array  $sort      object or array describing sort order for sql query.
   * @param type    $type            type of activity we're interested in
   * @param boolean $admin           if contact is admin
   * @param int     $caseId          case id
   * @param string  $context         context , page on which selector is build
   *
   * @return array (reference)      $values the relevant data object values of open activitie
   *
   * @access public
   * @static
   */
  static function &getActivities(&$data, $offset = NULL, $rowCount = NULL, $sort = NULL,
    $admin = FALSE, $caseId = NULL, $context = NULL
  ) {
    //step 1: Get the basic activity data
    $optionValues = CRM_Core_OptionGroup::values('activity_type', FALSE, FALSE,  FALSE, "AND v.name = 'Bulk Email'", 'label', $onlyActive = FALSE);
    $bulkActivityTypeID = key($optionValues);

    $config = CRM_Core_Config::singleton();

    $randomNum = md5(uniqid());
    $activityTempTable = "civicrm_temp_activity_details_{$randomNum}";

    $tableFields = array('activity_id' => 'int unsigned',
      'duration' => 'int unsigned',
      'activity_date_time' => 'datetime',
      'status_id' => 'int unsigned',
      'subject' => 'varchar(255)',
      'source_contact_id' => 'int unsigned',
      'source_record_id' => 'int unsigned',
      'source_contact_name' => 'varchar(255)',
      'activity_type_id' => 'int unsigned',
      'activity_type' => 'varchar(128)',
      'case_id' => 'int unsigned',
      'case_subject' => 'varchar(255)',
    );

    $sql = "CREATE TEMPORARY TABLE {$activityTempTable} ( ";
    $insertValueSQL = array();
    foreach ($tableFields as $name => $desc) {
      $sql .= "$name $desc,\n";
      $insertValueSQL[] = $name;
    }

    $sql .= "
          PRIMARY KEY ( activity_id )
        ) ENGINE=HEAP DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
        ";

    CRM_Core_DAO::executeQuery($sql);

    $insertSQL = "INSERT INTO {$activityTempTable} (" . implode(',', $insertValueSQL) . " ) ";

    $order = $limit = $groupBy = '';
    $groupBy = " GROUP BY activity_id";
    if ($sort) {
      $orderBy = $sort->orderBy();
      if (!empty($orderBy)) {
        $order = " ORDER BY $orderBy";
      }
    }

    if (empty($order)) {
      if ($context == 'activity') {
        $order = " ORDER BY activity_date_time desc ";
      }
      else {
        $order = " ORDER BY status_id asc, activity_date_time asc ";
      }
    }

    if ($rowCount > 0) {
      $limit = " LIMIT $offset, $rowCount ";
    }

    list($sqlClause, $params) = self::getActivitySQLClause($data['contact_id'], $admin, $caseId, $context);
    $query = "{$insertSQL}
       SELECT DISTINCT *  from ( {$sqlClause} )
as tbl ";

    $query = $query . $groupBy . $order . $limit;

    $dao = CRM_Core_DAO::executeQuery($query, $params);

    // step 2: Get target and assignee contacts for above activities
    // create temp table for target contacts
    $activityTargetContactTempTable = "civicrm_temp_target_contact_{$randomNum}";
    $query = "CREATE TEMPORARY TABLE {$activityTargetContactTempTable} ( 
                activity_id int unsigned, target_contact_id int unsigned, target_contact_name varchar(255) )
                ENGINE=MYISAM DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

    CRM_Core_DAO::executeQuery($query);

    // note that we ignore bulk email for targets, since we don't show it in selector
    $query = "INSERT INTO {$activityTargetContactTempTable} ( activity_id, target_contact_id, target_contact_name )
                  SELECT at.activity_id, 
                  at.target_contact_id , 
                  c.sort_name
                  FROM civicrm_activity_target at
                  INNER JOIN {$activityTempTable} ON ( at.activity_id = {$activityTempTable}.activity_id 
                    AND {$activityTempTable}.activity_type_id <> {$bulkActivityTypeID} )
                  INNER JOIN civicrm_contact c ON c.id = at.target_contact_id
                  WHERE c.is_deleted = 0";

    CRM_Core_DAO::executeQuery($query);

    // create temp table for assignee contacts
    $activityAssigneetContactTempTable = "civicrm_temp_assignee_contact_{$randomNum}";
    $query = "CREATE TEMPORARY TABLE {$activityAssigneetContactTempTable} ( 
                activity_id int unsigned, assignee_contact_id int unsigned, assignee_contact_name varchar(255) )
                ENGINE=MYISAM DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

    CRM_Core_DAO::executeQuery($query);

    // note that we ignore bulk email for assignee, since we don't show it in selector
    $query = "INSERT INTO {$activityAssigneetContactTempTable} ( activity_id, assignee_contact_id, assignee_contact_name )
                  SELECT DISTINCT ( aa.activity_id ) , 
                  aa.assignee_contact_id, 
                  c.sort_name
                  FROM civicrm_activity_assignment aa
                  INNER JOIN {$activityTempTable} ON ( aa.activity_id = {$activityTempTable}.activity_id
                      AND {$activityTempTable}.activity_type_id <> {$bulkActivityTypeID} )
                  INNER JOIN civicrm_contact c ON c.id = aa.assignee_contact_id
                  WHERE c.is_deleted = 0";

    CRM_Core_DAO::executeQuery($query);

    // step 3: Combine all temp tables to get final query for activity selector
    $query = " 
        SELECT {$activityTempTable}.*, 
               {$activityTargetContactTempTable}.target_contact_id,{$activityTargetContactTempTable}.target_contact_name, 
               {$activityAssigneetContactTempTable}.assignee_contact_id, {$activityAssigneetContactTempTable}.assignee_contact_name
        FROM  {$activityTempTable}
            LEFT JOIN {$activityTargetContactTempTable} on {$activityTempTable}.activity_id = {$activityTargetContactTempTable}.activity_id
            LEFT JOIN {$activityAssigneetContactTempTable} on {$activityTempTable}.activity_id = {$activityAssigneetContactTempTable}.activity_id                  
        ";

    $where = array();
    //filter case activities - CRM-5761
    $components = self::activityComponents();
    if (!in_array('CiviCase', $components)) {
      $query .= "
LEFT JOIN  civicrm_case_activity ON ( civicrm_case_activity.activity_id = {$activityTempTable}.activity_id ) ";
      $where[] = "civicrm_case_activity.id IS NULL";
    }

    // filter activity by data parameter(only allow activity table)
    if ($data['source_record_id'] && CRM_Utils_Type::validate($data['source_record_id'], 'Positive', FALSE)) {
      $where[] = "$activityTempTable.source_record_id = '{$data['source_record_id']}'";
    }
    if ($data['activity_type_id'] && CRM_Utils_Type::validate($data['activity_type_id'], 'Positive', FALSE)) {
      $where[] = "$activityTempTable.activity_type_id = '{$data['activity_type_id']}'";
    }

    if (!empty($where) ) {
      $query .= ' WHERE '.implode(' AND ', $where);
    }

    $dao = CRM_Core_DAO::executeQuery($query);

    //CRM-3553, need to check user has access to target groups.
    require_once 'CRM/Mailing/BAO/Mailing.php';
    $mailingIDs = &CRM_Mailing_BAO_Mailing::mailingACLIDs();
    $accessCiviMail = CRM_Core_Permission::check('access CiviMail');

    $values = array();
    while ($dao->fetch()) {
      $activityID = $dao->activity_id;
      $values[$activityID]['activity_id'] = $dao->activity_id;
      $values[$activityID]['duration'] = $dao->duration;
      $values[$activityID]['source_record_id'] = $dao->source_record_id;
      $values[$activityID]['activity_type_id'] = $dao->activity_type_id;
      $values[$activityID]['activity_type'] = $dao->activity_type;
      $values[$activityID]['activity_date_time'] = $dao->activity_date_time;
      $values[$activityID]['status_id'] = $dao->status_id;
      $values[$activityID]['subject'] = $dao->subject;
      $values[$activityID]['source_contact_name'] = $dao->source_contact_name;
      $values[$activityID]['source_contact_id'] = $dao->source_contact_id;
      $values[$activityID]['data_contact_id'] = $data['contact_id'];

      if ($bulkActivityTypeID != $dao->activity_type_id) {
        // build array of target / assignee names
        $values[$activityID]['target_contact_name'][$dao->target_contact_id] = $dao->target_contact_name;
        $values[$activityID]['assignee_contact_name'][$dao->assignee_contact_id] = $dao->assignee_contact_name;

        // case related fields
        $values[$activityID]['case_id'] = $dao->case_id;
        $values[$activityID]['case_subject'] = $dao->case_subject;
      }
      else {
        $values[$activityID]['recipients'] = ts('(recipients)');
        if ($accessCiviMail && in_array($dao->source_record_id, $mailingIDs)) {
          $values[$activityID]['mailingId'] = CRM_Utils_System::url('civicrm/mailing/report',
            "mid={$dao->source_record_id}&reset=1&cid={$dao->source_contact_id}&context=activitySelector"
          );
          $values[$activityID]['target_contact_name'] = '';
          $values[$activityID]['assignee_contact_name'] = '';
        }
      }
    }

    // add info on whether the related contacts are deleted (CRM-5673)
    // FIXME: ideally this should be tied to ACLs

    // grab all the related contact ids
    $cids = array();
    foreach ($values as $value) {
      $cids[] = $value['source_contact_id'];
    }
    $cids = array_filter(array_unique($cids));

    // see which of the cids are of deleted contacts
    if ($cids) {
      $sql = 'SELECT id FROM civicrm_contact WHERE id IN (' . implode(', ', $cids) . ') AND is_deleted = 1';
      $dao = &CRM_Core_DAO::executeQuery($sql);
      $dels = array();
      while ($dao->fetch()) {
        $dels[] = $dao->id;
      }

      // hide the deleted contacts
      foreach ($values as & $value) {
        if (in_array($value['source_contact_id'], $dels)) {
          unset($value['source_contact_id'], $value['source_contact_name']);
        }
      }
    }

    return $values;
  }

  /**
   * Get the component id and name those are enabled and logged in
   * user has permission. To decide whether we are going to include
   * component related activities w/ core activity retrieve process.
   *
   * return an array of component id and name.
   **/
  function activityComponents() {
    require_once 'CRM/Core/Permission.php';
    $components = array();
    $compInfo = CRM_Core_Component::getEnabledComponents();
    foreach ($compInfo as $compObj) {
      if (CRM_Utils_Array::value('showActivitiesInCore', $compObj->info)) {
        if ($compObj->info['name'] == 'CiviCampaign') {
          $componentPermission = "administer {$compObj->name}";
        }
        else {
          $componentPermission = "access {$compObj->name}";
        }
        if ($compObj->info['name'] == 'CiviCase') {
          require_once 'CRM/Case/BAO/Case.php';
          if (CRM_Case_BAO_Case::accessCiviCase()) {
            $components[$compObj->componentID] = $compObj->info['name'];
          }
        }
        elseif (CRM_Core_Permission::check($componentPermission)) {
          $components[$compObj->componentID] = $compObj->info['name'];
        }
      }
    }

    return $components;
  }

  /**
   * function to get the actvity count
   *
   * @param int     $contactID       Contact ID
   * @param boolean $admin           if contact is admin
   * @param int     $caseId          case id
   * @param string  $context         context , page on which selector is build
   *
   * @return int   count of activities
   *
   * @access public
   * @static
   */
  static function &getActivitiesCount($contactID, $admin = FALSE, $caseId = NULL, $context = NULL) {
    list($sqlClause, $params) = self::getActivitySQLClause($contactID, $admin, $caseId, $context, TRUE);

    $query = "SELECT COUNT(DISTINCT(activity_id)) as count  from ( {$sqlClause} ) as tbl";

    //filter case activities - CRM-5761
    $components = self::activityComponents();
    if (!in_array('CiviCase', $components)) {
      $query = "
   SELECT   COUNT(DISTINCT(tbl.activity_id)) as count  
     FROM   ( {$sqlClause} ) as tbl
LEFT JOIN   civicrm_case_activity ON ( civicrm_case_activity.activity_id = tbl.activity_id )
    WHERE   civicrm_case_activity.id IS NULL";
    }

    return CRM_Core_DAO::singleValueQuery($query, $params);
  }

  static function getActivitySQLClause($contactID, $admin = FALSE, $caseId = NULL, $context = NULL, $count = FALSE) {
    $params = array();
    $sourceWhere = $targetWhere = $assigneeWhere = $caseWhere = 1;

    $config = CRM_Core_Config::singleton();
    if (!$admin) {
      $sourceWhere = ' source_contact_id = %1 ';
      $targetWhere = ' at.target_contact_id = %1 ';
      $assigneeWhere = ' aa.assignee_contact_id = %1 ';
      $caseWhere = ' civicrm_case_contact.contact_id = %1 ';

      $params = array(1 => array($contactID, 'Integer'));
    }

    $statusClause = 1;
    if ($context == 'home') {
      $statusClause = " civicrm_activity.status_id = 1 ";
    }

    //Filter on component IDs.
    $components = self::activityComponents();
    $componentClause = 'civicrm_option_value.component_id IS NULL';
    if (!empty($components)) {
      $componentsIn = implode(',', array_keys($components));
      $componentClause = "( $componentClause OR civicrm_option_value.component_id IN ( $componentsIn ) )";
    }
    $includeCaseActivities = FALSE;
    if (in_array('CiviCase', $components)) {
      $includeCaseActivities = TRUE;
    }


    // build main activity table select clause
    $sourceSelect = '';
    $sourceJoin = '';

    if (!$count) {
      $sourceSelect = ',
                civicrm_activity.duration,
                civicrm_activity.activity_date_time,
                civicrm_activity.status_id, 
                civicrm_activity.subject,
                civicrm_activity.source_contact_id, 
                civicrm_activity.source_record_id,
                sourceContact.sort_name as source_contact_name,
                civicrm_option_value.value as activity_type_id,
                civicrm_option_value.label as activity_type,
                null as case_id, null as case_subject
            ';

      $sourceJoin = ' 
                left join civicrm_contact sourceContact on
                      source_contact_id = sourceContact.id ';
    }

    $sourceClause = "
            SELECT civicrm_activity.id as activity_id
            {$sourceSelect}    
            from civicrm_activity                   
            left join civicrm_option_value on
                civicrm_activity.activity_type_id = civicrm_option_value.value
            left join civicrm_option_group on                              
                civicrm_option_group.id = civicrm_option_value.option_group_id
            {$sourceJoin}                      
            where   {$sourceWhere}
                and civicrm_option_group.name = 'activity_type'                 
                and {$componentClause}                 
                and civicrm_activity.is_deleted = 0
                and civicrm_activity.is_current_revision = 1                 
                and is_test = 0
                and {$statusClause}
        ";

    // build target activity table select clause
    $targetAssigneeSelect = '';

    if (!$count) {
      $targetAssigneeSelect = ',
                civicrm_activity.duration,
                civicrm_activity.activity_date_time,
                civicrm_activity.status_id, 
                civicrm_activity.subject,
                civicrm_activity.source_contact_id,
                civicrm_activity.source_record_id, 
                sourceContact.sort_name as source_contact_name,
                civicrm_option_value.value as activity_type_id,
                civicrm_option_value.label as activity_type,
                null as case_id, null as case_subject
            ';
    }

    $targetClause = "
            SELECT civicrm_activity.id as activity_id
            {$targetAssigneeSelect}
            from civicrm_activity                   
            inner join civicrm_activity_target at on                             
                civicrm_activity.id = at.activity_id and {$targetWhere}
            left join civicrm_option_value on
                civicrm_activity.activity_type_id = civicrm_option_value.value
            left join civicrm_option_group on                              
                civicrm_option_group.id = civicrm_option_value.option_group_id
            {$sourceJoin}                      
            where   {$targetWhere}
                and civicrm_option_group.name = 'activity_type'                 
                and {$componentClause}                 
                and civicrm_activity.is_deleted = 0
                and civicrm_activity.is_current_revision = 1                 
                and is_test = 0
                and {$statusClause}
        ";

    // build assignee activity table select clause
    $assigneeClause = "
            SELECT civicrm_activity.id as activity_id
            {$targetAssigneeSelect}
            from civicrm_activity                   
            inner join civicrm_activity_assignment aa on
                civicrm_activity.id = aa.activity_id and {$assigneeWhere}
            left join civicrm_option_value on
                civicrm_activity.activity_type_id = civicrm_option_value.value
            left join civicrm_option_group on                              
                civicrm_option_group.id = civicrm_option_value.option_group_id                      
            {$sourceJoin}
            where   {$assigneeWhere}
                and civicrm_option_group.name = 'activity_type'                 
                and {$componentClause}                 
                and civicrm_activity.is_deleted = 0
                and civicrm_activity.is_current_revision = 1                 
                and is_test = 0
                and {$statusClause}
        ";

    // Build case clause
    // or else exclude Inbound Emails that have been filed on a case.
    $caseClause = '';

    if ($includeCaseActivities) {
      $caseSelect = '';
      if (!$count) {
        $caseSelect = ', 
                civicrm_activity.duration,
                civicrm_activity.activity_date_time,
                civicrm_activity.status_id, 
                civicrm_activity.subject,
                civicrm_activity.source_contact_id,
                civicrm_activity.source_record_id, 
                sourceContact.sort_name as source_contact_name,
                civicrm_option_value.value as activity_type_id,
                civicrm_option_value.label as activity_type,
                null as case_id, null as case_subject ';
      }

      $caseClause = "
                union all

                SELECT civicrm_activity.id as activity_id
                {$caseSelect}    
                from civicrm_activity                   
                inner join civicrm_case_activity on                               
                    civicrm_case_activity.activity_id = civicrm_activity.id                   
                inner join civicrm_case on                               
                    civicrm_case_activity.case_id = civicrm_case.id                     
                inner join civicrm_case_contact on                               
                    civicrm_case_contact.case_id = civicrm_case.id and {$caseWhere} 
                left join civicrm_option_value on 
                    civicrm_activity.activity_type_id = civicrm_option_value.value
                left join civicrm_option_group on                              
                    civicrm_option_group.id = civicrm_option_value.option_group_id
                {$sourceJoin}                                      
                where   {$caseWhere}
                    and civicrm_option_group.name = 'activity_type'                 
                    and {$componentClause}                 
                    and civicrm_activity.is_deleted = 0
                    and civicrm_activity.is_current_revision = 1                 
                    and is_test = 0
                    and {$statusClause}
                    and  ( ( civicrm_case_activity.case_id Is Null ) OR
                           ( civicrm_option_value.name <> 'Inbound Email' AND
                             civicrm_option_value.name <> 'Email' AND civicrm_case_activity.case_id
                             Is Not Null ) 
                         )             
            ";
    }

    $returnClause = " {$sourceClause}  union all {$targetClause} union all {$assigneeClause} {$caseClause} ";

    return array($returnClause, $params);
  }

  /**
   * send the message to all the contacts and also insert a
   * contact activity in each contacts record
   *
   * @param array  $contactDetails array('contact_id' => 123, 'email' => 'aaa@bbb.ccc')
   * @param string $subject      the subject of the message
   * @param string $message      the message contents
   * @param string $emailAddress use this 'to' email address instead of the default Primary address
   * @param int    $userID       use this userID if set
   * @param string $from
   * @param array  $attachments  the array of attachments if any
   * @param string $cc           cc recepient
   * @param string $bcc          bcc recepient
   * @param array $contactIds    contact ids
   * @param object form object for processing this email
   *
   * @return array               ( sent, activityId) if any email is sent and activityId
   * @access public
   * @static
   */
  static function sendEmail(
    &$contactDetails,
    &$subject,
    &$text,
    &$html,
    $emailAddress,
    $userID = NULL,
    $from = NULL,
    $attachments = NULL,
    $cc = NULL,
    $bcc = NULL,
    &$contactIds = NULL,
    &$object = NULL
  ) {
    $class = is_object($object) ? get_class($object) : 'CRM_Activity_BAO_Activity';
    if (empty($contactIds)) {
      foreach($contactDetails as $cDetails) {
        $contactIds[] = $cDetails['contact_id'];
      }
    }

    // get the contact details of logged in contact, which we set as from email
    if ($userID == NULL) {
      $session = CRM_Core_Session::singleton();
      $userID = $session->get('userID');

      // #27589 when anonymous user trigger this, use self as message sender
      if (empty($userID) && count($contactIds) == 1) {
        $userID = reset($contactIds);
      }
    }

    if (!$from) {
      list($fromDisplayName, $fromEmail, $fromDoNotEmail) = CRM_Contact_BAO_Contact::getContactDetails($userID);
      if (!$fromEmail) {
        return array(count($contactDetails), 0, count($contactDetails));
      }

      if (!trim($fromDisplayName)) {
        $fromDisplayName = $fromEmail;
      }
      $from = "$fromDisplayName <$fromEmail>";
    }

    //CRM-4575
    //token replacement of addressee/email/postal greetings
    // get the tokens added in subject and message
    $messageToken = CRM_Utils_Token::getTokens($text);
    $subjectToken = CRM_Utils_Token::getTokens($subject);
    $htmlToken = CRM_Utils_Token::getTokens($html);
    $allTokens = array_merge_recursive($messageToken, $subjectToken, $htmlToken);

    //create the meta level record first ( email activity )
    $activityTypeID = CRM_Core_OptionGroup::getValue('activity_type', 'Email', 'name');

    // CRM-6265: save both text and HTML parts in details (if present)
    if ($html and $text) {
      $details = "-ALTERNATIVE ITEM 0-\n$html\n-ALTERNATIVE ITEM 1-\n$text\n-ALTERNATIVE END-\n";
    }
    else {
      $details = $html ? $html : $text;
    }
    
    $activityParams = array(
      'source_contact_id' => $userID,
      'activity_type_id' => $activityTypeID,
      'activity_date_time' => date('YmdHis'),
      'subject' => $subject,
      'details' => $details,
      // FIXME: check for name Completed and get ID from that lookup
      'status_id' => 2,
    );

    // CRM-5916: strip [case #…] before saving the activity (if present in subject)
    $activityParams['subject'] = preg_replace('/\[case #([0-9a-h]{7})\] /', '', $activityParams['subject']);

    // add the attachments to activity params here
    if ($attachments) {
      // first process them
      $activityParams = array_merge($activityParams,
        $attachments
      );
    }

    $activity = self::create($activityParams);

    // get the set of attachments from where they are stored
    $attachments = &CRM_Core_BAO_File::getEntityFile('civicrm_activity',
      $activity->id
    );
    
    $returnProperties = $details = array();
    if (isset($allTokens['contact'])) {
      foreach ($allTokens['contact'] as $key => $value) {
        $returnProperties[$value] = 1;
      }
    }
    if (!empty($returnProperties) || !empty($allTokens)) {
      $flatten = array('html' => $allTokens);
      $flatten = CRM_Utils_Token::flattenTokens($flatten);
      list($details) = CRM_Utils_Token::getTokenDetails(
        $contactIds,
        $returnProperties,
        NULL, NULL, FALSE,
        $flatten,
        $class
      );
    }

    // call token hook
    $tokens = array();
    CRM_Utils_Hook::tokens($tokens);
    $categories = array_keys($tokens);

    $escapeSmarty = FALSE;
    if (defined('CIVICRM_MAIL_SMARTY')) {
      $smarty = CRM_Core_Smarty::singleton();
      require_once('CRM/Core/Smarty/resources/String.php');
      civicrm_smarty_register_string_resource();

      $escapeSmarty = TRUE;
    }

    $sent = $notSent = array();
    $domain = CRM_Core_BAO_Domain::getDomain();

    foreach ($contactDetails as $values) {
      $contactId = $values['contact_id'];
      $emailAddress = $values['email'];

      if (!empty($details) && is_array($details["{$contactId}"])) {
        // unset email from details since it always returns primary email address
        unset($details["{$contactId}"]['email']);
        unset($details["{$contactId}"]['email_id']);
        $values = array_merge($values, $details["{$contactId}"]);
      }

      $tokenSubject = CRM_Utils_Token::replaceContactTokens($subject, $values, FALSE, $subjectToken, FALSE, $escapeSmarty);
      $tokenSubject = CRM_Utils_Token::replaceDomainTokens($tokenSubject, $domain, FALSE, $subjectToken, $escapeSmarty);
      $tokenSubject = CRM_Utils_Token::replaceHookTokens($tokenSubject, $values, $categories, FALSE, $escapeSmarty);

      //CRM-4539
      if ($values['preferred_mail_format'] == 'Text' || $values['preferred_mail_format'] == 'Both') {
        $tokenText = CRM_Utils_Token::replaceContactTokens($text, $values, FALSE, $messageToken, FALSE, $escapeSmarty);
        $tokenText = CRM_Utils_Token::replaceDomainTokens($tokenText, $domain, FALSE, $messageToken, $escapeSmarty);
        $tokenText = CRM_Utils_Token::replaceHookTokens($tokenText, $values, $categories, FALSE, $escapeSmarty);
      }
      else {
        $tokenText = NULL;
      }

      if ($values['preferred_mail_format'] == 'HTML' || $values['preferred_mail_format'] == 'Both') {
        $tokenHtml = CRM_Utils_Token::replaceContactTokens($html, $values, TRUE, $htmlToken, FALSE, $escapeSmarty);
        $tokenHtml = CRM_Utils_Token::replaceDomainTokens($tokenHtml, $domain, TRUE, $htmlToken, $escapeSmarty);
        $tokenHtml = CRM_Utils_Token::replaceHookTokens($tokenHtml, $values, $categories, TRUE, $escapeSmarty);
      }
      else {
        $tokenHtml = NULL;
      }

      if (defined('CIVICRM_MAIL_SMARTY')) {
        // also add the contact tokens to the template
        $smarty->assign_by_ref('contact', $values);

        $tokenText = $smarty->fetch("string:$tokenText");
        $tokenHtml = $smarty->fetch("string:$tokenHtml");
      }

      $sent = FALSE;
      if (self::sendMessage($from,
          $userID,
          $contactId,
          $tokenSubject,
          $tokenText,
          $tokenHtml,
          $emailAddress,
          $activity->id,
          $attachments,
          $cc,
          $bcc
        )) {
        $sent = TRUE;
      }
    }

    return array($sent, $activity->id);
  }

  /**
   * @param int  $fromId     from contact id
   * @param int  $toId       to contact id
   * @param int  $templateId message template id
   * @param int  $check      check if mail should be send on hold, desease or do not email
   */
  public static function sendEmailTemplate($from, $toId, $template_id, $check = FALSE) {
    $returnProperties = array(
      'sort_name' => 1,
      'email' => 1,
      'do_not_email' => 1,
      'is_deceased' => 1,
      'on_hold' => 1,
      'display_name' => 1,
      'preferred_mail_format' => 1,
    );
    $getDetails = array($toId);
    if (is_numeric($from)) {
      $fromId = $from;
      $getDetails[] = $fromId;
      $from = NULL;
    }
    else {
      $fromId = NULL;
    }
    list($details) = CRM_Mailing_BAO_Mailing::getDetails($getDetails, $returnProperties, FALSE, FALSE);
    if (!empty($details)) {
      $toDetails = $details[$toId];
      $contactIds = array($toId);

      if ($check) {
        if ($toDetails['do_not_email'] || empty($toDetails['email']) || !empty($toDetails['is_deceased']) || $toDetails['on_hold']) {
          return FALSE;
        }
      }

      $toDetails = array($details[$toId]);
      $params = array('id' => $template_id);
      $template = array();
      CRM_Core_BAO_MessageTemplates::retrieve($params, $template);
      $subject = $template['msg_subject'];
      $text = !empty($template['msg_text']) ? $template['msg_text'] : '';
      $html = !empty($template['msg_html']) ? $template['msg_html'] : '';
      list($sent, $activityId) = self::sendEmail(
        $toDetails,
        $subject,
        $text,
        $html,
        NULL, // emailAddress
        $fromId, // sender contact id
        $from, // formatted from address
        NULL, // attachments
        NULL, // cc
        NULL, // bcc
        $contactIds // contact_id
      );
      if ($sent) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Prepare SMS, Copy from SMSCommon::postProcess()
   *
   * @param array $contactIds
   *        Also cound be 1 integer of contact_id
   * @param integer $providerId
   * @param string $message
   * @param array $values objects, contribution is $values['contribution']
   *
   * @return null
   */
  public static function prepareSMS(
    $contactIds,
    $providerId,
    $message,
    $values = array()
  ) {
    $activityParams = array(
      'sms_provider_id' => $providerId,
      'sms_text_message' => $message,
      'activity_subject' => substr($message, 0, 10),
    );
    $activityTypes = CRM_Core_PseudoConstant::activityType(TRUE, TRUE, FALSE, 'name', TRUE);
    if (!empty($values['participant'])) {
      $activityParams['activity_type_id'] = CRM_Utils_Array::key('Event Registration SMS', $activityTypes);
      $activityParams['source_record_id'] = $values['participant']->id;
      $activityParams['subject'] = $values['participant']->source;
    }
    else if (!empty($values['contribution'])) {
      $activityParams['activity_type_id'] = CRM_Utils_Array::key('Contribution SMS', $activityTypes);
      $activityParams['source_record_id'] = $values['contribution']->id;
      $activityParams['subject'] = $values['contribution']->source;
    }
    $smsParams = array(
      'provider_id' => $providerId,
    );
    // format contact details array to handle multiple sms from same contact
    $contactDetails = array();
    $phoneTypeId = array_search(ts('Mobile'), CRM_Core_PseudoConstant::phoneType());

    if(!is_array($contactIds)){
      $contactIds = array($contactIds);
    }
    foreach ($contactIds as $cid) {
      $contact = array();
      // print($cid."\n");

      $dao_phone = new CRM_Core_DAO_Phone();
      $dao_phone->contact_id = $cid;
      $dao_phone->phone_type_id = $phoneTypeId;
      $dao_phone->is_primary = true;
      if($dao_phone->find(TRUE)){
        // print_r($dao_phone);
        $contact['phone'] = $dao_phone->phone;
        $contact['phone_type_id'] = $phoneTypeId;

        $dao_contact = new CRM_Contact_DAO_Contact();
        $dao_contact->id = $cid;
        if($dao_contact->find(TRUE)){
          // print_r($dao_contact);
          $contact['id'] = $dao_contact->id;
          $contact['do_not_sms'] = $dao_contact->do_not_sms;
          $contact['is_deceased'] = $dao_contact->is_deceased;
          $contact['is_deleted'] = $dao_contact->is_deleted;
          $contact['sort_name'] = $dao_contact->sort_name;
          $contact['display_name'] = $dao_contact->display_name;
        }
        if ($values['contribution']) {
          foreach ($values['contribution'] as $key => $value) {
            if (substr($key, 0, 1) != '_') {
              $contact["contribution.{$key}"] = $value;
            }
          }
        }
      }
      if(!empty($contact)){
        $contactDetails[$cid] = $contact;
      }
    }
    // print_r($contactDetails);
    // exit;


    // $smsParams carries all the arguments provided on form (or via hooks), to the provider->send() method
    // this gives flexibity to the users / implementors to add their own args via hooks specific to their sms providers
    $smsParams = $activityParams;
    unset($smsParams['sms_text_message']);
    $smsParams['provider_id'] = $providerId;

    list($sent, $activityId, $countSuccess) = CRM_Activity_BAO_Activity::sendSMS(
      $contactDetails,
      $activityParams,
      $smsParams,
      $contactIds
    );
  }

  /**
   * Send SMS.
   *
   * @param array $contactDetails
   *        An Array of all contacts need to send,
   *        each contact is an array must contain 'phone', 'phone_type_id', 'contact_id'
   *        These field also could be included : 'do_not_sms', 'is_deceased', 'is_deleted'
   * @param array $activityParams An array of activity parameters.
   * @param array $smsParams An array of parameters of sending SMS, must include 'provider_id'.
   * @param array $contactIds An array of contact_id as values.
   * @param int $userID
   *
   * @return array
   * @throws CRM_Core_Exception
   */
  public static function sendSMS(
    &$contactDetails,
    &$activityParams,
    &$smsParams = array(),
    &$contactIds,
    $userID = NULL
  ) {
    if ($userID == NULL) {
      $session = CRM_Core_Session::singleton();
      if (!is_numeric($session->get('userID'))) {
        $userID = CRM_Core_BAO_UFMatch::getContactId(1);
      }
      else{
        $userID = $session->get('userID');
      }
    }

    $text = &$activityParams['sms_text_message'];

    // CRM-4575
    // token replacement of addressee/email/postal greetings
    // get the tokens added in subject and message
    $messageToken = CRM_Utils_Token::getTokens($text);

    // Create the meta level record first ( sms activity )
    if (empty($activityParams['activity_type_id'])) {
      $activityParams['activity_type_id'] = CRM_Utils_Array::key('SMS', CRM_Core_PseudoConstant::activityType(TRUE, TRUE, FALSE, 'name', TRUE));
    }
    // $activityTypeID = CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity',
    //   'activity_type_id',
    //   'SMS'
    // );

    $returnProperties = array();

    if (isset($messageToken['contact'])) {
      foreach ($messageToken['contact'] as $key => $value) {
        $returnProperties[$value] = 1;
      }
    }

    // call token hook
    $tokens = array();
    CRM_Utils_Hook::tokens($tokens);
    $categories = array_keys($tokens);

    // get token details for contacts, call only if tokens are used
    $details = array();
    if (!empty($returnProperties) || !empty($tokens)) {
      list($details) = CRM_Utils_Token::getTokenDetails($contactIds,
        $returnProperties,
        NULL, NULL, FALSE,
        $messageToken,
        'CRM_Activity_BAO_Activity'
      );
    }

    $success = 0;
    $escapeSmarty = FALSE;
    $errMsgs = array();
    foreach ($contactDetails as $values) {
      $eachActivityParams = $activityParams;
      if (!empty($values['contact_id'])) {
        $contactId = $values['contact_id'];
      }
      else {
        // When contribution success SMS, the contact_id index of $values is ['id'].
        $contactId = $values['id'];
      }

      if (!empty($details) && is_array($details["{$contactId}"])) {
        // unset phone from details since it always returns primary number
        unset($details["{$contactId}"]['phone']);
        unset($details["{$contactId}"]['phone_type_id']);
        $values = array_merge($values, $details["{$contactId}"]);
      }

      CRM_Utils_Hook::tokenValues($values, $contactId, NULL, $messageToken, 'CRM_Activity_BAO_Activity::sendSMS');

      $tokenText = CRM_Utils_Token::replaceContactTokens($text, $values, FALSE, $messageToken, FALSE, $escapeSmarty);
      $tokenText = CRM_Utils_Token::replaceComponentTokens($tokenText, $values, $messageToken, TRUE);
      $tokenText = CRM_Utils_Token::replaceHookTokens($tokenText, $values, $categories, FALSE, $escapeSmarty);

      // Only send if the phone is of type mobile
      $phoneTypes = CRM_Core_PseudoConstant::phoneType();
      if ($values['phone_type_id'] == CRM_Utils_Array::key(ts('Mobile'), $phoneTypes)) {
        $smsParams['To'] = $values['phone'];
      }
      else {
        $smsParams['To'] = '';
      }

      if (!empty($eachActivityParams['activity_subject']) && empty($eachActivityParams['subject'])) {
        $eachActivityParams['subject'] = $eachActivityParams['activity_subject'];
      }
      $eachActivityParams['source_contact_id'] = $userID;
      $eachActivityParams['activity_date_time'] = date('YmdHis');
      $eachActivityParams['details'] = ts("Body") . ": " . $tokenText;
      $eachActivityParams['status_id'] = CRM_Utils_Array::key('Scheduled', CRM_Core_PseudoConstant::activityStatus('name'));

      $activity = self::create($eachActivityParams);

      $message = '';
      $isSuccess = FALSE;
      $sendResult = self::sendSMSMessage(
        $contactId,
        $tokenText,
        $smsParams,
        $activity->id,
        $userID
      );

      $isSuccess = FALSE;
      $activity->details .= nl2br("\n" . ts("To") .": ". $smsParams['To']);
      if (empty($sendResult->_error)) {
        $activity->details .= nl2br("\n\n".$sendResult);
        if (preg_match('/^statuscode=(\w+)\r?$/m', $sendResult, $findResult)) {
          $resultKey = $findResult[1];
          if (CRM_Utils_Array::crmInArray($resultKey, array(0,1,2,3,4))) {
            // Send Success
            $isSuccess = TRUE;
            $activity->status_id = CRM_Utils_Array::key('Completed', CRM_Core_PseudoConstant::activityStatus('name'));
            if (preg_match('/^Duplicate=Y\r?$/m', $sendResult)) {
              // Send failed
              $isSuccess = FALSE;
              $activity->status_id = CRM_Utils_Array::key('Cancelled', CRM_Core_PseudoConstant::activityStatus('name'));
              $message = ts('Duplicated message');
            }
          }
        }
        if (preg_match('/^Error=([^\r]+)\r?$/m', $sendResult, $findError)) {
          $message = $findError[1];
        }
      }
      else {
        $message = $sendResult->_error['error'];
        $sendResult = $sendResult->_error['error'];
      }

      if (!$isSuccess) {
        if (empty($message)) {
          $message = ts('Unknown error');
        }

        // Send failed
        $activity->status_id = CRM_Utils_Array::key('Cancelled', CRM_Core_PseudoConstant::activityStatus('name'));
        $activity->details .= nl2br("\n" .ts("Additional Details:"). $message);

        $errMsgs[] = $message;
      }
      $activity->save();

      if ($isSuccess) {
        $success++;
      }
    }

    // If at least one message was sent and no errors
    // were generated then return a boolean value of TRUE.
    // Otherwise, return FALSE (no messages sent) or
    // and array of 1 or more PEAR_Error objects.
    $sent = FALSE;
    if ($success > 0 && count($errMsgs) == 0) {
      $sent = TRUE;
    }
    elseif (count($errMsgs) > 0) {
      $sent = $errMsgs;
    }

    return array($sent, $activity->id, $success);
  }

  /**
   * Send the sms message to a specific contact.
   *
   * @param int $toID
   *   The contact id of the recipient.
   * @param string $tokenText
   * @param array $smsParams
   *   The params used for sending sms.
   * @param int $activityID
   *   The activity ID that tracks the message.
   * @param int $userID
   *
   * @return bool|PEAR_Error
   *   true on success or PEAR_Error object
   */
  public static function sendSMSMessage(
    $toID,
    &$tokenText,
    $smsParams = array(),
    $activityID,
    $userID = NULL
  ) {
    $toPhoneNumber = "";

    if ($smsParams['To']) {
      $toPhoneNumber = trim($smsParams['To']);
    }
    elseif ($toID) {
      $toPhoneNumbers = CRM_Core_BAO_Phone::allPhones($toID, FALSE, ts('Mobile'));
      // To get primary mobile phonenumber,if not get the first mobile phonenumber
      if (!empty($toPhoneNumbers)) {
        if (count($toPhoneNumbers) >= 2) {
          // If there are multiple phone numbers, and one of them is primary.
          $filterToPhoneNumbers = array_filter($toPhoneNumbers, 
            function($phone){
              return $phone['is_primary'];
            }
          );
          if (!empty($filterToPhoneNumbers)) {
            $toPhoneNumbers = $filterToPhoneNumbers;
          }
          // If all of phone numbers are not Primary, retain all phone.
        }
        // Just select first one.
        $toPhoneNumerDetails = reset($toPhoneNumbers);
        $toPhoneNumber = CRM_Utils_Array::value('phone', $toPhoneNumerDetails);
      }
    }

    // make sure phone are valid
    if (empty($toPhoneNumber)) {
      return PEAR::raiseError(
        'Recipient phone number is invalid',
        NULL,
        PEAR_ERROR_RETURN
      );
    }

    $recipient = $toPhoneNumber;
    $smsParams['contact_id'] = $toID;
    $smsParams['parent_activity_id'] = $activityID;
    $sourceContactId = CRM_Core_DAO::getFieldValue('CRM_Activity_DAO_Activity', $activityID, 'source_contact_id');

    $providerObj = CRM_SMS_Provider::singleton(array('provider_id' => $smsParams['provider_id']));
    $sendResult = $providerObj->send($recipient, $smsParams, $tokenText, NULL, $userID);

    // add activity target record for every sms that is send
    $activityTargetParams = array(
      'activity_id' => $activityID,
      'target_contact_id' => $toID,
    );
    self::createActivityTarget($activityTargetParams);

    // If curl error: $sendResult will be a CRM_SMS_Provider object, which have _error property;
    // Otherwise, it well return curl receive string.
    return $sendResult;
  }


  /**
   * send the message to a specific contact
   *
   * @param string $from         the name and email of the sender
   * @param int    $toID         the contact id of the recipient
   * @param string $subject      the subject of the message
   * @param string $message      the message contents
   * @param string $emailAddress use this 'to' email address instead of the default Primary address
   * @param int    $activityID   the activity ID that tracks the message
   *
   * @return boolean             true if successfull else false.
   * @access public
   * @static
   */
  static function sendMessage($from,
    $fromID,
    $toID,
    &$subject,
    &$text_message,
    &$html_message,
    $emailAddress,
    $activityID,
    $attachments = NULL,
    $cc = NULL,
    $bcc = NULL
  ) {
    list($toDisplayName, $toEmail, $toDoNotEmail) = CRM_Contact_BAO_Contact::getContactDetails($toID);
    if ($emailAddress) {
      $toEmail = trim($emailAddress);
    }

    // make sure both email addresses are valid
    // and that the recipient wants to receive email
    if (empty($toEmail) or $toDoNotEmail) {
      return FALSE;
    }
    if (!trim($toDisplayName)) {
      $toDisplayName = $toEmail;
    }

    // create the params array
    $params = array();

    $params['groupName'] = 'Activity Email Sender';
    $params['from'] = $from;
    $params['toName'] = $toDisplayName;
    $params['toEmail'] = $toEmail;
    $params['subject'] = $subject;
    $params['cc'] = $cc;
    $params['bcc'] = $bcc;
    $params['text'] = $text_message;
    $params['html'] = $html_message;
    $params['attachments'] = $attachments;

    if (!CRM_Utils_Mail::send($params)) {
      return FALSE;
    }

    // add activity target record for every mail that is send
    $activityTargetParams = array(
      'activity_id' => $activityID,
      'target_contact_id' => $toID,
    );
    self::createActivityTarget($activityTargetParams);
    return TRUE;
  }

  /**
   * combine all the importable fields from the lower levels object
   *
   * The ordering is important, since currently we do not have a weight
   * scheme. Adding weight is super important and should be done in the
   * next week or so, before this can be called complete.
   *
   * @param NULL
   *
   * @return array    array of importable Fields
   * @access public
   */
  function &importableFields() {
    if (!self::$_importableFields) {
      if (!self::$_importableFields) {
        self::$_importableFields = array();
      }
      $fields = array('' => array('title' => ts('- Activity Fields -')));

      require_once 'CRM/Activity/DAO/Activity.php';
      $tmpFields = CRM_Activity_DAO_Activity::import();
      require_once 'CRM/Contact/BAO/Contact.php';
      $contactFields = CRM_Contact_BAO_Contact::importableFields('Individual', NULL);

      // Using new Dedupe rule.
      $ruleParams = array(
        'contact_type' => 'Individual',
        'level' => 'Strict',
      );
      require_once 'CRM/Dedupe/BAO/Rule.php';
      $fieldsArray = CRM_Dedupe_BAO_Rule::dedupeRuleFieldsMapping($ruleParams);

      $tmpConatctField = array();
      if (is_array($fieldsArray)) {
        foreach ($fieldsArray as $value) {
          $tmpConatctField[trim($value)] = $contactFields[trim($value)];
          $tmpConatctField[trim($value)]['title'] = $tmpConatctField[trim($value)]['title'] . " (match to contact)";
        }
      }
      $tmpConatctField['external_identifier'] = $contactFields['external_identifier'];
      $tmpConatctField['external_identifier']['title'] = $contactFields['external_identifier']['title'] . " (match to contact)";
      $fields = array_merge($fields, $tmpConatctField);
      $fields = array_merge($fields, $tmpFields);
      $fields = array_merge($fields, CRM_Core_BAO_CustomField::getFieldsForImport('Activity'));
      self::$_importableFields = $fields;
    }
    return self::$_importableFields;
  }

  /**
   * To get the Activities of a target contact
   *
   * @param $contactId    Integer  ContactId of the contact whose activities
   *                               need to find
   *
   * @return array    array of activity fields
   * @access public
   */
  function getContactActivity($contactId) {
    $activities = array();

    // First look for activities where contactId is one of the targets
    $query = "SELECT activity_id FROM civicrm_activity_target
                  WHERE  target_contact_id = $contactId";
    $dao = CRM_Core_DAO::executeQuery($query, CRM_Core_DAO::$_nullArray);
    while ($dao->fetch()) {
      $activities[$dao->activity_id]['targets'][$contactId] = $contactId;
    }

    // Then get activities where contactId is an asignee
    $query = "SELECT activity_id FROM civicrm_activity_assignment
                  WHERE  assignee_contact_id = $contactId";
    $dao = CRM_Core_DAO::executeQuery($query, CRM_Core_DAO::$_nullArray);
    while ($dao->fetch()) {
      $activities[$dao->activity_id]['asignees'][$contactId] = $contactId;
    }

    // Then get activities that contactId created
    $query = "SELECT id AS activity_id FROM civicrm_activity
                  WHERE  source_contact_id = $contactId";
    $dao = CRM_Core_DAO::executeQuery($query, CRM_Core_DAO::$_nullArray);
    while ($dao->fetch()) {
      $activities[$dao->activity_id]['source_contact_id'][] = $contactId;
    }
    $activityIds = array();
    // Then look up the activity details for each activity_id we saw above
    foreach ($activities as $activityId => $dummy) {
      $activityIds[] = $activityId;
    }
    if (count($activityIds) < 1) {
      return array();
    }
    $activityIds = implode(',', $activityIds);
    $query = "SELECT     activity.id as activity_id, source_contact_id, target_contact_id, assignee_contact_id, activity_type_id, 
                             subject, location, activity_date_time, details, status_id
                  FROM       civicrm_activity activity
                  LEFT JOIN  civicrm_activity_target target ON activity.id = target.activity_id
                  LEFT JOIN  civicrm_activity_assignment assignment ON activity.id = assignment.activity_id
                  WHERE      activity.id IN ($activityIds)";

    $dao = CRM_Core_DAO::executeQuery($query, CRM_Core_DAO::$_nullArray);

    require_once 'CRM/Core/OptionGroup.php';
    $activityTypes = CRM_Core_OptionGroup::values('activity_type');
    $activityStatuses = CRM_Core_OptionGroup::values('activity_status');

    while ($dao->fetch()) {
      $activities[$dao->activity_id]['source_contact_id'] = $dao->source_contact_id;
      if ($dao->target_contact_id) {
        $activities[$dao->activity_id]['targets'][$dao->target_contact_id] = $dao->target_contact_id;
      }
      if (isset($dao->assignee_contact_id)) {
        $activities[$dao->activity_id]['asignees'][$dao->assignee_contact_id] = $dao->assignee_contact_id;
      }
      $activities[$dao->activity_id]['activity_type_id'] = $dao->activity_type_id;
      $activities[$dao->activity_id]['subject'] = $dao->subject;
      $activities[$dao->activity_id]['location'] = $dao->location;
      $activities[$dao->activity_id]['activity_date_time'] = $dao->activity_date_time;
      $activities[$dao->activity_id]['details'] = $dao->details;
      $activities[$dao->activity_id]['status_id'] = $dao->status_id;
      $activities[$dao->activity_id]['activity_name'] = $activityTypes[$dao->activity_type_id];
      $activities[$dao->activity_id]['status'] = $activityStatuses[$dao->status_id];
    }
    return $activities;
  }

  /**
   * Function to add activity for Membership/Event/Contribution
   *
   * @param object  $activity   (reference) perticular component object
   * @param string  $activityType for Membership Signup or Renewal
   *
   *
   * @static
   * @access public
   */
  static function addActivity(&$activity, $activityType = 'Membership Signup', $targetContactID = NULL) {
    if ($activity->__table == 'civicrm_membership') {
      require_once "CRM/Member/PseudoConstant.php";
      $membershipType = CRM_Member_PseudoConstant::membershipType($activity->membership_type_id);

      if (!$membershipType) {
        $membershipType = ts('Membership');
      }

      $subject = "{$membershipType}";

      if ($activity->source != 'null') {
        $subject .= " - {$activity->source}";
      }

      if ($activity->owner_membership_id) {
        $query = "
SELECT  display_name 
  FROM  civicrm_contact, civicrm_membership  
 WHERE  civicrm_contact.id    = civicrm_membership.contact_id
   AND  civicrm_membership.id = $activity->owner_membership_id
";
        $displayName = CRM_Core_DAO::singleValueQuery($query);
        $subject .= " (by {$displayName})";
      }

      require_once 'CRM/Member/DAO/MembershipStatus.php';
      $subject .= " - Status: " . CRM_Core_DAO::getFieldValue('CRM_Member_DAO_MembershipStatus', $activity->status_id);
      $date = date('Y-m-d H:i:s');
      $component = 'Membership';
    }
    elseif ($activity->__table == 'civicrm_participant') {
      require_once "CRM/Event/BAO/Event.php";
      $event = CRM_Event_BAO_Event::getEvents(TRUE, $activity->event_id);

      require_once "CRM/Event/PseudoConstant.php";
      $roles = CRM_Event_PseudoConstant::participantRole();
      $status = CRM_Event_PseudoConstant::participantStatus();

      $subject = $event[$activity->event_id];
      if (CRM_Utils_Array::value($activity->role_id, $roles)) {
        $subject .= ' - ' . $roles[$activity->role_id];
      }
      if (CRM_Utils_Array::value($activity->status_id, $status)) {
        $subject .= ' - ' . $status[$activity->status_id];
      }
      $date = date('YmdHis');
      if ($activityType != 'Email') {
        $activityType = 'Event Registration';
      }
      $component = 'Event';
    }
    elseif ($activity->__table == 'civicrm_contribution') {
      //create activity record only for Completed Contributions
      if ($activity->contribution_status_id != 1) {
        return;
      }

      $subject = NULL;

      require_once "CRM/Utils/Money.php";
      $subject .= CRM_Utils_Money::format($activity->total_amount, $activity->currency);
      if ($activity->source != 'null') {
        $subject .= " - {$activity->source}";
      }
      $date = CRM_Utils_Date::isoToMysql($activity->receive_date);
      $activityType = $component = 'Contribution';
    }
    require_once "CRM/Core/OptionGroup.php";
    $activityParams = array('source_contact_id' => $activity->contact_id,
      'source_record_id' => $activity->id,
      'activity_type_id' => CRM_Core_OptionGroup::getValue('activity_type',
        $activityType,
        'name'
      ),
      'subject' => $subject,
      'activity_date_time' => $date,
      'is_test' => $activity->is_test,
      'status_id' => CRM_Core_OptionGroup::getValue('activity_status',
        'Completed',
        'name'
      ),
      'skipRecentView' => TRUE,
    );

    //CRM-4027
    if ($targetContactID) {
      $activityParams['target_contact_id'] = $targetContactID;
    }

    // create assignment activity if created by logged in user
    $session = &CRM_Core_Session::singleton();
    $id = $session->get('userID');
    if ($id) {
      $activityParams['source_contact_id'] = $id;
      $activityParams['assignee_contact_id'] = $activity->contact_id;
    }

    require_once 'api/v2/Activity.php';
    if (is_a(civicrm_activity_create($activityParams), 'CRM_Core_Error')) {
      CRM_Core_Error::fatal("Failed creating Activity for $component of id {$activity->id}");
      return FALSE;
    }
  }

  /**
   * Function to get Parent activity for currently viewd activity
   *
   * @param int  $activityId   current activity id
   *
   * @return int $parentId  Id of parent acyivity otherwise false.
   * @access public
   */
  static function getParentActivity($activityId) {
    static $parentActivities = array();

    $activityId = CRM_Utils_Type::escape($activityId, 'Integer');

    if (!array_key_exists($activityId, $parentActivities)) {
      $parentActivities[$activityId] = array();

      $parentId = CRM_Core_DAO::getFieldValue('CRM_Activity_DAO_Activity',
        $activityId,
        'parent_id'
      );

      $parentActivities[$activityId] = $parentId ? $parentId : FALSE;
    }

    return $parentActivities[$activityId];
  }

  /**
   * Function to get total count of prior revision of currently viewd activity
   *
   * @param int  $activityId   current activity id
   *
   * @return int $params  count of prior acyivities otherwise false.
   * @access public
   */
  static function getPriorCount($activityID) {
    static $priorCounts = array();

    $activityID = CRM_Utils_Type::escape($activityID, 'Integer');

    if (!array_key_exists($activityID, $priorCounts)) {
      $priorCounts[$activityID] = array();
      $originalID = CRM_Core_DAO::getFieldValue('CRM_Activity_DAO_Activity',
        $activityID,
        'original_id'
      );
      $count = 0;
      if ($originalID) {
        $query = "
SELECT count( id ) AS cnt
FROM civicrm_activity
WHERE ( id = {$originalID} OR original_id = {$originalID} )
AND is_current_revision = 0
AND id < {$activityID} 
";
        $params = array(1 => array($originalID, 'Integer'));
        $count = CRM_Core_DAO::singleValueQuery($query, $params);
      }
      $priorCounts[$activityID] = $count ? $count : 0;
    }

    return $priorCounts[$activityID];
  }

  /**
   * Function to get all prior activities of currently viewd activity
   *
   * @param int  $activityId   current activity id
   *
   * @return array $result  prior acyivities info.
   * @access public
   */
  static function getPriorAcitivities($activityID, $onlyPriorRevisions = FALSE) {
    static $priorActivities = array();

    $activityID = CRM_Utils_Type::escape($activityID, 'Integer');
    $index = $activityID . '_' . (int) $onlyPriorRevisions;

    if (!array_key_exists($index, $priorActivities)) {
      $priorActivities[$index] = array();

      $originalID = CRM_Core_DAO::getFieldValue('CRM_Activity_DAO_Activity',
        $activityID,
        'original_id'
      );
      if ($originalID) {
        $query = "
SELECT c.display_name as name, cl.modified_date as date, ca.id as activityID
FROM civicrm_log cl, civicrm_contact c, civicrm_activity ca
WHERE (ca.id = %1 OR ca.original_id = %1)
AND cl.entity_table = 'civicrm_activity'
AND cl.entity_id    = ca.id
AND cl.modified_id  = c.id
";
        if ($onlyPriorRevisions) {
          $query .= " AND ca.id < {$activityID}";
        }
        $query .= " ORDER BY ca.id DESC";

        $params = array(1 => array($originalID, 'Integer'));
        $dao = &CRM_Core_DAO::executeQuery($query, $params);

        while ($dao->fetch()) {
          $priorActivities[$index][$dao->activityID]['id'] = $dao->activityID;
          $priorActivities[$index][$dao->activityID]['name'] = $dao->name;
          $priorActivities[$index][$dao->activityID]['date'] = $dao->date;
          $priorActivities[$index][$dao->activityID]['link'] = 'javascript:viewActivity( $dao->activityID );';
        }
        $dao->free();
      }
    }
    return $priorActivities[$index];
  }

  /**
   * Function to find the latest revision of a given activity
   *
   * @param int  $activityId    prior activity id
   *
   * @return int $params  current activity id.
   * @access public
   */
  static function getLatestActivityId($activityID) {
    static $latestActivityIds = array();

    $activityID = CRM_Utils_Type::escape($activityID, 'Integer');

    if (!array_key_exists($activityID, $latestActivityIds)) {
      $latestActivityIds[$activityID] = array();

      $originalID = CRM_Core_DAO::getFieldValue('CRM_Activity_DAO_Activity',
        $activityID,
        'original_id'
      );
      if ($originalID) {
        $activityID = $originalID;
      }
      $params = array(1 => array($activityID, 'Integer'));
      $query = "SELECT id from civicrm_activity where original_id = %1 and is_current_revision = 1";

      $latestActivityIds[$activityID] = CRM_Core_DAO::singleValueQuery($query, $params);
    }

    return $latestActivityIds[$activityID];
  }

  /**
   * Function to create a follow up a given activity
   *
   * @activityId int activity id of parent activity
   *
   * @param array  $activity details
   *
   * @access public
   */
  static function createFollowupActivity($activityId, $params) {
    if (!$activityId) {
      return;
    }

    $session = &CRM_Core_Session::singleton();

    $followupParams = array();
    $followupParams['parent_id'] = $activityId;
    $followupParams['source_contact_id'] = $session->get('userID');
    $followupParams['status_id'] = CRM_Core_OptionGroup::getValue('activity_status', 'Scheduled', 'name');

    $followupParams['activity_type_id'] = $params['followup_activity_type_id'];
    // Get Subject of Follow-up Activiity, CRM-4491
    $followupParams['subject'] = CRM_Utils_Array::value('followup_activity_subject', $params);

    //create target contact for followup
    if (CRM_Utils_Array::value('target_contact_id', $params)) {
      $followupParams['target_contact_id'] = $params['target_contact_id'];
    }

    $followupDate = CRM_Utils_Date::intervalAdd($params['interval_unit'], $params['interval'], $params['activity_date_time']);
    $followupParams['activity_date_time'] = CRM_Utils_Date::format($followupDate);
    $followupActivity = self::create($followupParams);

    return $followupActivity;
  }

  /**
   * Function to get Activity specific File according activity type Id.
   *
   * @param int  $activityTypeId  activity id
   *
   * @return if file exists returns $activityTypeFile activity filename otherwise false.
   *
   * @static
   */
  static function getFileForActivityTypeId($activityTypeId, $crmDir = 'Activity') {
    require_once "CRM/Case/PseudoConstant.php";
    $activityTypes = CRM_Case_PseudoConstant::activityType(FALSE, TRUE);

    if ($activityTypes[$activityTypeId]['name']) {
      require_once 'CRM/Utils/String.php';
      $activityTypeFile = CRM_Utils_String::munge(ucwords($activityTypes[$activityTypeId]['name']), '', 0);
    }
    else {
      return FALSE;
    }

    global $civicrm_root;
    $config = CRM_Core_Config::singleton();
    if (!file_exists(rtrim($civicrm_root, '/') . "/CRM/{$crmDir}/Form/Activity/{$activityTypeFile}.php")) {
      if (empty($config->customPHPPathDir)) {
        return FALSE;
      }
      elseif (!file_exists(rtrim($config->customPHPPathDir, '/') . "/CRM/{$crmDir}/Form/Activity/{$activityTypeFile}.php")) {
        return FALSE;
      }
    }

    return $activityTypeFile;
  }

  /**
   * Function to restore the activity
   *
   * @param array  $params  associated array
   *
   * @return void
   * @access public
   *
   */
  public function restoreActivity(&$params) {
    $activity = new CRM_Activity_DAO_Activity();
    $activity->copyValues($params);

    $activity->is_deleted = 0;
    $result = $activity->save();

    return $result;
  }

  /**
   * Get the exportable fields for Activities
   *
   * @param string $name if it is called by case $name = Case else $name = Activity
   *
   * @return array array of exportable Fields
   * @access public
   */
  function &exportableFields($name = 'Activity') {
    if (!isset(self::$_exportableFields[$name])) {
      self::$_exportableFields[$name] = array();

      // TO DO, ideally we should retrieve all fields from xml, in this case since activity processing is done
      // my case hence we have defined fields as case_*
      if ($name == 'Activity') {
        require_once 'CRM/Activity/DAO/Activity.php';
        $exportableFields = CRM_Activity_DAO_Activity::export();
        $Activityfields = array(
          'activity_type' => array('title' => ts('Activity Type'), 'type' => CRM_Utils_Type::T_STRING),
          'activity_status' => array('title' => ts('Activity Status'), 'type' => CRM_Utils_Type::T_STRING),
          'target_contact_id' => array('title' => ts('Target Contact ID'), 'type' => CRM_Utils_Type::T_STRING),
          'target_contact_name' => array('title' => ts('Target Contact Name'), 'type' => CRM_Utils_Type::T_STRING),
          'assign_contact_id' => array('title' => ts('Assigned to Contact ID'), 'type' => CRM_Utils_Type::T_STRING),
          'assign_contact_name' => array('title' => ts('Assigned to Contact'), 'type' => CRM_Utils_Type::T_STRING),
        );
        $fields = array_merge($Activityfields, $exportableFields);
      }
      else {
        //set title to activity fields
        $fields = array(
          'case_subject' => array('title' => ts('Activity Subject'), 'type' => CRM_Utils_Type::T_STRING),
          'case_source_contact_id' => array('title' => ts('Activity Reporter'), 'type' => CRM_Utils_Type::T_STRING),
          'case_recent_activity_date' => array('title' => ts('Activity Actual Date'), 'type' => CRM_Utils_Type::T_DATE),
          'case_scheduled_activity_date' => array('title' => ts('Activity Scheduled Date'), 'type' => CRM_Utils_Type::T_DATE),
          'case_recent_activity_type' => array('title' => ts('Activity Type'), 'type' => CRM_Utils_Type::T_STRING),
          'case_activity_status' => array('title' => ts('Activity Status'), 'type' => CRM_Utils_Type::T_STRING),
          'case_activity_duration' => array('title' => ts('Activity Duration'), 'type' => CRM_Utils_Type::T_INT),
          'case_activity_medium_id' => array('title' => ts('Activity Medium'), 'type' => CRM_Utils_Type::T_INT),
          'case_activity_details' => array('title' => ts('Activity Details'), 'type' => CRM_Utils_Type::T_TEXT),
          'case_activity_is_auto' => array('title' => ts('Activity Auto-generated?'), 'type' => CRM_Utils_Type::T_BOOLEAN),
        );
      }

      // add custom data for case activities
      $fields = array_merge($fields, CRM_Core_BAO_CustomField::getFieldsForImport('Activity'));

      self::$_exportableFields[$name] = $fields;
    }
    return self::$_exportableFields[$name];
  }

  /**
   * This function delete activity record related to contact record,
   * when there are no target and assignee record w/ other contact.
   *
   * @param  int $contactId contactId
   *
   * @return true/null
   * @access public
   */
  public function cleanupActivity($contactId) {
    $result = NULL;
    if (!$contactId) {
      return $result;
    }

    require_once 'CRM/Core/Transaction.php';
    $transaction = new CRM_Core_Transaction();

    // delete activity if there are no record in
    // civicrm_activity_assignment or civicrm_activity_target
    // pointing to any other contact record.

    require_once 'CRM/Activity/DAO/ActivityTarget.php';
    require_once 'CRM/Activity/DAO/ActivityAssignment.php';

    $activity = new CRM_Activity_DAO_Activity();
    $activity->source_contact_id = $contactId;
    $activity->find();

    while ($activity->fetch()) {
      $noTarget = $noAssignee = TRUE;

      // check for target activity record.
      $target = new CRM_Activity_DAO_ActivityTarget();
      $target->activity_id = $activity->id;
      $target->find();
      while ($target->fetch()) {
        if ($target->target_contact_id != $contactId) {
          $noTarget = FALSE;
          break;
        }
      }
      $target->free();

      // check for assignee activity record.
      $assignee = new CRM_Activity_DAO_ActivityAssignment();
      $assignee->activity_id = $activity->id;
      $assignee->find();
      while ($assignee->fetch()) {
        if ($assignee->assignee_contact_id != $contactId) {
          $noAssignee = FALSE;
          break;
        }
      }
      $assignee->free();

      // finally delete activity.
      if ($noTarget && $noAssignee) {
        $activityParams = array('id' => $activity->id);
        $result = self::deleteActivity($activityParams);
      }
    }
    $activity->free();

    $transaction->commit();

    return $result;
  }

  /**
   * Does user has sufficient permission for view/edit activity record.
   *
   * @param  int   $activityId activity record id.
   * @param  int   $action     edit/view
   *
   * @return boolean $allow true/false
   * @access public
   */
  public function checkPermission($activityId, $action) {
    $allow = FALSE;
    if (!$activityId ||
      !in_array($action, array(CRM_Core_Action::UPDATE, CRM_Core_Action::VIEW))
    ) {
      return $allow;
    }

    $activity = new CRM_Activity_DAO_Activity();
    $activity->id = $activityId;
    if (!$activity->find(TRUE)) {
      return $allow;
    }

    //component related permissions.
    $compPermissions = array('CiviCase' => array('administer CiviCase',
        'access my cases and activities',
        'access all cases and activities',
      ),
      'CiviMail' => array('access CiviMail'),
      'CiviEvent' => array('access CiviEvent'),
      'CiviGrant' => array('access CiviGrant'),
      'CiviPledge' => array('access CiviPledge'),
      'CiviMember' => array('access CiviMember'),
      'CiviReport' => array('access CiviReport'),
      'CiviContribute' => array('access CiviContribute'),
      'CiviCampaign' => array('administer CiviCampaign'),
    );

    //return early when it is case activity.
    require_once 'CRM/Case/BAO/Case.php';
    $isCaseActivity = CRM_Case_BAO_Case::isCaseActivity($activityId);
    //check for civicase related permission.
    if ($isCaseActivity) {
      $allow = FALSE;
      foreach ($compPermissions['CiviCase'] as $per) {
        if (CRM_Core_Permission::check($per)) {
          $allow = TRUE;
          break;
        }
      }

      //check for case specific permissions.
      if ($allow) {
        $oper = 'view';
        if ($action == CRM_Core_Action::UPDATE) {
          $oper = 'edit';
        }
        $allow = CRM_Case_BAO_Case::checkPermission($activityId,
          $oper,
          $activity->activity_type_id
        );
      }

      return $allow;
    }

    require_once 'CRM/Core/Permission.php';
    require_once 'CRM/Contact/BAO/Contact/Permission.php';

    //first check the component permission.
    $sql = "
    SELECT  component_id
      FROM  civicrm_option_value val
INNER JOIN  civicrm_option_group grp ON ( grp.id = val.option_group_id AND grp.name = %1 )
     WHERE  val.value = %2";
    $params = array(1 => array('activity_type', 'String'),
      2 => array($activity->activity_type_id, 'Integer'),
    );
    $componentId = CRM_Core_DAO::singleValueQuery($sql, $params);

    if ($componentId) {
      require_once 'CRM/Core/Component.php';
      $componentName = CRM_Core_Component::getComponentName($componentId);
      $compPermission = CRM_Utils_Array::value($componentName, $compPermissions);

      //here we are interesting in any single permission.
      if (is_array($compPermission)) {
        foreach ($compPermission as $per) {
          if (CRM_Core_Permission::check($per)) {
            $allow = TRUE;
            break;
          }
        }
      }
    }

    //check for this permission related to contact.
    $permission = CRM_Core_Permission::VIEW;
    if ($action == CRM_Core_Action::UPDATE) {
      $permission = CRM_Core_Permission::EDIT;
    }

    //check for source contact.
    if (!$componentId || $allow) {
      $allow = CRM_Contact_BAO_Contact_Permission::allow($activity->source_contact_id, $permission);
    }

    //check for target and assignee contacts.
    if ($allow) {
      //first check for supper permission.
      $supPermission = 'view all contacts';
      if ($action == CRM_Core_Action::UPDATE) {
        $supPermission = 'edit all contacts';
      }
      $allow = CRM_Core_Permission::check($supPermission);

      //user might have sufficient permission, through acls.
      if (!$allow) {
        $allow = TRUE;
        //get the target contacts.
        $targetContacts = CRM_Activity_BAO_ActivityTarget::retrieveTargetIdsByActivityId($activity->id);
        foreach ($targetContacts as $cnt => $contactId) {
          if (!CRM_Contact_BAO_Contact_Permission::allow($contactId, $permission)) {
            $allow = FALSE;
            break;
          }
        }

        //get the assignee contacts.
        if ($allow) {
          $assigneeContacts = CRM_Activity_BAO_ActivityAssignment::retrieveAssigneeIdsByActivityId($activity->id);
          foreach ($assigneeContacts as $cnt => $contactId) {
            if (!CRM_Contact_BAO_Contact_Permission::allow($contactId, $permission)) {
              $allow = FALSE;
              break;
            }
          }
        }
      }
    }

    return $allow;
  }
}

