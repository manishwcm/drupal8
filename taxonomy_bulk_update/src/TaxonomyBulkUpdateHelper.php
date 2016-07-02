<?php

namespace Drupal\taxonomy_bulk_update;

use Drupal\node\Entity\Node;
use Drupal\Core\Url;

class TaxonomyBulkUpdateHelper{
  /*
   * Updates taxonomy terms.
   */
  
  /**
   * Function to return list of taxonomy terms.
   * @param type $selectedContentType
   * @return type
   */
  public static function populateTaxonomyFields($selectedContentType) {
    $taxonomy_field = array();
    $bundle_fields = \Drupal::entityManager()->getFieldDefinitions('node', $selectedContentType);
    foreach ($bundle_fields as $key => $value) {
      if ($value->getType() == 'entity_reference' && $value->getSetting('target_type') == 'taxonomy_term') {
        $taxonomy_field[$value->getName()] = $value->getLabel();
      }
    }
    if (count($taxonomy_field) > 0) {
      return $taxonomy_field;
    }
    else {
      return array();
    }
  }
  
  /**
   * Function to get taxonomy terms.
   */
  public static function populateTaxonomy($selectedContentType, $selectedTaxonomyField) {
    $vids = array();
    $taxonomy_tree = array();
    $taxonomy = array();
    $bundle_fields = \Drupal::entityManager()->getFieldDefinitions('node', $selectedContentType);
    foreach ($bundle_fields as $key => $value) {
      if ($value->getType() == 'entity_reference' && $value->getSetting('target_type') == 'taxonomy_term' && $value->getName() == $selectedTaxonomyField) {
        $vids = $value->getSetting('handler_settings')['target_bundles'];
      }
    }
    foreach ($vids as $key => $value) {
      $taxonomy_tree[$value] = \Drupal::entityManager()->getStorage('taxonomy_term')->loadTree($value, 0);
    }
    foreach ($taxonomy_tree as $key => $taxonomy_vocab_tree) {
      $taxonomy[$key] = "Vocabulary " . $key;
      $prefix = '';
      foreach ($taxonomy_vocab_tree as $key => $value) {
        $depth = $value->depth;
        $prefix = '';
        for ($i = 0; $i < $depth; $i++) {
          $prefix = $prefix . '-';
        }
        $taxonomy[$value->tid] = $prefix . $value->name;
      }
    }
    return $taxonomy;
  }
  
  /**
   * Function to get node list.
   */
  public static function populateNodes($selectedContentType, $itemsPerPager) {
    $result = db_select('node_field_data', 'nfd')
        ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
        ->fields('nfd', array('title', 'nid'))
        ->condition('type', $selectedContentType)
        ->condition('status', 1)
        ->limit($itemsPerPager)
        ->orderBy('nfd.nid')
        ->execute()
        ->fetchAll();
    return $result;
  }

  /**
   * Function to update taxonomy terms.
   */
  public static function updateTaxonomyTerms($key, $value, $term_update) {
    $node = Node::load($value);
    $node_terms = $node->get($term_update['field_name'])->getValue();
    foreach ($term_update['tid'] as $value) {
      $term_id = ['target_id' => $value];
      array_push($node_terms, $term_id);
    }
    $unique_terms = array();
    foreach ($node_terms as $key => $term) {
      if (!in_array($term['target_id'], $unique_terms)) {
        $unique_terms[$key] = $term['target_id'];
      }
      else {
        unset($node_terms[$key]);
      }
    }
    $node->$term_update['field_name'] = $unique_terms;
    $node->save();
  }

}