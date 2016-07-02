<?php

namespace Drupal\taxonomy_bulk_update\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Url;
use Drupal\taxonomy_bulk_update\TaxonomyBulkUpdateHelper;

/**
 * @file
 * Contains Drupal\taxonomy_bulk_update\Form\TaxonomyUpdateForm.
 */

/**
 * Implements the TaxonomyUpdateForm form controller.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class TaxonomyUpdateForm extends FormBase {

  /**
   * Build the simple form.
   *
   * A build form method constructs an array that defines how markup and
   * other form elements are included in an HTML form.
   *
   * @param array $form
   *   Default form array structure.
   * @param FormStateInterface $form_state
   *   Object containing current form state.
   *
   * @return array
   *   The render array defining the elements of the form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $contentTypes = \Drupal::service('entity.manager')->getStorage('node_type')->loadMultiple();
    $contentTypesList = [];
    foreach ($contentTypes as $contentType) {
      $contentTypesList[$contentType->id()] = $contentType->label();
    }
    $selectedContentType = $form_state->getValue('content_type') == NULL ? key($contentTypesList) : $form_state->getValue('content_type');
    

    $form['content_type'] = array(
      '#type' => 'select',
      '#title' => $this->t('Select Content Type'),
      '#options' => $contentTypesList,
      '#default_value' => $selectedContentType,
      '#ajax' => [
        'callback' => array($this, 'submitContentTypeForm'),
        'progress' => array(
          'type' => 'throbber',
          'message' => t('Loading Taxonomy Fields'),
        ),
      ],
    );
    $form['taxonomy_field'] = array(
      '#type' => 'select',
      '#title' => $this->t('Select Field'),
      '#default_value' => isset($form_state->taxonomy_field) ? $form_state->getValue('taxonomy_field') : '',
      '#options' => TaxonomyBulkUpdateHelper::populateTaxonomyFields($selectedContentType),
      '#ajax' => [
        'callback' => array($this, 'submitTaxonomyFieldForm'),
        'wrapper' => 'taxonomy_element',
        'progress' => array(
          'type' => 'throbber',
          'message' => t('Loading Taxonomy'),
        ),
      ],
      '#prefix' => "<div id = taxonomy_field_element>",
      '#suffix' => "</div>",
    );
    $default_term = array_keys(TaxonomyBulkUpdateHelper::populateTaxonomyFields($selectedContentType));
    $selectedTaxonomyField = $form_state->getValue('taxonomy_field') === NULL ? $default_term[0]:$form_state->getValue('taxonomy_field');
    $form['taxonomy'] = array(
      '#type' => 'select',
      '#multiple' => TRUE,
      '#size' => 15,
      '#title' => $this->t('Select Taxonomy'),
      '#options' => TaxonomyBulkUpdateHelper::populateTaxonomy($selectedContentType, $selectedTaxonomyField),
      '#empty_option' => t('-Select-'),
      '#required' => TRUE,
      '#prefix' => "<div id = taxonomy_element>",
      '#suffix' => "</div>",
    );

   $header = [
      'title' => $this->t('Title'),
    ];

    $itemsPerPager = 50;
    $nodes = TaxonomyBulkUpdateHelper::populateNodes($selectedContentType, $itemsPerPager);
    foreach ($nodes as $value) {
      $node_url = Url::fromRoute('entity.node.canonical', array('node' => $value->nid));
      $node_link = \Drupal::l($value->title, $node_url);
      $options[$value->nid] = ['title' => $node_link];
    }

    $form['nodes_list'] = array(
      '#type' => 'tableselect',
      '#multiple' => TRUE,
      '#js_select' => TRUE,
      '#title' => $this->t('Select Nodes'),
      '#header' => $header,
      '#options' => $options,
      '#prefix' => "<div id = node_element>",
      '#suffix' => "</div>",
    );

    $form['pager'] = [
      '#type' => 'pager',
      '#quantity' => 9,
    ];
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Update Node terms'),
    );

    return $form;
  }

  /**
   * Getter method for Form ID.
   *
   * The form ID is used in implementations of hook_form_alter() to allow other
   * modules to alter the render array built by this form controller.  it must
   * be unique site wide. It normally starts with the providing module's name.
   *
   * @return string
   *   The unique ID of the form defined by this class.
   */
  public function getFormId() {
    return 'taxonomy_bulk_update';
  }

  /**
   * Implements form validation.
   *
   * The validateForm method is the default method called to validate input on
   * a form.
   *
   * @param array $form
   *   The render array of the currently built form.
   * @param FormStateInterface $form_state
   *   Object describing the current state of the form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $nids = array_filter($form_state->getValue('nodes_list'));
    $selectedtaxonomy = $form_state->getValue('taxonomy');
    $taxonomy_field = $form_state->getValue('taxonomy_field');
    $term_update = ['field_name' => $taxonomy_field, 'tid' => $selectedtaxonomy];
    array_walk($nids, 'Drupal\taxonomy_bulk_update\TaxonomyBulkUpdateHelper::updateTaxonomyTerms',$term_update);
  }

  public function submitContentTypeForm(array &$form, FormStateInterface $form_state) {
    $ajax_response = new AjaxResponse();
    $ajax_response->addCommand(new ReplaceCommand('#taxonomy_field_element', $form['taxonomy_field']));
    $ajax_response->addCommand(new ReplaceCommand('#node_element', $form['nodes_list']));
    return $ajax_response;
  }

  
  public function submitTaxonomyFieldForm(array &$form) {
    return $form['taxonomy'];
  }

}
