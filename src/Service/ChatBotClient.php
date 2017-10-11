<?php

namespace Drupal\tcb_ui\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\serialization\Encoder\JsonEncoder;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use Symfony\Component\Serializer\Encoder\JsonDecode;

/**
 * Class ChatBotClient.
 *
 * Provides a client for connecting to chatbot servers and sending requests.
 *
 * @package Drupal\tcb_ui\Service
 */
class ChatBotClient {

  use StringTranslationTrait;

  /**
   * The http client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The tcb_ui logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * An array of hosts.
   *
   * @var array|null
   */
  protected $hostList;

  /**
   * Renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The set email from the config.
   *
   * @var string
   */
  protected $email;

  /**
   * The set display name from the config.
   *
   * @var string
   */
  protected $botDisplayName;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * ChatBotClient constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \GuzzleHttp\Client $httpClient
   *   The http client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The tcb_ui logger.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    Client $httpClient,
    LoggerChannelFactoryInterface $loggerChannelFactory,
    ConfigFactoryInterface $configFactory,
    RendererInterface $renderer
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->httpClient = $httpClient;
    $this->logger = $loggerChannelFactory->get('tcb_ui');
    $this->configFactory = $configFactory;
    $this->hostList = $configFactory->get('tcb_ui.chatbot_server_list')->get('server_list.hosts');
    $this->email = $configFactory->get('tcb_ui.settings')->get('settings.email_address');
    $this->botDisplayName = $configFactory->get('tcb_ui.settings')->get('settings.bot_display_name');
    $this->renderer = $renderer;
  }

  /**
   * Getter for the 'tcb_ui.settings' config.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The config.
   */
  public function getModuleSettings() {
    return $this->configFactory->get('tcb_ui.settings');
  }

  /**
   * Check, if the module has been properly set up.
   *
   * @return bool
   *   TRUE, if it's OK, FALSE otherwise.
   */
  public function selfCheck() {
    return !(
      empty($this->hostList)
      || empty($this->email)
      || empty($this->botDisplayName)
    );
  }

  /**
   * Send the user message to the bot and return the response.
   *
   * @param string $message
   *   The user message.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The bot response.
   *
   * @throws \RuntimeException
   * @throws \Symfony\Component\Serializer\Exception\UnexpectedValueException
   * @throws \Drupal\Core\Entity\Exception\UndefinedLinkTemplateException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \InvalidArgumentException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function sendRequest($message) {
    $requestOptions = [
      'form_params' => ['user_input' => $message],
    ];

    try {
      // @todo: Figure out how to allow multiple hosts.
      $host = $this->hostList[0];
      $response = $this->httpClient->post("$host/chatbot", $requestOptions);
    }
    catch (ConnectException $connectException) {
      $this->logger->error($connectException->getMessage());
      return $this->botUnavailableMessage();
    }

    $decoder = new JsonDecode(TRUE);
    $data = $decoder->decode($response->getBody()->getContents(), JsonEncoder::FORMAT);
    return $this->predictionCategoryToMessage($data['category']);
  }

  /**
   * Turn the bot response (category) into a proper message.
   *
   * @param string $category
   *   The detected category.
   *
   * @return string
   *   The human-readable translated response.
   *
   * @throws \Drupal\Core\Entity\Exception\UndefinedLinkTemplateException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \InvalidArgumentException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function predictionCategoryToMessage($category) {
    if ('personal' === $category) {
      return $this->t("Sorry, I'm not allowed to answer personal related questions. Please, contact the support: @support_mail", [
        '@support_mail' => Link::fromTextAndUrl(
          $this->email,
          Url::fromUri('mailto:' . $this->email)
        )->toString(),
      ]);
    }
    if ('devops_error' === $category) {
      return $this->t('It seems to be a generic problem. Please, contact the support: @support_mail', [
        '@support_mail' => Link::fromTextAndUrl(
          $this->email,
          Url::fromUri('mailto:' . $this->email)
        )->toString(),
      ]);
    }

    /*
     * login: node/177010
     * login_tcs: node/181077
     * login_ldap: node/180344
     */
    $link = $this->categoryToUrl($category);

    if (NULL === $link) {
      return $this->t("Sorry, I can't answer your question right now. Please, contact the support: @support_mail", [
        '@support_mail' => Link::fromTextAndUrl(
          $this->email,
          Url::fromUri('mailto:' . $this->email)
        )->toString(),
      ]);
    }

    return $this->t('The information on this link might be relevant to your problem: @link', [
      '@link' => $link,
    ]);
  }

  /**
   * Get the URL of a node which has the given $category as a keyword.
   *
   * @param string $category
   *   The category for which we want to search.
   *
   * @return string|null
   *   The generated absolute URL fo the node.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\Exception\UndefinedLinkTemplateException
   * @throws \InvalidArgumentException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function categoryToUrl($category) {
    $taxonomyStorage = $this->entityTypeManager->getStorage('taxonomy_term');

    /** @var \Drupal\taxonomy\Entity\Term[] $terms */
    $terms = $taxonomyStorage->loadByProperties(['name' => $category]);

    // If there are no terms for this category, return NULL.
    if (empty($terms)) {
      return NULL;
    }

    /** @var \Drupal\taxonomy\Entity\Term $term */
    $term = reset($terms);

    $nodeStorage = $this->entityTypeManager->getStorage('node');
    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $nodeStorage->loadByProperties(['field_keyword' => $term->id()]);

    // @todo: Maybe return a list of URLs?
    // If there are no nodes for this term, return NULL.
    if (empty($nodes)) {
      return NULL;
    }

    /** @var \Drupal\node\NodeInterface $node */
    // Get the first only.
    // @todo: Prepare to return multiple.
    $node = reset($nodes);
    // Get the SEO url for the node.
    $nodeUrl = $node
      ->toUrl('canonical', ['absolute' => TRUE])
      ->toString(TRUE)
      ->getGeneratedUrl();
    // Return the SEO url as a link.
    $link = Link::fromTextAndUrl(
      $nodeUrl,
      Url::fromUri($nodeUrl)
    )->toString();

    return $link;
  }

  /**
   * Helper function for creating an 'Unavailable' message.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The message.
   *
   * @throws \InvalidArgumentException
   */
  private function botUnavailableMessage() {
    // Add sleep() for better UX.
    sleep(1);
    return $this->t("I'm currently not available. Please, contact the support: @support_mail", [
      '@support_mail' => Link::fromTextAndUrl(
        $this->email,
        Url::fromUri('mailto:' . $this->email)
      )->toString(),
    ]);
  }

  /**
   * Generate HTML from a user message.
   *
   * @param string $message
   *   The bot message.
   *
   * @return string
   *   HTML as string.
   *
   * @internal
   * @note: Note yet used.
   */
  private function userResponseToHtml($message) {
    $build = [
      '#theme' => 'conversation_message',
      '#sender_class' => 'user',
      '#sender_name' => $this->t('You'),
      '#message' => $message,
    ];
    return (string) $this->renderer->render($build);
  }

  /**
   * Generate HTML from a bot message.
   *
   * @param string $message
   *   The bot message.
   *
   * @return string
   *   HTML as string.
   */
  public function botResponseToHtml($message) {
    $build = [
      '#theme' => 'conversation_message',
      '#sender_class' => 'ai',
      '#sender_name' => $this->botDisplayName,
      '#message' => $message,
    ];

    return (string) $this->renderer->render($build);
  }

}
