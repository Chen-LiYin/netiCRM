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
 * add static functions to include some common functionality
 * used across location sub object BAO classes
 *
 */
class CRM_Core_BAO_Block {

  /**
   * Fields that are required for a valid block
   */
  static $requiredBlockFields = array(
    'email' => array('email'),
    'phone' => array('phone'),
    'im' => array('name'),
    'openid' => array('openid'),
  );

  /**
   * Given the list of params in the params array, fetch the object
   * and store the values in the values array
   *
   * @param Object $block         typically a Phone|Email|IM|OpenID object
   * @param string $blockName     name of the above object
   * @param array  $params        input parameters to find object
   * @param array  $values        output values of the object
   *
   * @return array of $block objects.
   * @access public
   * @static
   */
  static function &getValues($blockName, $params) {
    if (empty($params)) {
      return NULL;
    }
    $BAOString = 'CRM_Core_BAO_' . $blockName;
    $block = new $BAOString( );

    $blocks = array();
    if (!isset($params['entity_table'])) {
      $block->contact_id = $params['contact_id'];
      if (!$block->contact_id) {
        CRM_Core_Error::fatal();
      }
      $blocks = self::retrieveBlock($block, $blockName);
    }
    else {
      $blockIds = self::getBlockIds($blockName, NULL, $params);

      if (empty($blockIds)) {
        return $blocks;
      }

      $count = 1;
      foreach ($blockIds as $blockId) {
        $block = new $BAOString( );
        $block->id = $blockId['id'];
        $getBlocks = self::retrieveBlock($block, $blockName);
        $blocks[$count++] = array_pop($getBlocks);
      }
    }

    return $blocks;
  }

  /**
   * Given the list of params in the params array, fetch the object
   * and store the values in the values array
   *
   * @param Object $block         typically a Phone|Email|IM|OpenID object
   * @param string $blockName     name of the above object
   * @param array  $values        output values of the object
   *
   * @return array of $block objects.
   * @access public
   * @static
   */
  static function retrieveBlock(&$block, $blockName) {
    // we first get the primary location due to the order by clause
    $block->orderBy('is_primary desc, id');
    $block->find();

    $count = 1;
    $blocks = array();
    while ($block->fetch()) {
      CRM_Core_DAO::storeValues($block, $blocks[$count]);
      //unset is_primary after first block. Due to some bug in earlier version
      //there might be more than one primary blocks, hence unset is_primary other than first
      if ($count > 1) {
        unset($blocks[$count]['is_primary']);
      }
      $count++;
    }

    return $blocks;
  }

