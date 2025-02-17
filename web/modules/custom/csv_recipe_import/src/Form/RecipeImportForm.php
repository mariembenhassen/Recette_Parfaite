<?php

namespace Drupal\csv_recipe_import\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\node\Entity\Node;
use Drupal\path_alias\AliasManagerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This class allows to import recettes.
 */
class RecipeImportForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'recipe_import_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['csv_recipe_import.settings'];
  }

  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The path validator.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * The request context.
   *
   * @var \Drupal\Core\Routing\RequestContext
   */
  protected $requestContext;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The entity query service.
   *
   * @var \Drupal\Core\Entity\Query\Sql\QueryFactory
   */
  protected $entityQuery;
  /**
   * The taxonomy term storage.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  protected $termStorage;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructs a RecipeImportForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The path alias manager.
   * @param \Drupal\Core\Path\PathValidatorInterface $path_validator
   *   The path validator.
   * @param \Drupal\Core\Routing\RequestContext $request_context
   *   The request context.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file handler.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   A Guzzle client object.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    AliasManagerInterface $alias_manager,
    PathValidatorInterface $path_validator,
    RequestContext $request_context,
    EntityTypeManagerInterface $entity_type_manager,
    FileSystemInterface $file_system,
    LoggerChannelInterface $logger,
    ClientInterface $http_client,
    MessengerInterface $messenger,
  ) {

    parent::__construct($config_factory, $typedConfigManager);
    $this->aliasManager = $alias_manager;
    $this->pathValidator = $path_validator;
    $this->requestContext = $request_context;
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
    $this->logger = $logger;
    $this->httpClient = $http_client;
    $this->messenger = $messenger;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('path_alias.manager'),
      $container->get('path.validator'),
      $container->get('router.request_context'),
      $container->get('entity_type.manager'),
      $container->get('file_system'),
      $container->get('logger.channel.default'),
      $container->get('http_client'),
      $container->get('messenger')

    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('csv_recipe_import.settings');
    $last_uploaded_file = $config->get('last_uploaded_file');

    $form['csv_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload CSV File'),
      '#description' => $this->t('Upload a CSV file with Recipe data: Title, Description, Preparation, Image URL.'),
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ],
      '#upload_location' => 'public://csv_uploads/',
      '#required' => TRUE,
    ];

    if ($last_uploaded_file) {
      $file_storage = $this->entityTypeManager->getStorage('file');
      $file = $file_storage->load($last_uploaded_file);
      if ($file) {
        // Set the default value to the last uploaded file.
        $form['csv_file']['#default_value'] = [$file->id()];
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Remove default button.
   */
  public function actions(array $form, FormStateInterface $form_state) {
    return [
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Import Recipes'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save the configuration settings before processing the file.
    $this->config('csv_recipe_import.settings')
      ->save();

    $this->messenger()->addStatus($this->t('Configuration saved successfully.'));

    // Process file upload.
    $file_ids = $form_state->getValue('csv_file');

    if (empty($file_ids)) {
      $this->messenger()->addError($this->t('No file uploaded.'));
      return;
    }

    $file_storage = $this->entityTypeManager->getStorage('file');
    $file = $file_storage->load(reset($file_ids));

    if (!$file) {
      $this->messenger()->addError($this->t('Invalid file upload.'));
      return;
    }

    $file->setPermanent();
    $file->save();

    // Store file ID in configuration.
    $this->config('csv_recipe_import.settings')
      ->set('last_uploaded_file', $file->id())
      ->save();

    $file_path = $this->fileSystem->realpath($file->getFileUri());

    if (($handle = fopen($file_path, 'r')) !== FALSE) {
      $row_index = 0;
      $is_empty = TRUE;
      $batch_operations = [];

      while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
        if ($row_index === 0) {
          $row_index++;
          continue;
        }

        if (count(array_filter($data)) > 0) {
          $is_empty = FALSE;
        }

        $batch_operations[] = [[$this, 'processRecipeBatch'], [$data]];
        $row_index++;
      }

      fclose($handle);

      if ($is_empty) {
        $this->messenger()->addError($this->t('The CSV file is empty.'));
      }
      else {
        $batch = [
          'title' => $this->t('Importing Recipes'),
          'operations' => $batch_operations,
          'finished' => 'Drupal\csv_recipe_import\Form\RecipeImportForm::importFinished',
        ];

        batch_set($batch);
      }
    }
    else {
      $this->messenger()->addError($this->t('Could not open the uploaded CSV file.'));
    }
  }

  /**
   * Batch process.
   *
   * @param array $data
   *   The input data.
   * @param array $context
   *   The context.
   */
  public function processRecipeBatch($data, array &$context) {
    if (!isset($context['results'])) {
      $context['results'] = [];
    }

    if (count($data) < 6) {
      $this->logger('csv_recipe_import')->warning('Skipping a row due to insufficient data.');
      return;
    }

    [$title, $description, $image_url, $category_name, $ingredients, $preparation] = $data;

    $term = $this->getOrCreateCategoryTerm($category_name);
    $file = self::fetchAndSaveImage($image_url);

    $node = Node::create([
      'type' => 'recette',
      'title' => $title,
      'body' => ['value' => $description, 'format' => 'full_html'],
      'field_preparation' => ['value' => $preparation, 'format' => 'full_html'],
      'field_ingredients' => ['value' => $ingredients, 'format' => 'full_html'],
    ]);

    if ($term) {
      $node->set('field_categorie', ['target_id' => $term->id()]);
    }

    if ($file) {
      $node->set('field_image_recette', ['target_id' => $file->id()]);
    }

    $node->save();

    $context['message'] = $this->t('Importing recipe: @title', ['@title' => $title]);
    $context['results'][] = $title;
    $context['total'] = count($context['results']);
  }

  /**
   * Batch finished method.
   *
   * @param bool $success
   *   The success.
   * @param array $results
   *   The results of the batch.
   * @param array $operations
   *   List of operations.
   */
  public function importFinished($success, array $results, array $operations) {
    if ($success) {
      $this->messenger()->addStatus($this->t('CSV file imported successfully with @count recipes.', ['@count' => count($results)]));
    }
    else {
      $this->messenger()->addError($this->t('There was an error importing the CSV file.'));
    }
  }

  /**
   * Category.
   *
   * @param string $category_name
   *   The  name of category.
   *
   * @return \Drupal\Core\Entity\ContentEntityBase|\Drupal\Core\Entity\EntityBase|\Drupal\Core\Entity\EntityInterface|Term|null
   *   load taxonomy if exists , if not then creat it ! .
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown when there is an issue saving the taxonomy term.
   */
  public function getOrCreateCategoryTerm($category_name) {
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $term_ids = $term_storage->getQuery()
      ->condition('vid', 'les_categories_de_recettes')
      ->accessCheck(FALSE)
      ->execute();

    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($term_ids);
    $existing_term = NULL;
    foreach ($terms as $term) {
      if ($term->getName() == $category_name) {
        $existing_term = $term;
        break;
      }
    }

    if (!$existing_term) {
      $existing_term = Term::create([
        'vid' => 'les_categories_de_recettes',
        'name' => $category_name,
      ]);
      $existing_term->save();
      $this->logger('csv_recipe_import')->notice('Created new category term: @name', ['@name' => $category_name]);
    }

    return $existing_term;
  }

  /**
   * Fetches an image from a URL.
   *
   * @param string $image_url
   *   The URL of the image to fetch.
   *
   * @return \Drupal\Core\Entity\ContentEntityBase|\Drupal\Core\Entity\EntityBase|\Drupal\Core\Entity\EntityInterface|File|null
   *   The created file successful or NULL if it fails.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown when there is an issue in the saving.
   */
  public function fetchAndSaveImage($image_url) {
    try {

      $http_client = $this->httpClient;
      $response = $http_client->get($image_url);

      if ($response->getStatusCode() !== 200) {
        $this->logger('csv_recipe_import')->error('Failed to download image from @url. Response code: @code', [
          '@url' => $image_url,
          '@code' => $response->getStatusCode(),
        ]);
        return NULL;
      }

      $image_data = $response->getBody()->getContents();
      $file_name = basename(parse_url($image_url, PHP_URL_PATH));
      $directory = 'public://images/';
      $destination = $directory . $file_name;

      $file_system = $this->fileSystem;

      if (!$file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY)) {
        $this->logger('csv_recipe_import')->error('Failed to create directory @dir', ['@dir' => $directory]);
        return NULL;
      }

      // Save the image data to the destination.
      $file_path = $file_system->saveData($image_data, $destination, FileSystemInterface::EXISTS_REPLACE);

      if (!$file_path) {
        $this->logger('csv_recipe_import')->error('Failed to save image @file', ['@file' => $destination]);
        return NULL;
      }
      $file_storage = $this->entityTypeManager->getStorage('file');
      $file = $file_storage->create([
        'uri' => $file_path,
        'status' => 1,
      ]);
      $file->save();

      return $file;
    }
    catch (RequestException $e) {
      $this->logger('csv_recipe_import')->error('Error while fetching image: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
  }

}
