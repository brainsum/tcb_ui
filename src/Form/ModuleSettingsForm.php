<?php

namespace Drupal\tcb_ui\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ModuleSettingsForm.
 *
 * @package Drupal\tcb_ui\Form
 */
class ModuleSettingsForm extends ConfigFormBase {

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'tcb_ui_settings_form';
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
      'tcb_ui.settings',
      'tcb_ui.chatbot_server_list',
    ];
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    drupal_set_message($this->t('Currently, we only support a single host. This means that only the first entry will be used.'), 'warning');

    $form['#tree'] = TRUE;

    $form['settings'] = [
      '#type' => 'details',
      '#title' => $this->t('General settings'),
      '#open' => TRUE,
    ];
    $form['settings']['email_address'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#description' => $this->t('The email address to be displayed when the bot is not available or has no relevant answer.'),
      '#required' => TRUE,
      '#default_value' => $this->config('tcb_ui.settings')->get('settings.email_address'),
    ];
    $displayName = $this->config('tcb_ui.settings')->get('settings.bot_display_name');
    $form['settings']['bot_display_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bot display name'),
      '#description' => $this->t('The name that is going to be displayed for the bot in the conversation.'),
      '#default_value' => empty($displayName) ? $this->t('Support') : $displayName,
      '#required' => TRUE,
    ];
    $form['settings']['conversation_header'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Conversation header'),
      '#description' => $this->t('The header/title of the conversation.'),
      '#default_value' => $this->config('tcb_ui.settings')->get('settings.conversation_header'),
    ];

    $form['server_list'] = [
      '#type' => 'details',
      '#title' => $this->t('Remote settings'),
      '#open' => TRUE,
    ];
    // Implode, to get a proper textarea value.
    $hosts = $this->config('tcb_ui.chatbot_server_list')->get('server_list.hosts');
    $hosts = empty($hosts) ? '' : implode("\r\n", $hosts);
    $form['server_list']['hosts'] = [
      '#title' => $this->t('Bot host list'),
      '#type' => 'textarea',
      '#description' => $this->t('One host per line with the port, if needed and without trailing slashes. E.g @example', [
        '@example' => 'http://localhost:5000',
      ]),
      '#default_value' => $hosts,
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $settings = $this->config('tcb_ui.settings');
    $settings->set('settings.email_address', $form_state->getValue(['settings', 'email_address']));
    $settings->set('settings.bot_display_name', $form_state->getValue(['settings', 'bot_display_name']));
    $settings->set('settings.conversation_header', $form_state->getValue(['settings', 'conversation_header']));
    $settings->save();

    $serverList = $this->config('tcb_ui.chatbot_server_list');
    $hosts = $form_state->getValue(['server_list', 'hosts']);
    // Explode, so it's more readable in the yml.
    $hosts = explode("\r\n", $hosts);

    $serverList->set('server_list.hosts', $hosts);
    $serverList->save();


    parent::submitForm($form, $form_state);
  }

}
