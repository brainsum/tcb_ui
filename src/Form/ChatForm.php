<?php

namespace Drupal\tcb_ui\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\tcb_ui\Service\ChatBotClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a form that the user can use to interact with the chatbot.
 */
class ChatForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The chatbot client.
   *
   * @var \Drupal\tcb_ui\Service\ChatBotClient
   */
  protected $botClient;

  /**
   * The header of the conversation.
   *
   * @var string|\Drupal\Core\StringTranslation\TranslatableMarkup
   */
  protected $conversationHeader;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('tcb_ui.chatbot_client')
    );
  }

  /**
   * ChatForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\tcb_ui\Service\ChatBotClient $botClient
   *   The chatbot client.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    ChatBotClient $botClient
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->botClient = $botClient;

    $header = $this->botClient->getModuleSettings()->get('settings.conversation_header');
    if (empty($header)) {
      $header = $this->t('Search assistant');
    }

    $this->conversationHeader = $header;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'chatform';
  }

  /**
   * Return the default text by the bot.
   *
   * @return string
   *   The text.
   */
  protected function defaultText() {
    if (FALSE === $this->botClient->selfCheck()) {
      return $this->botClient->botResponseToHtml('The interface is not yet set up. Please, contact the site administrators.');
    }

    return $this->botClient->botResponseToHtml('Hello!') . $this->botClient->botResponseToHtml('How can I help you today?');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#prefix'] = "<div id='chatblock' class='chat'>";
    $form['#suffix'] = '</div>';

    $form['conversation'] = [
      '#type' => 'container',
      '#id' => 'conversation-container',
      '#attributes' => [
        'id' => 'conversation-container',
      ],
    ];
    $form['conversation']['header'] = [
      '#type' => 'html_tag',
      '#tag' => 'header',
      '#value' => $this->conversationHeader,
      '#id' => 'conversation-header',
      '#attributes' => [
        'id' => 'conversation-header',
        'class' => [
          'clearfix',
        ],
      ],
    ];

    $form['conversation']['container'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->defaultText(),
      '#id' => 'conversation',
      '#attributes' => [
        'id' => 'conversation',
        'class' => [
          'chat-history',
        ],
      ],
    ];

    if (TRUE === $this->botClient->selfCheck()) {
      $form['user_input'] = [
        '#type' => 'textfield',
        '#placeholder' => $this->t('How can we help?'),
        '#id' => 'user-input',
        '#attributes' => [
          'id' => 'user-input',
        ],
        '#ajax' => [
          'target_as' => [
            'id' => 'tcb-ui-user-input-submit',
          ],
        ],
      ];

      // @todo: After submit, wherever the user clicks the submit is executed.
      $form['send'] = [
        '#type' => 'button',
        '#value' => t('Send'),
        '#id' => 'tcb-ui-user-input-submit',
        '#name' => 'user-input-submit',
        '#attributes' => [
          'class' => [
            'js-hide',
            'use-ajax-submit',
          ],
        ],
        '#ajax' => [
          'callback' => '::ajaxBotResponseCallback',
          'event' => 'click',
          'progress' => [
            'type' => 'throbber',
            'message' => NULL,
          ],
        ],
      ];
    }

    $form['#attached']['library'][] = 'tcb_ui/connect_bot';

    return $form;
  }

  /**
   * Callback for returning a bot response.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   *
   * @throws \Symfony\Component\Serializer\Exception\UnexpectedValueException
   * @throws \RuntimeException
   * @throws \InvalidArgumentException
   */
  public function ajaxBotResponseCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    // Instantiate an AjaxResponse Object to return.
    $ajaxResponse = new AjaxResponse();

    $botMessage = $this->botClient->botResponseToHtml($this->botClient->sendRequest($form_state->getValue('user_input')));
    $ajaxResponse->addCommand(new AppendCommand('#conversation', $botMessage));

    sleep(2);
    $form_state->setUserInput([]);
    $form_state->setRebuild();
    return $ajaxResponse;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(FALSE);
  }

}
