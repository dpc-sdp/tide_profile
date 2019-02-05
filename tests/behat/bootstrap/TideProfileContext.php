<?php

/**
 * @file
 * Tide Profile context for Behat testing.
 */

use Drupal\DrupalExtension\Context\DrupalContext;
use IntegratedExperts\BehatSteps\D8\ContentTrait;
use IntegratedExperts\BehatSteps\D8\MediaTrait;
use IntegratedExperts\BehatSteps\D8\MenuTrait;
use IntegratedExperts\BehatSteps\D8\TaxonomyTrait;
use IntegratedExperts\BehatSteps\FieldTrait;
use IntegratedExperts\BehatSteps\LinkTrait;
use IntegratedExperts\BehatSteps\PathTrait;
use IntegratedExperts\BehatSteps\ResponseTrait;

/**
 * Defines application features from the specific context.
 */
class TideProfileContext extends DrupalContext {
  use LinkTrait;
  use PathTrait;
  use FieldTrait;
  use ContentTrait;
  use MediaTrait;
  use MenuTrait;
  use TaxonomyTrait;
  use ResponseTrait;
  use TideCommonTrait;

}
