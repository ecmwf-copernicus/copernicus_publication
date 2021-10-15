<?php

namespace Drupal\copernicus_publication\Services;

use Drupal\copernicus_author\Entity\CopernicusAuthor;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use GuzzleHttp\Client;
use linuskohl\phpdatacite\models\AwardNumber;
use linuskohl\phpdatacite\models\Contributor;
use linuskohl\phpdatacite\models\Creator;
use linuskohl\phpdatacite\models\Date;
use linuskohl\phpdatacite\models\Description;
use linuskohl\phpdatacite\models\Format;
use linuskohl\phpdatacite\models\FundingReference;
use linuskohl\phpdatacite\models\geo\GeoLocationBox;
use linuskohl\phpdatacite\models\geo\GeoLocationPoint;
use linuskohl\phpdatacite\models\GeoLocation;
use linuskohl\phpdatacite\models\identifiers\AlternateIdentifier;
use linuskohl\phpdatacite\models\identifiers\FunderIdentifier;
use linuskohl\phpdatacite\models\identifiers\Identifier;
use linuskohl\phpdatacite\models\identifiers\NameIdentifier;
use linuskohl\phpdatacite\models\identifiers\RelatedIdentifier;
use linuskohl\phpdatacite\models\Resource;
use linuskohl\phpdatacite\models\ResourceType;
use linuskohl\phpdatacite\models\Rights;
use linuskohl\phpdatacite\models\Size;
use linuskohl\phpdatacite\models\Subject;
use linuskohl\phpdatacite\models\Title;

/**
 * Publication service.
 */
class PublicationService {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /** @var \Drupal\Core\Entity\EntityStorageInterface */
  protected $authorStorage;

  /** @var Client */
  protected $client;

  /** @var EntityTypeManagerInterface */
  protected $entityTypeManager;

  /** @var \Drupal\Core\Logger\LoggerChannelFactoryInterface */
  protected $loggerFactory;

  /** @var Resource */
  protected $resource;

  /**
   * DOI prefix associated with Repository account.
   *
   * @var string
   */
  protected $prefix;

  /**
   * An uniq DOI suffix.
   *
   * @var string
   */
  protected $suffix;

  /**
   * DOI State. (publish - triggers a state move from draft or registered to
   * findable; register - triggers a state move from draft to registered;
   * hide - triggers a state move from findable to registered).
   *
   * @var string
   */
  protected $state;

  /**
   * ID of a new node. Used to save DOI URL.
   *
   * @var string
   */
  protected $relatedNode;

  /**
   * Set to stop the process if at least an error appear. The process will be
   * stopped after all xml error are catch. True if provided XML is valid.
   *
   * @var bool
   */
  public $validXML = TRUE;

  /**
   * The Repository ID. Contains {MEMBER_ID.REPOSITORY_ID}.
   *
   * @var string
   */
  private $repositoryId;

  /**
   * Repository password.
   *
   * @var string
   */
  private $authorizationId;

  CONST DATACITE_TEST_API = 'https://api.test.datacite.org/';
  CONST DATACITE_API = 'https://api.datacite.org/';
  CONST DATACITE_FABRICA_TEST = 'https://doi.test.datacite.org/';
  CONST DATACITE_FABRICA = 'https://doi.datacite.org/';

  //Endpoints
  CONST REPORTS_ENDPOINT = 'reports';
  CONST PROVIDERS_ENDPOINT = 'providers';
  CONST PREFIXES_ENDPOINT = 'prefixes';
  CONST EVENTS_ENDPOINT = 'events';
  CONST DOIS_ENDPOINT = 'dois';
  CONST CLIENTS_ENDPOINT = 'clients';

  CONST DOI_EXIST = 2;
  CONST DOI_CREATED = 1;
  CONST DOI_ERROR = 0;

  CONST RESPONSE_SUCCESS = 200;
  CONST RESPONSE_SUCCESS_CREATED = 201;
  CONST RESPONSE_NO_CONTENT = 204; // DOI is known to MDS, but is not registered
  CONST ERROR_RESPONSE_UNAUTHORIZED = 401;
  CONST ERROR_RESPONSE_FORBIDDEN = 403;
  CONST ERROR_RESPONSE_NOT_FOUND = 404;
  CONST ERROR_SCHEMA = 422; // wrong schema

