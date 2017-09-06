<?php

namespace Caereservices\Mailup;

use Caereservices\Mailup\MailupStatus as MailupStatus;
use Caereservices\Mailup\MailupException as MailupException;
use Caereservices\Mailup\MailupClass as MailupClass;

/**
*  MailupClient Class - A Mailup.com platform API Interface
*
*  @author Massimo Villalta
*/
class MailupClient {

   private $mailUp = null;
   private $clientLogged = false;
   private $listId = -1;

   protected function makeRecipientsRequest($userData , $_dynafields) {
      if( !isset($userData["mail"]) || $userData["mail"] == "" ) return "";
      $fields = [];
      $dynafields = json_decode($_dynafields, true);
      foreach( $dynafields["Items"] as $df ) {
         if( $df["Description"] == "firstname" ) {
            if( isset($userData["name"]) && $userData["name"] != "" ) {
               $df["Value"] = $userData["name"];
               $fields[] = $df;
            }
         }
         if( $df["Description"] == "lastname" ) {
            if( isset($userData["surname"]) && $userData["surname"] != "" ) {
               $df["Value"] = $userData["surname"];
               $fields[] = $df;
            }
         }
         if( $df["Description"] == "phone" ) {
            if( isset($userData["mobile"]) && $userData["mobile"] != "" ) {
               $df["Value"] = $userData["mobile"];
               $fields[] = $df;
            }
         }
         if( $df["Description"] == "company" ) {
            if( isset($userData["company"]) && $userData["company"] != "" ) {
               $df["Value"] = $userData["company"];
               $fields[] = $df;
            }
         }
      }
      $retVal = [
         "Email" => $userData["mail"],
         "Fields" => $fields
      ];
      if( isset($userData["mobile"]) && $userData["mobile"] != "" ) {
         $retVal["MobileNumber"] = $userData["mobile"];
         $retVal["MobilePrefix"] = '0039';
      }
      return json_encode($retVal);
   }

   protected function get_list_id($listName = "") {
      $listId = -1;
      $url = $this->mailUp->getConsoleEndpoint() . "/Console/List";
      $result = $this->mailUp->callMethod($url, "GET", null, "JSON");
      if( $result === false ) return $listId;
      $result = json_decode($result);
      $arr = $result->Items;
      for( $i = 0; $i < count($arr); $i++ ) {
         $list = $arr[$i];
         if( $listName == $list->Name) {
            $listId = $list->IdList;
            break;
         }
      }
      return $listId;
   }

   protected function get_user_id($mail = "") {
      $itemID = -1;
      $url = $this->mailUp->getConsoleEndpoint() . "/Console/List/" . $this->listId . "/Recipients/Subscribed?filterby=\"Email.Contains('" . $mail . "')\"";
      $result = $this->mailUp->callMethod($url, "GET", null, "JSON");
      if( $result === false ) return $itemID;
      $result = json_decode($result);
      if( count($result->Items) > 0 ) {
         $itemID = $result->Items[0]->idRecipient;
      }
      return $itemID;
   }

   protected function get_group_id($groupName = "") {
      $groupId = -1;
      if( $this->listId != -1 ) {
         $url = $this->mailUp->getConsoleEndpoint() . "/Console/List/" . $this->listId . "/Groups";
         $result = $this->mailUp->callMethod($url, "GET", null, "JSON");
         if( $result === false ) return $groupId;
         $result = json_decode($result);
         if( isset($result->Items) ) {
            $arr = $result->Items;
            for( $i = 0; $i < count($arr); $i++ ) {
               $group = $arr[$i];
               if( $groupName == $group->Name) {
                  $groupId = $group->idGroup;
                  break;
               }
            }
         }
      }
      return $groupId;
   }

   protected function create_group($groupName = "") {
      $groupId = -1;
      if( $this->listId != -1 ) {
         $url = $this->mailUp->getConsoleEndpoint() . "/Console/List/" . $this->listId . "/Group";
         $groupRequest = "{\"Deletable\":true,\"Name\":\"" . $groupName . "\",\"Notes\":\"". $groupName . "\"}";
         $result = $this->mailUp->callMethod($url, "POST", $groupRequest, "JSON");
         if( $result === false ) return $groupId;
         $result = json_decode($result);
         $arr = $result->Items;
         for( $i = 0; $i < count($arr); $i++ ) {
            $group = $arr[$i];
            if( $groupName == $group->Name) {
               $groupId = $group->idGroup;
               break;
            }
         }
      }
      return $groupId;
   }

