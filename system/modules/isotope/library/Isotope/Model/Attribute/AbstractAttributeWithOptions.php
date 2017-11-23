<?php

/**
 * Isotope eCommerce for Contao Open Source CMS
 *
 * Copyright (C) 2009-2016 terminal42 gmbh & Isotope eCommerce Workgroup
 *
 * @link       https://isotopeecommerce.org
 * @license    https://opensource.org/licenses/lgpl-3.0.html
 */

namespace Isotope\Model\Attribute;

use Isotope\Interfaces\IsotopeAttributeForVariants;
use Isotope\Interfaces\IsotopeAttributeWithOptions;
use Isotope\Interfaces\IsotopeProduct;
use Isotope\Model\Attribute;
use Isotope\Model\AttributeOption;
use Isotope\Model\Product;
use Isotope\Model\ProductCollectionItem;
use Isotope\Translation;

abstract class AbstractAttributeWithOptions extends Attribute implements IsotopeAttributeWithOptions
{
    /**
     * Cache product options for attribute
     * "false" as long as the cache is not built
     * @var \Isotope\Collection\AttributeOption|array
     */
    protected $varOptionsCache = false;

    /**
     * @inheritdoc
     */
    public function canHavePrices()
    {
        if ($this instanceof IsotopeAttributeForVariants && $this->isVariantOption()) {
            return false;
        }

        return in_array($this->field_name, Attribute::getPricedFields(), true);
    }

    /**
     * @inheritdoc
     */
    public function getOptionsSource()
    {
        return $this->optionsSource;
    }


    /**
     * Get options of attribute from database
     *
     * @param IsotopeProduct $objProduct
     *
     * @return array|mixed
     *
     * @throws \InvalidArgumentException when optionsSource=product but product is null
     * @throws \UnexpectedValueException for unknown optionsSource
     */
    public function getOptionsForWidget(IsotopeProduct $objProduct = null)
    {
        $arrOptions = array();

        switch ($this->optionsSource) {

            // Single checkbox in the backend does not have options
            case IsotopeAttributeWithOptions::SOURCE_NAME:
                return [['value' => 1, 'label' => $this->name]];

            // @deprecated remove in Isotope 3.0
            case IsotopeAttributeWithOptions::SOURCE_ATTRIBUTE:
                $options = deserialize($this->options);

                if (!empty($options) && is_array($options)) {
                    if ($this->isCustomerDefined()) {
                        // Build for a frontend widget

                        foreach ($options as $option) {
                            $option['label'] = Translation::get($option['label']);

                            $arrOptions[] = $option;
                        }
                    } else {
                        // Build for a backend widget

                        $group = '';

                        foreach ($options as $option) {
                            $option['label'] = Translation::get($option['label']);

                            if ($option['group']) {
                                $group = $option['label'];
                                continue;
                            }

                            if ($group != '') {
                                $arrOptions[$group][] = $option;
                            } else {
                                $arrOptions[] = $option;
                            }
                        }
                    }
                }
                break;

            case IsotopeAttributeWithOptions::SOURCE_TABLE:
                $objOptions = $this->getOptionsFromManager();

                if (null === $objOptions) {
                    $arrOptions = array();

                } elseif ($this->isCustomerDefined()) {
                    $arrOptions = $objOptions->getArrayForFrontendWidget($objProduct, 'FE' === TL_MODE);

                } else {
                    $arrOptions = $objOptions->getArrayForBackendWidget();
                }
                break;

            case IsotopeAttributeWithOptions::SOURCE_PRODUCT:
                if ('FE' === TL_MODE && !($objProduct instanceof IsotopeProduct)) {
                    throw new \InvalidArgumentException(
                        'Must pass IsotopeProduct to Attribute::getOptions if optionsSource is "product"'
                    );
                }

                $objOptions = $this->getOptionsFromManager($objProduct);

                if (null === $objOptions) {
                    return array();

                } else {
                    return $objOptions->getArrayForFrontendWidget($objProduct, 'FE' === TL_MODE);
                }

                break;

            default:
                throw new \UnexpectedValueException(
                    'Invalid options source "'.$this->optionsSource.'" for '.static::$strTable.'.'.$this->field_name
                );
        }

        // Variant options cannot have a default value (see #1546)
        if ($this->isVariantOption()) {
            foreach ($arrOptions as &$option) {
                $option['default'] = '';
            }
        }

        return $arrOptions;
    }

