<?php

namespace Drupal\copernicus_publication\Form;

use Drupal\copernicus_publication\Services\PublicationService;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;

class UploadPublicationForm extends FormBase {

  /** @var PublicationService */
  protected $publicationService;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  public function __construct(PublicationService $publicationService, ModuleHandlerInterface $moduleHandler) {
    $this->publicationService  = $publicationService;
    $this->moduleHandler = $moduleHandler;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('copernicus_publication.upload_publication'),
      $container->get('module_handler')
    );
  }

  /**
   * @inheritDoc
   */
  public function getFormId() {
    return 'upload-publication-node-form';
  }



  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $form['#prefix'] = '<div id="datacite-fabrica-configuration">';
    $form['#suffix'] = '</div>';
    $form['info'] = [
      0 => [
        '#type' => 'container',
        '#weight' => 0,
        'message' => [
          '#type' => 'markup',
          '#markup' => $this->t("Use this screen to create a new publication.
          You can create the publication in both Drupal and DataCite Fabrica at the same time.</br>
          If you wish to create the publication only in Drupal, use the checkbox below. Upload the metadata file, then attach the PDF file by editing the new node."),
        ],
      ],
    ];
    $form['drupal_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Create the publication only in Drupal"),
    ];
    $form['copernicus_publication_file'] = [
      '#type' => 'managed_file',
      '#title' => t('Upload XML file'),
      '#required' => TRUE,
      '#upload_location' => 'public://upload-publication',
      '#multiple' => FALSE,
      '#default_value' => '',
      '#upload_validators' => [
        'file_validate_extensions' => ['xml']
      ],
    ];
    $form['warning'] = [
      0 => [
        '#type' => 'container',
        '#weight' => 0,
        '#attributes' => [
          'class' => ['messages', 'messages--warning']
        ],
        'message' => [
          '#type' => 'markup',
          '#markup' => $this->t("The XML file must contain at least the following fields:
            <b>DOI, creators, title, publisher, publicationYear, resourceTypeGeneral</b>."),
        ],
      ]
    ];
    $options = [
      'draft' => $this->t('Draft - DOI can be deleted'),
      'publish' => $this->t('Findable - Indexed in DataCite Search (DOI can\'t be deleted)'),
    ];
    $clientPrefixes = $this->publicationService->getClientPrefixes();
    $form['datacite_fabrica'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['panel']
      ],
      '#states' => [
        'invisible' => [
          ':input[name="drupal_only"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('DataCite Fabrica'),
      ],
      'info' => [
        0 => [
          '#type' => 'container',
          '#weight' => 0,
          'message' => [
            '#type' => 'markup',
            '#markup' => $this->t("When the publication is created in DataCite it will have the DOI URL set to Drupal node (i.e. /node/756)"),
          ],
        ],
      ],
      'doi_state' => [
        '#type' => 'radios',
        '#title' => t('DOI State in DataCite Fabrica'),
        '#default_value' => 'draft',
        '#options' => $options,
      ],
      'doi_id' => [
        '#type' => 'container',
        'doi_prefix' => [
          '#type' => 'select',
          '#title' => t('Prefix'),
          '#default_value' => reset($clientPrefixes),
          '#options' => $clientPrefixes,
          '#required' => TRUE,
        ],
        'doi_suffix' => [
          '#type' => 'hidden',
        ],
      ],
      'user' => [
        '#type' => 'textfield',
        '#disabled' => TRUE,
        '#default_value' => $this->publicationService->getRepositoryId(),
        '#title' => $this->t('Repository'),
        '#title_display' => 'before',
        '#size' => 25,
      ],
      'password' => [
        '#type' => 'password',
        '#title' => $this->t('Password'),
        '#size' => 25,
        '#states'=> [
          'required' => [
            ':input[name="drupal_only"]' => ['checked' => FALSE],
          ],
        ],
      ],
      'message' => [
        '#type' => 'markup',
        '#prefix' => $this->t('<h2>How it works</h2>'),
        '#markup' => $this->t("<b>Upload the BibTeX XML file without
the DOI URL filled in because it will be automatically generated during the upload. After the node is created,
you will manually attach the PDF file to the newly created publication node.</b>"),
      ],
      1 => [
        '#type' => 'container',
        '#weight' => 3,
        'image' => [
          '#theme' => 'image',
          '#uri' => $this->moduleHandler->getModule('copernicus_publication')->getPath() . '/img/ecmwf.png',
          '#width' => 500,
        ],
      ],
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create publication'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $drupal_only = $form_state->getValue('drupal_only');
    $fid = reset($form_state->getValue('copernicus_publication_file'));
    $file = File::load($fid);
    $path = $file->getFileUri();
    if (!$drupal_only) {
      $state = $form_state->getValue('doi_state');
      $pass = $form_state->getValues()['password'];
      $this->publicationService->setAuthorizationId($pass);
      $this->publicationService->setState($state);
      $this->publicationService->setPrefix($form_state->getValue('doi_prefix'));
      $suffix = !empty($form_state->getValue('doi_suffix')) ? $form_state->getValue('doi_suffix') : $this->publicationService->generateSuffix();
      $this->publicationService->setSuffix($suffix);
    }
    $this->publicationService->setResourceFromXML($path);

    if (!$this->publicationService->validXML) {
      return;
    }
    // If "Create the publication only in Drupal" is checked, create only the node
    if ($drupal_only) {
      $this->publicationService->createNode();
      return;
    }
    // Try to create DOI to ensure the XML is correct.
    $response = $this->publicationService->createDoi();
    switch ($response) {
      case PublicationService::DOI_EXIST:
      case PublicationService::ERROR_RESPONSE_UNAUTHORIZED:
        return;
      case PublicationService::DOI_ERROR:
        \Drupal::messenger()->addError('Node was not created.');
        return;
    }
    if ($this->publicationService->createNode()) {
      // Update DOI to add Url to node.
      $this->publicationService->updateDoiUrl();
    }
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getValue('drupal_only')) {

      $prefix = $form_state->getValue('doi_prefix');
      if (!$prefix) {
        $form_state->setErrorByName('doi_prefix', $this->t('DOI prefix is required!'));
      }
      $suffix = $form_state->getUserInput()['doi_suffix'];
      $doiId = "{$prefix}/{$suffix}";
      $response = $this->publicationService->getActivityForDoi($doiId);
      if ($response->data) {
        $message = 'You tried to create a DOI but the suffix you chose is already used for this prefix.';
        $form_state->setErrorByName('doi_suffix', $message);
      }
    }
  }

  /**
   * Ajax callback for a new DOI Suffix.
   */
  public function doiSuffixSuggestionCallback(array &$form, FormStateInterface $form_state) {
    $suffix = $form_state->getUserInput()['doi_suffix'] ?? $this->publicationService->generateSuffix();
    $form_state->setValue('doi_suffix', $suffix);

    return $form;
  }
}
