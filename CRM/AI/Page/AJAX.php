<?php

/**
 * This class contains all the AI function that are called by AJAX
 */
class CRM_AI_Page_AJAX {

  function chat() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['CONTENT_TYPE'] == 'application/json') {
      $jsonString = file_get_contents('php://input');
      $jsondata = json_decode($jsonString, true);
      if ($jsondata === FALSE) {
        self::responseError(array(
          'status' => 0,
          'message' => 'The request is not a valid JSON format.',
        ));
      }
      if (is_string($jsondata['tone']) && isset($jsondata['tone'])) {
        $tone_style = $jsondata['tone'];
        $data['tone_style'] = $tone_style;
      }
      if (is_string($jsondata['role']) && isset($jsondata['role'])) {
        $ai_role = $jsondata['role'];
        $data['ai_role'] = $ai_role;
      }
      if (is_string($jsondata['content']) && isset($jsondata['content'])) {
        $context = $jsondata['content'];
        $data['context'] = $context;
      }
      if (is_string($jsondata['sourceUrlPath']) && isset($jsondata['sourceUrlPath'])) {
        $url = $jsondata['sourceUrlPath'];
        switch ($url) {
          case 'civicrm/admin/contribute/setting':
          case '/zh-hant/civicrm/admin/contribute/settings':
            $data['component'] = "CiviContribute";
            break;
          case 'civicrm/member':
          case '/zh-hant/civicrm/member':
            $data['component'] = "CiviMember";
            break;
          case '/civicrm/event/manage/eventInfo':
          case '/zh-hant/civicrm/event/manage/eventInfo':
            $data['component'] = "CiviEvent";
            break;
          case '/zh-hant/civicrm/mailing/send':
          case '/civicrm/mailing/send':
            $data['component'] = "CiviMail";
            break;
          default:
            break;
        }
      }

      if ($tone_style && $ai_role && $context && $data['component']) {
        $system_prompt = ts("You are an %1 in Taiwan who uses Traditional Chinese and is skilled at writing %2 copywriting.",
          array(1 => $ai_role, 2 => $tone_style,)
        );
        $data['prompt'] = array(
          array(
            'role' => 'system',
            'content' => $system_prompt,
          ),
          array(
            'role' => 'user',
            'content' => $context,
          ),
        );
        try {
          $token = CRM_AI_BAO_AICompletion::prepareChat($data);
        }
        catch(CRM_Core_Exception $e) {
          $message = $e->getMessage();
          self::responseError(array(
            'status' => 0,
            'message' => $message,
          ));
        }

        self::responseOk(array(
          'status' => 1,
          'message' => 'Success create chat',
          'data' => array(
            'id' => $token['id'],
            'token' => $token['token'],
          )
        ));
      }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
      if (is_string($_GET['token']) && isset($_GET['token']) && is_string($_GET['id']) && isset($_GET['id'])) {
        $token = $_GET['token'];
        $id = $_GET['id'];
        $params = array(
          'token' => $token,
          'id' => $id,
          'stream' => TRUE,
          'temperature' => CRM_AI_BAO_AICompletion::TEMPERATURE_DEFAULT,
        );
        try{
          $result = CRM_AI_BAO_AICompletion::chat($params);
        }
        catch(CRM_Core_Exception $e) {
          $message = $e->getMessage();
          self::responseError(array(
            'status' => 0,
            'message' => $message,
          ));
        }
        self::responseOk(array(
          'status' => 1,
          'message' => 'Stream chat successfully',
          'data' => $result,
        ));
      }
    }
  }

  function getTemplateList() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['CONTENT_TYPE'] == 'application/json') {
      $jsonString = file_get_contents('php://input');
      $jsondata = json_decode($jsonString, true);
      if (is_string($jsondata['component']) && isset($jsondata['component'])) {
        $component = $jsondata['component'];
        $data['component'] = $component;
      }
      if (is_string($jsondata['field']) && isset($jsondata['field'])) {
        $field = $jsondata['field'];
        $data['field'] = $field;
      }
      if (is_string($jsondata['offset']) && isset($jsondata['offset'])) {
        $offset = $jsondata['offset'];
        $data['offset'] = $offset;
      }

      if (isset($data)) {
        $getListResult = CRM_AI_BAO_AICompletion::getTemplateList($data);
      }
      else {
        //Get all template list
        $getListResult = CRM_AI_BAO_AICompletion::getTemplateList();
      }

      if (is_array($getListResult) && !empty($getListResult)) {
        self::responseOk(array(
          'status' => 1,
          'message' => "Template list retrieved successfully",
          'data' => $getListResult,
        ));
      }
      else {
        self::responseError(array(
          'status' => 0,
          'message' => "Failed to retrieve template list",
        ));
      }
    }
  }

  function getTemplate() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['CONTENT_TYPE'] == 'application/json') {
      $jsonString = file_get_contents('php://input');
      $jsondata = json_decode($jsonString, true);
      if (is_string($jsondata['id']) && isset($jsondata['id'])) {
        $acId = $jsondata['id'];
      }
      if ($acId) {
        $getTemplateResult = CRM_AI_BAO_AICompletion::getTemplate($acId);
        if (is_array($getTemplateResult) && !empty($getTemplateResult)) {
          self::responseOk(array(
            'status' => 1,
            'message' => "Template retrieved successfully",
            'data' => $getTemplateResult,
          ));
        }
        else {
          self::responseError(array(
            'status' => 0,
            'message' => "Failed to retrieve template",
          ));
        }
      }
    }
  }

  function setTemplate() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['CONTENT_TYPE'] == 'application/json') {
      $jsonString = file_get_contents('php://input');
      $jsondata = json_decode($jsonString, true);
      if (is_string($jsondata['id']) && isset($jsondata['id'])) {
        $acId = $jsondata['id'];
        $data['id'] = $acId;
      }
      if (is_string($jsondata['is_template']) && isset($jsondata['is_template'])) {
        $acIsTemplate = $jsondata['is_template'];
        $data['is_template'] = $acIsTemplate;
      }
      if (is_string($jsondata['template_title']) && isset($jsondata['template_title'])) {
        $acTemplateTitle = $jsondata['template_title'];
        $data['template_title'] = $acTemplateTitle;
      }
      if (isset($acId) && isset($acIsTemplate) && isset($acTemplateTitle)) {
        $setTemplateResult = CRM_AI_BAO_AICompletion::setTemplate($data);
        $result = array();
        if ($setTemplateResult['is_error'] == '0') {
          //set or unset template successful return true
          if ($acIsTemplate == "1") {
            //0 -> 1
            $result = array(
              'status' => "success",
              'message' => "AI completion is set as template successfully",
              'data' => array(
                'id' => $setTemplateResult['id'],
                'is_template' => $setTemplateResult['is_template'],
                'template_title' => $setTemplateResult['template_title'],
              ),
            );
          }
          else {
            //  1 -> 0
            $result = array(
              'status' => "success",
              'message' => "AI completion is unset as template successfully",
              'data' => array(
                'id' => $setTemplateResult['id'],
                'is_template' => $setTemplateResult['is_template'],
                'template_title' => $setTemplateResult['template_title'],
              ),
            );
          }
        }
        else {
          //If it cannot be set/unset throw Error
          $result = array(
            'status' => "Failed",
            'message' => $setTemplateResult['message'],
            'data' => array(
              'id' => $setTemplateResult['id'],
              'is_template' => $setTemplateResult['is_template'],
              'template_title' => $setTemplateResult['template_title'],
            ),
          );
        }
        self::responseOk($result);
      }
    }
  }

  function setShare() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['CONTENT_TYPE'] == 'application/json') {
      $jsonString = file_get_contents('php://input');
      $jsondata = json_decode($jsonString, true);
      if (is_string($jsondata['id']) && isset($jsondata['id'])) {
        $acId = $jsondata['id'];
      }
      if (is_string($jsondata['is_share_with_others']) && isset($jsondata['is_share_with_others'])) {
        $acIsShare = $jsondata['is_share_with_others'];
      }
      if (isset($acId) && isset($acIsShare)) {
        $setShareResult = CRM_AI_BAO_AICompletion::setShare($acId);
        $result = array();
        if ($setShareResult) {
          $result = array(
            'status' => "success",
            'message' => "AI completion is set as shareable successfully",
            'data' => [
              'id' => $acId,
              'is_template' => $acIsShare,
            ],
          );
        }
        else {
          $result = array(
            'status' => "Failed",
            'message' => "AI completion has already been set as shareable",
            'data' => [
              'id' => $acId,
              'is_template' => $acIsShare,
            ],
          );
        }
        self::responseOk($result);
      }
    }
  }

  function responseError($error) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($error);
    CRM_Utils_System::civiExit();
  }

  public static function responseOk($data) {
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    CRM_Utils_System::civiExit();
  }
}