  CONST ALLOWED_RESOURCE_TYPE_GENERAL = [
    ResourceType::TYPE_RESOURCE_Audiovisual,
    'Book',
    'Book chapter',
    ResourceType::TYPE_RESOURCE_Collection,
    'Computational notebook',
    'Conference paper',
    'Conference proceeding',
    ResourceType::TYPE_RESOURCE_DataPaper,
    ResourceType::TYPE_RESOURCE_Dataset,
    'Dissertation',
    ResourceType::TYPE_RESOURCE_Event,
    ResourceType::TYPE_RESOURCE_Image,
    ResourceType::TYPE_RESOURCE_InteractiveResource,
    'Journal',
    'Journal article',
    ResourceType::TYPE_RESOURCE_Model,
    'Output management plan',
    ResourceType::TYPE_RESOURCE_Other,
    'Peer review',
    ResourceType::TYPE_RESOURCE_PhysicalObject,
    'Preprint',
    'Report',
    ResourceType::TYPE_RESOURCE_Service,
    ResourceType::TYPE_RESOURCE_Software,
    ResourceType::TYPE_RESOURCE_Sound,
    'Standard',
    ResourceType::TYPE_RESOURCE_Text,
    ResourceType::TYPE_RESOURCE_Workflow,
  ];
  CONST ALLOWED_TITLE_TYPES = [
    Title::TYPE_AlternativeTitle,
    Title::TYPE_Subtitle,
    Title::TYPE_TranslatedTitle,
    Title::TYPE_Other
  ];

  public function __construct(AccountInterface $account, EntityTypeManagerInterface $entityTypeManager, Client $client, LoggerChannelFactoryInterface $loggerFactory) {
    $this->account = $account;
    $this->client = $client;
    $this->loggerFactory = $loggerFactory;
    $this->resource = new Resource();
    $this->entityTypeManager = $entityTypeManager;
    $this->authorStorage = $this->entityTypeManager
      ->getStorage('copernicus_author');
    $this->setRepositoryId();
  }

  protected function getSettings() {
    return Settings::get('copernicus_publication');
  }

  protected function setRepositoryId() {
    $this->repositoryId = $this->getSettings()['datacite_repository_id'];
  }

  public function getRepositoryId(): string {
    return $this->repositoryId;
  }

  /**
   * @param string $authorizationId
   */
  public function setAuthorizationId(string $authorizationId): void {
    $this->authorizationId = base64_encode(sprintf("%s:%s", $this->repositoryId, $authorizationId));
  }

  /**
   * @return string
   */
  public function getAuthorizationId(): string {
    return $this->authorizationId;
  }

  public function getDataCiteUrl(): string {
    return $this->getSettings()['datacite_api'];
  }

  public function getFabricaUrl() {
    return $this->getSettings()['datacite_fabrica'];
  }

  public function getEndpointUrl($endpoint, $id = NULL, array $options = []): string {
    $url = $this->getDataCiteUrl() . $endpoint;
    if ($id) {
      $url = $url . '/' . $id;
      foreach ($options as $option) {
        $url .= sprintf('/%s', $option);
      }
    }
    return $url;
  }

  /**
   * @return \linuskohl\phpdatacite\models\Resource
   */
  public function getResource(): Resource {
    return $this->resource;
  }

  /**
   * @param string $suffix
   */
  public function setSuffix(string $suffix) {
    $this->suffix = $suffix;
  }

  /**
   * @param string $prefix
   */
  public function setPrefix(string $prefix) {
    $this->prefix = $prefix;
  }

  /**
   * @param string $state
   */
  public function setState(string $state) {
    $this->state = $state;
  }