  /**
   * check if the current block object has any valid data
   *
   * @param array  $blockFields   array of fields that are of interest for this object
   * @param array  $params        associated array of submitted fields
   *
   * @return boolean              true if the block has data, otherwise false
   * @access public
   * @static
   */
  static function dataExists($blockFields, &$params) {
    foreach ($blockFields as $field) {
      if (CRM_Utils_System::isNull($params[$field])) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * check if the current block exits
   *
   * @param string  $blockName   bloack name
   * @param array   $params      associated array of submitted fields
   *
   * @return boolean             true if the block exits, otherwise false
   * @access public
   * @static
   */
  static function blockExists($blockName, &$params) {
    // return if no data present
    if (!CRM_Utils_Array::value($blockName, $params) || !is_array($params[$blockName])) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * check if block value exists
   *
   * @param string  $blockName   bloack name that in self::requiredBlockFields key
   * @param array   $blockValue associated array of data without id
   *   ```
   *     array('phone_type_id' => 2, 'contact_id' => 1234, 'phone' => '123123123')
   *   ```
   *
   * @return boolean             true if the block exits, otherwise false
   * @access public
   * @static
   */
  static function blockValueExists($blockName, &$blockValue) {
    $require = self::$requiredBlockFields[$blockName];
    if (empty($require)) {
      return FALSE;
    }
    if (empty($blockValue[$require[0]])) {
      return FALSE;
    }
    // we won't check exists when id provided
    if (!empty($blockValue['id'])) {
      return FALSE;
    }
    // we won't check exists when contact_id not provided
    if (empty($blockValue['contact_id'])) {
      return FALSE;
    }
    $name = ucfirst($blockName);
    $baoString = 'CRM_Core_BAO_' . $name;
    $baoString::valueExists($blockValue);
    if (!empty($blockValue['id'])) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Function to get all block ids for a contact
   *
   * @param string $blockName block name
   * @param int    $contactId contact id
   *
   * @return array $contactBlockIds formatted array of block ids
   *
   * @access public
   * @static
   */
  static function getBlockIds($blockName, $contactId = NULL, $entityElements = NULL, $updateBlankLocInfo = FALSE) {
    $allBlocks = array();
    $name = ucfirst($blockName);
    $baoString = 'CRM_Core_BAO_' . $name;
    if ($contactId) {
      //@todo a cleverer way to do this would be to use the same fn name on each
      // BAO rather than constructing the fn
      // it would also be easier to grep for
      // e.g $bao = new $baoString;
      // $bao->getAllBlocks()
      $baoFunction = 'all' . $name . 's';
      $allBlocks = $baoString::$baoFunction( $contactId, $updateBlankLocInfo );
    }
    elseif (!empty($entityElements) && $blockName != 'openid') {
      $baoFunction = 'allEntity' . $name . 's';
      $allBlocks = $baoString::$baoFunction( $entityElements );
    }

    return $allBlocks;
  }

  /**
   * takes an associative array and creates a block
   *
   * @param string $blockName      block name
   * @param array  $params         (reference ) an assoc array of name/value pairs
   * @param array  $requiredFields fields that's are required in a block
   *
   * @return object       CRM_Core_BAO_Block object on success, null otherwise
   * @access public
   * @static
   */
  static function create($blockName, &$params, $entity = NULL) {
    if (!self::blockExists($blockName, $params)) {
      return NULL;
    }

    $name = ucfirst($blockName);
    $contactId = NULL;
    $isPrimary = $isBilling = TRUE;
    $entityElements = $blocks = array();

    if ($entity) {
      $entityElements = array('entity_table' => $params['entity_table'],
        'entity_id' => $params['entity_id'],
      );
    }
    else {
      $contactId = $params['contact_id'];
    }

    $updateBlankLocInfo = CRM_Utils_Array::value('updateBlankLocInfo', $params, FALSE);

    //get existsing block ids.
    $blockIds = self::getBlockIds($blockName, $contactId, $entityElements, $updateBlankLocInfo);

    //lets allow user to update block w/ the help of id, CRM-6170
    $resetPrimaryId = NULL;
    foreach ($params[$blockName] as $count => $value) {
      $blockId = CRM_Utils_Array::value('id', $value);
      if ($blockId) {
        if (is_array($blockIds) && array_key_exists($blockId, $blockIds)) {
          unset($blockIds[$blockId]);
        }
        else {
          unset($value['id']);
        }
      }
      //lets allow to update primary w/ more cleanly.
      if (!$resetPrimaryId && CRM_Utils_Array::value('is_primary', $value)) {
        if (is_array($blockIds)) {
          foreach ($blockIds as $blockId => $blockValue) {
            if (CRM_Utils_Array::value('is_primary', $blockValue)) {
              $resetPrimaryId = $blockId;
              break;
            }
          }
        }
        if ($resetPrimaryId) {
          $baoString = 'CRM_Core_BAO_' . $blockName;
          $block = new $baoString( );
          $block->selectAdd();
          $block->selectAdd("id, is_primary");
          $block->id = $resetPrimaryId;
          if ($block->find(TRUE)) {
            $block->is_primary = FALSE;
            $block->save();
          }
          $block->free();
        }
      }
    }

    foreach ($params[$blockName] as $count => $value) {
      if (!is_array($value)) {
        continue;
      }
      $contactFields = array(
        'contact_id' => $contactId,
        'location_type_id' => $value['location_type_id'],
      );

      //check for update
      if (!CRM_Utils_Array::value('id', $value) && is_array($blockIds) && !empty($blockIds)) {
        foreach ($blockIds as $blockId => $blockValue) {
          if ($updateBlankLocInfo) {
            if (CRM_Utils_Array::value($count, $blockIds)) {
              $value['id'] = $blockIds[$count]['id'];
              unset($blockIds[$count]);
            }
          }
          else if($blockName == 'phone') {
            if ($blockValue['locationTypeId'] == $value['location_type_id'] && $blockValue['phone_type_id'] == $value['phone_type_id'] && empty($value['append'])) {
              $value['id'] = $blockValue['id'];
              unset($blockIds[$blockId]);
              break;
            }
            elseif(!empty($value['append'])) {
              $value['contact_id'] = $contactId;
              self::blockValueExists($blockName, $value);
            }
          }
          else if($blockName == 'im') {
            if ($blockValue['locationTypeId'] == $value['location_type_id'] && $blockValue['provider_id'] == $value['provider_id'] && empty($value['append'])) {
              $value['id'] = $blockValue['id'];
              unset($blockIds[$blockId]);
              break;
            }
            elseif(!empty($value['append'])) {
              $value['contact_id'] = $contactId;
              self::blockValueExists($blockName, $value);
            }
          }
          else {
            if ($blockValue['locationTypeId'] == $value['location_type_id'] && empty($value['append'])) {
              //assigned id as first come first serve basis
              $value['id'] = $blockValue['id'];
              unset($blockIds[$blockId]);
              break;
            }
            elseif(!empty($value['append'])) {
              $value['contact_id'] = $contactId;
              self::blockValueExists($blockName, $value);
            }
          }
        }
      }

      $dataExists = self::dataExists(self::$requiredBlockFields[$blockName], $value);

      // Note there could be cases when block info already exist ($value[id] is set) for a contact/entity
      // BUT info is not present at this time, and therefore we should be really careful when deleting the block.
      // $updateBlankLocInfo will help take appropriate decision. CRM-5969
      if (CRM_Utils_Array::value('id', $value) && !$dataExists && $updateBlankLocInfo) {
        //delete the existing record
        self::blockDelete($name, array('id' => $value['id']));
        continue;
      }
      elseif (!$dataExists) {
        continue;
      }

      if ($isPrimary && CRM_Utils_Array::value('is_primary', $value)) {
        $contactFields['is_primary'] = $value['is_primary'];
        $isPrimary = FALSE;
      }
      else {
        $contactFields['is_primary'] = 0;
      }

      if ($isBilling && CRM_Utils_Array::value('is_billing', $value)) {
        $contactFields['is_billing'] = $value['is_billing'];
        $isBilling = FALSE;
      }
      else {
        $contactFields['is_billing'] = 0;
      }

      $blockFields = array_merge($value, $contactFields);
      $baoString = 'CRM_Core_BAO_' . $name;
      $blocks[] = $baoString::add( $blockFields );
    }

    // we need to delete blocks that were deleted during update
    if ($updateBlankLocInfo && !empty($blockIds)) {
      foreach ($blockIds as $deleteBlock) {
        if (!CRM_Utils_Array::value('id', $deleteBlock)) {
          continue;
        }
        self::blockDelete($name, array('id' => $deleteBlock['id']));
      }
    }

    return $blocks;
  }

  /**
   * Function to delete block
   *
   * @param  string $blockName       block name
   * @param  int    $params          associates array
   *
   * @return void
   * @static
   */
  static function blockDelete($blockName, $params) {
    $baoString = 'CRM_Core_DAO_' . $blockName;
    $block = new $baoString( );

    $block->copyValues($params);

    $block->delete();
  }
}

