<?php

/**
 * @file
 * Contains \Drupal\verf\ViewsHandlerSubFormState.
 */

namespace Drupal\verf;

use Drupal\Core\Form\FormStateDecoratorBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormStateValuesTrait;
use Drupal\Core\Render\Element;

/**
 * Provides a form state for Views handler forms.
 */
class ViewsHandlerFormState extends FormStateDecoratorBase {

  use FormStateValuesTrait;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Form\FormStateInterface $decorated_form_state
   *   The decorated form state.
   */
  public function __construct(FormStateInterface $decorated_form_state) {
    $this->decoratedFormState = $decorated_form_state;
  }

  /**
   * {@inheritdoc}
   */
  public function &getValues() {
    return $this->decoratedFormState->getValues();
  }

  /**
   * {@inheritdoc}
   */
  public function setRebuild($rebuild = TRUE) {
    parent::setRebuild($rebuild);
    $this->set('rerender', $rebuild);

    return $this;
  }

}