  /**
   * Prepare JSON file according to REST API DataCite. See
   * https://support.datacite.org/docs/api-create-dois.
   */
  public function prepareParameters($operation) {
    $attributes = ($operation == 'POST') ? (object)$this->resource : new \stdClass();
    $attributes->doi = $this->resource->identifier->getIdentifier();
    $attributes->prefix = $this->prefix;
    $attributes->suffix = $this->suffix;
    // Initial, make DOI draft because Url is required for Registered/Findable state.
    $attributes->event = $operation == 'POST' ? 'draft' : $this->state;
    if ($operation == 'PUT') {
      $attributes->url = Url::fromRoute('entity.node.canonical', ['node' => $this->relatedNode], ['absolute' => TRUE])->toString();
    }
    if ($operation == 'POST') {
      // some attributes have wrong names.
      $attributes->types = $this->resource->resourceType;
      if ($attributes->creators) {
        foreach ($attributes->creators as &$creator) {
          $creator->name = $creator->creatorName;
        }
      }
      if ($attributes->contributors) {
        foreach ($attributes->contributors as &$contributor) {
          $contributor->name = $contributor->contributorName;
        }
      }
    }

    $data = new \stdClass();
    $data->id = $this->resource->identifier->getIdentifier();
    $data->type = 'dois';
    if ($operation == 'POST') {
      $data->relationships['client']['data'] = [
        'id' => $this->repositoryId,
        'type' => 'clients',
      ];
    }
    $data->attributes = $attributes;

    $response = new \stdClass();
    $response->data = $data;

    return json_encode($response);
  }

  protected function setIdentifier($values) {
    $doi = (string)$values;
    $doi = strtolower($doi);
    $identifier = new Identifier();
    $identifier->setIdentifierType($this->trim($values->attributes()->identifierType));
    $identifier->setIdentifier($doi);
    $this->resource->setIdentifier($identifier);
    $doiIdentifier = explode('/', $doi);
    $this->setPrefix($doiIdentifier[0]);
    $this->setSuffix($doiIdentifier[1]);
  }

  /**
   * Generate fake suffix. Only for testing purposes.
   */
  public function setFakeIdentifier() {
    $pool = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $suffix = '';
    for ($i = 0; $i < 9; $i++) {
      if ($i == 4) {
        $suffix .= '-';
        continue;
      }
      $position = mt_rand(0, strlen($pool));
      $suffix .= substr($pool, $position, 1);
    }
    $this->setPrefix(10.82044);
    $this->setSuffix($suffix);
    $fakeIdentifier = $this->prefix . '/' . $this->suffix;
    $this->resource->identifier->setIdentifier($fakeIdentifier);
  }

  protected function trim($value): string {
    return trim((string)$value);
  }

