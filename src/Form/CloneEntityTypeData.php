<?php

namespace Drupal\entity_type_clone\Form;

use Drupal\node\Entity\NodeType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\entity_type_clone\Helpers\EntityTypeCloneHelper;

/**
 * Class CloneContentType.
 *
 * @package Drupal\entity_type_clone\Form
 */
class CloneEntityTypeData {

  /**
   * Clones a content type field.
   *
   * @param array $data
   *   Contains the field to clone and $form_state data.
   * @param array $context
   *   A reference to the batch operation context.
   */
  public static function cloneEntityTypeField(array $data, array &$context) {
    //Get the source field name.
    $sourceFieldName = $data['field']->getName();
    //Clone the field.
    $targetFieldConfig = $data['field']->createDuplicate();
    $targetFieldConfig->set('entity_type', $data['values']['show']['entity_type']);
    $targetFieldConfig->set('bundle', $data['values']['clone_bundle_machine']);
    $targetFieldConfig->save();
    //Copy the form display
    EntityTypeCloneHelper::copyFieldDisplay('form', 'default', $data);
    //Copy the view display
    EntityTypeCloneHelper::copyFieldDisplay('view', 'default', $data);
    //Update the progress information.target_machine_name
    $context['sandbox']['progress'] ++;
    $context['sandbox']['current_item'] = $sourceFieldName;
    $context['message'] = t(
      'Field @source successfully cloned.', ['@source' => $sourceFieldName]
    );
    $context['results']['fields'][] = $sourceFieldName;
  }

  /**
   * Clones a content type.
   *
   * @param array $values
   *   Contains the values of the form submitted via $form_state.
   * @param array $context
   *   A reference to the batch operation context.
   */
  public function cloneEntityTypeData(array $values, array &$context) {
    //Prepare the progress array.
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
    }
    //Load the source entity type.
    if ($values['show']['entity_type'] == 'node') {
      $sourceContentType = NodeType::load($values['show']['type']);
    }
    if ($values['show']['entity_type'] == 'taxonomy_term') {
      $sourceContentType = \Drupal\Core\Entity\EntityType::load($values['show']['type']);
    }
    //Create the target entity type.
    $targetContentType = $sourceContentType->createDuplicate();
    $targetContentType->set('uuid', \Drupal::service('uuid')->generate());
    $targetContentType->set('name', $values['clone_bundle']);
    $targetContentType->set('type', $values['clone_bundle_machine']);
    $targetContentType->set('originalId', $values['clone_bundle_machine']);
    $targetContentType->set('description', $values['target_description']);
    $targetContentType->save();
    //Update the progress information.
    $context['sandbox']['progress'] ++;
    $context['sandbox']['current_item'] = $values['show']['type'];
    $context['message'] = t(
      'Entity type @source successfully cloned.', ['@source' => $values['show']['type']]
    );
    $context['results']['source'][] = $values['show']['type'];
    $context['results']['target'][] = $values['clone_bundle_machine'];
  }

  /**
   * Handles results after the batch operations.
   *
   * @param bool $success
   *   The status of the batch process.
   * @param array $results
   *   Contains the results of the batch operation.
   * @param array $operations
   *   The array of operations processed by the batch.
   */
  public static function cloneEntityTypeFinishedCallback($success, array $results, array $operations) {
    //Check batch operations success.
    if ($success) {
      $message = t('"@source" content type and @fields field(s) cloned successfuly to "@target".', array(
        '@source' => $results['source'][0],
        '@fields' => count($results['fields']),
        '@target' => $results['target'][0],
        )
      );
    }
    else {
      $message = t('Finished with an error.');
    }
    //Send the result message.
    drupal_set_message($message, 'status', TRUE);
    //Redirect to the content type list
    $response = new RedirectResponse('admin/entity-type-clone');
    $response->send();
  }

}
