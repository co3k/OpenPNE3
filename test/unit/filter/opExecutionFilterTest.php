<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

include_once dirname(__FILE__).'/../../bootstrap/unit.php';

class testContext extends sfContext
{
  public function getRouting()
  {
    return $this->routing;
  }

  public function getModuleName()
  {
    return 'module';
  }

  public function getActionName()
  {
    return 'action';
  }
}

class sfExecutionFilter extends sfFilter
{
  protected function handleAction($filterChain, $actionInstance)
  {
    return "action";
  }
}

class testRequest extends opWebRequest
{
  public $isRedirect = false;

  public function needToRedirectToSoftBankGateway()
  {
    return true;
  }

  public function redirectToSoftBankGateway()
  {
    $this->isRedirect = true;
    return;
  }

  public function getIsRedirect()
  {
    return $this->isRedirect;
  }
}

class testAction extends sfActions
{
  public $request = null;

  public function __construct($context, $moduleName, $actionName)
  {
    parent::__construct($context, $moduleName, $actionName);
    $this->request = new testRequest(new sfEventDispatcher(), null);
  }

  public function getRequest()
  {
    return $this->request;
  }
}

class myFilter extends opExecutionFilter
{
  public function handleAction($filterChain, $actionInstance)
  {
    $result = parent::handleAction($filterChain, $actionInstance);
    var_dump($actionInstance->getRequest()->getIsRedirect());
    if ($actionInstance->getRequest()->getIsRedirect())
    {
      return "redirect";
    }
    else
    {
      return $result;
    }
  }

  public function setTestDate($date_str)
  {
    $this->spec_change_date = $date_str;
  }

  public function callNeedToRetriveMobileUID($module, $action, $retriveUIDMode = 1, $parameters = array())
  {
    $sslSelectableList = sfConfig::get('op_ssl_selectable_actions', array(
        sfConfig::get('sf_app') => array(),
    ));

    opConfig::set('retrive_uid', $retriveUIDMode);

    return $this->needToRetriveMobileUID($module, $action, new opWebRequest(new sfEventDispatcher(), $parameters), $sslSelectableList);
  }
}

$t = new lime_test(7);

require_once dirname(__FILE__).'/../../../config/ProjectConfiguration.class.php';
$configuration = ProjectConfiguration::getApplicationConfiguration('mobile_frontend', 'test', true);
testContext::createInstance($configuration);
$context = testContext::getInstance();

$filterChain = new sfFilterChain();
$filter = new myFilter($context);

$t->diag('spec_change_date < test_date');
$filter->setTestDate(date('Y-m-d H:i:s', time() - 60));
$actionInstance = new testAction($context, $context->getModuleName(), $context->getActionName());
$t->is($filter->handleAction($filterChain, $actionInstance), "action", "after spec_change_date, don't redirect to SoftBank GW");

$t->diag('spec_change_date > test_date');
$filter->setTestDate(date('Y-m-d H:i:s', time() + 60));
$actionInstance = new testAction($context, $context->getModuleName(), $context->getActionName());
$t->is($filter->handleAction($filterChain, $actionInstance), "redirect", "before spec_change_date, redirect to SoftBank GW");

$t->diag('Test for redirecting to HTTP action for retriving mobile UID');
$t->ok($filter->callNeedToRetriveMobileUID('member', 'configUID'), 'member/configUID redirects user to HTTP');
$t->ok($filter->callNeedToRetriveMobileUID('member', 'configUID', 0), 'member/configUID does not redirects user to HTTP when it does not retrieve uid');
$t->ok(!$filter->callNeedToRetriveMobileUID('member', 'home'), 'member/home does not redirect user to HTTP');
$t->ok(!$filter->callNeedToRetriveMobileUID('member', 'login'), 'member/login does not redirect user to HTTP');
$t->ok($filter->callNeedToRetriveMobileUID('member', 'login', 1, array('authMode' => 'MobileUID')), 'member/login redirect user to HTTP when the authMode is MobileUID');
