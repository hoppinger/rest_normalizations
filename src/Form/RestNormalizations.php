<?php

namespace Drupal\rest_normalizations\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements a codimth Config Form API.
 */
class RestNormalizations extends ConfigFormBase
{
  /**
   * @return string
   */
  public function getFormId() {
    return 'rest_normalizations_admin_settings';
  }

  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return [
      'rest_normalizations.settings',
    ];
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   * @return array
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('rest_normalizations.settings');
    $settings = $config->get('rest_fields') ?? [];

    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('Configuration form to add fields returned in REST reponse'),
    ];

    $count = $form_state->get('count') ? $form_state->get('count') : count($settings);
    if ($count === NULL) {
      $count = 1;
    }
    $form_state->set('count', $count);

    $form['#tree'] = TRUE;
    $form['rest_fields'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Fields'),
      '#prefix' => '<div id="fields-fieldset-wrapper">',
      '#suffix' => '</div>',
    ];

    for ($i = 0; $i < $count; $i++) {
      $form['rest_fields'][$i]['entity_type'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Entity Type'),
        '#required' => TRUE,
        '#description' => t("Entity types eg. 'node', 'paragraphs', 'user' etc."),
        '#default_value' => isset($settings[$i]) ? $settings[$i]['entity_type'] : ''
      ];
      $form['rest_fields'][$i]['entity_fields'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Fields'),
        '#required' => TRUE,
        '#description' => t('Field name seperated by comma'),
        '#default_value' => isset($settings[$i]) ? $settings[$i]['entity_fields'] : ''
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    
    $form['rest_fields']['actions']['add_name'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add one more'),
      '#submit' => ['::addOne'],
      '#ajax' => [
        'callback' => '::addmoreCallback',
        'wrapper' => 'fields-fieldset-wrapper',
      ],
    ];

    if ($count > 1) {
      $form['rest_fields']['actions']['remove_name'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove one'),
        '#submit' => ['::removeCallback'],
        '#ajax' => [
          'callback' => '::addmoreCallback',
          'wrapper' => 'fields-fieldset-wrapper',
        ],
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('rest_normalizations.settings');
    $values = $form_state->getValue('rest_fields');
    unset($values['actions']);
    foreach ($values as &$value) {
      $value['entity_type'] = str_replace(' ', '', $value['entity_type']);
      $value['entity_fields'] = str_replace(' ', '', $value['entity_fields']);
    }
    $config->set('rest_fields', $values)->save();
    parent::submitForm($form, $form_state);
  }

  public function addmoreCallback(array &$form, FormStateInterface $form_state) {
    return $form['rest_fields'];
  }

  public function addOne(array &$form, FormStateInterface $form_state) {
    $count = $form_state->get('count');
    $add_button = $count + 1;
    $form_state->set('count', $add_button);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for the "remove one" button.
   *
   * Decrements the max counter and causes a form rebuild.
   */
  public function removeCallback(array &$form, FormStateInterface $form_state) {
    $count = $form_state->get('count');
    if ($count > 1) {
      $remove_button = $count - 1;
      $form_state->set('count', $remove_button);
    }
    $form_state->setRebuild();
  }
}