<?php

namespace Drupal\apigee_edge\Entity\Query;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\Query\ConditionBase;
use Drupal\Core\Entity\Query\ConditionInterface;
use Drupal\Core\Entity\Query\QueryException;

/**
 * Defines the condition class for the edge entity query.
 */
class Condition extends ConditionBase implements ConditionInterface {

  /**
   * {@inheritdoc}
   */
  public function exists($field, $langcode = NULL) {
    return $this->condition($field, NULL, 'IS NOT NULL', $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function notExists($field, $langcode = NULL) {
    return $this->condition($field, NULL, 'IS NULL', $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function compile($query) {
    if (empty($this->conditions)) {
      return function () {
        return TRUE;
      };
    }

    // This closure will fold the conditions into a single closure if there are
    // more than one, depending on the conjunction.
    $fold = strtoupper($this->conjunction) === 'AND' ?
      function (array $filters) : callable {
        return function ($item) use ($filters) : bool {
          foreach ($filters as $filter) {
            if (!$filter($item)) {
              return FALSE;
            }
          }
          return TRUE;
        };
      } :
      function (array $filters) : callable {
        return function ($item) use ($filters) : bool {
          foreach ($filters as $filter) {
            if ($filter($item)) {
              return TRUE;
            }
          }

          return FALSE;
        };
      };

    $filters = [];
    foreach ($this->conditions as $condition) {
      // If the field is a condition object, compile it and add it to the
      // filters.
      if ($condition['field'] instanceof ConditionInterface) {
        $filters[] = $condition['field']->compile($query);
      }
      else {
        // Set the default operator if it is not set.
        if (!isset($condition['operator'])) {
          $condition['operator'] = is_array($condition['value']) ? 'IN' : '=';
        }

        // Normalize the value to lower case.
        if (is_array($condition['value'])) {
          $condition['value'] = array_map([Unicode::class, 'strtolower'], $condition['value']);
        }
        elseif (!is_bool($condition['value'])) {
          $condition['value'] = Unicode::strtolower($condition['value']);
        }

        $filters[] = static::matchProperty($condition);
      }
    }

    // Only fold in case of multiple filters.
    return count($filters) > 1 ? $fold($filters) : reset($filters);
  }

  /**
   * Creates a filter closure that matches a property.
   *
   * @param array $condition
   *   Condition structure.
   *
   * @return callable
   *   Filter function.
   */
  protected static function matchProperty(array $condition) : callable {
    return function ($item) use ($condition) : bool {
      $value = static::getProperty($item, $condition['field']);

      // Exit early in case of IS NULL or IS NOT NULL, because they can also
      // deal with array values.
      if (in_array($condition['operator'], ['IS NULL', 'IS NOT NULL'], TRUE)) {
        $should_be_set = $condition['operator'] === 'IS NOT NULL';
        return $should_be_set === isset($value);
      }

      if (isset($value)) {
        if (!is_bool($value)) {
          $value = Unicode::strtolower($value);
        }

        switch ($condition['operator']) {
          case '=':
            return $value == $condition['value'];

          case '>':
            return $value > $condition['value'];

          case '<':
            return $value < $condition['value'];

          case '>=':
            return $value >= $condition['value'];

          case '<=':
            return $value <= $condition['value'];

          case '<>':
            return $value != $condition['value'];

          case 'IN':
            return array_search($value, $condition['value']) !== FALSE;

          case 'NOT IN':
            return array_search($value, $condition['value']) === FALSE;

          case 'STARTS_WITH':
            return strpos($value, $condition['value']) === 0;

          case 'CONTAINS':
            return strpos($value, $condition['value']) !== FALSE;

          case 'ENDS_WITH':
            return substr($value, -strlen($condition['value'])) === (string) $condition['value'];

          default:
            throw new QueryException('Invalid condition operator.');
        }
      }

      return FALSE;
    };
  }

  /**
   * Gets a property from an object.
   *
   * To do this, the function tries to guess the name of the getter.
   *
   * @param object $item
   *   Source object.
   * @param string $property
   *   Property name.
   *
   * @return mixed|null
   *   Property value or NULL if not found.
   */
  public static function getProperty($item, string $property) {
    $normalized = ucfirst(implode('', array_map('ucfirst', explode('_', $property))));
    $getter_candidates = [
      "is{$normalized}",
      "get{$normalized}",
      $normalized,
    ];

    foreach ($getter_candidates as $getter) {
      if (method_exists($item, $getter)) {
        return call_user_func([$item, $getter]);
      }
    }

    return NULL;
  }

}