  protected function setNameIdentifier($identifier): NameIdentifier {
    $nameIdentifier = new NameIdentifier();
    $nameIdentifier->setNameIdentifier($this->trim($identifier));
    if ($identifier->attributes()->nameIdentifierScheme) {
      $nameIdentifier->setNameIdentifierScheme($this->trim($identifier->attributes()->nameIdentifierScheme));
    }
    if ($identifier->attributes()->schemeURI) {
      $nameIdentifier->setSchemeURI($this->trim($identifier->attributes()->schemeURI));
    }
    return $nameIdentifier;
  }
  protected function setCreators($values) {
    /** @var Creator[] $creators */
    $creators = [];
    foreach ($values as $value) {
      $creator = new Creator();
      $nameType = $this->trim($value->creatorName->attributes()->nameType);
      $nameType = $nameType ?: 'Unknown';
      $creator->setNameType($nameType);
      $creator->setCreatorName($this->trim($value->creatorName));
      $creator->setGivenName($this->trim($value->givenName));
      $creator->setFamilyName($this->trim($value->familyName));
      if ($nameType == Creator::TYPE_NAME_Personal) {
        $creatorName = implode(', ', array_filter([$creator->getFamilyName(), $creator->getGivenName()]));
        $creator->setCreatorName($creatorName);
      }
      if (!$creator->getCreatorName()) {
        \Drupal::messenger()->addWarning('Creator name can not be empty.');
        $this->validXML = FALSE;
      }
      $affiliations = [];
      foreach ($value->affiliation as $affiliation) {
        $affiliations[] = $this->trim($affiliation);
      }
      $creator->setAffiliations($affiliations);
      $nameIdentifiers = [];
      foreach ($value->nameIdentifier as $identifier) {
        $nameIdentifiers[] = $this->setNameIdentifier($identifier);
      }
      $creator->setNameIdentifiers($nameIdentifiers);
      $creators[] = $creator;
    }
    $this->resource->setCreators($creators);
  }
  protected function setTitles($values) {
    /** @var Title[] $titles */
    $titles = [];
    foreach ($values as $value) {
      $title = new Title();
      $titleText = $this->trim($value);
      if (!$titleText) {
        \Drupal::messenger()->addError('Title is required.');
        $this->validXML = FALSE;
      }
      $title->setText($titleText);
      if ($titleType = $value->attributes()->titleType) {
        if (!in_array($titleType, self::ALLOWED_TITLE_TYPES)) {
          \Drupal::messenger()->addError('Title type is wrong.');
          $this->validXML = FALSE;
        }

        $title->setTitleType($this->trim($titleType));
      }
      $title->setLang($this->trim($value->lang));
      $titles[] = $title;
    }
    $this->resource->setTitles($titles);
  }
  protected function setPublisher($values) {
    $this->resource->setPublisher($this->trim($values));
  }
  protected function setPublicationYear($values) {
    $publicationYear = $this->trim($values);
    $currentYear = date('Y', time());
    if ($publicationYear < 1000 || $publicationYear > $currentYear
      && $this->state != 'draft') {
      \Drupal::messenger()->addError('"Publication year" must be a year between 1000 and 2021.');
      $this->validXML = FALSE;
    }
    $this->resource->setPublicationYear($currentYear);
  }
  protected function setResourceType($values) {
    $resourceType = new ResourceType();
    $resourceType->setResourceType($this->trim($values));
    $resourceTypeGeneral = $this->trim($values->attributes()->resourceTypeGeneral);
    if (!$resourceTypeGeneral) {
      \Drupal::messenger()->addError('Resource type general is required.');
      $this->validXML = FALSE;
    }

    if (!in_array($resourceTypeGeneral, self::ALLOWED_RESOURCE_TYPE_GENERAL)) {
      \Drupal::messenger()->addError('Attribute "resourceTypeGeneral" from <resourceType> is not valid.');
      $this->validXML = FALSE;
    }
    $resourceType->setSesourceTypeGeneral($resourceTypeGeneral);
    $this->resource->setResourceType($resourceType);
  }
  protected function setSubjects($values) {
    $subjects = [];
    foreach ($values as $value) {
      $subject = new Subject();
      $subject->setSubject($this->trim($value));
      $subject->setSchemeURI($value->attributes()->schemeURI);
      $subject->setSubjectScheme($value->attributes()->subjectScheme);
      $subjects[] = $subject;
    }
    $this->resource->setSubjects($subjects);
  }
  protected function setContributors($values) {
    $contributors = [];
    foreach ($values as $value) {
      $contributor = new Contributor();
      if ($value->nameIdentifier) {
        $nameIdentifier = new NameIdentifier();
        $nameIdentifier->setSchemeURI($this->trim($value->nameIdentifier->attributes()->schemeURI));
        $nameIdentifier->setNameIdentifierScheme($this->trim($value->nameIdentifier->attributes()->nameIdentifierScheme));
        $nameIdentifier->setNameIdentifier($this->trim($value->nameIdentifier));
        $contributor->setNameIdentifiers([$nameIdentifier]);
      }
      $affiliations = [];
      foreach ($value->affiliation as $affiliation) {
        $affiliations[] = $this->trim($affiliation);
      }
      $contributor->setAffiliations($affiliations);
      $nameType = $this->trim($value->contributorName->attributes()->nameType);
      $nameType = $nameType ?: 'Unknown';
      $contributor->setNameType($nameType);
      $contributor->setContributorName($this->trim($value->contributorName)); //@TODO
      $contributor->setGivenName($this->trim($value->givenName));
      $contributor->setFamilyName($this->trim($value->familyName));
      $contributor->setContributorType($this->trim($value->attributes()->contributorType));
      if ($nameType == Contributor::TYPE_NAME_Personal) {
        $creatorName = implode(', ', array_filter([$contributor->getFamilyName(), $contributor->getGivenName()]));
        $contributor->setContributorName($creatorName);
      }
      if (!$contributor->getContributorName() && $this->state != 'draft') {
        \Drupal::messenger()->addWarning('Contributor name can not be empty.');
        $this->validXML = FALSE;
      }
      $nameIdentifiers = [];
      foreach ($value->nameIdentifier as $identifier) {
        $nameIdentifiers[] = $this->setNameIdentifier($identifier);
      }
      $contributor->setNameIdentifiers($nameIdentifiers);
      $contributors[] = $contributor;
    }
    $this->resource->setContributors($contributors);
  }
  protected function setDates($values) {
    /** @var Date[] $dates */
    $dates = [];
    foreach ($values as $value) {
      $date = new Date();
      $dateTimeValue = \DateTime::createFromFormat('Y-m-d', date('Y-m-d', strtotime($this->trim($value))));
      $date->setDate($dateTimeValue);
      $date->setDateType($this->trim($value->attributes()->dateType));
      if ($value->attributes()->dateInformation) {
        $date->setDateInformation($this->trim($value->attributes()->dateInformation));
      }
      $dates[] = $date;
    }
    $this->resource->setDates($dates);
  }
  protected function setLanguage($values) {
    $this->resource->setLanguage($this->trim($values));
  }
  protected function setAlternateIdentifiers($values) {
    $alternateIdentifiers = [];
    foreach ($values as $value) {
      $alternateIdentifier = new AlternateIdentifier();
      $alternateIdentifier->setAlternateIdentifier($this->trim($value));
      $alternateIdentifier->setAlternateIdentifierType($this->trim($value->attributes()->alternateIdentifierType));
      $alternateIdentifiers[] = $alternateIdentifier;
    }
    $this->resource->setAlternateIdentifiers($alternateIdentifiers);
  }
  protected function setRelatedIdentifiers($values) {
    $relatedIdentifiers = [];
    foreach ($values as $value) {
      $relatedIdentifier = new RelatedIdentifier();
      $relatedIdentifier->setSchemeURI($this->trim($value->attributes()->schemeURI));
      $relatedIdentifier->setRelatedIdentifierType($this->trim($value->attributes()->relatedIdentifierType));
      $relatedIdentifier->setRelatedMetadataScheme($this->trim($value->attributes()->relatedMetadataScheme));
      $relatedIdentifier->setRelationType($this->trim($value->attributes()->relationType));
      if ($value->attributes()->relatedIdentifier) {
        $relatedIdentifier->setRelatedIdentifier($this->trim($value->attributes()->relatedIdentifier));
      }
      if ($value->attributes()->resourceTypeGeneral) {
        $relatedIdentifier->setResourceTypeGeneral($this->trim($value->attributes()->resourceTypeGeneral));
      }
      if ($value->attributes()->schemeType) {
        $relatedIdentifier->setSchemeType($this->trim($value->attributes()->schemeType));
      }
    }
    $this->resource->setRelatedIdentifiers($relatedIdentifiers);
  }
  protected function setSizes($values) {
    $sizes = [];
    foreach ($values as $value) {
      $size = new Size();
      $size->setSize($this->trim($value));
      $sizes[] = $size;
    }
    $this->resource->setSizes($sizes);
  }
  protected function setFormats($values) {
    $formats = [];
    foreach ($values as $value) {
      $format = new Format();
      $format->setFormat($this->trim($value));
      $formats[] = $format;
    }
    $this->resource->setFormats($formats);
  }
  protected function setVersion($values) {
    $this->resource->setVersion($this->trim($values));
  }
  protected function setRightsList($values) {
    $rightsList = [];
    foreach ($values as $value) {
      $rights = new Rights();
      $rights->setRights($this->trim($value));
      if ($value->attributes()->rightsURI) {
        $rights->setRightsURI($this->trim($value->attributes()->rightsURI));
      }
      $rights->setLang($this->trim($value->attributes()->lang));
      $rightsList[] = $rights;
    }
    $this->resource->setRightsList($rightsList);
  }
  protected function setDescriptions($values) {
    $descriptions = [];
    foreach ($values as $value) {
      $description = new Description();
      if ($value->attributes()->lang) {
        $description->setLang($this->trim($value->attributes()->lang));
      }
      $description->setDescription($this->trim($value));
      $description->setDescriptionType($this->trim($value->attributes()->descriptionType));
      $descriptions[] = $description;
    }
    $this->resource->setDescriptions($descriptions);
  }
  protected function setGeoLocations($values) {
    $geoLocations = [];
    foreach ($values as $value) {
      $geoLocationBox = new GeoLocationBox();
      $geoLocationBox->setEastBoundLongitude($this->trim($value->geoLocationBox->eastBoundLongitude));
      $geoLocationBox->setNorthBoundLatitude($this->trim($value->geoLocationBox->northBoundLongitude));
      $geoLocationBox->setSouthBoundLatitude($this->trim($value->geoLocationBox->southBoundLongitude));
      $geoLocationBox->setWestBoundLongitude($this->trim($value->geoLocationBox->westBoundLongitude));
      $geoLocationPoint = new GeoLocationPoint();
      $geoLocationPoint->setPointLatitude($this->trim($value->geoLocationPoint->pointLatitude));
      $geoLocationPoint->setPointLongitude($this->trim($value->geoLocationPoint->pointLongitude));
      $geoLocation = new GeoLocation();
      $geoLocation->setGeoLocationBox($geoLocationBox);
      $geoLocation->setGeoLocationPoint($geoLocationPoint);
      $geoLocation->setGeoLocationPlace($this->trim($value->geoLocationPlace));
      $geoLocations[] = $geoLocation;
    }
    $this->resource->setGeoLocations($geoLocations);
  }
  protected function setFundingReferences($values) {
    $fundingReferences = [];
    foreach ($values as $value) {
      $fundingReference = new FundingReference();
      $awardNumber = new AwardNumber();
      $awardNumber->setAwardNumber($this->trim($value->awardNumber));
      $awardNumber->setAwardURI($this->trim($value->awardNumber->attributes()->awardURI));
      $fundingReference->setAwardNumber($awardNumber);

      $funderIdentifier = new FunderIdentifier();
      $funderIdentifier->setFunderIdentifier($this->trim($value->funderIdentifier));
      $funderIdentifier->setFunderIdentifierType($this->trim($value->funderIdentifier->attributes()->funderIdentifierType));
      $fundingReference->setFunderIdentifier($funderIdentifier);

      $fundingReference->setAwardTitle($this->trim($value->awardNumber));
      $fundingReference->setFunderName($this->trim($value->funderName));
      $fundingReferences[] = $fundingReference;
    }
    $this->resource->setFundingReferences($fundingReferences);
  }

