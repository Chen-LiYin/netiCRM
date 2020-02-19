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

require_once 'CRM/Core/Page.php';
class CRM_Pledge_Page_Payment extends CRM_Core_Page {

  /**
   * This function is the main function that is called when the page loads, it decides the which action has to be taken for the page.
   *
   * return null
   * @access public
   */
  function run() {
    $this->_action = CRM_Utils_Request::retrieve('action', 'String', $this, FALSE, 'browse');
    $this->_context = CRM_Utils_Request::retrieve('context', 'String', $this);

    $this->assign('action', $this->_action);
    $this->assign('context', $this->_context);

    $this->_contactId = CRM_Utils_Request::retrieve('cid', 'Positive', $this);

    require_once 'CRM/Pledge/Page/Tab.php';
    $this->setContext();

    if ($this->_action & CRM_Core_Action::UPDATE) {
      $this->edit();
      // set page title
      require_once 'CRM/Contact/Page/View.php';
      CRM_Contact_Page_View::setTitle($this->_contactId);
    }
    else {
      $pledgeId = CRM_Utils_Request::retrieve('pledgeId', 'Positive', $this);

      require_once 'CRM/Pledge/BAO/Payment.php';
      $paymentDetails = CRM_Pledge_BAO_Payment::getPledgePayments($pledgeId);

      $this->assign('rows', $paymentDetails);
      $this->assign('pledgeId', $pledgeId);
      $this->assign('contactId', $this->_contactId);

      // check if we can process credit card contribs
      $processors = CRM_Core_PseudoConstant::paymentProcessor(FALSE, FALSE,
        "billing_mode IN ( 1, 3 )"
      );
      if (count($processors) > 0) {
        $this->assign('newCredit', TRUE);
      }
      else {
        $this->assign('newCredit', FALSE);
      }

      // check is the user has view/edit signer permission
      $permission = 'view';
      if (CRM_Core_Permission::check('edit pledges')) {
        $permission = 'edit';
      }
      $this->assign('permission', $permission);
    }

    return parent::run();
  }

  /**
   * This function is called when action is update or new
   *
   * return null
   * @access public
   */
  function edit() {
    $controller = new CRM_Core_Controller_Simple('CRM_Pledge_Form_Payment',
      'Update Pledge Payment',
      $this->_action
    );

    $pledgePaymentId = CRM_Utils_Request::retrieve('ppId', 'Positive', $this);

    $controller->setEmbedded(TRUE);
    $controller->set('id', $pledgePaymentId);

    return $controller->run();
  }

  function setContext() {
    $context = CRM_Utils_Request::retrieve('context', 'String', $this, FALSE, 'search');

    $qfKey = CRM_Utils_Request::retrieve('key', 'String', $this);
    //validate the qfKey
    require_once 'CRM/Utils/Rule.php';
    if (!CRM_Utils_Rule::qfKey($qfKey)) {
      $qfKey = NULL;
    }

    switch ($context) {
      case 'dashboard':
      case 'pledgeDashboard':
        $url = CRM_Utils_System::url('civicrm/pledge', 'reset=1');
        break;

      case 'search':
        $urlParams = 'force=1';
        if ($qfKey) {
          $urlParams .= "&qfKey=$qfKey";
        }

        $url = CRM_Utils_System::url('civicrm/pledge/search', $urlParams);
        break;

      case 'user':
        $url = CRM_Utils_System::url('civicrm/user', 'reset=1');
        break;

      case 'pledge':
        $url = CRM_Utils_System::url('civicrm/contact/view',
          "reset=1&force=1&cid={$this->_contactId}&selectedChild=pledge"
        );
        break;

      case 'home':
        $url = CRM_Utils_System::url('civicrm/dashboard', 'force=1');
        break;

      case 'activity':
        $url = CRM_Utils_System::url('civicrm/contact/view',
          "reset=1&force=1&cid={$this->_contactId}&selectedChild=activity"
        );
        break;

      case 'standalone':
        $url = CRM_Utils_System::url('civicrm/dashboard', 'reset=1');
        break;

      default:
        $cid = NULL;
        if ($this->_contactId) {
          $cid = '&cid=' . $this->_contactId;
        }
        $url = CRM_Utils_System::url('civicrm/pledge/search',
          'force=1' . $cid
        );
        break;
    }
    $session = CRM_Core_Session::singleton();
    $session->pushUserContext($url);
  }
}

