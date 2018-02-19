<?php

namespace Drupal\entity_type_clone\Form;

use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get $form_state values.
    $values = $form_state->getValues();
    $op = (string) $values['op'];
    if ($op == t('Reset')) {
      $form_state->setRedirect('entity_type_clone.type');
    }
    elseif ($op == t('Clone')) {
      // Entity type.
      $entity_type = $values['show']['entity_type'];
      // Get bundle.
      $bundle = $values['show']['type'];
      // Get target bundle name.
      $target_bundle = $values['show']['clone_bundle'];
      // Get target bundle machine name.
      $target_machine_name = $values['show']['clone_bundle_name'];
      $form_state->setRedirect('entity_type_clone.entity_type_clone_confirmation', array('entity_type' => $entity_type, 'bundle' => $bundle, 'target' => $target_bundle, 'target_machine' => $target_machine_name));
    }
  }

}
