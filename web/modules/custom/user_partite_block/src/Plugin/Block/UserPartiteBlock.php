<?php

namespace Drupal\user_partite_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Url;

/**
 * Provides a 'User Partite Block' block.
 *
 * @Block(
 *   id = "user_partite_block",
 *   admin_label = @Translation("User Partite Block")
 * )
 */
class UserPartiteBlock extends BlockBase implements ContainerFactoryPluginInterface {

  protected $entityTypeManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * Recupera le partite arbitrate dall'utente.
   */
  private function getPartiteByArbitro($user) {
    if (!$user) {
      return [];
    }

    $uid = $user->id();
    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $nids = $query
      ->condition('type', 'partite')
      ->condition('field_arbitro', $uid)
      ->accessCheck(FALSE)
      ->execute();

    return $nids ? $this->entityTypeManager->getStorage('node')->loadMultiple($nids) : [];
  }

  /**
   * Renderizza le statistiche delle partite.
   */
  private function renderStats(array $nodes) {
    $cartellini = [];
    $tot_falli = 0;
    $tot_fuorigiochi = 0;
    $tot_km = 0;
    $tot_rigori = 0;
    $partite_totali = count($nodes);

    foreach ($nodes as $node) {
      if (!$node instanceof NodeInterface) continue;

      $tot_falli += $node->hasField('field_falli_fischiati') && !$node->get('field_falli_fischiati')->isEmpty()
        ? (int) $node->get('field_falli_fischiati')->value
        : 0;

      $tot_fuorigiochi += $node->hasField('field_fuorigiochi_fischiati') && !$node->get('field_fuorigiochi_fischiati')->isEmpty()
        ? (int) $node->get('field_fuorigiochi_fischiati')->value
        : 0;

      $tot_km += $node->hasField('field_km_percorsi') && !$node->get('field_km_percorsi')->isEmpty()
        ? (float) $node->get('field_km_percorsi')->value
        : 0;

      $tot_rigori += $node->hasField('field_rigori') && !$node->get('field_rigori')->isEmpty()
        ? (int) $node->get('field_rigori')->value
        : 0;

      if ($node->hasField('field_totale_cartellini') && !$node->get('field_totale_cartellini')->isEmpty()) {
        $paragraphs = $node->get('field_totale_cartellini')->referencedEntities() ?? [];
        foreach ($paragraphs as $paragraph) {
          if ($paragraph instanceof Paragraph) {
            $colore = strtolower(trim($paragraph->get('field_cartellini')->value ?? 'N/D'));
            $numero = (int) ($paragraph->get('field_totale_cartellini')->value ?? 0);
            $cartellini[$colore] = ($cartellini[$colore] ?? 0) + $numero;
          }
        }
      }
    }

    // Calcola le medie
    $medie_cartellini = [];
    foreach ($cartellini as $colore => $totale) {
      $media = $partite_totali > 0 ? round($totale / $partite_totali, 2) : 0;
      $medie_cartellini[$colore] = [
        'totale' => $totale,
        'media' => $media,
      ];
    }

    $media_falli = $partite_totali > 0 ? round($tot_falli / $partite_totali, 2) : 0;
    $media_fuori = $partite_totali > 0 ? round($tot_fuorigiochi / $partite_totali, 2) : 0;
    $media_km = $partite_totali > 0 ? round($tot_km / $partite_totali, 2) : 0;
    $media_rigori = $partite_totali > 0 ? round($tot_rigori / $partite_totali, 2) : 0;

    // Costruisci la tabella Drupal
    $header = ['Statistica', 'Totale', 'Media per partita'];
    $rows = [];

    $rows[] = ['Partite arbitrate', $partite_totali, '-'];
    foreach ($medie_cartellini as $colore => $dati) {
      $rows[] = ['Cartellini ' . ucfirst($colore), $dati['totale'], $dati['media']];
    }
    $rows[] = ['Falli fischiati', $tot_falli, $media_falli];
    $rows[] = ['Fuorigioco fischiati', $tot_fuorigiochi, $media_fuori];
    $rows[] = ['Km percorsi', $tot_km, $media_km];
    $rows[] = ['Rigori', $tot_rigori, $media_rigori];

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => ['class' => ['user-partite-stats']],
      '#empty' => $this->t('Nessuna statistica disponibile.'),
    ];
  }

  public function build() {
    $request = \Drupal::request();
    $arbitro_id = explode('/', $request->getPathInfo())[2];

    // Carica tutti gli arbitri
    $arbitri = $this->entityTypeManager->getStorage('user')->loadByProperties(['roles' => ['arbitro']]);
    if (empty($arbitri)) {
      return ['#markup' => $this->t('Nessun arbitro trovato.')];
    }

    $user = $arbitro_id && array_key_exists($arbitro_id, $arbitri) ? $arbitri[$arbitro_id] : NULL;

    // Costruisci la select
    $options = [];
    foreach ($arbitri as $a) {
      $options[$a->id()] = $a->getDisplayName();
    }

    // $form = [
    //   '#type' => 'form',
    //   '#method' => 'get',
    //   '#action' => Url::fromUri('internal:/statistiche-arbitri')->toString(),
    // ];

    // $form['arbitro'] = [
    //   '#type' => 'select',
    //   '#title' => $this->t('Seleziona un arbitro'),
    //   '#options' => $options,
    //   '#default_value' => $arbitro_id,
    // ];

    // $form['submit'] = [
    //   '#type' => 'submit',
    //   '#value' => $this->t('Mostra statistiche'),
    // ];

    if ($user) {
      $nodes = $this->getPartiteByArbitro($user);
      $form['stats'] = $this->renderStats($nodes);
    }

    return $form;
  }
}