   protected function create_mail_from_template($templateId = 0) {
      $url = $mailUp->getConsoleEndpoint() . "/Console/List/" . $this->listId . "/Email/Template/" . $templateId;
      $result = $mailUp->callMethod($url, "POST", null, "JSON");
      $result = json_decode($result);
      if( count($result) > 0 ) {
         $emailId = $result[0]->idMessage;
         if( $emailId != 0 ) {
            return $emailId;
         }
      }
      return false;
   }

   protected function create_mail_from_message($message = "") {

      return false;
   }

   protected function send_mail($messageId = 0, $groupName = "", $userMail = "") {

      $groupId = -1;
      if( $groupName != "" ) {
         $groupId = $this->get_group_id($groupName);
      }

      return false;
   }

   /*
       PUBLIC METHOD
   */
   function __construct($inClientId = "", $inClientSecret = "", $inCallbackUri = "") {
      if( ($inClientId != "") && ($inClientSecret != "") && ($inCallbackUri != "") ) {
         $this->mailUp = new MailupClass($inClientId, $inClientSecret, $inCallbackUri);
      }
   }

   function login($user = "", $password = "", $listName = "") {
      if( $this->mailUp && ($user != "") && ($password != "") ) {
         try {
            $this->clientLogged = $this->mailUp->logOnWithPassword($user, $password);
            if( $this->clientLogged ) {
               if( $listName != "" ) {
                  $this->listId = $this->get_list_id($listName);
                  if( $this->listId == -1 ) {
                     return MailupStatus::ERR_LIST_NOT_FOUND;
                  }
               }
               return MailupStatus::OK;
            }
            return MailupStatus::ERR_NOT_LOGGED_IN;
         } catch ( MailUpException $e ) {
            return MailupStatus::ERR_MAILUP_EXCEPTION;
         }
      }
      return MailupStatus::ERR_INVALID_PARAMETER;
   }

   function delUserFromGroup($mail = "", $groupName = "") {
      if( $this->clientLogged ) {
         if( $mail != "" && $groupName != "" && $this->listId != -1 ) {
            try {
               $itemID = $this->get_user_id($mail);
               $groupId = -1;
               if( intval($groupName) > 0 ) {
                  $groupId = intval($groupName);
               } else {
                  $groupId = $this->get_group_id($groupName);
               }
               if( $groupId != -1 && $itemID != -1 ) {
                  $url = $this->mailUp->getConsoleEndpoint() . "/Console/Group/" . $groupId . "/Unsubscribe/" . $itemID;
                  $result = $this->mailUp->callMethod($url, "DELETE", null, "JSON");
                  return MailupStatus::OK;
               } else {
                  return MailupStatus::ERR_GETTING_DATA;
               }
               return MailupStatus::ERR_USERDATA_NOTFOUND;
            } catch (MailUpException $e) {
               return MailupStatus::ERR_MAILUP_EXCEPTION;
            }
         } else {
            return MailupStatus::ERR_INVALID_PARAMETER;
         }
      } else {
         return MailupStatus::ERR_NOT_LOGGED_IN;
      }
   }

   function addGroup($groupName = "") {
      if( $this->clientLogged ) {
         if( $groupName != "" ) {
            try {
               $groupId = $this->get_group_id($groupName);
               if( $groupId == -1 ) {
                  $groupId = $this->create_group($groupName);
               }
               if( $groupId != -1 ) {
                  return $groupId;
               }
               return MailupStatus::ERR_CREATING_GROUPS;
            } catch (MailUpException $e) {
               return MailupStatus::ERR_MAILUP_EXCEPTION;
            }
         } else {
            return MailupStatus::ERR_INVALID_PARAMETER;
         }
      } else {
         return MailupStatus::ERR_NOT_LOGGED_IN;
      }
   }

