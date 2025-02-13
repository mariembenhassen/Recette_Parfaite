<?php
namespace Drupal\csv_recipe_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Batch\BatchBuilder;

class RecipeImportForm extends FormBase {

  public function getFormId() {
    return 'recipe_import_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
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

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import Recipes'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $file_ids = $form_state->getValue('csv_file');

    if (empty($file_ids)) {
      $this->messenger()->addError($this->t('No file uploaded.'));
      return;
    }

    $file = File::load(reset($file_ids));  // Get the file entity

    if (!$file) {
      $this->messenger()->addError($this->t('Invalid file upload.'));
      return;
    }

    $file->setPermanent();
    $file->save();

    $file_path = \Drupal::service('file_system')->realpath($file->getFileUri());

    // Open the CSV file
    if (($handle = fopen($file_path, 'r')) !== FALSE) {
      $row_index = 0;
      $is_empty = TRUE; // Variable to track if the file is empty
      $batch_operations = [];

      while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
        // Skip the header row
        if ($row_index === 0) {
          $row_index++;
          continue;
        }

        // If a row contains data, set the flag to false
        if (count(array_filter($data)) > 0) {
          $is_empty = FALSE;
        }

        // Add the row to the batch process
        $batch_operations[] = ['Drupal\csv_recipe_import\Form\RecipeImportForm::processRecipeBatch', [$data]];
        $row_index++;
      }

      fclose($handle);

      if ($is_empty) {
        $this->messenger()->addError($this->t('The CSV file is empty.'));
      } else {
        $batch = [
          'title' => $this->t('Importing Recipes'),
          'operations' => $batch_operations,
          'finished' => 'Drupal\csv_recipe_import\Form\RecipeImportForm::importFinished',
        ];

        batch_set($batch);
      }
    } else {
      $this->messenger()->addError($this->t('Could not open the uploaded CSV file.'));
    }
  }

  public static function processRecipeBatch($data, array &$context) {
    // Ensure that the 'results' key is initialized as an array
    if (!isset($context['results'])) {
      $context['results'] = [];
    }

    if (count($data) < 6) {
      \Drupal::logger('csv_recipe_import')->warning('Skipping a row due to insufficient data.');
      return;
    }

    // Map CSV data to variables
    [$title, $description, $image_url, $category_name, $ingredients, $preparation] = $data;

    $term = self::getOrCreateCategoryTerm($category_name);
    $file = self::fetchAndSaveImage($image_url);


    // Create the recipe node
    $node = Node::create([
      'type' => 'recette',
      'title' => $title,
      'body' => ['value' => $description, 'format' => 'full_html'],
      'field_preparation' => ['value' => $preparation, 'format' => 'full_html'],
      'field_ingredients' => ['value' => $ingredients, 'format' => 'full_html'],
    ]);

    // Set category field if a term is found
    if ($term) {
      $node->set('field_categorie', ['target_id' => $term->id()]);
    }

    // Attach image if a file was found
    if ($file) {
      $node->set('field_image_recette', ['target_id' => $file->id()]);
    }

    // Save the node
    $node->save();

    // Update the batch process progress
    $context['message'] = t('Importing recipe: @title', ['@title' => $title]);
    $context['results'][] = $title; // Add title to the results array

    // Update the total count of processed recipes
    $context['total'] = count($context['results']);
  }

  public static function importFinished($success, array $results, array $operations) {
    if ($success) {
      \Drupal::messenger()->addStatus(t('CSV file imported successfully with @count recipes.', ['@count' => count($results)]));
    } else {
      \Drupal::messenger()->addError(t('There was an error importing the CSV file.'));
    }
  }

  public static function getOrCreateCategoryTerm($category_name) {

    // Load terms in the 'les_categories_de_recettes' vocabulary
    $terms = \Drupal\taxonomy\Entity\Term::loadMultiple(
      \Drupal::entityQuery('taxonomy_term')
        ->condition('vid', 'les_categories_de_recettes') // Filter by vocabulary ID
        ->accessCheck(FALSE) // Disable access check for this query
        ->execute()
    );

    $existing_term = NULL;

    // Search for the term by name
    foreach ($terms as $term) {
      if ($term->getName() == $category_name) {
        $existing_term = $term;
        break;
      }
    }

    // If term doesn't exist, create it
    if (!$existing_term) {
      $existing_term = Term::create([
        'vid' => 'les_categories_de_recettes', // Your vocabulary machine name
        'name' => $category_name,  // Use the category name from the CSV
      ]);
      $existing_term->save();
      \Drupal::logger('csv_recipe_import')->notice('Created new category term: @name', ['@name' => $category_name]);
    }

    return $existing_term;
  }

  public static function fetchAndSaveImage($image_url) {

    try {
      $http_client = \Drupal::service('http_client');
      $response = $http_client->get($image_url);

      if ($response->getStatusCode() !== 200) {
        \Drupal::logger('csv_recipe_import')->error('Failed to download image from @url. Response code: @code', [
          '@url' => $image_url,
          '@code' => $response->getStatusCode()
        ]);
        return NULL;
      }

      $image_data = $response->getBody()->getContents();
      $file_name = basename(parse_url($image_url, PHP_URL_PATH));
      $directory = 'public://images/';  // Ensure this is a string, not a reference
      $destination = $directory . $file_name;

      $file_system = \Drupal::service('file_system');

      // Ensure the directory exists
      if (!$file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY)) {
        \Drupal::logger('csv_recipe_import')->error('Failed to create directory @dir', ['@dir' => $directory]);
        return NULL;
      }

      // Save the file
      $file_path = $file_system->saveData($image_data, $destination, FileSystemInterface::EXISTS_REPLACE);

      if (!$file_path) {
        \Drupal::logger('csv_recipe_import')->error('Failed to save image @file', ['@file' => $destination]);
        return NULL;
      }

      // Create a File entity
      $file = File::create([
        'uri' => $file_path,
        'status' => 1,
      ]);
      $file->save();

      return $file;
    } catch (RequestException $e) {
      \Drupal::logger('csv_recipe_import')->error('Error while fetching image: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
  }
}

?>