  public function setResourceFromXML($path) {
    /** @var \SimpleXMLElement $xml */
    $xml = simplexml_load_string(file_get_contents($path));

    /** @var \SimpleXMLElement $values */
    foreach ($xml as $key => $values) {
      switch ($key) {
        case 'identifier':
          $this->setIdentifier($values);
          if (!$values) {
            \Drupal::messenger()->addError('DOI identifier missing');
            $this->validXML = FALSE;
          }
          break;
        case 'creators':
          $this->setCreators($values);
          if (!$values) {
            \Drupal::messenger()->addError('Creators are required.');
            $this->validXML = FALSE;
          }
          break;
        case 'titles':
          $this->setTitles($values);
          if (!$values) {
            \Drupal::messenger()->addError('Titles tag is missing. At least one title is required.');
            $this->validXML = FALSE;
          }
          break;
        case 'publisher':
          $this->setPublisher($values);
          if (!$values) {
            \Drupal::messenger()->addError('Publisher is required. (The name of the entity that holds,
            archives, publishes prints, distributes, releases, issues, or produces the resource.)');
            $this->validXML = FALSE;
          }
          break;
        case 'publicationYear':
          $this->setPublicationYear($values);
          if (!$values) {
            \Drupal::messenger()->addError('Publication year is missing.');
            $this->validXML = FALSE;
          }
          break;
        case 'resourceType':
          $this->setResourceType($values);
          if (!$values) {
            \Drupal::messenger()->addError('Resource type is missing.');
            $this->validXML = FALSE;
          }
          break;
        case 'subjects':
          $this->setSubjects($values);
          break;
        case 'contributors':
          $this->setContributors($values);
          break;
        case 'dates':
          $this->setDates($values);
          break;
        case 'language':
          $this->setLanguage($values);
          break;
        case 'alternateIdentifiers':
          $this->setAlternateIdentifiers($values);
          break;
        case 'relatedIdentifiers':
          $this->setRelatedIdentifiers($values);
          break;
        case 'sizes':
          $this->setSizes($values);
          break;
        case 'formats':
          $this->setFormats($values);
          break;
        case 'version':
          $this->setVersion($values);
          break;
        case 'rightsList':
          $this->setRightsList($values);
          break;
        case 'descriptions':
          $this->setDescriptions($values);
          break;
        case 'geoLocations':
          $this->setGeoLocations($values);
          break;
        case 'fundingReferences':
          $this->setFundingReferences($values);
          break;
      }
    }
    foreach (['identifier', 'creators', 'titles', 'publisher', 'publicationYear', 'resourceType'] as $key) {
      if (empty($this->resource->$key)) {
        \Drupal::messenger()->addError(sprintf("<%s> tag is missing and is required.", $key));
        $this->validXML = FALSE;
      }
    }
  }