    /**
     * Get AttributeOption models for current attribute
     *
     * @param IsotopeProduct $objProduct
     *
     * @return \Isotope\Collection\AttributeOption
     *
     * @throws \InvalidArgumentException when optionsSource=product but product is null
     * @throws \UnexpectedValueException for unknown optionsSource
     */
    public function getOptionsFromManager(IsotopeProduct $objProduct = null)
    {
        switch ($this->optionsSource) {

            case IsotopeAttributeWithOptions::SOURCE_TABLE:
                if (false === $this->varOptionsCache) {
                    $this->varOptionsCache = AttributeOption::findByAttribute($this);
                }

                return $this->varOptionsCache;

            case IsotopeAttributeWithOptions::SOURCE_PRODUCT:
                if ('FE' === TL_MODE && !($objProduct instanceof IsotopeProduct)) {
                    throw new \InvalidArgumentException(
                        'Must pass IsotopeProduct to Attribute::getOptionsFromManager if optionsSource is "product"'
                    );
                }

                $productId = $objProduct->getId();

                if ($objProduct->isVariant()
                    && !in_array($this->field_name, $objProduct->getVariantAttributes(), true)
                ) {
                    $productId = $objProduct->getProductId();
                }

                if (!is_array($this->varOptionsCache)
                    || !array_key_exists($productId, $this->varOptionsCache)
                ) {
                    $this->varOptionsCache[$productId] = AttributeOption::findByProductAndAttribute(
                        $objProduct,
                        $this
                    );
                }

                return $this->varOptionsCache[$productId];

            default:
                throw new \UnexpectedValueException(
                    static::$strTable.'.'.$this->field_name . ' does not use options manager'
                );
        }
    }

