<?php

/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */

$PROF_URL = 'http://localhost/~co3k/xhp/';
$XHPROF_ROOT = '/Users/co3k/src/xhprof-0.9.2';

xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY);

require_once(dirname(__FILE__).'/../config/ProjectConfiguration.class.php');

// load opMobileUserAgent before initializing application
$old_error_level = error_reporting();

error_reporting($old_error_level & ~(E_STRICT | E_DEPRECATED));

set_include_path(dirname(__FILE__).'/../lib/vendor/PEAR/'.PATH_SEPARATOR.get_include_path());
require_once(dirname(__FILE__).'/../lib/util/opMobileUserAgent.class.php');

$is_mobile = !opMobileUserAgent::getInstance()->getMobile()->isNonMobile();

error_reporting($old_error_level);

// decide an application that should load
if ($is_mobile)
{
  $configuration = ProjectConfiguration::getApplicationConfiguration('mobile_frontend', 'prod', false);
}
else
{
  $configuration = ProjectConfiguration::getApplicationConfiguration('pc_frontend', 'prod', false);
}

sfConfig::set('sf_no_script_name', false);
sfContext::createInstance($configuration)->dispatch();

$xhprof_data = xhprof_disable();

$lastEntry = sfContext::getInstance()->getActionStack()->getLastEntry();
$routeName = sfContext::getInstance()->getRouting()->getCurrentRouteName();
$entryId = $routeName.'_'.$lastEntry->getModuleName().'_'.$lastEntry->getActionName().'_'.getmypid();

include_once $XHPROF_ROOT . "/xhprof_lib/utils/xhprof_lib.php";
include_once $XHPROF_ROOT . "/xhprof_lib/utils/xhprof_runs.php";

$xhprof_runs = new XHProfRuns_Default();

$run_id = $xhprof_runs->save_run($xhprof_data, $entryId);

echo '<pre>'.htmlspecialchars("---------------\n".
     "Assuming you have set up the http based UI for \n".
     "XHProf at some address, you can view run at \n".
     $PROF_URL."index.php?run=$run_id&source=$entryId\n".
     "---------------\n").'</pre>';