  public function createDoi(): int {
//    $this->setFakeIdentifier();
    $doiId = $this->resource->identifier->getIdentifier();
    $response = $this->getActivityForDoi($doiId);
    if ($response->data) {
      $message = t('A publication with DOI <b>@doi</b> already exists in DataCite. <a href="@link" target="_blank">Click here to see it</a>.', [
        '@doi' => $doiId, '@link' => $this->getFabricaUrl() . self::DOIS_ENDPOINT . '/' . urlencode($doiId)
      ]);
      $this->loggerFactory->get('copernicus_publication')
        ->warning($message);
      \Drupal::messenger()->addWarning($message);
      return self::DOI_EXIST;
    }

    try {
      return $this->curlOperation('POST', $this->prepareParameters('POST'));
    } catch (\Exception $e) {
      return self::DOI_ERROR;
    }
  }

  public function updateDoiUrl(): int {
    try {
      return $this->curlOperation('PUT', $this->prepareParameters('PUT'));
    } catch (\Exception $e) {
      return self::DOI_ERROR;
    }
  }

  private function curlOperation($operation, $encoded_json): int {
    $url = $operation == 'POST' ?
      $this->getEndpointUrl(self::DOIS_ENDPOINT) : $this->getEndpointUrl(self::DOIS_ENDPOINT, $this->resource->getIdentifier()->identifier);
    $curl = curl_init();
    curl_setopt_array($curl, [
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => $operation,
      CURLOPT_POSTFIELDS => $encoded_json,
      CURLOPT_HTTPHEADER => [
        "Authorization: Basic {$this->getAuthorizationId()}",
        "Content-Type: application/vnd.api+json",
      ],
    ]);
    $response = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    switch ($status) {
      case self::RESPONSE_SUCCESS_CREATED:
        \Drupal::messenger()->addStatus($this->getStatusMessage($status));
        return self::DOI_CREATED;
      case self::ERROR_RESPONSE_UNAUTHORIZED:
        \Drupal::messenger()->addError($this->getStatusMessage($status));
        return self::ERROR_RESPONSE_UNAUTHORIZED;
      case self::ERROR_SCHEMA:
      case self::ERROR_RESPONSE_NOT_FOUND:
      case self::ERROR_RESPONSE_FORBIDDEN:
        \Drupal::messenger()->addError($this->getStatusMessage($status));
        return self::DOI_ERROR;
      default:
        return self::DOI_ERROR;
    }
  }
  /**
   * API return a DOI JSON only if it's Findable. NULL otherwise.
   * @param $doiId
   *
   * @return string
   *  Response body|false.
   */
  public function getDoi($doiId) {
    $url = $this->getEndpointUrl(self::DOIS_ENDPOINT, $doiId);
    switch ($this->getDoiStatus($url)) {
      case self::RESPONSE_SUCCESS:
        return $this->client->get($url)->getBody()->getContents();
      default:
        return NULL;
    }
  }

