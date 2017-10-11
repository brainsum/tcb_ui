<?php

namespace Drupal\tcb_ui\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\tcb_ui\Form\ChatForm;

/**
 * Provides a 'ChatBlock' block.
 *
 * @Block(
 *   id = "chat_block",
 *   admin_label = @Translation("ChatBot Search Block"),
 * )
 */
class ChatBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = \Drupal::formBuilder()->getForm(ChatForm::class);

    return [
      'form' => $form,
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @see BookNavigationBlock
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
