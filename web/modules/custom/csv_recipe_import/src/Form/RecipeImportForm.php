<?php
namespace Drupal\csv_recipe_import\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\taxonomy\Entity\Term;

class RecipeImportForm extends ConfigFormBase {

public function getFormId() {
return 'recipe_import_form';
}

protected function getEditableConfigNames() {
return ['csv_recipe_import.settings'];
}

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('csv_recipe_import.settings');
    $last_uploaded_file = $config->get('last_uploaded_file');

    if ($last_uploaded_file) {
      $file = File::load($last_uploaded_file);
      if ($file) {
        $file_uri = $file->getFileUri();
        $file_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file_uri);
        $form['last_uploaded_file'] = [
          '#markup' => $this->t('Last uploaded file: <a href="@file" target="_blank">@file</a>', ['@file' => $file_url]),
        ];
      }
    }

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
    return parent::buildForm($form, $form_state);
  }
//remove default button
  public function actions(array $form, FormStateInterface $form_state) {
    return [
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Import Recipes'),
      ],
    ];
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save the configuration settings before processing the file
    $this->config('csv_recipe_import.settings')
      ->save();

    $this->messenger()->addStatus($this->t('Configuration saved successfully.'));

    // Process file upload
    $file_ids = $form_state->getValue('csv_file');

    if (empty($file_ids)) {
      $this->messenger()->addError($this->t('No file uploaded.'));
      return;
    }

    $file = File::load(reset($file_ids));

    if (!$file) {
      $this->messenger()->addError($this->t('Invalid file upload.'));
      return;
    }

    $file->setPermanent();
    $file->save();

    // Store file ID in configuration
    $this->config('csv_recipe_import.settings')
      ->set('last_uploaded_file', $file->id())
      ->save();

    $file_path = \Drupal::service('file_system')->realpath($file->getFileUri());

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
if (!isset($context['results'])) {
$context['results'] = [];
}

if (count($data) < 6) {
\Drupal::logger('csv_recipe_import')->warning('Skipping a row due to insufficient data.');
return;
}

[$title, $description, $image_url, $category_name, $ingredients, $preparation] = $data;

$term = self::getOrCreateCategoryTerm($category_name);
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

$context['message'] = t('Importing recipe: @title', ['@title' => $title]);
$context['results'][] = $title;
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
$terms = \Drupal\taxonomy\Entity\Term::loadMultiple(
\Drupal::entityQuery('taxonomy_term')
->condition('vid', 'les_categories_de_recettes')
->accessCheck(FALSE)
->execute()
);

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
\Drupal::logger('csv_recipe_import')->notice('Created new category term: @name', ['@name' => $category_name]);
}

return $existing_term;
}

public static function fetchAndSaveImage($image_url) {
try {
// Use HttpClient service to fetch the image
$http_client = \Drupal::service('http_client');
$response = $http_client->get($image_url);

if ($response->getStatusCode() !== 200) {
\Drupal::logger('csv_recipe_import')->error('Failed to download image from @url. Response code: @code', [
'@url' => $image_url,
'@code' => $response->getStatusCode(),
]);
return NULL;
}

// Get the image content
$image_data = $response->getBody()->getContents();
$file_name = basename(parse_url($image_url, PHP_URL_PATH));
$directory = 'public://images/';
$destination = $directory . $file_name;

$file_system = \Drupal::service('file_system');

// Ensure the directory exists
if (!$file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY)) {
\Drupal::logger('csv_recipe_import')->error('Failed to create directory @dir', ['@dir' => $directory]);
return NULL;
}

// Save the image data to the destination
$file_path = $file_system->saveData($image_data, $destination, FileSystemInterface::EXISTS_REPLACE);

if (!$file_path) {
\Drupal::logger('csv_recipe_import')->error('Failed to save image @file', ['@file' => $destination]);
return NULL;
}

// Create and save the file entity
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
