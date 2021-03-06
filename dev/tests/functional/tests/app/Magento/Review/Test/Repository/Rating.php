<?php
/**
 * @copyright Copyright (c) 2014 X.commerce, Inc. (http://www.magentocommerce.com)
 */

namespace Magento\Review\Test\Repository;

use Mtf\Repository\AbstractRepository;

/**
 * Class Rating
 * Data for creation product Rating
 */
class Rating extends AbstractRepository
{
    /**
     * @constructor
     * @param array $defaultConfig
     * @param array $defaultData
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __construct(array $defaultConfig = [], array $defaultData = [])
    {
        $this->_data['default'] = [
            'rating_code' => 'Rating %isolation%',
            'stores' => ['Main Website/Main Website Store/Default Store View'],
            'is_active' => 'Yes',
        ];

        $this->_data['visibleOnDefaultWebsite'] = [
            'rating_code' => 'productRating_%isolation%',
            'stores' => ['Main Website/Main Website Store/Default Store View'],
            'is_active' => 'Yes',
        ];
    }
}
