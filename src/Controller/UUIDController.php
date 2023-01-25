<?php

namespace Drupal\entity_type_clone\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for UUID-related functionality.
 *
 * @package Drupal\entity_type_clone\Controller
 */
class UUIDController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The service container.
   *
   * @var \Drupal\Component\DependencyInjection\Container|\Symfony\Component\DependencyInjection\Container
   */
  private $serviceContainer;

  /**
   * Constructor function.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, $serviceContainer) {
    $this->entityTypeManager = $entity_type_manager;
    $this->serviceContainer = $serviceContainer;
  }

  /**
   * Creates a new container for dependency injection.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Gets the UUID.
   */
  public function uuidGet() {
    $uuid = [];
    $entity_type_definations = $this->entityTypeManager->getDefinitions();
    foreach ($entity_type_definations as $definition) {
      if ($definition instanceof ContentEntityType) {
        $content_types = $this->serviceContainer->get('entity_type.bundle.info')
          ->getBundleInfo($definition->id());
        $entity_type = $definition->getBundleEntityType();
        if ($entity_type && $content_types) {
          foreach ($content_types as $type_id => $type) {
            $uuid[$entity_type][$type_id] = $this->entityTypeManager
              ->getStorage($entity_type)
              ->load($type_id)
              ->uuid();
          }
        }
      }
    }
    return $uuid;
  }

}