  /**
   * Returns activity for a specific DOI even if it's draft.
   * Endpoint: DATACITE_API_URL/dois/{doiId}/activities.
   *
   * @param $doiId
   *
   * @return mixed
   */
  public function getActivityForDoi($doiId) {
    $url = $this->getEndpointUrl(self::DOIS_ENDPOINT, $doiId, ['activities']);
    $response = $this->client->get($url)->getBody()->getContents();
    return json_decode($response);
  }

  public function getDoiStatus($url) {
    try {
      return $this->client->get($url)->getStatusCode();
    } catch (\Exception $e) {
      return $e->getCode();
    }
  }

  public function createNode(): bool {
    /** @var Title $title */
    $title = reset($this->resource->titles);
    $creators = $this->getCreators($this->resource->creators);
    $keywords = $this->resource->subjects ? $this->getKeywords($this->resource->subjects) : [];
    $date = date('Y-m-d', strtotime($this->resource->publicationYear));
    $node = Node::create([
      'type' => 'publication',
      'title' => $title->getTitle(),
      'uid' => $this->account->id(),
      'langcode' => 'en',
      'status' => TRUE,
      'body' => [
        'summary' => '',
        'value' => $this->getDescriptionsForNode(),
        'format' => 'full_html',
      ],
      'field_authors' => $creators,
      'field_doi' => $this->resource->getIdentifier()->identifier,
      'field_keywords' => $keywords, //subjects
      'field_start_date' => $date,
      'field_publication_publisher' => $this->resource->publisher,
      // @TODO
      'field_type' => ['target_id' => 49],
    ]);

    /** @var \Drupal\Core\Entity\EntityConstraintViolationListInterface $errors */
    $errors = $node->validate();
    if ($errors->count()) {
      $message = sprintf('Validation error while importing publication "%s. Errors: %s".',
        $title->getTitle(), implode(',', $errors->getFieldNames()));
      $this->loggerFactory->get('copernicus_publication')
        ->error($message);
      \Drupal::messenger()->addError($message);
      return FALSE;
    }
    $node->save();
    $this->relatedNode = $node->id();
    $message = t('A new node was created. <a href=":link" target="_blank">Click to see the publication in Drupal</a>', [
      ':link' => Url::fromRoute('entity.node.canonical', ['node' => $this->relatedNode], ['absolute' => TRUE])->toString()]);
    $this->loggerFactory->get('copernicus_publication')
      ->info($message);
    \Drupal::messenger()->addStatus($message);
    return TRUE;
  }

