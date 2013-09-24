<?php

namespace POData\Writers\Json;

use POData\ObjectModel\ODataFeed;
use POData\ObjectModel\ODataEntry;
use POData\ObjectModel\ODataURLCollection;
use POData\ObjectModel\ODataURL;
use POData\ObjectModel\ODataLink;
use POData\ObjectModel\ODataPropertyContent;
use POData\ObjectModel\ODataBagContent;
use POData\ObjectModel\ODataProperty;
use POData\ObjectModel\ODataMediaLink;
use POData\Writers\Json\JsonWriter;
use POData\Common\Version;
use POData\Common\ODataConstants;
use POData\Common\Messages;
use POData\Common\ODataException;
use POData\Common\InvalidOperationException;

use POData\Writers\Json\JsonLightMetadataLevel;


/**
 * Class JsonLightODataWriter is a writer for the json format in OData V3 also known as JSON Light
 * @package POData\Writers\Json
 */
class JsonLightODataWriter extends JsonODataV2Writer
{

	/**
	 * @var JsonLightMetadataLevel
	 */
	protected $metadataLevel;


	/**
	 *
	 * The service base uri
	 * @var string
	 */
	protected $baseUri;


	public function __construct(JsonLightMetadataLevel $metadataLevel, $absoluteServiceUri)
	{
		if(strlen($absoluteServiceUri) == 0)
		{
			throw new \Exception("absoluteServiceUri must not be empty or null");
		}
		$this->baseUri = $absoluteServiceUri;

		$this->_writer = new JsonWriter('');
		$this->urlKey = ODataConstants::JSON_URL_STRING;
		$this->dataArrayName = ODataConstants::JSON_LIGHT_VALUE_NAME;
		$this->rowCountName = ODataConstants::JSON_LIGHT_ROWCOUNT_STRING;
		$this->metadataLevel = $metadataLevel;
	}

	/**
	 * Write the given OData model in a specific response format
	 *
	 * @param  ODataURL|ODataURLCollection|ODataPropertyContent|ODataFeed|ODataEntry $model Object of requested content.
	 *
	 * @return JsonLightODataWriter
	 */
	public function write($model){
		$this->_writer->startObjectScope();

		if ($model instanceof ODataURL) {
			$this->writeTopLevelMeta("url");
			$this->writeURL($model);
		} elseif ($model instanceof ODataURLCollection) {
			$this->writeTopLevelMeta("urlCollection");
			$this->writeURLCollection($model);
		} elseif ($model instanceof ODataPropertyContent) {
			$this->writeTopLevelMeta( $model->properties[0]->typeName );
			$this->writeTopLevelProperty($model->properties[0]);
		} elseif ($model instanceof ODataFeed) {
			$this->writeTopLevelMeta($model->title);
			$this->writeFeed($model);
		}elseif ($model instanceof ODataEntry) {
			$this->writeTopLevelMeta($model->resourceSetName . "/@Element");
			$this->writeEntry($model);
		}

		$this->_writer->endScope();

		return $this;
	}


	/**
	 *
	 * @param ODataProperty $property
	 *
	 * @return JsonLightODataWriter
	 */
	protected function writeTopLevelProperty(ODataProperty $property)
	{
		$this->writePropertyMeta($property);
		if ($property->value == null) {
			$this->_writer->writeName(ODataConstants::JSON_LIGHT_VALUE_NAME);
			$this->_writer->writeValue("null");
		} elseif ($property->value instanceof ODataPropertyContent) {
			//In the case of complex properties at the top level we don't write the name of the property,
			//just the sub properties.
			$this->writeComplexPropertyMeta($property)
				->writeProperties($property->value);
		} elseif ($property->value instanceof ODataBagContent) {
			$this->_writer->writeName(ODataConstants::JSON_LIGHT_VALUE_NAME);
			$this->writeBagContent($property->value);
		} else {
			$this->_writer->writeName(ODataConstants::JSON_LIGHT_VALUE_NAME);
			$this->_writer->writeValue($property->value, $property->typeName);
		}

		return $this;
	}



	protected function writeTopLevelMeta($fragment)
	{
		if($this->metadataLevel == JsonLightMetadataLevel::NONE())
		{
			return;
		}

		$this->_writer
			->writeName(ODataConstants::JSON_LIGHT_METADATA_STRING)
			->writeValue($this->baseUri . '/' . ODataConstants::URI_METADATA_SEGMENT . '#' . $fragment);

	}


