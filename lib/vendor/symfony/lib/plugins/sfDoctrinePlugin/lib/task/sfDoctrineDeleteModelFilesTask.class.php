<?php

/*
 * This file is part of the symfony package.
 * (c) Jonathan H. Wage <jonwage@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once(dirname(__FILE__).'/sfDoctrineBaseTask.class.php');

/**
 * Delete all generated files associated with a Doctrine model. Forms, filters, etc.
 *
 * @package    symfony
 * @subpackage doctrine
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 * @version    SVN: $Id: sfDoctrineCreateModelTables.class.php 16087 2009-03-07 22:08:50Z Jonathan.Wage $
 */
class sfDoctrineDeleteModelFilesTask extends sfDoctrineBaseTask
{
  protected function configure()
  {
    $this->addArguments(array(
      new sfCommandArgument('name', sfCommandArgument::REQUIRED | sfCommandArgument::IS_ARRAY, 'The name of the model you wish to delete all related files for.'),
    ));

    $this->addOptions(array(
      new sfCommandOption('no-confirmation', null, sfCommandOption::PARAMETER_NONE, 'Do not ask for confirmation'),
    ));

    $this->aliases = array();
    $this->namespace = 'doctrine';
    $this->name = 'delete-model-files';
    $this->briefDescription = 'Delete all the related auto generated files for a given model name.';

    $this->detailedDescription = <<<EOF
The [doctrine:delete-model-files|INFO] task deletes all files associated with certain
models:

  [./symfony doctrine:delete-model-files Article Author|INFO]
EOF;
  }

  /**
   * @see sfTask
   */
  protected function execute($arguments = array(), $options = array())
  {
    $paths = array_merge(
      array(
        sfConfig::get('sf_lib_dir').'/model/doctrine',
        sfConfig::get('sf_lib_dir').'/form/doctrine',
        sfConfig::get('sf_lib_dir').'/filter/doctrine',
      ),
      $this->configuration->getPluginSubPaths('/lib/model/doctrine'),
      $this->configuration->getPluginSubPaths('/lib/form/doctrine'),
      $this->configuration->getPluginSubPaths('/lib/filter/doctrine')
    );

    $total = 0;

    foreach ($arguments['name'] as $modelName)
    {
      $finder = sfFinder::type('file')->name('/^(Base|Plugin)?'.$modelName.'(Form(Filter)?|Table)?\.class\.php$/');
      $files = $finder->in($paths);

      if ($files)
      {
        if (!$options['no-confirmation'] && !$this->askConfirmation(array_merge(
          array('The following '.$modelName.' files will be deleted:', ''),
          array_map(create_function('$v', 'return \' - \'.sfDebug::shortenFilePath($v);'), $files),
          array('', 'Continue? (y/N)')
        ), 'QUESTION_LARGE', false))
        {
          $this->logSection('doctrine', 'Aborting delete of "'.$modelName.'" files');
          continue;
        }

        $this->logSection('doctrine', 'Deleting "'.$modelName.'" files');
        $this->getFilesystem()->remove($files);

        $total += count($files);
      }
      else
      {
        $this->logSection('doctrine', 'No files found for the model named "'.$modelName.'"');
      }
    }

    $this->logSection('doctrine', 'Deleted a total of '.$total.' file(s)');
  }
}
