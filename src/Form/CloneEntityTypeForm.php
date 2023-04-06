<?php

namespace Drupal\entity_type_clone\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class for cloning entity type form.
 *
 * @package Drupal\entity_type_clone\Form
 */
class CloneEntityTypeForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_type_clone_form';
  }

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructor function.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Request $request, ModuleHandlerInterface $moduleHandler, EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityFieldManagerInterface $entity_field_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->request = $request;
    $this->moduleHandler = $moduleHandler;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * Creates a new container for dependency injection.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('module_handler'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $params = $this->request->query;
    $disbaled = FALSE;
    if ($params) {
      $entity_type = $params->get('entity');
      $bundle_type = $params->get('bundle');
      if ($entity_type && $bundle_type) {
        $disbaled = TRUE;
      }
    }
    $form['displays'] = [];
    $input = &$form_state->getUserInput();
    $wrapper = 'entity-wrapper';
    // Create the part of the form that allows the user to select the basic
    // properties of what the entity to delete.
    $form['displays']['show'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Entity Clone Settings'),
      '#tree' => TRUE,
      '#attributes' => ['class' => ['container-inline']],
    ];
    $content_entity_types = [];
    $entity_type_definations = $this->entityTypeManager->getDefinitions();
    /** @var \Drupal\Core\Entity\EntityTypeInterface $definition */
    $clone_types = [
      'block_content',
      'node',
      'taxonomy_term',
    ];
    $moduleHandler = $this->moduleHandler;
    if ($moduleHandler->moduleExists('paragraphs')) {
      $clone_types[] = 'paragraph';
    }
    if ($moduleHandler->moduleExists('profile')) {
      $clone_types[] = 'profile';
    }
    if ($moduleHandler->moduleExists('storage')) {
      $clone_types[] = 'storage';
    }
    foreach ($entity_type_definations as $definition) {
      if ($definition instanceof ContentEntityType) {
        if (in_array($definition->id(), $clone_types)) {
          $content_entity_types[$definition->id()] = $definition->getLabel();
          $bundleEntityTypes[$definition->id()] = $definition->getBundleEntityType();
        }
      }
    }
    if ($bundleEntityTypes) {
      $form['bundle_types'] = [
        '#type' => 'hidden',
        '#value' => Json::encode($bundleEntityTypes),
      ];
    }
    $form['displays']['show']['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Entity Type'),
      '#options' => $content_entity_types,
      '#empty_option' => $this->t('- Select Entity Type -'),
      '#size' => 1,
      '#required' => TRUE,
      '#disabled' => $disbaled,
      '#default_value' => $entity_type ?? '',
      '#suffix' => '<div id="' . $wrapper . '"></div>',
      '#ajax' => [
        'callback' => [$this, 'ajaxCallChangeEntity'],
        'wrapper' => $wrapper,
      ],
    ];
    if (isset($input['show']['entity_type']) || isset($entity_type)) {
      $entity_type_selected = $input['show']['entity_type'] ?? $entity_type;
      if ($entity_type_selected) {
        $default_bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_selected);
        // If the current base table support bundles and
        // has more than one (like user).
        if (!empty($default_bundles)) {
          // Get all bundles and their human-readable names.
          foreach ($default_bundles as $type => $bundle) {
            $type_options[$type] = $bundle['label'];
          }
          $form['displays']['show']['type']['#options'] = $type_options;
        }
      }
    }
    if (isset($type_options)) {
      $form['displays']['show']['type'] = [
        '#type' => 'select',
        '#empty_option' => $this->t('- Select -'),
        '#title' => $this->t('of type'),
        '#options' => $type_options,
        '#disabled' => $disbaled,
        '#default_value' => $bundle_type,
        '#prefix' => '<div id="' . $wrapper . '">',
        '#suffix' => '</div>',
      ];
    }
    // Target content type fieldset.
    $form['target'] = [
      '#type' => 'details',
      '#title' => $this->t('Target Entity details'),
      '#open' => TRUE,
      '#states' => [
        'visible' => [
          'select[name="show[entity_type]"]' => ['!value' => ''],
        ],
        'enabled' => [
          'select[name="show[type]"]' => ['!value' => ''],
        ],
      ],
    ];
    $form['target']['clone_bundle'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Target bundle name'),
      '#required' => TRUE,
    ];
    $form['target']['clone_bundle_machine'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Target bundle machine name'),
      '#required' => TRUE,
      '#machine_name' => [
        'exists' => $this->getEntityLookupCallback($entity_type),
        'source' => ['target', 'clone_bundle'],
      ],
    ];
    $form['target']['target_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#required' => FALSE,
    ];

    $form['message'] = [
      '#markup' => $this->t('Note: After cloning with <b>ENTITY TYPE CLONE</b> please check the cloned entity type before doing config export or save any content in it.<br>'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clone'),
    ];
    $form['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxCallChangeEntity(array &$form, FormStateInterface $form_state) {
    return $form['displays']['show']['type'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get the form state values.
    $values = $form_state->getValues();
    $op = (string) $values['op'];
    if ($op == $this->t('Reset')) {
      $form_state->setRedirect('entity_type_clone.type');
    }
    elseif ($op == $this->t('Clone')) {
      // Create the batch process for clone operations.
      $batch = [
        'title' => $this->t('Cloning in process.'),
        'operations' => $this->cloneEntityType($form_state),
        'init_message' => $this->t('Performing clone operations...'),
        'finished' => '\Drupal\entity_type_clone\CloneEntityType::cloneEntityTypeFinishedCallback',
        'error_message' => $this->t('Something went wrong. Please check the errors log.'),
      ];
      batch_set($batch);
    }
  }

  /**
   * Implements to perform batch operations.
   */
  public function cloneEntityType(FormStateInterface $form_state) {
    // Get the form state values.
    $values = $form_state->getValues();
    $entity_type = $values['show']['entity_type'];
    $operations = [];
    // Clone entity type operation.
    $operations[] = [
      '\Drupal\entity_type_clone\CloneEntityType::cloneEntityTypeData',
      [$values],
    ];
    // Clone fields operations.
    $fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $values['show']['type']);
    if ($fields) {
      foreach ($fields as $field) {
        if (!empty($field->getTargetBundle()) && !($entity_type === 'taxonomy_term' && $field->getName() === 'parent')) {
          $data = ['field' => $field, 'values' => $values];
          $operations[] = [
            '\Drupal\entity_type_clone\CloneEntityType::cloneEntityTypeField',
            [$data],
          ];
        }
      }
    }
    return $operations;
  }

  /**
   * Returns the exists callback used for the clone_bundle_machine field.
   *
   * @param string $entity_type
   *
   * @return array
   */
  protected function getEntityLookupCallback($entity_type) {
    switch ($entity_type) {
      case 'node':
        return ['Drupal\node\Entity\NodeType', 'load'];
      case 'paragraph':
        return ['Drupal\paragraphs\Entity\ParagraphsType', 'load'];
      case 'profile':
        return ['Drupal\profile\Entity\ProfileType', 'load'];
      case 'taxonomy_term':
        return ['Drupal\taxonomy\Entity\Vocabulary', 'load'];
    }
  }

}