  /**
   * @param Creator[] $creators
   *
   * @return array
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function getCreators(array $creators): array {
    $list = [];
    foreach ($creators as $creator) {
      if ($creator->nameType == Creator::TYPE_NAME_Personal) {
        $name = implode(' ', array_filter([$creator->getGivenName(), $creator->getFamilyName()]));
      } else {
        $name = $creator->getCreatorName();
      }
      $props['name'] = $name;
      /** @var NameIdentifier $nameIdentifiers */
      if ($nameIdentifiers = $creator->getNameIdentifiers()) {
        $nameIdentifiers = reset($nameIdentifiers);
        if ($nameIdentifiers->nameIdentifierScheme == "ORCID") {
          $props['orcid'] = $nameIdentifiers->getNameIdentifier();
        }
      }
      $author = $this->authorStorage->loadByProperties($props);
      if (!$author) {
        CopernicusAuthor::create($props)->save();
        $author = $this->authorStorage->loadByProperties($props);
        $message = sprintf('New author %s was created.', $name);
        \Drupal::logger('copernicus_publication')->info($message);
      }
      $list[] = reset($author);
    }

    return $list;
  }

  protected function getKeywords($subjects): array {
    $tids = [];
    /** @var Subject $subject */
    foreach ($subjects as $subject) {
      $title = $subject->getSubject();
      $term = taxonomy_term_load_multiple_by_name($title);
      if ($term) {
        $term = reset($term);
        $tids[] = $term->id();
        continue;
      }
      $term = Term::create([
        'name' => $title,
        'vid' => 'publication_keywords',
      ]);
      $term->save();
      $tids[] = $term->id();
    }
    return $tids;
  }

  protected function getDescriptionsForNode(): string {
    $body = '';
    $descriptions = $this->resource->descriptions;
    if (!$descriptions) {
      return $body;
    }
    foreach ($descriptions as $description) {
      $body .= "<p>{$description->getDescription()}</p>";
    }

    return $body;
  }

  public function getStatusMessage($status_code) {
    switch ($status_code) {
      case self::RESPONSE_SUCCESS_CREATED:
        $doiId = $this->resource->identifier->getIdentifier();
        return t('A new DOI was successfully created. <a href=":link" target="_blank">Click to see the publication in DataCite</a>', [
          ':link' => $this->getFabricaUrl() . self::DOIS_ENDPOINT . '/' . urlencode($doiId)]);
      case self::RESPONSE_SUCCESS:
        return "DOI was successfully updated.";
      case self::ERROR_RESPONSE_FORBIDDEN:
        return 'You are not authorized to access this resource.';
      case self::ERROR_SCHEMA:
        return 'Wrong schema';
      case self::ERROR_RESPONSE_UNAUTHORIZED:
        return 'Bad credentials.';
      case self::ERROR_RESPONSE_NOT_FOUND:
        return 'DOI does not exist in database or you are not authorized with your username/password combination';
    }
  }
}
