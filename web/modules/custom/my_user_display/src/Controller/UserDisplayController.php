<?php

namespace Drupal\my_user_display\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller che mostra info sull’utente loggato e calcola distanze.
 */
class UserDisplayController extends ControllerBase {

  /**
   * L'utente corrente.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Costruttore.
   */
  public function __construct(AccountInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * Iniezione del servizio.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user')
    );
  }

  /**
   * Mostra il nome utente loggato.
   */
  public function displayUser() {
    if ($this->currentUser->isAuthenticated()) {
      $username = $this->currentUser->getDisplayName();
      $markup = "<p>Ciao <strong>{$username}</strong>! 👋</p>";
    } else {
      $markup = "<p>Non sei loggato. <a href='/user/login'>Accedi qui</a>.</p>";
    }

    return [
      '#type' => 'markup',
      '#markup' => $markup,
    ];
  }

  /**
   * Calcola la distanza tra due coordinate (in km).
   */
  public function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    // Formula di Haversine.
    $earthRadius = 6371; // raggio medio terrestre in km

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    $distance = $earthRadius * $c;

    // Recupera nome utente loggato (se presente).
    $username = $this->currentUser->isAuthenticated() ? $this->currentUser->getDisplayName() : 'ospite';

    return [
      '#type' => 'markup',
      '#markup' => "<p>Ciao <strong>{$username}</strong>! La distanza tra i due punti è di <strong>" . round($distance, 2) . " km</strong>.</p>",
    ];
  }
}
