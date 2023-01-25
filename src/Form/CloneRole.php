<?php

namespace Drupal\entity_type_clone\Form;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\user\Entity\Role;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class for cloning role.
 *
 * @package Drupal\entity_type_clone\Form
 */
class CloneRole extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a DuplicateRoleForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(EntityTypeManager $entity_type_manager, MessengerInterface $messenger, RouteMatchInterface $route_match) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('messenger'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_clone_role_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $note = NULL) {
    $userRoles = user_role_names();
    asort($userRoles);
    foreach ($userRoles as $key => $value) {
      $options[$key] = $value;
    }
    $form['role_id'] = [
      '#type' => 'select',
      '#empty_option' => $this->t('- Select -'),
      '#title' => $this->t('Choose role'),
      '#description' => $this->t('Select role to clone'),
      '#options' => $options,
      '#required' => TRUE,
    ];
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('New role'),
      '#required' => TRUE,
      '#size' => 40,
      '#maxlength' => 40,
      '#description' => $this->t('The name for the cloned role.'),
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => '',
      '#required' => TRUE,
      '#size' => 30,
      '#maxlength' => 64,
      '#machine_name' => [
        'exists' => ['\Drupal\user\Entity\Role', 'load'],
      ],
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clone'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $new_role = Role::create([
      'id' => $form_state->getValue('id'),
      'label' => $form_state->getValue('label'),
    ]);
    $new_role->save();
    /** @var \Drupal\user\RoleInterface $role */
    $roleLoad = $this->entityTypeManager->getStorage('user_role')
      ->load($form_state->getValue('role_id'));
    user_role_grant_permissions($new_role->id(), $roleLoad->getPermissions());
    $this->messenger->addStatus($this->t('Role %role_name has been cloned.', ['%role_name' => $form_state->getValue('role_id')]));
  }

}
