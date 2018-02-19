<?php

namespace Drupal\entity_type_clone\Form;

use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_type_clone\Helpers\EntityTypeCloneHelper;
use Drupal\node\Entity\NodeType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class CloneEntityType.
 *
 * @package Drupal\entity_type_clone\Form
 */
class CloneEntityType extends FormBase {

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

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['displays'] = array();
    $input = &$form_state->getUserInput();
    $wrapper = 'entity-wrapper';
    // Create the part of the form that allows the user to select the basic
    // properties of what the entity to delete.
    $form['displays']['show'] = [
      '#type' => 'fieldset',
      '#title' => t('Entity Clone Settings'),
      '#tree' => TRUE,
      '#attributes' => ['class' => ['container-inline']],
    ];
    $content_entity_types = [];
    $entity_type_definations = $this->entityTypeManager->getDefinitions();
    /* @var $definition \Drupal\Core\Entity\EntityTypeInterface */
    foreach ($entity_type_definations as $definition) {
      if ($definition instanceof ContentEntityType) {
        if ($definition->id() == 'node' || $definition->id() == 'taxonomy_term') {
          $content_entity_types[$definition->id()] = $definition->getLabel();
        }
      }
    }
    $form['displays']['show']['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Entity Type'),
      '#options' => $content_entity_types,
      '#empty_option' => $this->t('-select-'),
      '#size' => 1,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'ajaxCallChangeEntity'],
        'wrapper' => $wrapper,
      ]
    ];
    if (isset($input['show']['entity_type'])) {
      $default_bundles = entity_get_bundles($input['show']['entity_type']);
      // If the current base table support bundles and has more than one (like user).
      if (!empty($default_bundles)) {
        // Get all bundles and their human readable names.
        foreach ($default_bundles as $type => $bundle) {
          $type_options[$type] = $bundle['label'];
        }
        $form['displays']['show']['type']['#options'] = $type_options;
      }
    }
    $form['displays']['show']['type'] = [
      '#type' => 'select',
      '#title' => $this->t('of type'),
      '#options' => $type_options,
      '#prefix' => '<div id="' . $wrapper . '">',
      '#suffix' => '</div>'
    ];
    //Target content type fieldset.
    $form['target'] = array(
      '#type' => 'details',
      '#title' => t('Target Entity details'),
      '#open' => TRUE,
    );

    $form['target']['clone_bundle'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Target bundle name'),
      '#required' => TRUE,
    ];
    $form['target']['clone_bundle_machine'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Target bundle machine name'),
      '#required' => TRUE,
    ];
    $form['target']['target_description'] = array(
      '#type' => 'textarea',
      '#title' => t('Description'),
      '#required' => FALSE,
    );
    $form['message'] = [
      '#markup' => $this->t('Note: Use <b>ENTITY TYPE CLONE</b> only to clone Content Type, Taxonomy.<br>'),
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
  public function validateForm(array &$form, FormStateInterface $form_state) {
    //Get the submitted form values.
    $values = $form_state->getValues();
    $entity_type = $values['show']['entity_type'];
    //Retrieve the existing content type names.
    $contentTypesNames = $this->getContentTypesList($entity_type);
    //Check if the machine name already exists.
    if (in_array($values['clone_bundle_machine'], $contentTypesNames)) {
      $form_state->setErrorByName(
        'clone_bundle_machine', $this->t('The machine name of the target entity type already exists.')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get $form_state values.
    $values = $form_state->getValues();
    $op = (string) $values['op'];
    if ($op == t('Reset')) {
      $form_state->setRedirect('entity_type_clone.type');
    }
    elseif ($op == t('Clone')) {
      //Create the batch process.
      $batch = array(
        'title' => t('Batch operations'),
        'operations' => $this->cloneEntityType($form_state),
        'finished' => '\Drupal\entity_type_clone\Form\CloneEntityTypeData::cloneEntityTypeFinishedCallback',
        'init_message' => t('Performing batch operations...'),
        'error_message' => t('Something went wrong. Please check the errors log.'),
      );
      //Set the batch.
      batch_set($batch);
    }
  }

  public function cloneEntityType(FormStateInterface $form_state) {
    //Get the form values.
    $values = $form_state->getValues();
    $entity_type = $values['show']['entity_type'];
    //Prepare the operations array.
    $operations = array();
    //Clone content type operation.
    $operations[] = ['\Drupal\entity_type_clone\Form\CloneEntityTypeData::cloneEntityTypeData', [$values]];
    //Clone fields operations.
    $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type, $values['show']['type']);
    foreach ($fields as $field) {
      if (!empty($field->getTargetBundle())) {
        $data = ['field' => $field, 'values' => $values];
        $operations[] = [
          '\Drupal\entity_type_clone\Form\CloneEntityTypeData::cloneEntityTypeField',
          [$data],
        ];
      }
    }
    //Return the result.
    return $operations;
  }

  protected function getContentTypesList($entity_type) {
    if ($entity_type == 'node') {
      // Get the existing content types.
      $contentTypes = \Drupal::service('entity.manager')->getStorage('node_type')->loadMultiple();
      //Retrieve the existing content type names.
      $entityTypesNames = [];
      foreach ($contentTypes as $contentType) {
        $entityTypesNames[] = $contentType->id();
      }
    }
    elseif ($entity_type == 'taxonomy_term') {
      $taxonomyTypes = taxonomy_vocabulary_get_names();
      foreach ($taxonomyTypes as $taxonomyType) {
        $entityTypesNames[] = $taxonomyType;
      }
    }
    //Return the result.
    return $entityTypesNames;
  }

}
