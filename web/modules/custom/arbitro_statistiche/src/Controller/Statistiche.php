<?php

namespace Drupal\arbitro_statistiche\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\node\Entity\Node;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Controller per la pagina delle statistiche arbitri.
 */
class Statistiche extends ControllerBase {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  public function content(Request $request) {
    $selected = $request->query->get('arbitro');
    $options = $this->getArbitriOptions();

    $form = [
      '#type' => 'container',
      '#attributes' => ['class' => ['arbitri-stats-select']],
      'select' => [
        '#type' => 'select',
        '#title' => $this->t('Seleziona Arbitro'),
        '#options' => $options,
        '#empty_option' => $this->t('- Scegli un arbitro -'),
        '#default_value' => $selected,
        '#attributes' => [
          'onchange' => 'if (this.value) window.location="?arbitro=" + this.value;',
        ],
      ],
    ];
    //recupera dall'url il parametro arbitro -> parametro GET
        $selected = $request->query->get('arbitro');
        $stat = [];
    if ($selected) {
      $stat = $this->getStatsForArbitro($selected);
    }

    $build['select_form'] = $form;
    dump($stat);

    return [
      '#theme' => 'arbitri_stats_page',
      '#content' => $build,
      '#stat' => $stat
    ];
  }

  /**
   * Restituisce l’elenco degli arbitri come opzioni della select.
   */
  private function getArbitriOptions() {
   $query = $this->database->select('users_field_data', 'u');
  $query->fields('u', ['uid', 'name']);
  $query->join('user__roles', 'r', 'u.uid = r.entity_id');
  $query->condition('r.roles_target_id', 'arbitro');
  $query->condition('u.status', 1);
  $query->orderBy('u.name', 'ASC');

  $result = $query->execute()->fetchAll();

  $options = [];
  foreach ($result as $record) {
    $options[$record->uid] = $record->name;
  }

  return $options;
  }


/**
   * Estrae le statistiche dai match associati a un arbitro.
   */
 private function getStatsForArbitro($arbitro_nid) {
    // Qui dipende da come è fatto il tuo content type "match"
    // Supponiamo che ci sia un campo entity reference “field_arbitro”
    // che punta al nodo dell’arbitro.

      $query = $this->database->select('node_field_data', 'n');
      $query->addField('n', 'nid');
      $query->condition('n.type', 'partite');

  // Join con il campo entity reference verso l’arbitro.
    $query->join('node__field_arbitro', 'a', 'a.entity_id = n.nid');
    $query->condition('a.field_arbitro_target_id', $arbitro_nid);
    //recuperiamo i vari field
    $query->leftJoin('node__field_rigori', 'r', 'r.entity_id = n.nid');
    $query->addField('r', 'field_rigori_value');

    //KM percorsi
     $query->leftJoin('node__field_km_percorsi', 'k', 'k.entity_id = n.nid');
    $query->addField('k', 'field_km_percorsi_value');

// Falli fischiati
$query->leftJoin('node__field_falli_fischiati', 'f', 'f.entity_id = n.nid');
    $query->addField('f', 'field_falli_fischiati_value');

// fuorigioco fischiati
$query->leftJoin('node__field_fuorigiochi_fischiati', 'o', 'o.entity_id = n.nid');
    $query->addField('o', 'field_fuorigiochi_fischiati_value');

      // Join con paragraphs cartellini
  $query->leftJoin('node__field_totale_cartellini', 'nc', 'nc.entity_id = n.nid');
  $query->leftJoin('paragraph__field_cartellini', 'ct', 'ct.entity_id = nc.field_totale_cartellini_target_id');
  $query->leftJoin('paragraph__field_totale_cartellini', 'tt', 'tt.entity_id = nc.field_totale_cartellini_target_id');

  $query->addField('ct', 'field_cartellini_value');
  $query->addField('tt', 'field_totale_cartellini_value');

     $result = $query->execute()->fetchAll();

$unificati = [];

foreach ($result as $row) {
  $nid = $row->nid;

  // Se non esiste ancora la voce per questo nid, inizializzala.
  if (!isset($unificati[$nid])) {
    $unificati[$nid] = [
      'nid' => $nid,
      'field_rigori_value' => (int) $row->field_rigori_value,
      'field_km_percorsi_value' => (float) $row->field_km_percorsi_value,
      'field_falli_fischiati_value' => (int) $row->field_falli_fischiati_value,
      'field_fuorigiochi_fischiati_value' => (int) $row->field_fuorigiochi_fischiati_value,
      'cartellini_gialli' => 0,
      'cartellini_rossi' => 0,
    ];
  }

  // Gestione del tipo di cartellino
  if ($row->field_cartellini_value === 'gialli') {
    $unificati[$nid]['cartellini_gialli'] += (int) $row->field_totale_cartellini_value;
  } elseif ($row->field_cartellini_value === 'rossi') {
    $unificati[$nid]['cartellini_rossi'] += (int) $row->field_totale_cartellini_value;
  }
}

// Se vuoi ottenere un array semplice (senza chiave nid)
$unificati = array_values($unificati);

// Debug
\Drupal::logger('arbitro_statistiche')->notice('<pre>@data</pre>', ['@data' => print_r($unificati, TRUE)]);


$aggregati = [];

foreach ($result as $row) {
  $nid = $row->nid;

  if (!isset($aggregati[$nid])) {
    $aggregati[$nid] = [
      'nid' => $nid,
      'rigori' => [],
      'km' => [],
      'falli' => [],
      'fuorigiochi' => [],
      'cartellini_gialli' => 0,
      'cartellini_rossi' => 0,
    ];
  }

  // Accumulo dei valori per calcolare la media dopo
  $aggregati[$nid]['rigori'][] = (int) $row->field_rigori_value;
  $aggregati[$nid]['km'][] = (float) $row->field_km_percorsi_value;
  $aggregati[$nid]['falli'][] = (int) $row->field_falli_fischiati_value;
  $aggregati[$nid]['fuorigiochi'][] = (int) $row->field_fuorigiochi_fischiati_value;

  // Gestione del tipo di cartellino
  if ($row->field_cartellini_value === 'gialli') {
    $aggregati[$nid]['cartellini_gialli'] += (int) $row->field_totale_cartellini_value;
  } elseif ($row->field_cartellini_value === 'rossi') {
    $aggregati[$nid]['cartellini_rossi'] += (int) $row->field_totale_cartellini_value;
  }
}
// Calcolo medie finali
$statistiche = [];
foreach ($aggregati as $nid => $data) {
  $statistiche[] = [
    'nid' => $nid,
    'media_rigori' => round(array_sum($data['rigori']) / count($data['rigori']), 2),
    'media_km' => round(array_sum($data['km']) / count($data['km']), 2),
    'media_falli' => round(array_sum($data['falli']) / count($data['falli']), 2),
    'media_fuorigiochi' => round(array_sum($data['fuorigiochi']) / count($data['fuorigiochi']), 2),
    'cartellini_gialli' => $data['cartellini_gialli'],
    'cartellini_rossi' => $data['cartellini_rossi'],
  ];
}

// Debug temporaneo
\Drupal::logger('arbitro_statistiche')->notice('<pre>@data</pre>', ['@data' => print_r($statistiche, TRUE)]);
  return $statistiche;
  }
}