   function addUserToGroup($userData = [], $groupName = "") {
      if( $this->clientLogged ) {
         if( is_array($userData) && (count($userData) > 0) && ($groupName != "") ) {
            try {
               $groupId = -1;
               if( intval($groupName) > 0 ) {
                  $groupId = intval($groupName);
               } else {
                  $groupId = $this->get_group_id($groupName);
                  if( $groupId == -1 ) {
                     $groupId = $this->create_group($groupName);
                  }
               }
               if( $groupId != -1 ) {
                  $itemID = $this->get_user_id($userData["mail"]);
                  if( $itemID > 0 ) {
                     return MailupStatus::OK;
                  } else {
                     $url = $this->mailUp->getConsoleEndpoint() . "/Console/Recipient/DynamicFields?PageNumber=0&PageSize=30&orderby=\"Id+asc\"";
                     $result = $this->mailUp->callMethod($url, "GET", null, "JSON");
                     if( $result === false ) return MailupStatus::ERR_GETTING_FIELDS;
                     $recipientRequest = $this->makeRecipientsRequest($userData, $result);
                     if( $recipientRequest != "" ) {
                        $url = $this->mailUp->getConsoleEndpoint() . "/Console/Group/" . $groupId . "/Recipient";
                        $result = $this->mailUp->callMethod($url, "POST", $recipientRequest, "JSON");
                        if( $result === false ) return MailupStatus::ERR_INVALID_USERDATA;
                        return MailupStatus::OK;
                     }
                     return MailupStatus::ERR_INVALID_USERDATA;
                  }
               }
               return MailupStatus::ERR_ADDING_USER;
            } catch (MailUpException $e) {
               return MailupStatus::ERR_MAILUP_EXCEPTION;
            }
         } else {
            return MailupStatus::ERR_INVALID_PARAMETER;
         }
      } else {
         return MailupStatus::ERR_NOT_LOGGED_IN;
      }
   }

   function getTemplateList() {
      if( $this->clientLogged ) {
         try {
            if( $this->listId != -1 ) {
               $url = $this->mailup->getConsoleEndpoint() . "/Console/List/" . $this->listId . "/Templates";
               $result = $this->mailUp->callMethod($url, "GET", null, "JSON");
               $retVal = json_decode($result);
               if( count($retVal) == 0 ) {
                  return MailupStatus::ERR_NO_TEMPLATES;
               }
               return $retVal;
            } else {
               return MailupStatus::ERR_UNKNOW_LIST;
            }
         } catch (MailUpException $ex) {
            return MailupStatus::ERR_MAILUP_EXCEPTION;
         }
      }
      return MailupStatus::ERR_NOT_LOGGED_IN;
   }

   function sendFromTemplate($templateId = 0) {
      if( $this->clientLogged ) {
         if( $templateId != 0 ) {
            if( $groupName != "" || $userMail != "" ) {
               $result = $this->create_mail_from_template($templateId);
               if( (gettype($result) == "integer") && (intval($result) != 0) ) {
                  if( $this->send_mail($result) ) {
                     return MailupStatus::MESSAGE_SENDED;
                  } else {
                     return MailupStatus::ERR_MESSAGE_NOT_SENDED;
                  }
               } else {
                  return MailupStatus::ERR_CANT_CREATE_MESSAGE;
               }
            } else {
               return MailupStatus::ERR_NO_RECIPIENTS;
            }
         } else {
            return MailupStatus::ERR_NO_TEMPLATES;
         }
      }
      return MailupStatus::ERR_NOT_LOGGED_IN;
   }

   function sendMessage($message = "", $groupName = "", $userMail = "") {
      if( $this->clientLogged ) {
         if( $groupName != "" || $userMail != "" ) {
            if( $message != "" ) {
               $result = $this->create_mail_from_message($message);
               if( (gettype($result) == "integer") && (intval($result) != 0) ) {
                  if( $this->send_mail($result, $groupName, $userMail) ) {
                     return MailupStatus::MESSAGE_SENDED;
                  } else {
                     return MailupStatus::ERR_MESSAGE_NOT_SENDED;
                  }
               } else {
                  return MailupStatus::ERR_CANT_CREATE_MESSAGE;
               }
               return MailupStatus::MESSAGE_SENDED;
            } else {
               return MailupStatus::ERR_MESSAGE_TEXT_EMPTY;
            }
         } else {
            return MailupStatus::ERR_NO_RECIPIENTS;
         }
      }
      return MailupStatus::ERR_NOT_LOGGED_IN;
   }

}