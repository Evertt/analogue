<?php

namespace Analogue\ORM\Relationships;

class EmbedsOne extends EmbeddedRelationship
{
    /**
     * The relation attribute on the parent object.
     *
     * @var string
     */
    protected $relation;

    /**
     * Transform attributes into embedded object(s), and
     * match it into the given resultset.
     *
     * @param array $results
     *
     * @return array
     */
    public function match(array $results) : array
    {
        return array_map([$this, 'matchSingleResult'], $results);
    }

    /**
     * Match a single database row's attributes to a single
     * object, and return the updated attributes.
     *
     * @param array $attributes
     *
     * @return array
     */
    public function matchSingleResult(array $attributes) : array
    {
        return $this->asArray ? $this->matchAsArray($attributes) : $this->matchAsAttributes($attributes);
    }

    /**
     * Match array attribute from parent to an embedded object,
     * and return the updated attributes.
     *
     * @param array $attributes
     *
     * @return array
     */
    protected function matchAsArray(array $attributes) : array
    {
        // Extract the attributes with the key of the relation,
        // which should be an array.
        $key = $this->relation;

        if (!array_key_exists($key, $attributes) && !is_array($key)) {
            throw new MappingException("'$key' column should be an array");
        }

        $attributes[$key] = $this->buildEmbeddedObject($attributes[$key]);

        return $attributes;
    }

    /**
     * Transform attributes from the parent entity result into
     * an embedded object, and return the updated attributes.
     *
     * @param array $attributes
     *
     * @return array
     */
    protected function matchAsAttributes(array $attributes) : array
    {
        $attributesMap = $this->getAttributesDictionnary();

        // Get the subset that only the embedded object is concerned with and, convert it back
        // to embedded object attributes keys
        $originalAttributes = array_only($attributes, $attributesMap);

        $embeddedAttributes = [];

        foreach ($originalAttributes as $key => $value) {
            $embeddedKey = array_search($key, $attributesMap);
            $embeddedAttributes[$embeddedKey] = $value;
        }

        // Unset original attributes before, just in case one of the keys of the
        // original attributes is equals the relation name.
        foreach (array_keys($originalAttributes) as $key) {
            unset($attributes[$key]);
        }

        // Build object
        $attributes[$this->relation] = $this->buildEmbeddedObject($embeddedAttributes);

        return $attributes;
    }

    /**
     * Return a dictionnary of attributes key on parent Entity.
     *
     * @return array
     */
    protected function getAttributesDictionnary() : array
    {
        // Get attributes that belongs to the embedded object
        $embeddedAttributeKeys = $this->getEmbeddedObjectAttributes();

        $attributesMap = [];

        // Build a dictionnary for corresponding object attributes => parent attributes
        foreach ($embeddedAttributeKeys as $key) {
            $attributesMap[$key] = $this->getParentAttributeKey($key);
        }

        return $attributesMap;
    }

    /**
     * Transform embedded object into DB column(s).
     *
     * @param mixed $object
     *
     * @return array $columns
     */
    public function normalize($object) : array
    {
        return $this->asArray ? $this->normalizeAsArray($object) : $this->normalizeAsAttributes($object);
    }

    /**
     * Normalize object an array containing raw attributes.
     *
     * @param mixed $object
     *
     * @return array
     */
    protected function normalizeAsArray($object) : array
    {
        $wrapper = $this->factory->make($object);

        return [$this->relation => $wrapper->getEntityAttributes()];
    }

    /**
     * Normalize object as parent's attributes.
     *
     * @param mixed $object
     *
     * @return array
     */
    protected function normalizeAsAttributes($object) : array
    {
        if (is_null($object)) {
            return $this->nullObjectAttributes();
        }

        $attributesMap = $this->getAttributesDictionnary();

        $wrapper = $this->factory->make($object);

        $normalizedAttributes = [];

        foreach ($attributesMap as $embedKey => $parentKey) {
            $normalizedAttributes[$parentKey] = $wrapper->getEntityAttribute($embedKey);
        }

        return $normalizedAttributes;
    }

    /**
     * Set all object attributes to null.
     *
     * @return array
     */
    protected function nullObjectAttributes() : array
    {
        $attributesMap = $this->getAttributesDictionnary();

        $normalizedAttributes = [];

        foreach ($attributesMap as $embedKey => $parentKey) {
            $normalizedAttributes[$parentKey] = null;
        }

        return $normalizedAttributes;
    }
}
