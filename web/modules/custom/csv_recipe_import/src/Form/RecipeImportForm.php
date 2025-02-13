<?php
namespace Drupal\csv_recipe_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\taxonomy\Entity\Term;


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

/*public function processRecipe($data) {
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
}*/
  public function processRecipe($data) {
    if (count($data) < 6) {
      \Drupal::logger('csv_recipe_import')->warning('Skipping a row due to insufficient data.');
      return;
    }

    // Map CSV data to variables
    [$title, $description, $image_url, $category_name, $ingredients, $preparation] = $data;

    // Fetch or create the taxonomy term based on category name (category_name)
    $term = $this->getOrCreateCategoryTerm($category_name);

    // Fetch and save the image
    $file = $this->fetchAndSaveImage($image_url);

    // Create the recipe node
    $node = Node::create([
      'type' => 'recette',
      'title' => $title, // Using CSV "Title" for node title
      'body' => ['value' => $description, 'format' => 'full_html'], // Description
      'field_preparation' => ['value' => $preparation, 'format' => 'full_html'], // Preparation
      'field_ingredients' => ['value' => $ingredients, 'format' => 'full_html'], // Ingredients
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
    \Drupal::logger('csv_recipe_import')->notice('Successfully imported recipe: @title', ['@title' => $title]);
  }

  public function getOrCreateCategoryTerm($category_name) {
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

<!--to test here is a csv exemple : Title,Description,Image URL,Catégorie,Ingrédients,Préparation
"Crêpe","On prend autant de plaisir à les faire sauter qu’à les manger. Avec ou sans bolée de cidre, la crêpe se plie toujours en 4 pour nous régaler ! Si elle se savoure de façon incontournable à la chandeleur, elle s’invite aussi lors de toutes les occasions festives. Du goûter à l’anniversaire en passant par la fameuse « crêpes party », personne ne lui résiste. Pour une recette de pâte à crêpes facile garantie zéro grumeau, c’est par ici !","https://img.cuisineaz.com/660x495/2013/12/20/i27173-marbre-au-chocolat.webp","Desserts","Farine 200 g, Lait 0.5 l, Sucre vanillé 2 paquet(s), Levure en poudre 5 g, Beurre, Sel 1 pincée(s), Fruit(s) frais, Sucre","Étape 1: Mélangez la farine, la levure, le sel et le sucre. Étape 2: Creusez un puits. Étape 3: Ajoutez le lait progressivement, en fouettant. Étape 4: Ajoutez les œufs et fouettez jusqu'à obtenir une pâte homogène. Étape 5: Faites cuire dans une poêle huilée. Étape 6: Retournez les crêpes et laissez dorer. Étape 7: Faites glisser sur une assiette et passez à la suivante. Étape 8: Saupoudrez légèrement de sucre."

-->
