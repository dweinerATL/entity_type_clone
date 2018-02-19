<?php

namespace Drupal\entity_type_clone\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

class CloneEntityType extends ControllerBase {

  public function confirmation() {
    return new JsonResponse('ajay');
  }

}
