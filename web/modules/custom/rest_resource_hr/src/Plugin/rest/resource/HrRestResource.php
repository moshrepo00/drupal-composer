<?php

namespace Drupal\rest_resource_hr\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\paragraphs\Entity\Paragraph;


/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "hr_rest_resource",
 *   label = @Translation("Hr rest resource"),
 *   uri_paths = {
 *     "canonical" = "/hr_rest_api/hr_resource"
 *   }
 * )
 */
class HrRestResource extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new HrRestResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest_resource_hr'),
      $container->get('current_user')
    );
  }

  /**
   * Responds to GET requests.
   *
   * @param string $payload
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function get($payload) {

    // You must to implement the logic of your REST Resource here.
    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }
//      return new ResourceResponse($payload, 200);

// Create single new paragraph

    $build = array(
      '#cache' => array(
        'max-age' => 0,
      ),
    );

    $user = \Drupal\user\Entity\User::load(1);


    $query = \Drupal::request()->query->get('type');

    $termData   = [];
    $pData      = [];
    $leaveNew   = [];
    $paragraphs = $user->field_leave->getValue();
    if ($query === 'categories') {

      $leaveall = \Drupal::entityTypeManager()
        ->getStorage('paragraph')->load('leave');

      foreach ($leaveall as $p) {
        // $p       = \Drupal\paragraphs\Entity\Paragraph::load($item['target_id']);
        $leaveNew[] = [
          'name' => $p->get('field_message'),
          'selectedDate' => $p->get('field_leave_category')
            ->get(0)
            ->get('entity')
            ->getString()
        ];
      }

      foreach ($paragraphs as $item) {
        $p = \Drupal\paragraphs\Entity\Paragraph::load($item['target_id']);


        $pData[] = [
          'name' => $p->get('field_message'),
          'selectedCategory' => $p->get('field_leave_category')
            ->get(0)
            ->get('entity')
            ->getString(),
          'startDate' => $p->get('field_start_date'),
          'endDate' => $p->get('field_end_date'),
          'target_id' => $p->id()
        ];
      }

      $vid   = 'leave_categories';
      $terms = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->loadTree($vid);
      foreach ($terms as $term) {
        $termData[] = array(
          "id" => $term->tid,
          "name" => $term->name
        );
      }
    }
    else {
      if ($query === 'all') {

        $start      = \Drupal::request()->query->get('start');
        $end        = \Drupal::request()->query->get('end');
        $taxonomyId = \Drupal::request()->query->get('taxonomy');


        $paragraphNew     = [];
        $paragraphCreated = Paragraph::create([
          'type' => 'leave',
          'field_leave_category' => ['target_id' => $taxonomyId],
          'field_start_date' => $start,
          'field_end_date' => $end,
        ]);
        $paragraphCreated->save();

        foreach ($paragraphs as $item) {
          $p = \Drupal\paragraphs\Entity\Paragraph::load($item['target_id']);


          $paragraphNew[] = [
            'target_id' => $p->id(),
            'target_revision_id' => $p->getRevisionId(),
          ];

        }
        $paragraphNew[] = [
          'target_id' => $paragraphCreated->id(),
          'target_revision_id' => $paragraphCreated->getRevisionId(),
        ];

        $user->set('field_leave', $paragraphNew);
        $user->save();
      }
      else {
        if ($query === 'delete') {
          $pid = \Drupal::request()->query->get('pid');

          $paragraphsUpdated = [];


          foreach ($paragraphs as $item) {
            $p = \Drupal\paragraphs\Entity\Paragraph::load($item['target_id']);

            if ($p->id() != $pid) {
              $paragraphsUpdated[] = [
                'target_id' => $p->id(),
                'target_revision_id' => $p->getRevisionId(),
              ];
            }

          }
          $user->set('field_leave', $paragraphsUpdated);
          $user->save();

          $paragraphs = $user->field_leave->getValue();

          $pData = [];

          foreach ($paragraphs as $item) {
            $p = \Drupal\paragraphs\Entity\Paragraph::load($item['target_id']);


            $pData[] = [
              'name' => $p->get('field_message'),
              'selectedCategory' => $p->get('field_leave_category')
                ->get(0)
                ->get('entity')
                ->getString(),
              'startDate' => $p->get('field_start_date'),
              'endDate' => $p->get('field_end_date'),
              'target_id' => $p->id()
            ];
          }


        }
      }
    }

    if ($query != 'delete') {
      $response = [
        'message' => 'Hello, this is a restkljlkj service',
        'user' => $this->currentUser->getAccountName(),
        'query' => $query,
        'terms' => $termData,
        'leaveUserList' => $pData,
        'leaveNew' => $leaveNew,
        'para' => $paragraphNew
      ];
    }
    else {
      $response = [
        'updated' => $pData
      ];
    }

    $build = array(
      '#cache' => array(
        'max-age' => 0,
      ),
    );

    return (new ResourceResponse($response))->addCacheableDependency($build);

  }

}
