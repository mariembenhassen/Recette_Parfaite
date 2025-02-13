<?php
namespace Drupal\csv_recipe_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\File\FileSystemInterface;

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

if (($handle = fopen($file_path, 'r')) !== FALSE) {
$row_index = 0;
while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
if ($row_index === 0) {
// Skip the header row
$row_index++;
continue;
}
$this->processRecipe($data);
$row_index++;
}
fclose($handle);
$this->messenger()->addStatus($this->t('CSV file imported successfully.'));
} else {
$this->messenger()->addError($this->t('Could not open the uploaded CSV file.'));
}
}

public function processRecipe($data) {
if (count($data) < 4) {
\Drupal::logger('csv_recipe_import')->warning('Skipping a row due to insufficient data.');
return;
}

[$title, $description, $preparation, $image_url] = $data;

// Fetch and save the image
$file = $this->fetchAndSaveImage($image_url);

// Create the recipe node
$node = Node::create([
'type' => 'recette',
'title' => $title,
'body' => ['value' => $description, 'format' => 'full_html'],
'field_preparation' => ['value' => $preparation, 'format' => 'full_html'],
]);

if ($file) {
$node->set('field_image_recette', ['target_id' => $file->id()]);
}

$node->save();
\Drupal::logger('csv_recipe_import')->notice('Successfully imported recipe: @title', ['@title' => $title]);
}
  public function fetchAndSaveImage($image_url) {
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