	protected function writePropertyMeta(ODataProperty $property)
	{
		if($this->metadataLevel != JsonLightMetadataLevel::FULL())
		{
			//Only full meta level outputs this info
			return $this;
		}

		if(is_null($property->value))
		{
			//it appears full metadata doesn't output types for nulls...
			return $this;
		}


		switch($property->typeName)
		{
			//The type metadata is only included on certain types of properties
			//Note this also excludes Complex types

			case "Edm.Decimal":
			case "Edm.DateTime":
				$this->_writer
					->writeName($property->name . ODataConstants::JSON_LIGHT_METADATA_PROPERTY_TYPE_SUFFIX_STRING)
					->writeValue($property->typeName);
		}


		return $this;
	}

	/**
	 *
	 * @param ODataEntry $entry Entry to write metadata for.
	 *
	 * @return JsonLightODataWriter
	 */
	protected function writeEntryMetadata(ODataEntry $entry){

		if($this->metadataLevel != JsonLightMetadataLevel::FULL())
		{
			//Only full meta level outputs this info
			return $this;
		}

		$this->_writer
			->writeName(ODataConstants::JSON_LIGHT_METADATA_TYPE_STRING)
			->writeValue($entry->type)
			->writeName(ODataConstants::JSON_LIGHT_METADATA_ID_STRING)
			->writeValue($entry->id)
			->writeName(ODataConstants::JSON_LIGHT_METADATA_ETAG_STRING)
			->writeValue($entry->eTag)
			->writeName(ODataConstants::JSON_LIGHT_METADATA_EDIT_LINK_STRING)
			->writeValue($entry->editLink)
		;

		return $this;
	}


	/**
	 * @param ODataLink $link Link to write.
	 *
	 * @return JsonLightODataWriter
	 */
	protected function writeLink(ODataLink $link){

		if($this->metadataLevel != JsonLightMetadataLevel::FULL())
		{
			//Only full meta level outputs any link info
			return $this;
		}
		//Interestingly the fullmetadata outputs this metadata..even if the thing is expanded
		$this->_writer
			->writeName($link->title . ODataConstants::JSON_LIGHT_METADATA_LINK_NAVIGATION_SUFFIX_STRING)
			->writeValue($link->url);

		if($link->isExpanded)
		{
			$this->_writer->writeName($link->title);
			$this->writeExpandedLink($link);
		}

		return $this;
	}

	protected function writeExpandedLink(ODataLink $link)
	{
		$this->_writer->startObjectScope();

		if ($link->isCollection) {
			$this->_writer->startArrayScope();
			$this->writeFeed($link->expandedResult);
			$this->_writer->endScope();
		} else {
			$this->writeEntry($link->expandedResult);
		}


		$this->_writer->endScope();
	}

	/**
	 * Writes the next page link.
	 *
	 * @param ODataLink $nextPageLinkUri Uri for next page link.
	 *
	 * @return JsonLightODataWriter
	 */
	protected function writeNextPageLink(ODataLink $nextPageLinkUri = null)
	{
		/*
		// "__next" : uri
		if ($nextPageLinkUri != null) {
			$this->_writer
				->writeName(ODataConstants::JSON_NEXT_STRING)
				->writeValue($nextPageLinkUri->url);
		}

		return $this;
		*/
	}


	/**
	 * Begin write complex property.
	 *
	 * @param ODataProperty $property property to write.
	 *
	 * @return JsonLightODataWriter
	 */
	protected function writeComplexProperty(ODataProperty $property)
	{

		// {
		$this->_writer->startObjectScope();

		$this->writeComplexPropertyMeta($property)
			->writeProperties($property->value);

		$this->_writer->endScope();

		return $this;
	}

	protected function writeComplexPropertyMeta(ODataProperty $property)
	{
		if($this->metadataLevel == JsonLightMetadataLevel::FULL()){
			$this->_writer
				->writeName(ODataConstants::JSON_LIGHT_METADATA_TYPE_STRING)
				->writeValue($property->typeName);
		}

		return $this;
	}

	protected function writeBagContent(ODataBagContent $bag)
	{

		$this->_writer


			/*
			->writeName(ODataConstants::JSON_METADATA_STRING) //__metadata : { Type : "typename" }
			->startObjectScope()

			->writeName(ODataConstants::JSON_TYPE_STRING)
			->writeValue($bag->type)
			->endScope()  // }
			*/


			->startArrayScope(); // [

		foreach ($bag->propertyContents as $content) {
			if ($content instanceof ODataPropertyContent) {
				$this->_writer->startObjectScope();
				$this->writeProperties($content);
				$this->_writer->endScope();
			} else {
				// retrieving the collection datatype in order
				//to write in json specific format, with in chords or not
				preg_match('#\((.*?)\)#', $bag->type, $type);
				$this->_writer->writeValue($content, $type[1]);
			}
		}


		$this->_writer->endScope();  // ]
		return $this;
	}

}