    /**
     * Get options for the frontend product filter widget
     *
     * @param array $arrValues
     *
     * @return array
     *
     * @throws \UnexpectedValueException on invalid options source
     */
    public function getOptionsForProductFilter(array $arrValues)
    {
        switch ($this->optionsSource) {

            case IsotopeAttributeWithOptions::SOURCE_NAME:
                $arrOptions = [];

                if (array_key_exists('1', $arrValues)) {
                    $arrOptions['1'] = $GLOBALS['TL_LANG']['MSC']['yes'];
                }

                if (array_key_exists('', $arrValues)) {
                    $arrOptions[''] = $GLOBALS['TL_LANG']['MSC']['no'];
                }

                return $arrOptions;

            // @deprecated remove in Isotope 3.0
            case IsotopeAttributeWithOptions::SOURCE_ATTRIBUTE:
                $arrOptions = array();
                $options = deserialize($this->options);

                if (!empty($options) && is_array($options)) {
                    foreach ($options as $option) {
                        if (in_array($option['value'], $arrValues)) {
                            $option['label'] = Translation::get($option['label']);
                            $arrOptions[] = $option;
                        }
                    }
                }

                return $arrOptions;

            case IsotopeAttributeWithOptions::SOURCE_FOREIGNKEY:
                $foreignKey = $this->parseForeignKey($this->foreignKey, $GLOBALS['TL_LANGUAGE']);
                list($table, $field) = explode('.', $foreignKey, 2);
                $result = \Database::getInstance()->execute("
                    SELECT id AS value, $field AS label
                    FROM $table
                    WHERE id IN (" . implode(',', $arrValues) . ")
                ");

                return $result->fetchAllAssoc();

            case IsotopeAttributeWithOptions::SOURCE_TABLE:
            case IsotopeAttributeWithOptions::SOURCE_PRODUCT:
                /** @var \Isotope\Collection\AttributeOption $objOptions */
                $objOptions = AttributeOption::findPublishedByIds($arrValues);

                return (null === $objOptions) ? array() : $objOptions->getArrayForFrontendWidget(null, false);

            default:
                throw new \UnexpectedValueException(
                    'Invalid options source "'.$this->optionsSource.'" for '.static::$strTable.'.'.$this->field_name
                );
        }
    }

    /**
     * Make sure array values are unserialized and CSV values are splitted.
     *
     * @param IsotopeProduct $product
     *
     * @return mixed
     */
    public function getValue(IsotopeProduct $product)
    {
        $value = parent::getValue($product);

        if ($this->multiple) {
            if (IsotopeAttributeWithOptions::SOURCE_TABLE === $this->optionsSource
                || IsotopeAttributeWithOptions::SOURCE_FOREIGNKEY === $this->optionsSource
            ) {
                $value = explode(',', $value);
            } else {
                $value = deserialize($value);
            }
        }

        return $value;
    }

    /**
     * @inheritdoc
     */
    public function generateValue($value, array $options = [])
    {
        $product = null;

        if ($options['product'] instanceof IsotopeProduct) {
            $product = $options['product'];
        } elseif (($item = $options['item']) instanceof ProductCollectionItem && $item->hasProduct()) {
            $product = $item->getProduct();
        }

        if (null === $product) {
            return parent::generateValue($value, $options);
        }

        /** @var \Widget $strClass */
        $strClass = $this->getFrontendWidget();
        $arrField = $strClass::getAttributesFromDca(
            $GLOBALS['TL_DCA']['tl_iso_product']['fields'][$this->field_name],
            $this->field_name,
            $value,
            $this->field_name,
            'tl_iso_product',
            $product
        );

        if (empty($arrField['options']) && is_array($arrField['options'])) {
            return parent::generateValue($value, $options);
        }

        $values     = (array) $value;
        $arrOptions = [];

        foreach ($arrField['options'] as $k => &$option) {
            if (($pos = array_search($option['value'], $values)) !== false) {
                $arrOptions[$k] = $option['label'];
                unset($values[$pos]);

                if (0 === count($values)) {
                    break;
                }
            }
        }
        unset($option);

        if (0 !== count($values)) {
            $arrOptions = array_merge($arrOptions, $values);
        }

        return implode(', ', $arrOptions);
    }

    /**
     * Adjust DCA field for this attribute
     *
     * @param array $arrData
     */
    public function saveToDCA(array &$arrData)
    {
        $this->fe_search = false;

        if ($this->isCustomerDefined() && IsotopeAttributeWithOptions::SOURCE_PRODUCT === $this->optionsSource) {
            $this->be_filter = false;
            $this->fe_filter = false;
        }

        if ($this->multiple
            && (IsotopeAttributeWithOptions::SOURCE_TABLE === $this->optionsSource
                || IsotopeAttributeWithOptions::SOURCE_FOREIGNKEY === $this->optionsSource
            )
        ) {
            $this->csv = ',';
        }

        parent::saveToDCA($arrData);

        if ('BE' === TL_MODE) {
            if ($this->be_filter
                && \Input::get('act') == ''
                && IsotopeAttributeWithOptions::SOURCE_TABLE === $this->optionsSource
            ) {
                $arrData['fields'][$this->field_name]['foreignKey'] = 'tl_iso_attribute_option.label';
            }

            if ($this->isCustomerDefined() && IsotopeAttributeWithOptions::SOURCE_PRODUCT === $this->optionsSource) {
                \Controller::loadDataContainer(static::$strTable);
                \System::loadLanguageFile(static::$strTable);

                $fieldTemplate = $GLOBALS['TL_DCA'][static::$strTable]['fields']['optionsTable'];
                unset($fieldTemplate['label']);

                $arrField = array_merge(
                    $arrData['fields'][$this->field_name],
                    $fieldTemplate
                );

                $arrField['attributes']['dynamic'] = true;
                $arrField['foreignKey'] = 'tl_iso_attribute_option.label';

                if ('iso_products' === \Input::get('do')) {
                    $arrField['eval']['whereCondition'] = "field_name='{$this->field_name}'";
                }

                $arrData['fields'][$this->field_name] = $arrField;
            }
        }
    }
}
