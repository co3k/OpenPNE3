<?php

/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */

// xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY);

require_once(dirname(__FILE__).'/../config/ProjectConfiguration.class.php');

$configuration = ProjectConfiguration::getApplicationConfiguration('pc_frontend', 'prod', false);

if (!opMobileUserAgent::getInstance()->getMobile()->isNonMobile())
{
  $configuration = ProjectConfiguration::getApplicationConfiguration('mobile_frontend', 'prod', false);
}

sfContext::createInstance($configuration)->dispatch();
/*
$xhprof_data = xhprof_disable();

$lastEntry = sfContext::getInstance()->getActionStack()->getLastEntry();
$routeName = sfContext::getInstance()->getRouting()->getCurrentRouteName();
$entryId = $routeName.'_'.$lastEntry->getModuleName().'_'.$lastEntry->getActionName().'_'.getmypid();

$XHPROF_ROOT = '/home/co3k/src/xhprof-0.9.2';
include_once $XHPROF_ROOT . "/xhprof_lib/utils/xhprof_lib.php";
include_once $XHPROF_ROOT . "/xhprof_lib/utils/xhprof_runs.php";

$xhprof_runs = new XHProfRuns_Default();

$run_id = $xhprof_runs->save_run($xhprof_data, $entryId);

echo '<pre>'.htmlspecialchars("---------------\n".
     "Assuming you have set up the http based UI for \n".
     "XHProf at some address, you can view run at \n".
     "http://ebizori.deb/xhp/index.php?run=$run_id&source=$entryId\n".
     "---------------\n").'</pre>';
     */
